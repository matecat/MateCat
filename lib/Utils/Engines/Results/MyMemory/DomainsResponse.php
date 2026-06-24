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
class DomainsResponse extends TMSAbstractResponse
{

    /**
     * @var array<string, mixed>
     */
    public array $entries = [];

    /**
     * @param array<string, mixed> $response
     *
     * @throws TypeError
     */
    public function __construct(array $response)
    {
        $this->entries = $response['entries'] ?? [];
    }

}