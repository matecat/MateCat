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

class LoginValidator extends Base {

    public function _validate(): void {
        if ( !$this->controller->isLoggedIn() ) {
            throw new AuthenticationError( "Invalid Login.", 401 );
        }
    }
}