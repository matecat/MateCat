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
        if( $this->isLoggedIn() && isset( $_SESSION[ 'wanted_url' ] ) ){
            $this->redirectToWantedUrl();
        }
    }

    /**
     * @inheritDoc
     */
    function setTemplateVars() {
        $this->intOauthClients();
    }
}