<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 14/11/25
 * Time: 16:08
 *
 */

namespace Utils\Engines\Validators;

use Exception;
use Utils\Engines\EnginesFactory;
use Utils\Engines\MMT as MMTEngine;
use Utils\Engines\MMT\MMTServiceApiException;
use Utils\Engines\Validators\Contracts\EngineValidatorObject;
use Utils\Validator\Contracts\AbstractValidator;
use Utils\Validator\Contracts\ValidatorObject;

class MMTEngineValidator extends AbstractValidator
{

    /**
     * @param EngineValidatorObject $object
     * @throws MMTServiceApiException
     * @throws Exception
     */
    public function validate(ValidatorObject $object): ?ValidatorObject
    {
        /** @var MMTEngine $newTestCreatedMT */
        $newTestCreatedMT = EnginesFactory::createTempInstance($object->engineStruct);

        // Check the account
        $checkAccount = $newTestCreatedMT->checkAccount();

        if (!isset($checkAccount['billingPeriod']['planForCatTool'])) {
            throw new Exception("MMT license not valid");
        }

        $planForCatTool = $checkAccount['billingPeriod']['planForCatTool'];

        if ($planForCatTool === false) {
            throw new Exception(
                "The ModernMT license you entered cannot be used inside CAT tools. Please subscribe to a suitable license to start using the ModernMT plugin."
            );
        }

        return null;
    }

}