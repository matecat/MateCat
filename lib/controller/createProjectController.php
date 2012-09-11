<?php
include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$ROOT."/lib/utils/segmentExtractor.php";

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
define('DEFAULT_NUM_RESULTS', 2);

class createProjectController extends ajaxcontroller {

    private $file_name;
    private $project_name;
    private $source_language;
    private $target_language;

    public function __construct() {
        parent::__construct();
        $this->file_name = $this->get_from_get_post('file_name'); // da cambiare
        $this->project_name = $this->get_from_get_post('project_name');
        $this->source_language = $this->get_from_get_post('source_language');
        $this->target_language = $this->get_from_get_post('target_language');
    }

    public function doAction() {

        if (empty($this->file_name)) {
            $this->result['errors'][] = array("code" => -1, "message" => "missing file_name");
        }

        if (empty($this->project_name)) {
            $this->project_name = 'PROJ-'.$this->create_project_name();
        }

        if (empty($this->source_language)) {
            $this->result['errors'][] = array("code" => -3, "message" => "missing source_language");
        }

        if (empty($this->target_language)) {
            $this->result['errors'][] = array("code" => -4, "message" => "missing target_language");
        }

        // project name validation

        $pattern = "/^[\ 0-9a-zA-Z_\.\-]+$/";
		if(!preg_match($pattern, $this->project_name)) {
	        $this->result['errors'][] = array("code" => -5, "message" => "Invalid Project Name: it should only contain numbers and letters!");
//	        $this->result['project_name_error'] = $this->project_name;
			return false;         	
		}


		$arFiles= split('[/,]', $this->file_name);
		
		// create project
		$pid = insertProject('translated_user', $this->project_name);
		//create job
		$password = $this->create_password();
		$jid = insertJob($password, $pid, 'translator_1', $this->source_language, $this->target_language);
		
		$intDir=$_SERVER['DOCUMENT_ROOT'].'/storage/upload/'.$_COOKIE['upload_session'];
	    foreach ($arFiles as $file) {
			$filename = $intDir.'/'.$file;
			
			if (file_exists($filename)) {
			} else {
	            $this->result['error'][] = array("code" => -4, "message" => "file non trovato");
			}
			
	
			$handle = fopen($filename, "r");
			$contents = fread($handle, filesize($filename));
			fclose($handle);
			$fileSplit = split('\.',$file);
			$mimeType = $fileSplit[count($fileSplit)-1];
			
			$fid = insertFile($pid, $file, $this->source_language, $mimeType, $contents);
			
			insertFilesJob($jid, $fid);
			
			$insertSegments = extractSegments($intDir, $pid, $fid);
	    }

				


		$this->deleteDir($intDir);
			
        if($insertSegments) {
	        $this->result['code'] = 1;
	        $this->result['data'] = "OK";          	
	        $this->result['password'] = $password;          	
	        $this->result['id_job'] = $jid;
			$this->result['project_name'] = $this->project_name;         	
	        $this->result['source_language'] = $this->source_language;          	
	        $this->result['target_language'] = $this->target_language;          	
//			$this->result['prova0'] = $arFiles[0];
			}

    }

    public function create_project_name($namespace = '') {     
	   static $guid = '';
	   $uid = uniqid("", true);
	   $data = $namespace;
	   $data .= $_SERVER['REQUEST_TIME'];
	   $data .= $_SERVER['HTTP_USER_AGENT'];
	   $data .= $_SERVER['REMOTE_ADDR'];
	   $hash = strtoupper(hash('ripemd128', $uid . $guid . md5($data)));
	   $guid = '' .   
	       substr($hash,  0,  8) .
	       '' .
	       substr($hash,  8,  4) .
	       '' .
	       substr($hash, 12,  4) .
	       '' .
	       substr($hash, 16,  4) .
	       '' .
	       substr($hash, 20, 12) .
	       '';
	   return $guid;
	}

    public function create_password() {
    	$pwd = 'sldfjw322d';  
		return $pwd;
	}

	public static function deleteDir($dirPath) {
	    if (! is_dir($dirPath)) {
	        throw new InvalidArgumentException('$dirPath must be a directory');
	    }
	    if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
	        $dirPath .= '/';
	    }
	    $files = glob($dirPath . '*', GLOB_MARK);
	    foreach ($files as $file) {
	        if (is_dir($file)) {
	            self::deleteDir($file);
	        } else {
	            unlink($file);
	        }
	    }
	    rmdir($dirPath);
	}


}

?>
