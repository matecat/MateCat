<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 17/10/2018
 * Time: 18:53
 */


namespace Plugins\Features\ReviewExtended;

use Exception;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use Model\LQA\ModelStruct;
use Model\Projects\ProjectStruct;

class ChunkReviewModel implements IChunkReviewModel {

    /**
     * @var ChunkReviewStruct
     */
    protected ChunkReviewStruct $chunk_review;

    /**
     * @var JobStruct
     */
    protected JobStruct $chunk;

    /**
     * @return JobStruct
     */
    public function getChunk(): JobStruct {
        return $this->chunk;
    }

    /**
     * ChunkReviewModel constructor.
     *
     * @param ChunkReviewStruct $chunk_review
     */
    public function __construct( ChunkReviewStruct $chunk_review ) {
        $this->chunk_review = $chunk_review;
        $this->chunk        = $this->chunk_review->getChunk();
    }

    /**
     * adds penalty_points and updates pass fail result
     *
     * @param                         $penalty_points
     *
     * @param ProjectStruct           $projectStruct
     *
     * @throws Exception
     */
    public function addPenaltyPoints( $penalty_points, ProjectStruct $projectStruct ) {
        $this->updateChunkReviewCountersAndPassFail( $penalty_points, 0, 0, $projectStruct );
    }

    /**
     * subtract penalty_points and updates pass fail result
     *
     * @param float         $penalty_points
     *
     * @param ProjectStruct $projectStruct
     *
     * @throws Exception
     */
    public function subtractPenaltyPoints( float $penalty_points, ProjectStruct $projectStruct ) {
        $this->updateChunkReviewCountersAndPassFail( -$penalty_points, 0, 0, $projectStruct );
    }

    /**
     * Update chunk review
     *
     * Warning, integer parameters are expected signed (+/-) for increment or decrement
     *
     * @throws Exception
     */
    public function updateChunkReviewCountersAndPassFail( float $penalty_points, int $reviewed_word_count, int $tte, ProjectStruct $projectStruct ){
        $data = [
                'chunkReview'          => $this->chunk_review,
                'penalty_points'       => $penalty_points,
                'reviewed_words_count' => $reviewed_word_count,
                'total_tte'            => $tte,
        ];

        $this->_updatePassFailResult( $projectStruct, $data );
    }

    /**
     * Returns the calculated score
     */
    public function getScore(): float {
        if ( $this->chunk_review->reviewed_words_count == 0 ) {
            return 0;
        }

        return $this->chunk_review->penalty_points / $this->chunk_review->reviewed_words_count * 1000;
    }

    /**
     * @return float
     */
    public function getPenaltyPoints(): float {
        return $this->chunk_review->penalty_points;
    }

    public function getReviewedWordsCount(): int {
        return $this->chunk_review->reviewed_words_count;
    }

    /**
     * Used only by ChunkReviewModel::[subtractPenaltyPoints, addPenaltyPoints]
     *
     * @param ProjectStruct $project
     * @param array         $data
     *
     * @throws Exception
     */
    protected function _updatePassFailResult( ProjectStruct $project, array $data ): void {

        $chunkReviewDao = new ChunkReviewDao();
        $chunkReviewDao->passFailCountsAtomicUpdate( $this->chunk_review->id, $data );

        $project->getFeaturesSet()->run(
                'chunkReviewUpdated', $this->chunk_review, 1, $this, $project
        );

    }

    /**
     * Returns the proper limit for the current review stage.
     *
     * @param ModelStruct $lqa_model
     *
     * @return int
     * @throws Exception
     */
    public function getQALimit( ModelStruct $lqa_model ): int {
        return ReviewUtils::filterLQAModelLimit( $lqa_model, $this->chunk_review->source_page );
    }

    /**
     *
     * Used to recount total in qa_chunk reviews in case of: [ split/merge/chunk record created/disaster recovery ]
     *
     * Used in AbstractRevisionFeature::postJobMerged and AbstractRevisionFeature::postJobSplitted
     *
     * @param ProjectStruct $project
     *
     * @throws Exception
     */
    public function recountAndUpdatePassFailResult( ProjectStruct $project ) {

        /**
         * Count penalty points based on this source_page
         */
        $chunkReviewDao                           = new ChunkReviewDao();
        $this->chunk_review->penalty_points       = $chunkReviewDao->getPenaltyPointsForChunk( $this->chunk, $this->chunk_review->source_page );
        $this->chunk_review->reviewed_words_count = $chunkReviewDao->getReviewedWordsCountForSecondPass( $this->chunk, $this->chunk_review->source_page );
        $this->chunk_review->total_tte            = $chunkReviewDao->countTimeToEdit( $this->chunk, $this->chunk_review->source_page );

        if ( $project->getLqaModel() ) {
            $this->chunk_review->is_pass = ( $this->getScore() <= $this->getQALimit( $project->getLqaModel() ) );
        } else {
            $this->chunk_review->is_pass = true;
        }

        $update_result = ChunkReviewDao::updateStruct( $this->chunk_review, [
                        'fields' => [
                                'reviewed_words_count',
                                'is_pass',
                                'penalty_points',
                                'total_tte'
                        ]
                ]
        );

        // External call by Plugins
        $project->getFeaturesSet()->run(
                'chunkReviewUpdated', $this->chunk_review, $update_result, $this, $project
        );

    }


}