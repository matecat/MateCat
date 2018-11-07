<?php

class SegmentTranslationModel extends AbstractModelSubject {

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

    public function entersReviewedState() {
        /*return
                (($this->old_translation->match_type == "ICE" && $this->old_translation->isReviewedStatus() && $this->old_translation->time_to_edit <= 0) OR !
                        $this->old_translation->isReviewedStatus()) and
            $this->translation->isReviewedStatus();*/

        return
                ! $this->old_translation->isReviewedStatus() and
                $this->translation->isReviewedStatus();
    }

    public function exitsReviewedState() {
        return
            ! $this->translation->isReviewedStatus() and
            $this->old_translation->isReviewedStatus();
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