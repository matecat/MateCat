<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 19/02/2019
 * Time: 15:08
 */

namespace Features\ReviewExtended;

interface IChunkReviewModel {
    /**
     * Adds reviewed words count and recomputes result
     *
     * @param $count
     */
    public function addWordsCount( $count );

    /**
     * Subtracts reviewed_words_count and recomputes result
     *
     * @param $count
     */
    public function subtractWordsCount( $count );

    /**
     * adds penalty_points and updates pass fail result
     *
     * @param $penalty_points
     */
    public function addPenaltyPoints( $penalty_points );

    /**
     * subtract penalty_points and updates pass fail result
     *
     * @param $penalty_points
     */
    public function subtractPenaltyPoints( $penalty_points );

    /**
     * Returns the calculated score
     */
    public function getScore();

    public function getPenaltyPoints();

    public function getReviewedWordsCount();

    /**
     *
     * @throws \Exception
     */
    public function updatePassFailResult();

    /**
     * This method invokes the recount of reviewed_words_count and
     * penalty_points for the chunk and updates the passfail result.
     */
    public function recountAndUpdatePassFailResult();
}