<?php

class Propagation_PropagationTotalStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct, JsonSerializable {

    /**
     * @var array
     */
    protected array $totals = [];

    /**
     * @var array
     */
    protected array $propagated_ids = [];

    /**
     * @var array
     */
    protected array $propagated_ids_to_update_version = [];

    /**
     * @var array
     */
    protected array $segments_for_propagation = [
            'propagated'     => [
                    'ice'     => [],
                    'not_ice' => [],
            ],
            'not_propagated' => [
                    'ice'     => [],
                    'not_ice' => [],
            ],
    ];

    /**
     * @return array
     */
    public function getTotals(): array {
        return $this->totals;
    }

    /**
     * @param array $params
     */
    public function setTotals( array $params ) {
        $this->totals[ 'total' ]    = $params[ 'total' ];
        $this->totals[ 'countSeg' ] = $params[ 'countSeg' ];
        $this->totals[ 'status' ]   = $params[ 'status' ];
    }

    /**
     * @return array
     */
    public function getPropagatedIds(): array {
        return $this->propagated_ids;
    }

    /**
     * @param int $id_segment
     */
    public function addPropagatedId( int $id_segment ) {
        if ( false === in_array( $id_segment, $this->propagated_ids ) ) {
            $this->propagated_ids[]                               = $id_segment;
            $this->segments_for_propagation[ 'propagated_ids' ][] = $id_segment;
        }
    }

    /**
     * @return array
     */
    public function getPropagatedIdsToUpdateVersion(): array {
        return $this->propagated_ids_to_update_version;
    }

    /**
     * @param int $id_segment
     */
    public function addPropagatedIdToUpdateVersion( int $id_segment ) {
        if ( false === in_array( $id_segment, $this->propagated_ids_to_update_version ) ) {
            $this->propagated_ids_to_update_version[ $id_segment ] = $id_segment;
        }
    }

    /**
     * @return array
     */
    public function getSegmentsForPropagation(): array {
        return $this->segments_for_propagation;
    }

    /**
     * @param Translations_SegmentTranslationStruct $segmentTranslation
     */
    public function addPropagatedIce( Translations_SegmentTranslationStruct $segmentTranslation ) {
        $this->segments_for_propagation[ 'propagated' ][ 'ice' ][ 'id' ][]            = $segmentTranslation->id_segment;
        $this->segments_for_propagation[ 'propagated' ][ 'ice' ][ 'object' ][]        = $segmentTranslation;
        $this->segments_for_propagation[ 'propagated' ][ 'ice' ][ 'eq_word_count' ][] = $segmentTranslation->eq_word_count;
    }

    /**
     * @param Translations_SegmentTranslationStruct $segmentTranslation
     */
    public function addNotPropagatedIce( Translations_SegmentTranslationStruct $segmentTranslation ) {
        $this->segments_for_propagation[ 'not_propagated' ][ 'ice' ][ 'id' ][]     = $segmentTranslation->id_segment;
        $this->segments_for_propagation[ 'not_propagated' ][ 'ice' ][ 'object' ][] = $segmentTranslation;
    }

    /**
     * @param Translations_SegmentTranslationStruct $segmentTranslation
     */
    public function addPropagatedNotIce( Translations_SegmentTranslationStruct $segmentTranslation ) {
        $this->segments_for_propagation[ 'propagated' ][ 'not_ice' ][ 'id' ][]            = $segmentTranslation->id_segment;
        $this->segments_for_propagation[ 'propagated' ][ 'not_ice' ][ 'object' ][]        = $segmentTranslation;
        $this->segments_for_propagation[ 'propagated' ][ 'not_ice' ][ 'eq_word_count' ][] = $segmentTranslation->eq_word_count;
    }

    /**
     * @param Translations_SegmentTranslationStruct $segmentTranslation
     */
    public function addNotPropagatedNotIce( Translations_SegmentTranslationStruct $segmentTranslation ) {
        $this->segments_for_propagation[ 'not_propagated' ][ 'not_ice' ][ 'id' ][]     = $segmentTranslation->id_segment;
        $this->segments_for_propagation[ 'not_propagated' ][ 'not_ice' ][ 'object' ][] = $segmentTranslation;
    }

    public function jsonSerialize(): array {
        return [
                "totals"                           => $this->totals,
                "propagated_ids"                   => $this->propagated_ids,
                "propagated_ids_to_update_version" => $this->propagated_ids_to_update_version,
                "segments_for_propagation"         => $this->segments_for_propagation,
        ];
    }

    public function getAllToPropagate(): array {
        $aggregator = [];

        if ( !empty( $this->segments_for_propagation[ 'propagated' ][ 'ice' ][ 'object' ] ) ) {
            foreach ( $this->segments_for_propagation[ 'propagated' ][ 'ice' ][ 'object' ] as $segment ) {
                $aggregator[] = $segment;
            }
        }

        if ( !empty( $this->segments_for_propagation[ 'propagated' ][ 'not_ice' ][ 'object' ] ) ) {
            foreach ( $this->segments_for_propagation[ 'propagated' ][ 'not_ice' ][ 'object' ] as $segment ) {
                $aggregator[] = $segment;
            }
        }

        return $aggregator;
    }

}