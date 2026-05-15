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
use Model\FeaturesBase\Hook\Event\Filter\IsAnInternalUserEvent;

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
        $event = $this->controller->getFeatureSet()->dispatch(new IsAnInternalUserEvent($this->controller->getUser()->email));
        $event->isInternal() ?: throw new AuthorizationError('Forbidden, please contact support for generating a valid API key');
    }


}
