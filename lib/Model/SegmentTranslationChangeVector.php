<?php

class SegmentTranslationChangeVector {

    /**
     * @var Translations_SegmentTranslationStruct
     */
    private $translation;
    /**
     * @var Translations_SegmentTranslationStruct
     */
    private $old_translation;

    /**
     * @var Jobs_JobStruct
     */
    private $job;

    private $propagated_ids;

    public function __construct(Translations_SegmentTranslationStruct $translation) {
        $this->translation = $translation;
        $this->job = $translation->getJob();
    }

    /**
     * @return bool
     */
    public function translationTextChanged() {
        return $this->translation->translation != $this->old_translation->translation ;
    }
    /**
     * @param Translations_SegmentTranslationStruct $translation
     */
    public function setOldTranslation( Translations_SegmentTranslationStruct $translation ) {
        $this->old_translation = $translation ;
    }

    public function setPropagatedIds( $propagated_ids ) {
        $this->propagated_ids = $propagated_ids ;
    }

    public function getPropagatedIds() {
        return $this->propagated_ids ;
    }

    public function didPropagate() {
        return !empty( $this->propagated_ids ) ;
    }

    /**
     * @return Translations_SegmentTranslationStruct
     */
    public function getTranslation() {
        return $this->translation;
    }

    public function getOldTranslation() {
        if ( is_null( $this->old_translation ) ) {
            throw new \Exception('Old translation is not set');
        }
        return $this->old_translation ;
    }

    /**
     * @return bool
     */
    public function isEnteringReviewedState() {
        return (
                    $this->old_translation->isTranslationStatus() &&
                    $this->translation->isReviewedStatus() &&
                    ! $this->isUnmodifiedICE()
                )
                ||
                (
                    $this->old_translation->isReviewedStatus() &&
                    $this->translation->isReviewedStatus() &&
                    $this->isModifyingICE()
                );
    }

    protected function isModifyingICE() {
        return $this->old_translation->isICE() &&
                $this->old_translation->translation != $this->translation->translation &&
                $this->old_translation->version_number == 0 ;
    }

    protected  function isUnmodifiedICE() {
        return $this->old_translation->isICE() && $this->translation->version_number == 0 ;
    }

    /**
     * @return bool
     */
    public function isExitingReviewedState() {
        return $this->old_translation->isReviewedStatus() &&
                $this->translation->isTranslationStatus() &&
                (
                        ( $this->old_translation->version_number > 0  && $this->old_translation->isICE() )
                        ||
                        ( $this->old_translation->version_number == 0 && !$this->old_translation->isICE() )
                ) ;
    }

    /**
     * @return Segments_SegmentStruct
     */
    public function getSegmentStruct() {
        $dao = new \Segments_SegmentDao( Database::obtain() );
        return $dao->getByChunkIdAndSegmentId(
            $this->job->id,
            $this->job->password,
            $this->translation->id_segment
        );
    }

}