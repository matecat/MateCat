<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 19/02/2019
 * Time: 15:08
 */

namespace Features\ReviewExtended;

use Jobs_JobStruct;
use LQA\ModelStruct;
use Projects_ProjectStruct;

interface IChunkReviewModel {

    /**
     * @return Jobs_JobStruct
     */
    public function getChunk();

    /**
     * adds penalty_points and updates pass fail result
     *
     * @param                         $penalty_points
     * @param \Projects_ProjectStruct $projectStruct
     *
     * @return
     */
    public function addPenaltyPoints( $penalty_points, \Projects_ProjectStruct $projectStruct );

    /**
     * subtract penalty_points and updates pass fail result
     *
     * @param                         $penalty_points
     * @param \Projects_ProjectStruct $projectStruct
     *
     * @return
     */
    public function subtractPenaltyPoints( $penalty_points, \Projects_ProjectStruct $projectStruct );

    /**
     * Returns the calculated score
     */
    public function getScore();

    public function getPenaltyPoints();

    public function getReviewedWordsCount();

    public function getQALimit( ModelStruct $lqa_model );

    /**
     * This method invokes the recount of reviewed_words_count and
     * penalty_points for the chunk and updates the passfail result.
     *
     * @param Projects_ProjectStruct $project
     *
     */
    public function recountAndUpdatePassFailResult( Projects_ProjectStruct $project );
}