<?php

namespace Views;

use AbstractControllers\BaseKleinViewController;
use Exception;

class SignInController extends BaseKleinViewController {

    /**
     * Renders the appropriate view based on the user's session and login status.
     *
     * @return void
     * @throws Exception
     */
    public function renderView() {

        if ( $this->isLoggedIn() && isset( $_SESSION[ 'wanted_url' ] ) ) {
            $this->redirectToWantedUrl();
        }

        $this->setView( "signin.html" );
        $this->render();
    }

}