<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 19/02/2019
 * Time: 15:08
 */

namespace Plugins\Features\ReviewExtended;

use Model\Jobs\JobStruct;
use Model\LQA\ModelStruct;
use Model\Projects\ProjectStruct;

interface IChunkReviewModel {

    /**
     * @return JobStruct
     */
    public function getChunk();

    /**
     * adds penalty_points and updates pass fail result
     *
     * @param                         $penalty_points
     * @param ProjectStruct           $projectStruct
     *
     * @return
     */
    public function addPenaltyPoints( $penalty_points, ProjectStruct $projectStruct );

    /**
     * subtract penalty_points and updates pass fail result
     *
     * @param float         $penalty_points
     * @param ProjectStruct $projectStruct
     *
     * @return
     */
    public function subtractPenaltyPoints( float $penalty_points, ProjectStruct $projectStruct );

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
     * @param ProjectStruct $project
     *
     */
    public function recountAndUpdatePassFailResult( ProjectStruct $project );
}