<?php

namespace LQA ;

class ChunkReviewStruct extends \DataAccess_AbstractDaoSilentStruct implements \DataAccess_IDaoStruct {

    public $id;
    public $id_project ;
    public $id_job ;
    public $password ;
    public $review_password ;
    public $score ;
    public $num_errors ;
    public $is_pass ;
    public $force_pass_at ;
    public $reviewed_words_count ;

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
        return (int) ( $this->reviewed_words_count /
        $this->getChunk()->totalWordsCount() *
        100 );
    }


}
