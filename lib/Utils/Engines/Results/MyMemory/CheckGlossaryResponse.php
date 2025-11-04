<?php

namespace Utils\Engines\Results\MyMemory;

use Exception;
use Utils\Engines\Results\TMSAbstractResponse;

class CheckGlossaryResponse extends TMSAbstractResponse
{

    public $matches = [];

    public function __construct($response)
    {
        if (!is_array($response)) {
            throw new Exception("Invalid Response", -1);
        }

        $this->matches = $response[ 'matches' ] ?? [];
    }

}