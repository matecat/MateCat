<?php

namespace Utils\Engines\Results\MyMemory;

use Exception;
use Utils\Engines\Results\TMSAbstractResponse;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 02/03/15
 * Time: 19.17
 */
class CreateUserResponse extends TMSAbstractResponse
{

    public $key;
    public $id;
    public $pass;

    public function __construct($response)
    {
        if (!is_array($response)) {
            throw new Exception("Invalid Response", -1);
        }

        $this->responseStatus = (int)($response['code'] ?? 200);
        $this->key = $response['key'] ?? '';
        $this->id = $response['id'] ?? '';
        $this->pass = $response['pass'] ?? '';
    }

} 