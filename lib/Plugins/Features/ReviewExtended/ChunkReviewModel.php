<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 17/10/2018
 * Time: 18:53
 */


namespace Features\ReviewExtended;

use Features\ReviewExtended\Model\ChunkReviewDao;

use Features\SecondPassReview;
use Features\SecondPassReview\Utils;
use LQA\ChunkReviewStruct;
use Projects_ProjectDao;

class ChunkReviewModel implements IChunkReviewModel {

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
     * @param $eq_count
     */

    public function addWordsCount( $count, $eq_count ) {
        $this->chunk_review->reviewed_words_count += $count ;
        $this->chunk_review->eq_reviewed_words_count += $eq_count ;
        $this->updatePassFailResult() ;
    }

    /**
     * Subtracts reviewed_words_count and recomputes result
     *
     * @param $count
     * @param $eq_count
     */
    public function subtractWordsCount( $count, $eq_count ) {
        $this->chunk_review->reviewed_words_count -= $count ;
        $this->chunk_review->eq_reviewed_words_count -= $eq_count ;
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
        $this->chunk_review->is_pass = ( $this->getScore() <= $this->getQALimit() ) ;

        $update_result = ChunkReviewDao::updateStruct( $this->chunk_review, [
             'fields' => array('eq_reviewed_words_count', 'reviewed_words_count', 'is_pass', 'penalty_points')
            ]
        );

        $this->chunk_review->getChunk()->getProject()->getFeatures()->run(
                'chunkReviewUpdated', $this->chunk_review, $update_result, $this
        );
    }

    /**
     * Returns the proper limit for the current review stage.
     *
     * @return array|mixed
     */
    public function getQALimit() {
        $project = Projects_ProjectDao::findById( $this->chunk_review->id_project );
        $lqa_model = $project->getLqaModel();
        return SecondPassReview\Utils::filterLQAModelLimit( $lqa_model, $this->chunk_review->source_page ) ;
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


}