<?php

namespace ChunkReviewTransition;

use Constants;
use Database;
use Features\ReviewExtended\ChunkReviewModel;
use Features\ReviewExtended\Model\ChunkReviewDao;
use Features\SecondPassReview\Model\SegmentTranslationEventDao;
use IUnitOfWork;
use LQA\EntryCommentStruct;
use LQA\EntryDao;
use PDOException;
use TransactionableTrait;

class UnitOfWork implements IUnitOfWork {

    use TransactionableTrait;

    /**
     * @var PDO
     */
    private $conn;

    /**
     * @var ChunkReviewTransitionModel[]
     */
    private $models;

    /**
     * ChunkReviewTransitionDao_UnitOfWork constructor.
     *
     * @param ChunkReviewTransitionModel[] $models
     */
    public function __construct( $models = [] ) {
        $this->conn   = Database::obtain()->getConnection();
        $this->models = $models;
    }

    /**
     * @throws \Exception
     */
    public function commit() {

        try {
            // commit the updates in a transaction
            $this->openTransaction();
            $this->updatePassFailAndCounts();

            foreach ( $this->models as $model ) {
                $this->updateFinalRevisionFlag( $model );
                $this->deleteIssues( $model );
            }

            $this->commitTransaction();

            // run chunkReviewUpdated
            foreach ( $this->models as $model ) {
                foreach ( $model->getChunkReviews() as $chunkReview ) {
                    $project          = $chunkReview->getChunk()->getProject();
                    $chunkReviewModel = new ChunkReviewModel( $chunkReview );
                    $project->getFeaturesSet()->run( 'chunkReviewUpdated', $chunkReview, true, $chunkReviewModel, $project );
                }
            }

        } catch ( PDOException $e ) {
            $this->rollback();

            \Log::doJsonLog( 'ChunkReviewTransition UnitOfWork transaction failed: ' . $e->getMessage() );

            $this->clearAll();

            return false;
        }

        $this->clearAll();

        return true;
    }

    /**
     * Update chunk review is_pass and counters
     *
     * @throws \Exception
     */
    private function updatePassFailAndCounts() {

        $data = [];

        // $data will contain an array of DIFF values used to update qa_chunk_review table
        foreach ( $this->models as $model ) {
            foreach ( $model->getChunkReviews() as $chunkReview ) {
                $data[ $chunkReview->id ][ 'chunkReview_partials' ]          = $chunkReview;
                $data[ $chunkReview->id ][ 'penalty_points' ]       = isset( $data[ $chunkReview->id ][ 'penalty_points' ] ) ? $data[ $chunkReview->id ][ 'penalty_points' ] + $chunkReview->penalty_points : $chunkReview->penalty_points;
                $data[ $chunkReview->id ][ 'reviewed_words_count' ] = isset( $data[ $chunkReview->id ][ 'reviewed_words_count' ] ) ? $data[ $chunkReview->id ][ 'reviewed_words_count' ] + $chunkReview->reviewed_words_count : $chunkReview->reviewed_words_count;
                $data[ $chunkReview->id ][ 'advancement_wc' ]       = isset( $data[ $chunkReview->id ][ 'advancement_wc' ] ) ? $data[ $chunkReview->id ][ 'advancement_wc' ] + $chunkReview->advancement_wc : $chunkReview->advancement_wc;
                $data[ $chunkReview->id ][ 'total_tte' ]            = isset( $data[ $chunkReview->id ][ 'total_tte' ] ) ? $data[ $chunkReview->id ][ 'total_tte' ] + $chunkReview->total_tte : $chunkReview->total_tte;
            }
        }

        $chunkReviewDao = new ChunkReviewDao();

        // just ONE UPDATE for each ChunkReview
        foreach ( $data as $id => $datum ) {
            $chunkReviewDao->passFailCountsAtomicUpdate( $id, $datum );
        }
    }

    /**
     * @param ChunkReviewTransitionModel $model
     *
     * @throws \Exception
     */
    private function updateFinalRevisionFlag( ChunkReviewTransitionModel $model ) {
        $eventStruct = $model->getChangeVector()->getEventModel()->getCurrentEvent();
        $is_revision = (int)$eventStruct->source_page > Constants::SOURCE_PAGE_TRANSLATE;

        if ( $is_revision ) {
            $unsetFinalRevision = array_merge( $model->getUnsetFinalRevision(), [ $eventStruct->source_page ] );
        }

        if ( !empty( $unsetFinalRevision ) ) {
            ( new SegmentTranslationEventDao() )->unsetFinalRevisionFlag(
                    $model->getChangeVector()->getChunk()->id, [ $model->getChangeVector()->getSegmentStruct()->id ], $unsetFinalRevision
            );
        }

        $eventStruct->final_revision = $is_revision;
        SegmentTranslationEventDao::updateStruct( $eventStruct, [ 'fields' => [ 'final_revision' ] ] );
    }

    /**
     * Delete all issues
     *
     * @param ChunkReviewTransitionModel $model
     */
    private function deleteIssues( ChunkReviewTransitionModel $model ) {
        foreach ( $model->getIssuesToDelete() as $issue ) {
            $issue->addComments( ( new EntryCommentStruct() )->getEntriesById( $issue->id ) );
            EntryDao::deleteEntry( $issue );
        }
    }

    public function rollback() {
        $this->rollbackTransaction();
    }

    public function clearAll() {
        $this->models = new self( $this->models );
    }
}