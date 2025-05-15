<?php

namespace Views;

use AbstractControllers\BaseKleinViewController;
use API\Commons\ViewValidators\LoginRedirectValidator;
use Exception;

class SignInController extends BaseKleinViewController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginRedirectValidator( $this ) );
    }

    /**
     * Renders the appropriate view based on the user's session and login status.
     *
     * @return void
     * @throws Exception
     */
    public function renderView() {
        $this->setView( "signin.html" );
        $this->render();
    }

}