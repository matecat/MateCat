<?php

namespace Validator\Contracts;

abstract class AbstractValidator {

    /**
     * @return ValidatorErrorObject[]
     */
    protected $errors = [];

    /**
     * @param ValidatorObject $object
     *
     * @throws \Exception
     * @return bool
     */
    abstract public function validate(ValidatorObject $object);

    /**
     * @return ValidatorErrorObject[]
     */
    public function getErrors(){
        return $this->errors;
    }

    /**
     * @param $error
     */
    public function addError($error)
    {
        $errorObject = new ValidatorErrorObject();
        $errorObject->error = $error;
        $this->errors[] = $errorObject;
    }

    /**
     * @return bool
     */
    public function isValid() {
        return empty($this->errors);
    }
}