<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 24/02/2017
 * Time: 14:41
 */

namespace Features\Dqf\Controller\API;


use API\V2\KleinController;
use Features\Dqf\Service\Client;

class LoginController extends KleinController {

    public function login() {
        // TODO: these should be passed as params
        $username = 'fabrizio@translated.net';
        $password = 'fabrizio@translated.net';

        $client = new Client();
        $session = $client->getSession( $username, $password )->login();
        $this->response->code(200);
        $this->response->json(['session' => [
                'sessionId' => $session->getSessionId(),
                'expires' => $session->getExpires()
        ]]) ;
    }
}