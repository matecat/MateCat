<?php

class loginPageController extends viewcontroller {

	private $oauthFormUrl;
	private $incomingUrl;

    public function __construct() {
        parent::__construct();
        parent::makeTemplate("login.html");
	//set url forpopup to oauth
	$this->oauthFormUrl='http://matecat.translated.home/oauth/request';

	//open session to pull put information about incoming url
	session_start();
	$this->incomingUrl=$_SESSION['incomingUrl'];
	session_write_close();
    }

    public function doAction() {

    }

    public function setTemplateVars() {
	$this->template->javascript_loader="javascript:gopopup('".$this->oauthFormUrl."');";
	$this->template->incomingUrl=$this->incomingUrl;
    }

}

?>
