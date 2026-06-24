<?php

namespace Utils\Engines\Results\MyMemory;

use Exception;
use TypeError;
use Utils\Engines\Results\TMSAbstractResponse;

class SearchGlossaryResponse extends TMSAbstractResponse
{

    /** @var array<int, array<string, mixed>> */
    public array $matches = [];

    /**
     * @throws Exception
     * @throws TypeError
     */
    public function __construct(mixed $response)
    {
        if (!is_array($response)) {
            throw new Exception("Invalid Response", -1);
        }

        $this->matches = $response['matches'] ?? [];
    }

}
