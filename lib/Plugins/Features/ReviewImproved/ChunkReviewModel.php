<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/25/16
 * Time: 3:28 PM
 */

namespace Features\ReviewImproved;


use LQA\ChunkReviewDao;

class ChunkReviewModel
{
    /**
     * @var \LQA\ChunkReviewStruct
     */
    private $chunk_review;


    private $score;


    public function __construct( \LQA\ChunkReviewStruct $chunk_review ) {
        $this->chunk_review = $chunk_review ;
        $this->score = $this->chunk_review->score ;
    }

    /**
     * Adds reviewed words count and recomputes result
     *
     * @param $count
     */

    public function addWordsCount( $count ) {
        $this->chunk_review->reviewed_words_count += $count ;
        $this->updatePassFailResult() ;
    }

    /**
     * Subtracts reviewed_words_count and recomputes result
     *
     * @param $count
     */
    public function subtractWordsCount( $count ) {
        $this->chunk_review->reviewed_words_count -= $count ;
        $this->updatePassFailResult() ;
    }

    /**
     * adds score and updates pass fail result
     *
     * @param $score
     */
    public function addScore( $score ) {
        $this->chunk_review->score += $score;
        $this->updatePassFailResult();
    }

    /**
     * subtract score and updates pass fail result
     *
     * @param $score
     */

    public function subtractScore( $score ) {
        $this->chunk_review->score -= $score;
        $this->updatePassFailResult();
    }

    /**
     * This method invokes the recount of reviewed_words_count and
     * score for the chunk and updates the passfail result.
     */
    public function recountAndUpdatePassFailResult() {
        $chunk = $this->chunk_review->getChunk();

        $this->chunk_review->score =
                ChunkReviewDao::getScoreForChunk( $chunk );

        $this->chunk_review->reviewed_words_count =
                ChunkReviewDao::getReviewedWordsCountForChunk( $chunk );

        $this->updatePassFailResult();
    }

    /**
     *
     * @throws \Exception
     */
    public function updatePassFailResult() {
        $score_per_mille =  $this->chunk_review->score /
            $this->chunk_review->reviewed_words_count * 1000 ;

        $project = \Projects_ProjectDao::findById( $this->chunk_review->id_project );
        $lqa_model = $project->getLqaModel();

        $this->chunk_review->is_pass = ( $score_per_mille <= $lqa_model->getLimit() ) ;

        ChunkReviewDao::updateStruct( $this->chunk_review, array(
            'fields' => array('reviewed_words_count', 'is_pass', 'score'))
        );
    }

}