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

    /**
     * MyMemory answers /authkey with a bare 1 (valid key) or 0 (unknown key), which carries no status.
     * A failed call arrives as an array instead, with the status of the failure:
     * for example ['error' => [...], 'responseStatus' => 0] when curl could not reach the server.
     *
     * @param array<string, mixed>|int|null $response
     */
    public function __construct(array|int|null $response)
    {
        $this->responseData = $response ?? [];
        $this->responseStatus = is_array($response) && isset($response['responseStatus']) ? (int)$response['responseStatus'] : 200;
    }

}