<?php
/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 30/05/19
 * Time: 15.39
 *
 */

namespace API\App\Json;

use API\V2\KleinController;

class Ping {

    protected $controller;

    public function __construct( KleinController $kleinController ) {
        $this->controller = $kleinController;
    }

    public function render() {

        $_request_uri = parse_url( $this->controller->getRequest()->server()[ 'REQUEST_URI' ] );
        if ( isset( $_request_uri[ 'query' ] ) ) {
            parse_str( $_request_uri[ 'query' ], $str );
            $_request_uri[ 'query' ] = $str;
        }

        return [
                'status'  => 'OK',
                'message' => 'Pong...',
                "client_ip"   => \Utils::getRealIpAddr(),
                "user"        => ( $this->controller->userIsLogged() ? [
                        "uid"        => $this->controller->getUser()->getUid(),
                        "email"      => $this->controller->getUser()->getEmail(),
                        "first_name" => $this->controller->getUser()->getFirstName(),
                        "lat_name"   => $this->controller->getUser()->getLastName()
                ] : [ "uid" => 0 ] ),
                "browser"     => \Utils::getBrowser(),
                "request_uri" => $_request_uri,
                "took"        => $this->controller->getTimer()
        ];

    }

}