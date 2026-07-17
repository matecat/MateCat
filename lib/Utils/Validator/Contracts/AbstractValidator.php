<?php

namespace Utils\Validator\Contracts;

use Exception;
use Throwable;

abstract class AbstractValidator
{

    /** @var Throwable[] */
    protected array $errors = [];

    /**
     * @param ValidatorObjectInterface $object
     *
     * @return ValidatorObjectInterface|null
     * @throws Exception
     */
    abstract public function validate(ValidatorObjectInterface $object): ?ValidatorObjectInterface;

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