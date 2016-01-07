<?php

class xliffToTargetViewController extends newProjectController {

    private $incomingUrl;

    public function __construct() {
        parent::__construct( );
        parent::makeTemplate( "xliffToTarget.html" );
    }

    public function setTemplateVars(){
        INIT::$CONVERSION_ENABLED = false;
        $this->template->basepath = "/";
        parent::setTemplateVars();
    }



}
