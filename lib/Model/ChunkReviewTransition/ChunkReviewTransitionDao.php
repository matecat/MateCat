<?php

use Features\ReviewExtended\ChunkReviewModel;
use LQA\EntryCommentDao;
use LQA\EntryDao;

class ChunkReviewTransitionDao_ChunkReviewTransitionDao {
    /**
     * @var ChunkReviewTransitionDao_ChunkReviewTransitionModel
     */
    private $model;

    /**
     * ChunkReviewTransitionDao_ChunkReviewTransitionDao constructor.
     *
     * @param ChunkReviewTransitionDao_ChunkReviewTransitionModel $model
     */
    public function __construct( ChunkReviewTransitionDao_ChunkReviewTransitionModel $model ) {
        $this->model = $model;
    }


    /**
     * Update chunk review counters
     *
     * @throws Exception
     */
    public function updatePassFailResult() {
        foreach ($this->model->getChunkReviews() as $chunkReview){
            $chunkReviewModel = new ChunkReviewModel( $chunkReview );
            $chunkReviewModel->atomicUpdatePassFailResult( $chunkReview->getChunk()->getProject() );
        }
    }

    /**
     * Delete all issues
     */
    public function deleteIssues() {
        foreach ( $this->model->getIssuesToDelete() as $issue ) {
            $issue->addComments( ( new EntryCommentDao() )->findByIssueId( $issue->id ) );
            EntryDao::deleteEntry( $issue );
        }
    }
}