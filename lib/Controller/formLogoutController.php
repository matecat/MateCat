<?php

class formLogoutController extends ajaxController {
    private $login = '';
    private $pass  = '';

    public function  __construct() {

        //SESSION ENABLED
        parent::sessionStart();
        parent::__construct();

        //set parameters
        if ( isset( $_POST[ 'login' ] ) and !empty( $_POST[ 'login' ] ) ) {
            $this->login = $_POST[ 'login' ];
        }

        if ( isset( $_POST[ 'pass' ] ) and !empty( $_POST[ 'pass' ] ) ) {
            $this->pass = $_POST[ 'pass' ];
        }
    }

    function doAction() {
        if ( isset( $_POST[ 'logout' ] ) ) {
            unset( $_SESSION[ 'cid' ] );
            AuthCookie::destroyAuthentication();
            $this->result = 'unlogged';
        }
    }

}

