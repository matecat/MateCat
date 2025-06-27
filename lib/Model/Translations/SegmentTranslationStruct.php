<?php

use Model\Analysis\Constants\InternalMatchesConstants;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\ArrayAccessTrait;
use Model\DataAccess\IDaoStruct;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;

class Translations_SegmentTranslationStruct extends AbstractDaoSilentStruct implements IDaoStruct, ArrayAccess {

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

    public function isReviewedStatus(): bool {
        return in_array( $this->status, Constants_TranslationStatus::$REVISION_STATUSES );
    }

    public function isICE(): bool {
        // In some cases, ICEs are not locked (translations from bilingual xliff). Only consider locked ICEs
        return $this->match_type == InternalMatchesConstants::TM_ICE && $this->locked;
    }

    /**
     * @return bool
     */
    public function isPreTranslated(): bool {
        return $this->tm_analysis_status == 'SKIPPED';
    }

    public function isRejected(): bool {
        return $this->status == Constants_TranslationStatus::STATUS_REJECTED;
    }

    public function isTranslationStatus(): bool {
        return !$this->isReviewedStatus();
    }

    /**
     * @return JobStruct|null
     */
    public function getJob(): ?JobStruct {
        return $this->cachable( __FUNCTION__, $this->id_job, function ( $id_job ) {
            return JobDao::getById( $id_job )[ 0 ] ?? null;
        } );
    }

    /**
     * @return JobStruct[]|null
     */
    public function getChunk(): ?JobStruct {
        return $this->cachable( __FUNCTION__, $this->id_job, function ( $id_job ) {
            return JobDao::getById( $id_job, 0 )[ 0 ] ?? null;
        } );
    }

}
