<?php

namespace View\API\V2\Json;

use Model\Propagation\PropagationResult;
use Model\Propagation\PropagationTotalStruct;

class Propagation
{

    /**
     * @var PropagationTotalStruct
     */
    private $propagation_PropagationTotalStruct;

    /**
     * Propagation constructor.
     *
     * @param PropagationTotalStruct $propagation_PropagationTotalStruct
     */
    public function __construct(PropagationTotalStruct $propagation_PropagationTotalStruct)
    {
        $this->propagation_PropagationTotalStruct = $propagation_PropagationTotalStruct;
    }

    public function render(): PropagationResult
    {
        return PropagationResult::fromTotalStruct($this->propagation_PropagationTotalStruct);
    }
}
