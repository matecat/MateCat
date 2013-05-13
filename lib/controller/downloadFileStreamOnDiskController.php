<?php

set_time_limit(180);
include_once INIT::$MODEL_ROOT."/queries.php";
include_once INIT::$UTILS_ROOT."/cat.class.php";
include_once INIT::$UTILS_ROOT."/fileFormatConverter.class.php";
include_once(INIT::$UTILS_ROOT.'/XliffSAXTranslationReplacer.class.php');

class downloadFileStreamOnDiskController extends downloadController {

	private $id_job;
	private $password;
	private $fname;
	private $download_type;

	public function __construct() {
		parent::__construct();
		//echo "<pre>";print_r ($_POST);

		$this->fname = $this->get_from_get_post('filename');
		$this->id_file = $this->get_from_get_post('id_file');
		$this->id_job = $this->get_from_get_post('id_job');
		$this->download_type = $this->get_from_get_post('download_type');
		$this->filename = $this->fname;
		$this->password = $this->get_from_get_post("password");

		$this->download_type = $this->get_from_get_post("download_type");

		if (empty($this->id_job)) {
			$this->id_job = "Unknown";
		}
	}

	public function doAction() {
		//2013-03-25 by Massidda: this method contains a lot of debugging code introduced by me. I'll remove it at the end of my current task, so pleas do not touch
		$debug=array();
		$debug['total'][]=time();
		// specs for filename at the task https://app.asana.com/0/1096066951381/2263196383117
		/* if ($this->download_type == 'all') {
		//in this case fname contains the project name (see html)
		$pathinfo = pathinfo($this->fname);
		if ($pathinfo['extension'] != "xliff" and $pathinfo['extension'] != "sdlxliff" and $pathinfo['extension'] != "xlf" and $pathinfo['extension'] != "zip") {
		$this->filename = $pathinfo['basename'] . ".sdlxliff";
		} else {
		$this->filename = $this->fname;
		}
		}
		 */
		$converter = new fileFormatConverter();
		$debug['get_file'][]=time();
		$files_job = getFilesForJob($this->id_job, $this->id_file);
		$debug['get_file'][]=time();
		//$nonew=1;
		//if ($this->download_type=='pseudo'){
		//    $nonew=0;
		//}
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
			$path=INIT::$TMP_DOWNLOAD.'/'.$this->id_job.'/'.$id_file.'/'.$current_filename.'.xliff';
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
			$debug['get_segments'][]=time();
		//echo "<pre>";print_r ($data); exit;	
			//create a secondary indexing mechanism on segments' array; this will be useful
			foreach($data as $i=>$k){
				$data[$k['internal_id']][]=$i;
			}
			//echo "<pre>"; print_r ($data); exit;
			$transunit_translation = "";

			$debug['replace'][]=time();
			//instatiate parser
			$xsp=new XliffSAXTranslationReplacer($path,$data);
			//run parsing
			$xsp->replaceTranslation();
			unset($xsp);
			$debug['replace'][]=time();


			$original=file_get_contents($path.'.out.xliff');
			//echo $original; exit;
			$output_content[$id_file]['content'] = $original;
			$output_content[$id_file]['filename'] = $current_filename;

			if (!in_array($mime_type, array("xliff", "sdlxliff", "xlf"))) {
				$debug['do_conversion'][]=time();
				file_put_contents("/home/matecat/test.sdlxliff", $output_content[$id_file]['content']);
				$convertResult = $converter->convertToOriginal($output_content[$id_file]['content']);
				$output_content[$id_file]['content'] = $convertResult['documentContent'];
				$debug['do_conversion'][]=time();
			}
		}
		//print_r ($output_content);
		//exit;

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
				//                foreach ($output_content as $oc) {
				//                    $pathinfo = pathinfo($oc['filename']);
				//                    $this->filename = $oc['filename'];
				//                    $ext = $pathinfo['extension'];
				//                    if ($ext == 'pdf' or $ext == "PDF") {
				//                        $this->filename = $pathinfo['basename'] . ".docx";
				//                    }
				//                    /*
				//                      if (!in_array($pathinfo['extension'],array("xliff","sdlxliff","xlf"))){
				//                      $this->filename = $pathinfo['basename'] . ".sdlxliff";
				//                      }
				//                     */
				//                    /*
				//                      $converter = new fileFormatConverter();
				//                      $convertResult = $converter->convertToOriginal($oc['content']);
				//                      //					print_r($convertResult);exit;
				//
				//                      $this->content = $convertResult['documentContent'];
				//
				//                     */
				//                    $this->content = $oc['content'];
				//                }
			}
		} else {
			$this->setContent($output_content);
			//            foreach ($output_content as $oc) {
			//                $pathinfo = pathinfo($oc['filename']);
			//                $ext = $pathinfo['extension'];
			//                $this->filename = $oc['filename'];
			//                //if (!in_array($ext, array("xliff", "sdlxliff", "xlf"))) {
			//                //    $this->filename = $pathinfo['basename'] . ".sdlxliff";
			//                //}
			//
			//                if ($ext == 'pdf' or $ext == "PDF") {
			//                    $this->filename = $pathinfo['basename'] . ".docx";
			//                }
			//                $this->content = $oc['content'];
			//            }
		}
		$debug['total'][]=time();
		/*
		   foreach($debug as $k=>$subarr){
		   $debug[$k]=($subarr[1]-$subarr[0])." sec";
		   }
		   echo "<pre>";print_r($debug);exit;
		 */
	}

	private function setContent($output_content) {
		foreach ($output_content as $oc) {
			$pathinfo = pathinfo($oc['filename']);
			$ext = $pathinfo['extension'];
			$this->filename = $oc['filename'];
			//if (!in_array($ext, array("xliff", "sdlxliff", "xlf"))) {
			//    $this->filename = $pathinfo['basename'] . ".sdlxliff";
			//}

			if ($ext == 'pdf' or $ext == "PDF") {
				$this->filename = $pathinfo['basename'] . ".docx";
			}
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
			$ext = $pathinfo['extension'];
			if ($ext == 'pdf' or $ext == "PDF") {
				$f['filename'] = $pathinfo['basename'] . ".docx";
			}
			$zip->addFromString($f['filename'], $f['content']);
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
