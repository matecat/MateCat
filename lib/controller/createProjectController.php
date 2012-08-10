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
            $this->result['error'][] = array("code" => -1, "message" => "missing file_name");
        }

        if (empty($this->project_name)) {
            $this->project_name = 'PROJ-'.$this->create_project_name();
        }

        if (empty($this->source_language)) {
            $this->result['error'][] = array("code" => -3, "message" => "missing source_language");
        }

        if (empty($this->target_language)) {
            $this->result['error'][] = array("code" => -4, "message" => "missing target_language");
        }

		$intDir=$_SERVER['DOCUMENT_ROOT'].'/storage/upload/'.$_COOKIE['upload_session'];
		$filename = $intDir.'/'.$this->file_name;
		$password = $this->create_password();
		
		if (file_exists($filename)) {
		} else {
            $this->result['error'][] = array("code" => -4, "message" => "file non trovato");
		}
		
		$pid = insertProject('translated_user', $this->project_name);
		$jid = insertJob($password, $pid, 'translator_1', $this->source_language, $this->target_language);

		$handle = fopen($filename, "r");
		$contents = fread($handle, filesize($filename));
		fclose($handle);
		$fileSplit = split('\.',$this->file_name);
		$mimeType = $fileSplit[count($fileSplit)-1];
		
		$fid = insertFile($pid, $this->file_name, $this->source_language, $mimeType, $contents);
		
		insertFilesJob($jid, $fid);
		
		$insertSegments = extractSegments($intDir, $pid, $fid);

		$this->deleteDir($intDir);
			
        if($insertSegments) {
	        $this->result['code'] = 1;
	        $this->result['data'] = "OK";          	
	        $this->result['password'] = $password;          	
	        $this->result['id_job'] = $jid;
			$this->result['project_name'] = $this->project_name;         	
	        $this->result['source_language'] = $this->source_language;          	
	        $this->result['target_language'] = $this->target_language;          	
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
