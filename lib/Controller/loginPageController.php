<?php

header("Cache-Control: no-store, no-cache, must-revalidate");  // HTTP/1.1
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

class loginPageController extends viewController {

	private $incomingUrl;

	public function __construct() {

        //SESSION ENABLED
        parent::sessionStart();
		parent::__construct();
		parent::makeTemplate("login.html");

		$filterArgs = array(
			'incomingUrl'  => array( 'filter' => FILTER_SANITIZE_URL )
		);

		$__postInput = filter_input_array( INPUT_GET, $filterArgs );

		$this->incomingUrl = $__postInput[ 'incomingUrl' ];

		//if nothing is specified by user
		if(empty($this->incomingUrl)){
			//open session to pull put information about incoming url
			$this->incomingUrl=$_SESSION['incomingUrl'];
		}else{
			//else, update session
			$_SESSION['incomingUrl']=$this->incomingUrl;
		}

                if( isset($_SESSION[ 'oauthScope' ]) && $_SESSION[ 'oauthScope' ] === 'GDrive' ) {
                        $this->authURL = \GDrive::generateGDriveAuthUrl();

                        unset( $_SESSION[ 'oauthScope' ] );
                }
	}

	public function doAction() {
		$this->generateAuthURL();
	}

	public function setTemplateVars() {
		$this->template->authURL   = $this->authURL;
		$this->template->incomingUrl    = $this->incomingUrl;
		$this->template->build_number   = INIT::$BUILD_NUMBER;
	}

}
