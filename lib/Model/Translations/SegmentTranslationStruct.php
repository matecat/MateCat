<?php

use DataAccess\ArrayAccessTrait;

class Translations_SegmentTranslationStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct, ArrayAccess {

    use ArrayAccessTrait;

    public $id_segment;
    public $id_job;
    public $segment_hash;
    public $autopropagated_from;
    public $status;
    public $translation;
    public $translation_date;
    public $time_to_edit;
    public $match_type;
    public $context_hash;
    public $eq_word_count;
    public $standard_word_count;
    public $suggestions_array;
    public $suggestion;
    public $suggestion_match;
    public $suggestion_source;
    public $suggestion_position;
    public $mt_qe;
    public $tm_analysis_status;
    public $locked;
    public $warning;
    public $serialized_errors_list;
    public $version_number = 0; // this value should be not null

    public function isReviewedStatus() {
        return in_array( $this->status, Constants_TranslationStatus::$REVISION_STATUSES );
    }

    public function isICE() {
        // in some cases ICEs are not locked ( translations from bilingual xliff ). Only consider locked ICEs
        return $this->match_type == Constants_SegmentTranslationsMatchType::ICE && $this->locked;
    }

    public function isPreTranslated(){
        return $this->match_type == Constants_SegmentTranslationsMatchType::ICE && !$this->locked;
    }

    public function isPreApprovedFromTM(){
        return
                ( $this->match_type == Constants_SegmentTranslationsMatchType::_100 || $this->match_type == Constants_SegmentTranslationsMatchType::_100_PUBLIC )&&
                !$this->locked && /* redundant a 100% is not locked */
                $this->status == Constants_TranslationStatus::STATUS_APPROVED &&
                empty( $this->version_number );
    }

    public function isPostReviewedStatus() {
        return in_array( $this->status, Constants_TranslationStatus::$POST_REVISION_STATUSES );
    }

    public function isRejected(){
        return $this->status == Constants_TranslationStatus::STATUS_REJECTED;
    }

    public function isTranslationStatus() {
        return !$this->isReviewedStatus();
    }

    /**
     * @return Jobs_JobStruct
     */
    public function getJob() {
        return $this->cachable( __FUNCTION__, $this->id_job, function ( $id_job ) {
            return Jobs_JobDao::getById( $id_job )[ 0 ];
        } );
    }

    /**
     * @return Jobs_JobStruct[]|null
     */
    public function getChunk() {
        return $this->cachable( __FUNCTION__, $this->id_job, function ( $id_job ) {
            return Jobs_JobDao::getById( $id_job )[ 0 ];
        } );
    }

}
