<?php

namespace Utils\Engines\Results\MyMemory;

use TypeError;
use Utils\Engines\Results\TMSAbstractResponse;

class KeysGlossaryResponse extends TMSAbstractResponse
{

    /**
     * @var array<string, bool>
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

    /**
     * @return bool
     */
    public function hasGlossary()
    {
        if (empty($this->entries)) {
            return false;
        }

        foreach ($this->entries as $value) {
            if ($value === true) {
                return true;
            }
        }

        return false;
    }

}