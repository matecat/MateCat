<?php

namespace View\API\V2\Json;

class Propagation {

    /**
     * @var \Model\Propagation\PropagationTotalStruct
     */
    private $propagation_PropagationTotalStruct;

    /**
     * Propagation constructor.
     *
     * @param \Model\Propagation\PropagationTotalStruct $propagation_PropagationTotalStruct
     */
    public function __construct( \Model\Propagation\PropagationTotalStruct $propagation_PropagationTotalStruct ) {
        $this->propagation_PropagationTotalStruct = $propagation_PropagationTotalStruct;
    }

    /**
     * @return array
     */
    public function render() {
        return [
                'totals'                   => $this->propagation_PropagationTotalStruct->getTotals(),
                'propagated_ids'           => $this->propagation_PropagationTotalStruct->getPropagatedIds(),
                'segments_for_propagation' => $this->propagation_PropagationTotalStruct->getSegmentsForPropagation(),
        ];
    }
}
