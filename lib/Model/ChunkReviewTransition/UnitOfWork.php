<?php

namespace ChunkReviewTransition;

use Constants;
use Database;
use Features\ReviewExtended\ChunkReviewModel;
use Features\SecondPassReview\Model\SegmentTranslationEventDao;
use IUnitOfWork;
use LQA\EntryCommentDao;
use LQA\EntryDao;
use PDOException;

class UnitOfWork implements IUnitOfWork {
    /**
     * @var PDO
     */
    private $conn;

    /**
     * @var ChunkReviewTransitionModel
     */
    private $model;

    /**
     * ChunkReviewTransitionDao_UnitOfWork constructor.
     *
     * @param ChunkReviewTransitionModel $model
     */
    public function __construct( ChunkReviewTransitionModel $model ) {
        $this->conn  = Database::obtain()->getConnection();
        $this->model = $model;
    }

    /**
     * @throws \Exception
     */
    public function commit() {

        try {

            if ( !$this->conn->inTransaction() ) {
                $this->conn->beginTransaction();
            }

            $this->updatePassFailResult();
            $this->updateFinalRevisionFlag();
            $this->deleteIssues();

            $this->conn->commit();
        } catch ( PDOException $e ) {
            $this->rollback();

            \Log::doJsonLog( 'ChunkReviewTransition UnitOfWork transaction failed: ' . $e->getMessage() );
        }

        $this->clearAll();
    }

    /**
     * Update chunk review counters
     *
     * @throws \Exception
     */
    private function updatePassFailResult() {
        foreach ( $this->model->getChunkReviews() as $chunkReview ) {
            $chunkReviewModel = new ChunkReviewModel( $chunkReview );
            $chunkReviewModel->atomicUpdatePassFailResult( $chunkReview->getChunk()->getProject() );
        }
    }

    /**
     * @throws \Exception
     */
    private function updateFinalRevisionFlag() {
        $eventStruct = $this->model->getChangeVector()->getEventModel()->getCurrentEvent();
        $is_revision = (int)$eventStruct->source_page > Constants::SOURCE_PAGE_TRANSLATE;

        if ( $is_revision ) {
            $unsetFinalRevision = array_merge( $this->model->getUnsetFinalRevision(), [ $eventStruct->source_page ] );
        }

        if ( !empty( $unsetFinalRevision ) ) {
            ( new SegmentTranslationEventDao() )->unsetFinalRevisionFlag(
                    $this->model->getChangeVector()->getChunk()->id, [ $this->model->getChangeVector()->getSegmentStruct()->id ], $unsetFinalRevision
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
        $this->model = new self( $this->model );
    }
}