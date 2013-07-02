<?php
header("Cache-Control: no-store, no-cache, must-revalidate");  // HTTP/1.1
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once INIT::$UTILS_ROOT .'/openid/LightOpenId.class.php';
include_once INIT::$MODEL_ROOT . "/queries.php";

class oauthResponseHandlerController extends viewcontroller{

	private $openid;
	private $redirectUrl;
	private $userdata=array();

	public function __construct(){
		parent::__construct();
		parent::makeTemplate("oauth_response_handler.html");

		//instantiate openid client
		$this->openid = new LightOpenID(INIT::$HTTPHOST);

		//get response from third-party
		$this->openid_mode = $this->get_from_get_post("openid_mode");
		$this->userdata['email'] = $this->get_from_get_post("openid_ext1_value_contact_email");
		$this->userdata['first_name'] = $this->get_from_get_post("openid_ext1_value_namePerson_first");
		$this->userdata['last_name'] = $this->get_from_get_post("openid_ext1_value_namePerson_last");

		//get url to redirect to
		session_start();
		//add default if not set
		if(!isset($_SESSION['incomingUrl']) or empty($_SESSION['incomingUrl'])){
			$_SESSION['incomingUrl']='/';	
		}
		$this->redirectUrl=$_SESSION['incomingUrl'];
	}

	public function __destruct(){
		session_write_close();
	}

	public function doAction(){
		if('cancel'!=$this->openid_mode) {
			//validate incoming data
			$result = $this->openid->validate();
			if($result !== false) {
				//user has been validated, data was by Google

				//check if user exists in db; if not, create 
				$result=tryInsertUserFromOAuth($this->userdata);

				if(false==$result){
					die("error in insert");
				}
				//send mail to new customer
				//$this->sendNotifyMail($this->userdata);

				//ok mail sent, set stuff
				$_SESSION['cid']=$this->userdata['email'];
			}
		}
	}

	public function setTemplateVars() {
		$this->template->javascript_loader="javascript:doload('".$this->redirectUrl."');";
	}

	private function sendNotifyMail($data){
		$message="Dear ".$data['firstname']."\n\nA Matecat account has been automatically created for you with the following name: ".$data['mail'];
		$mail_res=mailer('Matecat Team','noreply@'.INIT::$HTTPHOST,$data['name'],$data['mail'],'Welcome to Matecat!',$message);
	}
}

?>