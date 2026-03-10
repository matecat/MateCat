<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 30/10/25
 * Time: 14:38
 *
 */

namespace Controller\API\Commons\Validators;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Exception;
use Model\Jobs\JobStruct;

class IsOwnerInternalUserValidator extends Base
{

    private JobStruct $jobStruct;

    public function __construct(KleinController $controller, JobStruct $jobStruct)
    {
        parent::__construct($controller);
        $this->jobStruct = $jobStruct;
    }

    /**
     * @return void
     * @throws AuthorizationError
     * @throws AuthenticationError
     * @throws Exception
     */
    public function _validate(): void
    {
        $this->controller->getFeatureSet()->filter(
            "isAnInternalUser",
            $this->jobStruct->owner
        ) ?: throw new AuthorizationError('Forbidden, Lara Think only accepts requests from internal users');
    }


}