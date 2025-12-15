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
use Controller\API\Commons\Exceptions\UnprocessableException;
use Exception;
use Model\Jobs\JobStruct;

class IsOwnerInternalUserValidator extends Base
{
    /**
     * @return void
     * @throws AuthorizationError
     * @throws AuthenticationError
     * @throws Exception
     */
    public function _validate(): void
    {
        /** @var JobStruct $jobStruct */
        $jobStruct = $this->args[0];
        if (!$jobStruct instanceof JobStruct || empty($jobStruct->owner)) {
            throw new UnprocessableException("Invalid job");
        }
        $this->controller->getFeatureSet()->filter(
            "isAnInternalUser",
            $jobStruct->owner
        ) ?: throw new AuthorizationError('Forbidden, Lara Think only accepts requests from internal users');
    }


}