<?php

namespace Validator;

use Swaggest\JsonSchema\InvalidValue;
use Swaggest\JsonSchema\Schema;
use Swaggest\JsonSchema\SchemaContract;
use Validator\Contracts\ValidatorObject;
use Validator\Errors\JSONValidatorError;

class JSONValidatorObject extends ValidatorObject {

    public $json;
}