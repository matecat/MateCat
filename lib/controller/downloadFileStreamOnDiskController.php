<?php

set_time_limit(180);
include_once INIT::$MODEL_ROOT."/queries.php";
include_once INIT::$UTILS_ROOT."/cat.class.php";
include_once(INIT::$UTILS_ROOT.'/XliffSAXTranslationReplacer.class.php');


class downloadFileStreamOnDiskController extends downloadController {

	private $id_job;
	private $password;
	private $fname;
	private $download_type;

	public function __construct() {
		parent::__construct();

		$this->fname = $this->get_from_get_post('filename');
		$this->id_file = $this->get_from_get_post('id_file');
		$this->id_job = $this->get_from_get_post('id_job');
		$this->download_type = $this->get_from_get_post('download_type');
		$this->filename = $this->fname;
		$this->password = $this->get_from_get_post("password");

		if (empty($this->id_job)) {
			$this->id_job = "Unknown";
		}
	}

	public function doAction() {
		$debug=array();
		$debug['total'][]=time();

		$debug['get_file'][]=time();
		$files_job = getFilesForJob($this->id_job, $this->id_file);
		$debug['get_file'][]=time();
		$nonew = 0;
		$output_content = array();

		/*
		the procedure is now as follows:
		1)original file is loaded from DB into RAM and the flushed in a temp file on disk; a file handler is obtained 
		2)RAM gets freed from original content
		3)the file is read chunk by chunk by a stream parser: for each tran-unit that is encountered, 
			target is replaced (or added) with the corresponding translation among segments
			the current string in the buffe is flushed on standard output 
		4)the temporary file is deleted by another process after some time
		*/

		foreach ($files_job as $file) {
			$mime_type = $file['mime_type'];
			$id_file = $file['id_file'];
			$current_filename = $file['filename'];
			$original = $file['xliff_file'];
			
			//flush file on disk
			$original=str_replace("\r\n","\n",$original);
			//get path
			$path=INIT::$TMP_DOWNLOAD.'/'.$this->id_job.'/'.$id_file.'/'.$current_filename.'.sdlxliff';
			//make dir if doesn't exist
			if(!file_exists(dirname($path))){
				mkdir(dirname($path), 0777, true);
				exec ("chmod 666 $path");
			}
			//create file
			$fp=fopen($path,'w+');
			//flush file to disk
			fwrite($fp,$original);
			//free memory, as we can work with file on disk now
			unset($original);

			$debug['get_segments'][]=time();
			$data = getSegmentsDownload($this->id_job, $this->password, $id_file, $nonew);

            //get job language and data
            $jobData = getJobData($this->id_job);

			$debug['get_segments'][]=time();
			//create a secondary indexing mechanism on segments' array; this will be useful
                        //prepend a string so non-trans unit id ( ex: numerical ) are not overwritten
			foreach($data as $i=>$k){
                           $data[ 'matecat|' . $k['internal_id'] ][]=$i;
                        }
			$transunit_translation = "";
			$debug['replace'][] = time();
			//instatiate parser
			$xsp = new XliffSAXTranslationReplacer( $path, $data, $jobData['target'] );
			//run parsing
			$xsp->replaceTranslation();
			unset($xsp);
			$debug['replace'][] = time();


			$original=file_get_contents($path.'.out.sdlxliff');

			$output_content[$id_file]['content'] = $original;
			$output_content[$id_file]['filename'] = $current_filename;

		}

		$ext = "";
		if ($this->download_type == 'all') {
			if (count($output_content) > 1) {
				$this->filename = $this->fname;
				$pathinfo = pathinfo($this->fname);
				if ($pathinfo['extension'] != 'zip') {
					$this->filename = $pathinfo['basename'] . ".zip";
				}
				$this->content = $this->composeZip($output_content); //add zip archive content here;
			} elseif (count($output_content) == 1) {
				$this->setContent($output_content);
			}
		} else {
			$this->setContent($output_content);
		}
		$debug['total'][]=time();
	}

	private function setContent($output_content) {
		foreach ($output_content as $oc) {
			$pathinfo = pathinfo($oc['filename']);
            $this->filename = $pathinfo['basename'] . ".sdlxliff";
			$this->content = $oc['content'];
		}
	}

	private function composeZip($output_content) {
		$file = tempnam("/tmp", "zipmatecat");
		$zip = new ZipArchive();
		$zip->open($file, ZipArchive::OVERWRITE);

		// Staff with content
		foreach ($output_content as $f) {
			$pathinfo = pathinfo($f['filename']);
            $f['filename'] = $pathinfo['basename'] . ".sdlxliff";
            //Php Zip bug, utf-8 not supported
			$zip->addFromString( iconv( "UTF-8", 'ASCII//TRANSLIT//IGNORE', $f['filename'] ), $f['content']);
		}

		// Close and send to users
		$zip->close();
		$zip_content = file_get_contents("$file");
		unlink($file);
		return $zip_content;
	}

	private function convertToOriginalFormat() {

	}

}

?>
