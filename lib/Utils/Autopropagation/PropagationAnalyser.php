<?php

namespace Utils\Autopropagation;

use Model\Propagation\PropagationTotalStruct;
use Model\Translations\SegmentTranslationStruct;
use Utils\Tools\Utils;

class PropagationAnalyser
{

    /**
     * @var int
     */
    private int $propagatedIceCount = 0;

    /**
     * @var int
     */
    private int $notPropagatedIceCount = 0;

    /**
     * @var int
     */
    private int $propagatedCount = 0;

    /**
     * @var int
     */
    private int $notPropagatedCount = 0;

    /**
     * @return int
     */
    public function getPropagatedIceCount(): int
    {
        return $this->propagatedIceCount;
    }

    /**
     * @return int
     */
    public function getNotPropagatedIceCount(): int
    {
        return $this->notPropagatedIceCount;
    }

    /**
     * @return int
     */
    public function getPropagatedCount(): int
    {
        return $this->propagatedCount;
    }

    /**
     * @return int
     */
    public function getNotPropagatedCount(): int
    {
        return $this->notPropagatedCount;
    }

    /**
     * @param SegmentTranslationStruct   $parentSegmentTranslation
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
                    $this->notPropagatedIceCount++;
                } else {
                    $propagation->addPropagatedNotIce($segmentTranslation);
                    $propagation->addPropagatedId($segmentTranslation->id_segment);

                    if (false === Utils::stringsAreEqual(
                                    $parentSegmentTranslation->translation,
                                    $segmentTranslation->translation ?? ''
                            )) {
                        $propagation->addPropagatedIdToUpdateVersion($segmentTranslation->id_segment);
                    }

                    $this->propagatedCount++;
                }
            }
        } else { // keep only ICE with the corresponding hash
            foreach ($arrayOfSegmentTranslationToPropagate as $segmentTranslation) {
                //Propagate to other ICEs
                if ($this->detectMatchingIce($parentSegmentTranslation, $segmentTranslation)) {
                    $propagation->addPropagatedIce($segmentTranslation);
                    $propagation->addPropagatedId($segmentTranslation->id_segment);

                    if (false === Utils::stringsAreEqual(
                                    $parentSegmentTranslation->translation,
                                    $segmentTranslation->translation ?? ''
                            )) {
                        $propagation->addPropagatedIdToUpdateVersion($segmentTranslation->id_segment);
                    }

                    $this->propagatedIceCount++;
                } else { // ??? Why ICEs can not propagate to normal segments?
                    $propagation->addNotPropagatedNotIce($segmentTranslation);
                    $this->notPropagatedCount++;
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
