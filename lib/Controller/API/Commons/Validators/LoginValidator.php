<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 14/04/17
 * Time: 21.19
 *
 */

namespace Controller\API\Commons\Validators;


use Controller\API\Commons\Exceptions\AuthenticationError;

class LoginValidator extends Base
{

    /**
     * @return void
     * @throws AuthenticationError
     */
    public function _validate(): void
    {
        $this->controller->isLoggedIn() ?: throw new AuthenticationError("Invalid Login.", 401);
    }
}