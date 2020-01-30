<?php

namespace Autopropagation;

use Translations_SegmentTranslationStruct;

class PropagationAnalyser {

    /**
     * @var int
     */
    private $propagatedIceCount = 0;

    /**
     * @var int
     */
    private $notPropagatedIceCount = 0;

    /**
     * @var int
     */
    private $propagatedCount = 0;

    /**
     * @var int
     */
    private $notPropagatedCount = 0;

    /**
     * @param Translations_SegmentTranslationStruct $parentSegmentTranslation
     * @param array                                 $arrayOfSegmentTranslationToPropagate
     *
     * @return array
     */
    public function analyse( Translations_SegmentTranslationStruct $parentSegmentTranslation, $arrayOfSegmentTranslationToPropagate ) {

        $propagation                                  = [];
        $propagation[ 'propagated' ][ 'ice' ]         = [];
        $propagation[ 'propagated' ][ 'not_ice' ]     = [];
        $propagation[ 'not_propagated' ][ 'ice' ]     = [];
        $propagation[ 'not_propagated' ][ 'not_ice' ] = [];

        if ( $parentSegmentTranslation->match_type !== 'ICE' ) { // remove ICE
            foreach ( $arrayOfSegmentTranslationToPropagate as $segmentTranslationArray ) {

                $segmentTranslation = new Translations_SegmentTranslationStruct( $segmentTranslationArray );

                if ( $this->detectIce( $segmentTranslation ) ) {
                    $propagation[ 'not_propagated' ][ 'ice' ][ 'id' ][]     = $segmentTranslation->id_segment;
                    $propagation[ 'not_propagated' ][ 'ice' ][ 'object' ][] = $segmentTranslation;
                    $this->notPropagatedIceCount++;
                } else {
                    $propagation[ 'propagated' ][ 'not_ice' ][ 'id' ][]     = $segmentTranslation->id_segment;
                    $propagation[ 'propagated' ][ 'not_ice' ][ 'object' ][] = $segmentTranslation;
                    $propagation[ 'propagated_ids' ][]                      = $segmentTranslation->id_segment;
                    $this->propagatedCount++;
                }
            }
        } else { // keep only ICE with the corresponding hash
            foreach ( $arrayOfSegmentTranslationToPropagate as $segmentTranslation ) {
                if ( $this->detectMatchingIce( $parentSegmentTranslation, $segmentTranslation ) ) {
                    $propagation[ 'propagated' ][ 'ice' ][ 'id' ][]     = $segmentTranslation->id_segment;
                    $propagation[ 'propagated' ][ 'ice' ][ 'object' ][] = $segmentTranslation;
                    $propagation[ 'propagated_ids' ][]                  = $segmentTranslation->id_segment;
                    $this->propagatedIceCount++;
                } else {
                    $propagation[ 'not_propagated' ][ 'not_ice' ][ 'id' ][]     = $segmentTranslation->id_segment;
                    $propagation[ 'not_propagated' ][ 'not_ice' ][ 'object' ][] = $segmentTranslation;
                    $this->notPropagatedCount++;
                }
            }
        }

        return $propagation;
    }

    /**
     * @param Translations_SegmentTranslationStruct $segmentTranslation
     *
     * @return bool
     */
    private function detectIce( Translations_SegmentTranslationStruct $segmentTranslation ) {
        return ( $segmentTranslation->match_type === 'ICE' and $segmentTranslation->locked == 1 and $segmentTranslation->id_segment !== null );
    }

    /**
     * @param Translations_SegmentTranslationStruct $segmentTranslation
     *
     * @return bool
     */
    private function detectUnlockedIce( Translations_SegmentTranslationStruct $segmentTranslation ) {
        return ( $segmentTranslation->match_type === 'ICE' and $segmentTranslation->locked == 0 and $segmentTranslation->id_segment !== null );
    }

    /**
     * @param Translations_SegmentTranslationStruct $parentSegmentTranslation
     * @param Translations_SegmentTranslationStruct $segmentTranslation
     *
     * @return bool
     */
    private function detectMatchingIce( Translations_SegmentTranslationStruct $parentSegmentTranslation, Translations_SegmentTranslationStruct $segmentTranslation ) {
        return ( $segmentTranslation->match_type === 'ICE' and $segmentTranslation->locked == 1 and $segmentTranslation->segment_hash === $parentSegmentTranslation->segment_hash and
                $segmentTranslation->id_segment !== null );
    }
}
