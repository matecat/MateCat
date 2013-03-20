<?php

set_time_limit(180);
include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/cat.class.php";
include_once INIT::$UTILS_ROOT . "/fileFormatConverter.class.php";

class downloadOriginalController extends downloadController {

    private $id_job;
    private $password;
    private $fname;
    private $download_type;
    private $id_file;
   
    //[filename] => 041-HTML-default_definitions.htm

    public function __construct() {
        parent::__construct();
        //echo "<pre>";print_r ($_POST);//exit;

        $this->fname = $this->get_from_get_post('filename');
        $this->id_file = $this->get_from_get_post('id_file');
        $this->id_job = $this->get_from_get_post('id_job');
        $this->password = $this->get_from_get_post("password");
        $this->filename = $this->fname;
        $this->download_type=$this->get_from_get_post("download_type");
        
        //$this->download_type = $this->get_from_get_post("download_type");

        if (empty($this->id_job)) {
            $this->id_job = "Unknown";
        }
    }

    public function doAction() {
        $files_job = getOriginalFilesForJob($this->id_job, $this->id_file, $this->password);


        //print_r ($files_job); exit;
        $output_content = array();
        foreach ($files_job as $file) {
            $id_file = $file['id_file'];
            $output_content[$id_file]['filename'] = $file['filename'];
			$output_content[$id_file]['content'] = @gzinflate($file['original_file']);
			if(!$output_content[$id_file]['content']){
				$output_content[$id_file]['content'] = $file['original_file'];
			}
            //echo "<pre>";
            // print_r ($data); 
            // exit;
        }
        //print_r ($output_content);
        //exit;
        //echo $this->download_type; exit;
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
    }

    private function setContent($output_content) {
        foreach ($output_content as $oc) {
            $pathinfo = pathinfo($oc['filename']);
            $ext = $pathinfo['extension'];
            $this->filename = $oc['filename'];
            //if (!in_array($ext, array("xliff", "sdlxliff", "xlf"))) {
            //    $this->filename = $pathinfo['basename'] . ".sdlxliff";
            //}

            /*if ($ext == 'pdf' or $ext == "PDF") {
                $this->filename = $pathinfo['basename'] . ".docx";
            }*/
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
