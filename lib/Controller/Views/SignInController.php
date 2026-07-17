<?php

namespace Controller\Views;

use Controller\Abstracts\BaseKleinViewController;
use Exception;

class SignInController extends BaseKleinViewController
{

    /**
     * Renders the appropriate view based on the user's session and login status.
     *
     * @return never
     * @throws Exception
     */
    public function renderView(): never
    {
        if ($this->isLoggedIn() && isset($_SESSION['wanted_url'])) {
            $this->redirectToWantedUrl();
        }

        $this->setView("signin.html");
        $this->render();
    }

}