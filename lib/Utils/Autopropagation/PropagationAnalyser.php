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

        if (!$parentSegmentTranslation->isLocked()) { // check IF the parent segment is locked
            foreach ($arrayOfSegmentTranslationToPropagate as $segmentTranslation) {
                if ($segmentTranslation->isLocked()) {
                    $propagation->addNotPropagatedIce($segmentTranslation); // IF the parent segment is NOT locked, we can not propagate it to locked segments
                } else {
                    $propagation->addPropagatedNotIce($segmentTranslation);
                    $propagation->addPropagatedId((string) $segmentTranslation->id_segment);

                    if ($parentSegmentTranslation->translation != ($segmentTranslation->translation ?? '')) {
                        $propagation->addPropagatedIdToUpdateVersion((string) $segmentTranslation->id_segment);
                    }
                }
            }
        } else { // keep only locked segments with the corresponding hash
            foreach ($arrayOfSegmentTranslationToPropagate as $segmentTranslation) {
                //Propagate to other locked segments
                if ($this->detectMatchingLocked($parentSegmentTranslation, $segmentTranslation)) {
                    $propagation->addPropagatedIce($segmentTranslation);
                    $propagation->addPropagatedId((string) $segmentTranslation->id_segment);

                    if ($parentSegmentTranslation->translation != ($segmentTranslation->translation ?? '')) {
                        $propagation->addPropagatedIdToUpdateVersion((string) $segmentTranslation->id_segment);
                    }
                } else { // ??? Why locked segments can not propagate to normal segments?
                    $propagation->addNotPropagatedNotIce($segmentTranslation);
                }
            }
        }

        return $propagation;
    }

    /**
     * @param SegmentTranslationStruct $parentSegmentTranslation
     * @param SegmentTranslationStruct $segmentTranslation
     *
     * @return bool
     */
    private function detectMatchingLocked(SegmentTranslationStruct $parentSegmentTranslation, SegmentTranslationStruct $segmentTranslation): bool
    {
        return ($segmentTranslation->isLocked() and $segmentTranslation->segment_hash === $parentSegmentTranslation->segment_hash);
    }
}
