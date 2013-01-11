<?php
include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$ROOT."/lib/utils/segmentExtractor.php";

define('DEFAULT_NUM_RESULTS', 2);

class createProjectController extends ajaxcontroller {

    private $file_name;
    private $project_name;
    private $source_language;
    private $target_language;

    private $mt_engine;
    private $tms_engine;

    public function __construct() {
        parent::__construct();
        $this->file_name = $this->get_from_get_post('file_name'); // da cambiare
        $this->project_name = $this->get_from_get_post('project_name');
        $this->source_language = $this->get_from_get_post('source_language');
        $this->target_language = $this->get_from_get_post('target_language');
        $this->mt_engine = $this->get_from_get_post('mt_engine'); // null è ammesso
        $this->tms_engine = $this->get_from_get_post('tms_engine'); // se empty allora MyMemory
    }

    public function doAction() {

        if (empty($this->file_name)) {
            $this->result['errors'][] = array("code" => -1, "message" => "Missing file name.");
		return false;
        }
        $arFiles = explode(',', $this->file_name);
	$default_project_name=$arFiles[0];
	if (count($arFiles)>1){
		$default_project_name="MATECAT_PROJ-".date("Ymdhi");
	}


        if (empty($this->project_name)) {
            $this->project_name = $default_project_name;//'NO_NAME'.$this->create_project_name();
        }

        if (empty($this->source_language)) {
            $this->result['errors'][] = array("code" => -3, "message" => "Missing source language.");
		return false;
        }

        if (empty($this->target_language)) {
            $this->result['errors'][] = array("code" => -4, "message" => "Missing target language.");
		return false;
        }

        if (empty($this->tms_engine)) {
            $this->tms_engine=1; // default MyMemory
        }

	
	// add her the cookie mangement for remembere the last 3 choosed languages

	 // project name sanitize
        $this->project_name=preg_replace('/["\' \(\)\[\]\{\}\+\*]/',"_", $this->project_name);
        $this->project_name=preg_replace('/[_]{2,}/', "_",$this->project_name);
        $this->project_name=STR_replace('_.', ".",$this->project_name);
        //echo $this->project_name; 

        // project name validation        
        $pattern = "/^[\p{L}\ 0-9a-zA-Z_\.\-]+$/";
		if(!preg_match($pattern, $this->project_name)) {
	        $this->result['errors'][] = array("code" => -5, "message" => "Invalid Project Name $this->project_name: it should only contain numbers and letters!");
//	        $this->result['project_name_error'] = $this->project_name;
			return false;         	
		}

		// create project
		$pid = insertProject('translated_user', $this->project_name);
		//create job
		$password = $this->create_password();
        $jid = insertJob($password, $pid, '', $this->source_language, $this->target_language, $this->mt_engine,$this->tms_engine);
		
		$intDir=$_SERVER['DOCUMENT_ROOT'].'/storage/upload/'.$_COOKIE['upload_session'];
	    foreach ($arFiles as $file) {
			$filename = $intDir.'/'.$file;
			
			if (file_exists($filename)) {
			} else {
	            $this->result['errors'][] = array("code" => -6, "message" => "File not found on server after upload.");
			}
			
	
			$handle = fopen($filename, "r");
			$contents = fread($handle, filesize($filename));
			fclose($handle);
            $fileSplit = explode('.', $file);
			$mimeType = $fileSplit[count($fileSplit)-1];
			
			$fid = insertFile($pid, $file, $this->source_language, $mimeType, $contents);
			
			insertFilesJob($jid, $fid);
			
			$insertSegments = extractSegments($intDir, $file, $pid, $fid);
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
		} else {
			$this->result['errors'][] = array("code" => -7, "message" => "Not able to import this XLIFF file. ($file)");
		}
		
		setcookie ("upload_session", "", time() - 10000);
    }

    public function create_project_name($namespace = '') {    
	  return "";
	   static $guid = '';
	   $uid = uniqid("", true);
	   $data = $namespace;
	   $data .= $_SERVER['REQUEST_TIME'];
	   $data .= $_SERVER['HTTP_USER_AGENT'];
	   $data .= $_SERVER['REMOTE_ADDR'];
	   $hash = strtoupper(hash('ripemd128', $uid . $guid . md5($data)));
	   $guid = '' .   
	       substr($hash,  0,  2) .
	       '' .
	       substr($hash,  2,  2) .
	       '' .
	       substr($hash, 4,  2) .
	       '' .
	       substr($hash, 6,  2) .
	       '' .
	       substr($hash, 8, 2) .
	       '';
	   return "-$guid";
	}

    public function create_password($length=8) {


    	// Random
    	$pool = "abcdefghkmnpqrstuvwxyz23456789"; // skipping iljo01 because not easy to distinguish
    	$pool_lenght = strlen($pool);
    	
    	$pwd = "";
    	for($index = 0; $index < $length; $index++) {
          $pwd .= substr($pool,(rand()%($pool_lenght)),1);
        }
    	  
		return $pwd;
	}

	public static function deleteDir($dirPath) {
	    if (! is_dir($dirPath)) {
	        throw new InvalidArgumentException('$dirPath must be a directory.');
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
