<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/25/16
 * Time: 3:28 PM
 */

namespace Features\ReviewImproved;


use LQA\ChunkReviewDao;
use LQA\ChunkReviewStruct;

class ChunkReviewModel
{
    /**
     * @var \LQA\ChunkReviewStruct
     */
    protected $chunk_review;


    protected $penalty_points;


    public function __construct( ChunkReviewStruct $chunk_review ) {
        $this->chunk_review = $chunk_review ;
        $this->penalty_points = $this->chunk_review->penalty_points ;
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
     * adds penalty_points and updates pass fail result
     *
     * @param $penalty_points
     */
    public function addPenaltyPoints($penalty_points ) {
        $this->chunk_review->penalty_points += $penalty_points;
        $this->updatePassFailResult();
    }

    /**
     * subtract penalty_points and updates pass fail result
     *
     * @param $penalty_points
     */

    public function subtractPenaltyPoints($penalty_points ) {
        $this->chunk_review->penalty_points -= $penalty_points;
        $this->updatePassFailResult();
    }

    /**
     * This method invokes the recount of reviewed_words_count and
     * penalty_points for the chunk and updates the passfail result.
     */
    public function recountAndUpdatePassFailResult() {
        $chunk = $this->chunk_review->getChunk();

        $this->chunk_review->penalty_points =
                ChunkReviewDao::getPenaltyPointsForChunk( $chunk );

        $this->chunk_review->reviewed_words_count =
                ChunkReviewDao::getReviewedWordsCountForChunk( $chunk );

        $this->updatePassFailResult();
    }

    /**
     * Returns the calculated score
     */
    public function getScore() {
        if ( $this->chunk_review->reviewed_words_count == 0 ) {
            return 0 ;
        } else {
            return $this->chunk_review->penalty_points / $this->chunk_review->reviewed_words_count * 1000 ;
        }
    }

    public function getPenaltyPoints(){
        return $this->chunk_review->penalty_points;
    }

    public function getReviewedWordsCount(){
        return $this->chunk_review->reviewed_words_count;
    }

    /**
     *
     * @throws \Exception
     */
    public function updatePassFailResult() {
        $score_per_mille = $this->getScore();

        $project = \Projects_ProjectDao::findById( $this->chunk_review->id_project );
        $lqa_model = $project->getLqaModel();

        $this->chunk_review->is_pass = ( $score_per_mille <= $lqa_model->getLimit() ) ;

        $update_result = ChunkReviewDao::updateStruct( $this->chunk_review, array(
            'fields' => array('reviewed_words_count', 'is_pass', 'penalty_points'))
        );

        $this->chunk_review->getChunk()->getProject()->getFeatures()->run(
                'chunkReviewUpdated', $this->chunk_review, $update_result, $this
        );
    }

}