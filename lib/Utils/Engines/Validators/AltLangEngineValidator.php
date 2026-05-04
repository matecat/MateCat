<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 14/11/25
 * Time: 18:15
 *
 */

namespace Utils\Engines\Validators;

use DomainException;
use Exception;
use Utils\Engines\Altlang;
use Utils\Engines\EnginesFactory;
use Utils\Engines\Validators\Contracts\EngineValidatorObject;
use Utils\Validator\Contracts\AbstractValidator;
use Utils\Validator\Contracts\ValidatorObject;

class AltLangEngineValidator extends AbstractValidator
{

    /**
     * @param EngineValidatorObject $object
     * @return ValidatorObject|null
     * @throws Exception
     */
    public function validate(ValidatorObject $object): ?ValidatorObject
    {
        $engineStruct = $object->engineStruct ?? throw new Exception('Engine struct required');

        /** @var Altlang $newTestCreatedMT */
        $newTestCreatedMT = EnginesFactory::createTempInstance($engineStruct);
        $config = $newTestCreatedMT->getConfigStruct();
        $config['segment'] = "Hello World";
        $config['source'] = "en-US";
        $config['target'] = "en-GB";

        $mt_result = $newTestCreatedMT->get($config);

        if (is_array($mt_result) && isset($mt_result['error']['code'])) {
            throw new DomainException($mt_result['error']['message']);
        }

        return null;
    }
}