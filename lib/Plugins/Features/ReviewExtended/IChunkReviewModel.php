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

interface IChunkReviewModel
{

    /**
     * @return JobStruct
     */
    public function getChunk(): JobStruct;

    /**
     * adds penalty_points and updates pass fail result
     *
     * @param                         $penalty_points
     * @param ProjectStruct           $projectStruct
     *
     * @return void
     */
    public function addPenaltyPoints($penalty_points, ProjectStruct $projectStruct): void;

    /**
     * subtract penalty_points and updates pass fail result
     *
     * @param float         $penalty_points
     * @param ProjectStruct $projectStruct
     *
     * @return void
     */
    public function subtractPenaltyPoints(float $penalty_points, ProjectStruct $projectStruct): void;

    /**
     * Returns the calculated score
     */
    public function getScore(): float;

    public function getPenaltyPoints(): ?float;

    public function getReviewedWordsCount(): int;

    public function getQALimit(ModelStruct $lqa_model): int;

    /**
     * This method invokes the recount of reviewed_words_count and
     * penalty_points for the chunk and updates the passfail result.
     *
     * @param ProjectStruct $project
     *
     * @return void
     */
    public function recountAndUpdatePassFailResult(ProjectStruct $project): void;
}