<?php

namespace Utils\Engines\Results\MyMemory;

use TypeError;
use Utils\Engines\Results\TMSAbstractResponse;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 02/03/15
 * Time: 19.17
 */
class CreateUserResponse extends TMSAbstractResponse
{

    public string $key = '';
    public string $id = '';
    public string $pass = '';

    /**
     * @param array<string, mixed> $response
     *
     * @throws TypeError
     */
    public function __construct(array $response)
    {
        $this->responseStatus = (int)($response['code'] ?? 200);
        $this->key = $response['key'] ?? '';
        $this->id = $response['id'] ?? '';
        $this->pass = $response['pass'] ?? '';
    }

} 