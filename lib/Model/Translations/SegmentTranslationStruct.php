<?php

namespace Model\Translations;

use ArrayAccess;
use Constants_TranslationStatus;
use Model\Analysis\Constants\InternalMatchesConstants;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\ArrayAccessTrait;
use Model\DataAccess\IDaoStruct;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;

class SegmentTranslationStruct extends AbstractDaoSilentStruct implements IDaoStruct, ArrayAccess {

    use ArrayAccessTrait;

    public int     $id_segment;
    public int     $id_job;
    public string  $segment_hash;
    public ?int    $autopropagated_from    = null;
    public string  $status;
    public ?string $translation            = null;
    public ?string $translation_date       = null;
    public int     $time_to_edit           = 0;
    public ?string $match_type             = null;
    public ?string $context_hash           = null;
    public float   $eq_word_count          = 0;
    public float   $standard_word_count    = 0;
    public ?string $suggestions_array      = null;
    public ?string $suggestion             = null;
    public ?string $suggestion_match       = null;
    public ?string $suggestion_source      = null;
    public ?int    $suggestion_position    = null;
    public int     $mt_qe                  = 0;
    public ?string $tm_analysis_status     = null;
    public bool    $locked                 = false;
    public bool    $warning                = false;
    public ?string $serialized_errors_list = null;
    public ?int    $version_number         = 0; // this value should be not null

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
            return JobDao::getById( $id_job )[ 0 ] ?? null;
        } );
    }

}
