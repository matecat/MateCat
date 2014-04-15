<?php


class redirectSuccessPageController extends viewController {



	public function __construct() {
		parent::__construct(false);
		parent::makeTemplate("redirectSuccessPage.html");
	}

	public function doAction() {
	}


	public function setTemplateVars() {
        $this->template->prova          = "prova";


	}

}

?>
