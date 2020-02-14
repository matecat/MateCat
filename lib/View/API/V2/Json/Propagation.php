<?php

namespace API\V2\Json;

class Propagation {

    /**
     * @var \Propagation_PropagationTotalStruct
     */
    private $propagation_PropagationTotalStruct;

    /**
     * Propagation constructor.
     *
     * @param \Propagation_PropagationTotalStruct $propagation_PropagationTotalStruct
     */
    public function __construct( \Propagation_PropagationTotalStruct $propagation_PropagationTotalStruct ) {
        $this->propagation_PropagationTotalStruct = $propagation_PropagationTotalStruct;
    }

    /**
     * @return array
     */
    public function render() {
        return [
             'totals' => $this->propagation_PropagationTotalStruct->getTotals(),
             'propagated_ids' => $this->propagation_PropagationTotalStruct->getPropagatedIds(),
             'segments_for_propagation' => $this->propagation_PropagationTotalStruct->getSegmentsForPropagation(),
        ];
    }
}
