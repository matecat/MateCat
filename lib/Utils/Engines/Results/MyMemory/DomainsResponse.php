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
class DomainsResponse extends TMSAbstractResponse
{

    public $entries = [];

    public function __construct($response)
    {
        if (!is_array($response)) {
            throw new Exception("Invalid Response", -1);
        }

        $this->entries = $response['entries'] ?? [];
    }

}