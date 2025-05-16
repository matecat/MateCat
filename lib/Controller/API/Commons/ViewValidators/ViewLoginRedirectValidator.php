<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 14/04/17
 * Time: 21.19
 *
 */

namespace API\Commons\ViewValidators;


use AbstractControllers\BaseKleinViewController;
use API\Commons\Validators\Base;
use INIT;

class LoginRedirectValidator extends Base {

    /**
     * @param BaseKleinViewController $controller
     */
    public function __construct( BaseKleinViewController $controller ) {
        parent::__construct( $controller );
    }

    public function _validate(): void {
        if ( !$this->controller->isLoggedIn() ) {
            $_SESSION[ 'wanted_url' ] = ltrim( $_SERVER[ 'REQUEST_URI' ], '/' );
            header( "Location: " . INIT::$HTTPHOST . INIT::$BASEURL . "signin", false );
            exit;
        } elseif ( isset( $_SESSION[ 'wanted_url' ] ) ) {
            // handle redirect after login
            /** @var $controller BaseKleinViewController */
            $controller = $this->controller;
            $controller->redirectToWantedUrl();
        }
    }
}