<?php

namespace Model\LQA;

use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;
use Model\Jobs\ChunkDao;
use Model\Jobs\JobStruct;
use Utils\Tools\Utils;

class ChunkReviewStruct extends AbstractDaoSilentStruct implements IDaoStruct {

    public ?int    $id                   = null;
    public int     $id_project;
    public int     $id_job;
    public string  $password;
    public ?string $review_password      = null;
    public ?float  $penalty_points       = 0;
    public int     $source_page;
    public ?bool   $is_pass              = null;
    public ?string $force_pass_at        = null;
    public int     $reviewed_words_count = 0;
    public ?string $undo_data            = null;
    public ?float  $advancement_wc       = 0;
    public int     $total_tte            = 0;
    public int     $avg_pee              = 0;

    /**
     * Sets default values for an empty struct
     */
    public function setDefaults() {
        if ( $this->review_password == null ) {
            $this->review_password = Utils::randomString();
        }
    }

    /**
     * @return JobStruct
     */
    public function getChunk(): JobStruct {
        return $this->cachable( __METHOD__, function () {
            return ChunkDao::getByIdAndPassword( $this->id_job, $this->password );
        } );
    }

    public function getUndoData() {
        return json_decode( $this->undo_data, true );
    }

}
