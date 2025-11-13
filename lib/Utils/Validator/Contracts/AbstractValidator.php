<?php

namespace Utils\Validator\Contracts;

use Exception;
use Throwable;

abstract class AbstractValidator
{

    /**
     * @return Throwable[]
     */
    protected array $errors = [];

    /**
     * @param ValidatorObject $object
     *
     * @return ValidatorObject|null
     * @throws Exception
     */
    abstract public function validate(ValidatorObject $object): ?ValidatorObject;

    /**
     * @return Throwable[]
     */
    public function getExceptions(): array
    {
        return $this->errors;
    }

    /**
     * @param Throwable $error
     */
    public function addException(Throwable $error): void
    {
        $this->errors[] = $error;
    }

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return empty($this->errors);
    }
}