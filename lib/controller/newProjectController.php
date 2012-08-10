<?php
class newProjectController extends viewcontroller {
	private $guid = '';
    public function __construct() {
        parent::__construct();
        parent::makeTemplate("upload.html");
		$this->guid = $this->create_guid();
    }
    
    public function doAction(){
		if (!isset($_COOKIE['upload_session'])) {
    		setcookie("upload_session", $this->guid,time()+86400);
			$intDir=$_SERVER['DOCUMENT_ROOT'].'/storage/upload/'.$this->guid.'/';
			mkdir($intDir, 0777);
		}
    }
    
    public function setTemplateVars() {
        $this->template->upload_session_id = $this->guid;
    }

    public function create_guid($namespace = '') {     
	   static $guid = '';
	   $uid = uniqid("", true);
	   $data = $namespace;
	   $data .= $_SERVER['REQUEST_TIME'];
	   $data .= $_SERVER['HTTP_USER_AGENT'];
	   $data .= $_SERVER['LOCAL_ADDR'];
	   $data .= $_SERVER['LOCAL_PORT'];
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
