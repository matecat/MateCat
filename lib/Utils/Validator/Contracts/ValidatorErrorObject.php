<?php

namespace Validator\Contracts;

use Validator\Errors\JsonValidatorExceptionInterface;

class ValidatorErrorObject {

    /**
     * @var JsonValidatorExceptionInterface
     */
    public $error;
}
