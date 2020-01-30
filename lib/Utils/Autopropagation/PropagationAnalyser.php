<?php

namespace Autopropagation;

use Translations_SegmentTranslationStruct;

class PropagationAnalyser {

    private $propagatedIceCount    = 0;
    private $notPropagatedIceCount = 0;
    private $propagatedCount       = 0;
    private $notPropagatedCount    = 0;

    /**
     * @param Translations_SegmentTranslationStruct   $parentSegmentTranslation
     * @param array $arrayOfSegmentTranslationToPropagate
     *
     * @return array
     */
    public function analyse( Translations_SegmentTranslationStruct $parentSegmentTranslation, $arrayOfSegmentTranslationToPropagate ) {

        $propagation = [];

        if ( $parentSegmentTranslation->match_type !== 'ICE' ) { // remove ICE
            foreach ( $arrayOfSegmentTranslationToPropagate as $segmentTranslationArray ) {

                $segmentTranslation = new Translations_SegmentTranslationStruct($segmentTranslationArray);

                if ( $this->detectIce($segmentTranslation) ) {
                    $propagation[ 'not_propagated_ice' ][] = $segmentTranslation->id_segment;
                    $this->notPropagatedIceCount++;
                } else {
                    $propagation[ 'propagated' ][] = $segmentTranslation->id_segment;
                    $this->propagatedCount++;
                }
            }
        } else { // keep only ICE with the corresponding hash
            foreach ( $arrayOfSegmentTranslationToPropagate as $segmentTranslation ) {
                if ( $this->detectMatchingIce($parentSegmentTranslation, $segmentTranslation) ) {
                    $propagation[ 'propagated_ice' ][] = $segmentTranslation->id_segment;
                    $this->propagatedIceCount++;
                } else {
                    $propagation[ 'not_propagated' ][] = $segmentTranslation->id_segment;
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
