<?php

namespace unit\Workers\TMAnalysisV2;

use Utils\Engines\Results\MyMemory\GetMemoryResponse;

class FakeGetMemoryResponse extends GetMemoryResponse
{
    private array $cannedMatch;

    public function __construct(array $cannedMatch)
    {
        parent::__construct(null);
        $this->cannedMatch = $cannedMatch;
    }

    public function get_matches_as_array(int $layerNum = 2, array $dataRefMap = [], ?string $source = null, ?string $target = null, ?array $subfiltering_handlers = []): array
    {
        return empty($this->cannedMatch) ? [] : [$this->cannedMatch];
    }
}
