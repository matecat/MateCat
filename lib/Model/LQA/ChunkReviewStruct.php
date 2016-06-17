<?php

namespace LQA ;

class ChunkReviewStruct extends \DataAccess_AbstractDaoSilentStruct implements \DataAccess_IDaoStruct {

    public $id;
    public $id_project ;
    public $id_job ;
    public $password ;
    public $review_password ;
    public $penalty_points ;
    public $num_errors ;
    public $is_pass ;
    public $force_pass_at ;
    public $reviewed_words_count ;

    /**
     * Sets default values for an empty struct
     */
    public function setDefaults() {
        if ( $this->review_password == null ) {
            $this->review_password = \CatUtils::generate_password( 12 );
        }
    }
    /**
     * @return \Chunks_ChunkStruct
     */
    public function getChunk() {
        $review = clone $this;
        return $this->cachable(__FUNCTION__, $review , function($review) {
            return \Chunks_ChunkDao::getByIdAndPassword($review->id_job, $review->password);
        });
    }

    /**
     * @return int
     */
    public function getReviewedPercentage() {
        return round( ($this->reviewed_words_count /
        $this->getChunk()->totalWordsCount() *
        100), 2 );
    }


}
