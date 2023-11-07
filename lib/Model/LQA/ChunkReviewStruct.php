<?php

namespace LQA ;

use Chunks_ChunkDao;
use Utils;

class ChunkReviewStruct extends \DataAccess_AbstractDaoSilentStruct implements \DataAccess_IDaoStruct {

    public $id ;
    public $id_project ;
    public $id_job ;
    public $password ;
    public $review_password ;
    public $penalty_points ;
    public $source_page ;
    public $is_pass ;
    public $force_pass_at ;
    public $reviewed_words_count = 0;
    public $undo_data ;
    public $advancement_wc = 0;
    public $total_tte = 0;
    public $avg_pee = 0;

    /**
     * Sets default values for an empty struct
     */
    public function setDefaults() {
        if ( $this->review_password == null ) {
            $this->review_password = Utils::randomString();
        }
    }
    /**
     * @return \Chunks_ChunkStruct
     */
    public function getChunk() {
        $review = clone $this;
        return $this->cachable(__FUNCTION__, $review , function($review) {
            return Chunks_ChunkDao::getByIdAndPassword($review->id_job, $review->password);
        });
    }

    /**
     * @return int
     */
    public function getReviewedPercentage() {
        $count = $this->getChunk()->totalWordsCount();
        return round( ($this->reviewed_words_count /
                ( empty( $count ) ? 1 : $count ) *
        100), 2 );
    }

    public function getUndoData() {
        return json_decode( $this->undo_data, true ) ;
    }

}
