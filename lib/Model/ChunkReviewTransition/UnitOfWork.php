<?php

namespace ChunkReviewTransition;

use Constants;
use Database;
use Features\ReviewExtended\ChunkReviewModel;
use Features\ReviewExtended\Model\ChunkReviewDao;
use Features\ReviewExtended\ReviewUtils;
use Features\SecondPassReview\Model\SegmentTranslationEventDao;
use IUnitOfWork;
use LQA\ChunkReviewStruct;
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
                $data[ $chunkReview->id ][ 'penalty_points' ]       = $data[ $chunkReview->id ][ 'penalty_points' ] + $chunkReview->penalty_points;
                $data[ $chunkReview->id ][ 'reviewed_words_count' ] = $data[ $chunkReview->id ][ 'reviewed_words_count' ] + $chunkReview->reviewed_words_count;
                $data[ $chunkReview->id ][ 'advancement_wc' ]       = $data[ $chunkReview->id ][ 'advancement_wc' ] + $chunkReview->advancement_wc;
                $data[ $chunkReview->id ][ 'total_tte' ]            = $data[ $chunkReview->id ][ 'total_tte' ] + $chunkReview->total_tte;
            }
        }

        $chunkReviewDao = new ChunkReviewDao();

        // just ONE UPDATE for each ChunkReview
        foreach ( $data as $id => $datum ) {

            $chunkReview = $chunkReviewDao->findById( $id );

            $datum[ 'is_pass' ]              = $this->calculateIsPass( $chunkReview, $datum );
            $datum[ 'penalty_points' ]       = $this->recheckDatum( $chunkReview, $datum, 'penalty_points' );
            $datum[ 'reviewed_words_count' ] = $this->recheckDatum( $chunkReview, $datum, 'reviewed_words_count' );
            $datum[ 'advancement_wc' ]       = $this->recheckDatum( $chunkReview, $datum, 'advancement_wc' );
            $datum[ 'total_tte' ]            = $this->recheckDatum( $chunkReview, $datum, 'total_tte' );

            $chunkReviewDao->passFailCountsAtomicUpdate( $chunkReview, $datum );
        }
    }

    /**
     * @param ChunkReviewStruct $chunkReview
     * @param array             $datum
     *
     * @return bool
     * @throws \Exception
     */
    private function calculateIsPass( ChunkReviewStruct $chunkReview, $datum ) {
        $lqaModelLimit = ReviewUtils::filterLQAModelLimit( $chunkReview->getChunk()->getProject()->getLqaModel(), $chunkReview->source_page );
        $wordCount     = $chunkReview->reviewed_words_count + $datum[ 'reviewed_words_count' ];
        $penaltyPoints = $chunkReview->penalty_points + $datum[ 'penalty_points' ];
        $score         = ( $wordCount == 0 ) ? 0 : $penaltyPoints / $wordCount * 1000;

        return ( $score <= $lqaModelLimit );
    }

    /**
     * This method does not allow to update a ChunkReviewStruct field to a negative value
     * (in case of negative values this method set them to 0)
     *
     * @param ChunkReviewStruct $chunkReview
     * @param array             $datum
     * @param string            $key
     *
     * @return int
     */
    private function recheckDatum( ChunkReviewStruct $chunkReview, $datum, $key ) {

        if ( ( $chunkReview->$key + $datum[ $key ] ) < 0 ) {
            return 0;
        }

        return $datum[ $key ];
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