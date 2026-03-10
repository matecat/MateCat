<?php

namespace Utils\Engines\Validators;

use Exception;
use Utils\Engines\DeepL;
use Utils\Engines\EnginesFactory;
use Utils\Engines\Validators\Contracts\EngineValidatorObject;
use Utils\Logger\LoggerFactory;
use Utils\Validator\Contracts\AbstractValidator;
use Utils\Validator\Contracts\ValidatorObject;

class DeepLEngineValidator extends AbstractValidator
{
    /**
     * @param EngineValidatorObject $object
     * @return ValidatorObject|null
     * @throws Exception
     */
    public function validate(ValidatorObject $object): ?ValidatorObject
    {
        /** @var DeepL $newTestCreatedMT */
        $newTestCreatedMT = EnginesFactory::createTempInstance($object->engineStruct);
        try {
            $config = $newTestCreatedMT->getConfigStruct();
            $config['segment'] = "Hello World";
            $config['source'] = "en-US";
            $config['target'] = "fr-FR";
            $config['pid'] = -(mt_rand(1000, PHP_INT_MAX));

            $newTestCreatedMT->get($config);
        } catch (Exception $e) {
            LoggerFactory::getLogger('engines')->error($e->getMessage());
            throw new Exception("Invalid DeepL API key.");
        }
        return null;
    }
}