<?php

namespace Utils\Autopropagation;

use Model\Propagation\PropagationTotalStruct;
use Model\Translations\SegmentTranslationStruct;

class PropagationAnalyser
{

    /**
     * @param SegmentTranslationStruct $parentSegmentTranslation
     * @param SegmentTranslationStruct[] $arrayOfSegmentTranslationToPropagate
     *
     * @return PropagationTotalStruct
     */
    public function analyse(SegmentTranslationStruct $parentSegmentTranslation, array $arrayOfSegmentTranslationToPropagate): PropagationTotalStruct
    {
        $propagation = new PropagationTotalStruct();

        if ($parentSegmentTranslation->match_type !== 'ICE' || $parentSegmentTranslation->locked != 1) { // check IF the parent segment is ICE
            foreach ($arrayOfSegmentTranslationToPropagate as $segmentTranslation) {
                if ($this->detectIce($segmentTranslation)) {
                    $propagation->addNotPropagatedIce($segmentTranslation); // IF the parent segment is NOT ICE, we can not propagate it to ICEs
                } else {
                    $propagation->addPropagatedNotIce($segmentTranslation);
                    $propagation->addPropagatedId((string) $segmentTranslation->id_segment);

                    if ($parentSegmentTranslation->translation != ($segmentTranslation->translation ?? '')) {
                        $propagation->addPropagatedIdToUpdateVersion((string) $segmentTranslation->id_segment);
                    }
                }
            }
        } else { // keep only ICE with the corresponding hash
            foreach ($arrayOfSegmentTranslationToPropagate as $segmentTranslation) {
                //Propagate to other ICEs
                if ($this->detectMatchingIce($parentSegmentTranslation, $segmentTranslation)) {
                    $propagation->addPropagatedIce($segmentTranslation);
                    $propagation->addPropagatedId((string) $segmentTranslation->id_segment);

                    if ($parentSegmentTranslation->translation != ($segmentTranslation->translation ?? '')) {
                        $propagation->addPropagatedIdToUpdateVersion((string) $segmentTranslation->id_segment);
                    }
                } else { // ??? Why ICEs can not propagate to normal segments?
                    $propagation->addNotPropagatedNotIce($segmentTranslation);
                }
            }
        }

        return $propagation;
    }

    /**
     * @param SegmentTranslationStruct $segmentTranslation
     *
     * @return bool
     */
    private function detectIce(SegmentTranslationStruct $segmentTranslation): bool
    {
        return ($segmentTranslation->match_type === 'ICE' and $segmentTranslation->locked == 1);
    }

    /**
     * @param SegmentTranslationStruct $parentSegmentTranslation
     * @param SegmentTranslationStruct $segmentTranslation
     *
     * @return bool
     */
    private function detectMatchingIce(SegmentTranslationStruct $parentSegmentTranslation, SegmentTranslationStruct $segmentTranslation): bool
    {
        return ($segmentTranslation->match_type === 'ICE' and $segmentTranslation->locked == 1 and $segmentTranslation->segment_hash === $parentSegmentTranslation->segment_hash);
    }
}
