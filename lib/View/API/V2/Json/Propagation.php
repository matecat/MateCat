<?php

namespace API\V2\Json;

class Propagation {

    /**
     * @var array
     */
    private $data;

    /**
     * Propagation constructor.
     *
     * @param array $data
     */
    public function __construct( $data ) {
        $this->data = $data;
    }

    /**
     * @return array
     */
    public function render() {

        unset( $this->data[ 'segments_for_propagation' ][ 'propagated_ids' ] );

        return $this->data;
    }
}