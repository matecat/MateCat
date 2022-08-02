<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 17/10/2018
 * Time: 18:53
 */


namespace Features\ReviewExtended;

use Chunks_ChunkStruct;
use Exception;
use Features\ReviewExtended\Model\ChunkReviewDao;
use LQA\ChunkReviewStruct;
use LQA\ModelStruct;
use Projects_ProjectDao;
use Projects_ProjectStruct;

class ChunkReviewModel implements IChunkReviewModel {

    /**
     * @var \LQA\ChunkReviewStruct
     */
    protected $chunk_review;

    protected $penalty_points;

    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk;

    /**
     * @var ModelStruct
     */
    protected $lqa_model;

    /**
     * @return Chunks_ChunkStruct
     */
    public function getChunk(){
        return $this->chunk;
    }

    /**
     * ChunkReviewModel constructor.
     *
     * @param ChunkReviewStruct $chunk_review
     */
    public function __construct( ChunkReviewStruct $chunk_review ) {
        $this->chunk_review = $chunk_review;
        $this->chunk = $this->chunk_review->getChunk();
    }

    /**
     * adds penalty_points and updates pass fail result
     *
     * @param                         $penalty_points
     *
     * @param \Projects_ProjectStruct $projectStruct
     *
     * @throws \Exception
     */
    public function addPenaltyPoints( $penalty_points, \Projects_ProjectStruct $projectStruct ) {
        $this->chunk_review->penalty_points += $penalty_points;
        $this->_updatePassFailResult( $projectStruct );
    }

    /**
     * subtract penalty_points and updates pass fail result
     *
     * @param                         $penalty_points
     *
     * @param \Projects_ProjectStruct $projectStruct
     *
     * @throws \Exception
     */
    public function subtractPenaltyPoints( $penalty_points, \Projects_ProjectStruct $projectStruct  ) {
        $this->chunk_review->penalty_points -= $penalty_points;
        $this->_updatePassFailResult( $projectStruct );
    }

    /**
     * Returns the calculated score
     */
    public function getScore() {
        if ( $this->chunk_review->reviewed_words_count == 0 ) {
            return 0;
        } else {
            return $this->chunk_review->penalty_points / $this->chunk_review->reviewed_words_count * 1000;
        }
    }

    public function getPenaltyPoints() {
        return $this->chunk_review->penalty_points;
    }

    public function getReviewedWordsCount() {
        return $this->chunk_review->reviewed_words_count;
    }

    /**
     * WARNING: ( should be protected ) this **MUST** be used only by ChunkReviewModel::[recountAndUpdatePassFailResult, subtractPenaltyPoints, addPenaltyPoints]
     * @param Projects_ProjectStruct $project
     *
     * @return bool
     * @throws \Exception
     */
    public function _updatePassFailResult( Projects_ProjectStruct $project ) {

        $this->chunk_review->is_pass = ( $this->getScore() <= $this->getQALimit( $project->getLqaModel() ) );

        $update_result = ChunkReviewDao::updateStruct( $this->chunk_review, [
                        'fields' => [
                                'advancement_wc',
                                'reviewed_words_count',
                                'is_pass',
                                'penalty_points',
                                'total_tte'
                        ]
                ]
        );

        $project->getFeaturesSet()->run(
            'chunkReviewUpdated', $this->chunk_review, $update_result, $this, $project
        );

        return $update_result;
    }

    /**
     * Returns the proper limit for the current review stage.
     *
     * @param ModelStruct $lqa_model
     *
     * @return array|mixed
     * @throws Exception
     */
    public function getQALimit( ModelStruct $lqa_model ) {
        return ReviewUtils::filterLQAModelLimit( $lqa_model, $this->chunk_review->source_page );
    }

    /**
     * This method invokes the recount of reviewed_words_count and
     * penalty_points for the chunk and updates the passfail result.
     *
     * @param \Projects_ProjectStruct $project
     *
     * @throws \Exception
     */
    public function recountAndUpdatePassFailResult( \Projects_ProjectStruct $project ) {

        $chunkReviewDao = new ChunkReviewDao();

        $this->chunk_review->penalty_points       = ChunkReviewDao::getPenaltyPointsForChunk( $this->chunk );
        $this->chunk_review->reviewed_words_count = ChunkReviewDao::getReviewedWordsCountForChunk( $this->chunk );

        $this->chunk_review->advancement_wc = $chunkReviewDao->recountAdvancementWords( $this->chunk, $this->chunk_review->source_page );
        $this->chunk_review->total_tte      = $chunkReviewDao->countTimeToEdit( $this->chunk, $this->chunk_review->source_page );

        $this->_updatePassFailResult( $project );
    }


}