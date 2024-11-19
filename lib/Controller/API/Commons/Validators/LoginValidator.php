<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 14/04/17
 * Time: 21.19
 *
 */

namespace API\Commons\Validators;


use API\Commons\Exceptions\AuthenticationError;
use API\Commons\KleinController;

class LoginValidator extends Base {

    /**
     * @var KleinController
     */
    protected $controller;

    public function __construct( KleinController $controller ) {

        parent::__construct( $controller->getRequest() );
        $this->controller = $controller;

    }

    public function _validate() {
//        if( !$this->controller->isLoggedIn() ){
//            throw new AuthenticationError( "Invalid Login.", 401 );
//        }
    }
}