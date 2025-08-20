<?php

namespace Utils\Validator\JSONSchema;

use Utils\Validator\Contracts\ValidatorObject;

class JSONValidatorObject extends ValidatorObject {

    public string $json;
    public object $decoded;

}