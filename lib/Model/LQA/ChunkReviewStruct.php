<?php

namespace Model\LQA;

use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;
use Model\Jobs\ChunkDao;
use Model\Jobs\JobStruct;
use Utils\Tools\Utils;

class ChunkReviewStruct extends AbstractDaoSilentStruct implements IDaoStruct {

    public $id;
    public $id_project;
    public $id_job;
    public $password;
    public $review_password;
    public $penalty_points       = 0;
    public $source_page;
    public $is_pass;
    public $force_pass_at;
    public $reviewed_words_count = 0;
    public $undo_data;
    public $advancement_wc       = 0;
    public $total_tte            = 0;
    public $avg_pee              = 0;

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
        $review = clone $this;

        return $this->cachable( __FUNCTION__, $review, function ( $review ) {
            return ChunkDao::getByIdAndPassword( $review->id_job, $review->password );
        } );
    }

    public function getUndoData() {
        return json_decode( $this->undo_data, true );
    }

}
