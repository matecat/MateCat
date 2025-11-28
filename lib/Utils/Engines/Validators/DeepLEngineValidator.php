<?php

namespace Utils\Engines\Validators;

use Exception;
use Utils\Engines\DeepL;
use Utils\Engines\EnginesFactory;
use Utils\Engines\Validators\Contracts\EngineValidatorObject;
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
            $newTestCreatedMT->glossaries();
        } catch (Exception $e) {
            throw new Exception("Invalid DeepL API key.");
        }
        return null;
    }
}