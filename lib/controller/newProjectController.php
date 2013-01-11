<?php
include_once INIT::$MODEL_ROOT . "/queries.php";

class newProjectController extends viewcontroller {
	private $guid = '';
    private $mt_engines;
    private $tms_engines;
    public function __construct() {
        parent::__construct();
	if (!isset($_REQUEST['fork'])){
       	 parent::makeTemplate("upload.html");
	}else{
		parent::makeTemplate("upload_cloud.html");

	}
		$this->guid = $this->create_guid();
    }
    
    public function doAction(){
		if (!isset($_COOKIE['upload_session'])) {
    			setcookie("upload_session", $this->guid,time()+86400);
		}else{
			$this->guid = $_COOKIE['upload_session'];
		}
	
	$intDir=$_SERVER['DOCUMENT_ROOT'].'/storage/upload/'.$this->guid.'/';
	if (!is_dir($intDir)) {
		mkdir($intDir, 0775, true);
	}

        $this->mt_engines = getEngines('MT');
        $this->tms_engines = getEngines('TM');
    }
    
    public function setTemplateVars() {
        $this->template->upload_session_id = $this->guid;
        $this->template->mt_engines = $this->mt_engines;
        $this->template->tms_engines = $this->tms_engines;
    }

    public function create_guid($namespace = '') {     
	   static $guid = '';
	   $uid = uniqid("", true);
	   $data = $namespace;
	   $data .= $_SERVER['REQUEST_TIME'];
	   $data .= $_SERVER['HTTP_USER_AGENT'];
	   if (isset($_SERVER['LOCAL_ADDR'])) {
	   	$data .= $_SERVER['LOCAL_ADDR']; // Windows only
	   }
	   if (isset($_SERVER['LOCAL_PORT'])) {
	    $data .= $_SERVER['LOCAL_PORT']; // Windows only
	   }
	   $data .= $_SERVER['REMOTE_ADDR'];
	   $data .= $_SERVER['REMOTE_PORT'];
	   $hash = strtoupper(hash('ripemd128', $uid . $guid . md5($data)));
	   $guid = '{' .   
	       substr($hash,  0,  8) .
	       '-' .
	       substr($hash,  8,  4) .
	       '-' .
	       substr($hash, 12,  4) .
	       '-' .
	       substr($hash, 16,  4) .
	       '-' .
	       substr($hash, 20, 12) .
	       '}';
	   return $guid;
	}
}


?>
