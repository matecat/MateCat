<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 14/11/25
 * Time: 17:23
 *
 */

namespace Utils\Engines\Validators;

use DomainException;
use Exception;
use Utils\Engines\EnginesFactory;
use Utils\Engines\Validators\Contracts\EngineValidatorObject;
use Utils\Validator\Contracts\AbstractValidator;
use Utils\Validator\Contracts\ValidatorObject;

class GoogleTranslateEngineValidator extends AbstractValidator
{

    /**
     * @param $object EngineValidatorObject
     * @return ValidatorObject|null
     * @throws Exception
     */
    public function validate(ValidatorObject $object): ?ValidatorObject
    {
        $newTestCreatedMT = EnginesFactory::createTempInstance($object->engineStruct);
        $config = $newTestCreatedMT->getConfigStruct();
        $config['segment'] = "Hello World";
        $config['source'] = "en-US";
        $config['target'] = "fr-FR";
        $config['key'] = $newTestCreatedMT->getEngineRecord()->getExtraParamsAsArray()['client_secret'] ?? null;

        $mt_result = $newTestCreatedMT->get($config);

        if (isset($mt_result['error']['code'])) {
            throw new DomainException($mt_result['error']['message']);
        }
        return null;
    }

}