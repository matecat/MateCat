<?php

namespace Utils\Engines\Results\MyMemory;

use Utils\Engines\Results\TMSAbstractResponse;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 02/03/15
 * Time: 18.24
 */
class AuthKeyResponse extends TMSAbstractResponse
{

    public function __construct($response)
    {
        $this->responseData   = $response ?? [];
        $this->responseStatus = 200;
    }

}