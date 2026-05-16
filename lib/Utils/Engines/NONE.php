<?php

namespace Utils\Engines;

use TypeError;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;

class NONE extends AbstractEngine
{

    /**
     * @param array<string, mixed> $_config
     *
     * @throws TypeError
     */
    public function get(array $_config): GetMemoryResponse
    {
        return new GetMemoryResponse(['responseStatus' => 200, 'responseData' => []]);
    }

    /**
     * @param mixed $_config
     */
    public function set($_config): bool
    {
        return true;
    }

    /**
     * @param mixed $_config
     */
    public function update($_config): bool
    {
        return true;
    }

    /**
     * @param mixed $_config
     */
    public function delete($_config): bool
    {
        return true;
    }

    protected function _decode(mixed $rawValue, array $parameters = [], $function = null): array
    {
        return [];
    }

    public static function getConfigurationParameters(): array
    {
        return [];
    }
}
