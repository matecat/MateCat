<?php
include_once INIT::$MODEL_ROOT . "/queries.php";

class analyzeController extends viewcontroller {


    public function __construct() {
        parent::__construct();
        parent::makeTemplate("analyze.html");

    }

    public function doAction() {
        
    }

    public function setTemplateVars() {
    }

}

?>