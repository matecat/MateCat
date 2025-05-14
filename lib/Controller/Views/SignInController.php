<?php

namespace Views;

use AbstractControllers\BaseKleinViewController;
use Klein\Request;
use Klein\Response;

class SignInController extends BaseKleinViewController {

    public function __construct() {
        parent::__construct( Request::createFromGlobals(), new Response(), null, null );
        $this->setView( "signin.html" );
    }

    /**
     * Renders the appropriate view based on the user's session and login status.
     *
     * @return void
     */
    function renderView() {
        if ( $this->isLoggedIn() && isset( $_SESSION[ 'wanted_url' ] ) ) {
            $this->redirectToWantedUrl();
        }
        $this->render();
    }

}