<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 24/02/2017
 * Time: 14:41
 */

namespace Features\Dqf\Controller\API;


use API\V2\KleinController;
use Features\Dqf\Service\Session;

class LoginController extends KleinController {

    public function login() {
        // TODO: these should be passed as params
        $username = 'fabrizio@translated.net';
        $password = 'fabrizio@translated.net';

        $session = new Session($username, $password) ;
        $session->login();

        $this->response->code(200);
        $this->response->json(['session' => [
                'sessionId' => $session->getSessionId(),
                'expires' => $session->getExpires()
        ]]) ;
    }
}