<?php

namespace View\API\V2\Json;

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

    /**
     * @return array
     */
    public function render(): array
    {
        return [
                'totals'                   => $this->propagation_PropagationTotalStruct->getTotals(),
                'propagated_ids'           => $this->propagation_PropagationTotalStruct->getPropagatedIds(),
                'segments_for_propagation' => $this->propagation_PropagationTotalStruct->getSegmentsForPropagation(),
        ];
    }
}
