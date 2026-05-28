<?php

namespace Utils\Engines\Validators;

use Exception;
use Utils\Engines\Validators\Contracts\EngineValidatorObject;
use Utils\Validator\Contracts\AbstractValidator;
use Utils\Validator\Contracts\ValidatorObject;

class LaraGlossaryValidator extends AbstractValidator
{

    /**
     * @param EngineValidatorObject $object
     *
     * @return ValidatorObject|null
     * @throws Exception
     */
    public function validate(ValidatorObject $object): ?ValidatorObject
    {
        $laraGlossariesArray = json_decode($object->glossaryString, true);

        if (!is_array($laraGlossariesArray)) {
            throw new Exception("lara_glossaries is not a valid JSON");
        }

        foreach ($laraGlossariesArray as $glossaryId) {
            if (!is_string($glossaryId)) {
                throw new Exception("`glossaries` array contains a non string value in `lara_glossaries` JSON");
            }
        }

        return $object;
    }
}
