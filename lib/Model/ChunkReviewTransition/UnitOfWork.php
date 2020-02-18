<?php

use Features\ReviewExtended\ChunkReviewModel;
use Features\SecondPassReview\Model\SegmentTranslationEventDao;
use LQA\EntryCommentDao;
use LQA\EntryDao;

class ChunkReviewTransitionDao_UnitOfWork implements IUnitOfWork
{
    /**
     * @var PDO
     */
    private $conn;

    /**
     * @var ChunkReviewTransitionDao_ChunkReviewTransitionModel
     */
    private $model;

    /**
     * ChunkReviewTransitionDao_UnitOfWork constructor.
     *
     * @param ChunkReviewTransitionDao_ChunkReviewTransitionModel $model
     */
    public function __construct( ChunkReviewTransitionDao_ChunkReviewTransitionModel $model) {
        $this->conn  = Database::obtain()->getConnection();
        $this->model = $model;
    }

    /**
     * @throws Exception
     */
    public function commit() {

        try {
            $this->conn->beginTransaction();

            $this->updatePassFailResult();
            $this->deleteIssues();

            $this->conn->commit();
        } catch (PDOException $e){
            $this->rollback();

            \Log::doJsonLog('ChunkReviewTransition UnitOfWork transaction failed: ' . $e->getMessage());
        }

        $this->clearAll();
    }

    /**
     * Update chunk review counters
     *
     * @throws Exception
     */
    private function updatePassFailResult() {
        foreach ($this->model->getChunkReviews() as $chunkReview){
            $chunkReviewModel = new ChunkReviewModel( $chunkReview );
            $chunkReviewModel->atomicUpdatePassFailResult( $chunkReview->getChunk()->getProject() );
        }
    }

    /**
     * Unset the final_revision flag from any revision we removed reviwed_words.
     * Apply final_revision flag to the current event.
     *
     * If the current event is a revision, ensure the source_page is included in the
     * unset list.
     *
     * @param $unsetFinalRevision
     *
     * @throws \Exception
     */
    private function updateFinalRevisionFlag( $unsetFinalRevision ) {
        $eventStruct = $this->_model->getEventModel()->getCurrentEvent();
        $is_revision = (int)$eventStruct->source_page > Constants::SOURCE_PAGE_TRANSLATE;

        if ( $is_revision ) {
            $unsetFinalRevision = array_merge( $unsetFinalRevision, [ $eventStruct->source_page ] );
        }

        if ( !empty( $unsetFinalRevision ) ) {
            ( new SegmentTranslationEventDao() )->unsetFinalRevisionFlag(
                    $this->_chunk->id, [ $this->_model->getSegmentStruct()->id ], $unsetFinalRevision
            );
        }

        $eventStruct->final_revision = $is_revision;
        SegmentTranslationEventDao::updateStruct( $eventStruct, [ 'fields' => [ 'final_revision' ] ] );
    }

    /**
     * Delete all issues
     */
    private function deleteIssues() {
        foreach ( $this->model->getIssuesToDelete() as $issue ) {
            $issue->addComments( ( new EntryCommentDao() )->findByIssueId( $issue->id ) );
            EntryDao::deleteEntry( $issue );
        }
    }

    public function rollback() {
        $this->conn->rollBack();
    }

    public function clearAll() {
        $this->model = new ChunkReviewTransitionDao_ChunkReviewTransitionModel();
    }
}