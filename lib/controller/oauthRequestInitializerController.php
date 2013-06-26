<?php 
header("Cache-Control: no-store, no-cache, must-revalidate");  // HTTP/1.1
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once(INIT::$UTILS_ROOT .'/openid/LightOpenId.class.php');

class oauthRequestInitializerController extends helperController{

	private $openid;

	public function __construct(){
		parent::__construct();

		//instantiate openid client
		$this->openid = new LightOpenID(INIT::$HTTPHOST);

		//set user data we want to access
		$this->openid->required = array(
				'namePerson',
				'namePerson/first',
				'namePerson/last',
				'contact/email'
				);
		//set return url
		$this->openid->returnUrl= INIT::$HTTPHOST."/oauth/response";
		//set identity website for google
		$this->openid->identity = 'https://www.google.com/accounts/o8/id';
	}

	public function doAction(){
		try {
			if(!$this->openid->mode) {
				//go to Google page
				$this->redirect($this->openid->authUrl());
			} elseif($this->openid->mode == 'cancel') {
				echo 'User has canceled authentication!';
			} 
		} catch(ErrorException $e) {
			log::doLog($e->getMessage());
		}
	}
}
?>
