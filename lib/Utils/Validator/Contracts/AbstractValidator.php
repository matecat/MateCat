<?php

namespace Utils\Validator\Contracts;

use Exception;

abstract class AbstractValidator {

    /**
     * @return ValidatorErrorObject[]
     */
    protected array $errors = [];

    /**
     * @param ValidatorObject $object
     *
     * @return ValidatorObject|null
     * @throws Exception
     */
    abstract public function validate( ValidatorObject $object ): ?ValidatorObject;

    /**
     * @return ValidatorErrorObject[]
     */
    public function getExceptions(): array {
        return $this->errors;
    }

    /**
     * @param ValidatorExceptionInterface $error
     */
    public function addException( ValidatorExceptionInterface $error ) {
        $errorObject        = new ValidatorErrorObject();
        $errorObject->error = $error;
        $this->errors[]     = $errorObject;
    }

    /**
     * @return bool
     */
    public function isValid(): bool {
        return empty( $this->errors );
    }
}