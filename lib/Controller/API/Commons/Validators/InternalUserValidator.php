<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 30/10/25
 * Time: 14:38
 *
 */

namespace Controller\API\Commons\Validators;

use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Exception;

class InternalUserValidator extends LoginValidator
{
    /**
     * @return void
     * @throws AuthorizationError
     * @throws AuthenticationError
     * @throws Exception
     */
    public function _validate(): void
    {
        parent::_validate();
        $this->controller->getFeatureSet()->filter(
            "isAnInternalUser",
            $this->controller->getUser()->email
        ) ?: throw new AuthorizationError('Forbidden, please contact support for generating a valid API key');
    }


}