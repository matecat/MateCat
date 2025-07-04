<?php

namespace Validator\JSONSchema;

use Validator\Contracts\ValidatorObject;

class JSONValidatorObject extends ValidatorObject {

    public string $json;
    public object $decoded;

}