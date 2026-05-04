<?php

namespace Utils\Engines\Results\MyMemory;

use TypeError;
use Utils\Engines\Results\TMSAbstractResponse;

class GetGlossaryResponse extends TMSAbstractResponse
{

    /**
     * @var array<int, mixed>
     */
    public array $matches = [];

    /**
     * @param array<string, mixed> $response
     *
     * @throws TypeError
     */
    public function __construct(array $response)
    {
        $this->matches = $response['matches'] ?? [];
    }

}