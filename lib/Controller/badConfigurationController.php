<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 14/07/14
 * Time: 12.43
 */

class badConfigurationController extends viewController{

	private $error_mail_content;

	public function __construct(){
		$this->error_mail_content    .= "<h1>Matecat is not working.</h1>";
		$this->error_mail_content    .= "<h2>You are using an older version of config.ini<br/>";
		$this->error_mail_content    .= "Verify that the current version of config.ini is aligned with config.ini.sample.php ";
		$this->error_mail_content    .= "</h2>";
	}

	/**
	 * When Called it perform the controller action to retrieve/manipulate data
	 *
	 * @return mixed
	 */
	function doAction()
	{
		Utils::sendErrMailReport( $this->error_mail_content );
		//echo $this->error_mail_content;exit;

		parent::makeTemplate( "badConfiguration.html" );
	}

	/**
	 * tell the children to set the template vars
	 *
	 * @return mixed
	 */
	function setTemplateVars(){}
}