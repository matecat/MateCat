<?php

class signinController extends viewController {

    public function __construct() {
        parent::__construct();
        parent::makeTemplate( "signin.html" );
    }

    /**
     * @inheritDoc
     */
    function doAction() {
    }

    /**
     * @inheritDoc
     */
    function setTemplateVars() {
        $this->intOauthClients();
    }
}