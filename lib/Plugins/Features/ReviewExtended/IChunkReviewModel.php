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
use Model\Users\UserStruct;

interface IChunkReviewModel
{

    /**
     * @return JobStruct
     */
    public function getChunk(): JobStruct;

    /**
     * adds penalty_points and updates pass fail result
     *
     * @param float $penalty_points
     * @param ProjectStruct $projectStruct
     * @param UserStruct $actingUser
     * @return void
     */
    public function addPenaltyPoints(float $penalty_points, ProjectStruct $projectStruct, UserStruct $actingUser): void;

    /**
     * subtract penalty_points and updates pass fail result
     *
     * @param float $penalty_points
     * @param ProjectStruct $projectStruct
     * @param UserStruct $actingUser
     * @return void
     */
    public function subtractPenaltyPoints(float $penalty_points, ProjectStruct $projectStruct, UserStruct $actingUser): void;

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
     * @param UserStruct $actingUser
     * @return void
     */
    public function recountAndUpdatePassFailResult(ProjectStruct $project, UserStruct $actingUser): void;

    /**
     * The same recount, but deriving reviewed_words_count from the final revision records rather than from
     * the segments' current status, which is only correct for the top phase of a job. Repair tasks use this
     * one because they must hold for any job shape.
     *
     * @param ProjectStruct $project
     * @param UserStruct $actingUser
     * @return void
     */
    public function recountAndUpdatePassFailResultFromFinalRevisions(ProjectStruct $project, UserStruct $actingUser): void;
}