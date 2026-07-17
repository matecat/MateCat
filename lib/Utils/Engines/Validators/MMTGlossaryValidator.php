<?php

namespace Utils\Engines\Validators;

use Exception;
use Utils\Engines\Validators\Contracts\EngineValidatorObject;
use Utils\Validator\Contracts\AbstractValidator;
use Utils\Validator\Contracts\ValidatorObjectInterface;

class MMTGlossaryValidator extends AbstractValidator
{

    /**
     * @param EngineValidatorObject $object
     *
     * @return ValidatorObjectInterface|null
     * @throws Exception
     */
    public function validate(ValidatorObjectInterface $object): ?ValidatorObjectInterface
    {
        $glossaryString = $object->glossaryString ?? throw new Exception('Glossary string required');
        $mmtGlossariesArray = json_decode($glossaryString, true);

        if (!is_array($mmtGlossariesArray)) {
            throw new Exception("mmt_glossaries is not a valid JSON");
        }

        foreach ($mmtGlossariesArray as $glossaryId) {
            if (!is_int($glossaryId)) {
                throw new Exception("`glossaries` array contains a non integer value in `mmt_glossaries` JSON");
            }
        }

        return $object;
    }
}