<?php

namespace Validator\Contracts;

use Validator\Errors\JsonValidatorExceptionInterface;

abstract class AbstractValidator {

    /**
     * @return ValidatorErrorObject[]
     */
    protected $errors = [];

    /**
     * @param ValidatorObject $object
     *
     * @return bool
     * @throws \Exception
     */
    abstract public function validate( ValidatorObject $object );

    /**
     * @return ValidatorErrorObject[]
     */
    public function getExceptions() {
        return $this->errors;
    }

    /**
     * @param $error
     */
    public function addException( JsonValidatorExceptionInterface $error ) {
        $errorObject        = new ValidatorErrorObject();
        $errorObject->error = $error;
        $this->errors[]     = $errorObject;
    }

    /**
     * @return bool
     */
    public function isValid() {
        return empty( $this->errors );
    }
}