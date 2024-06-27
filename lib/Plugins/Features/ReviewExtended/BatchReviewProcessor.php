<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/06/2019
 * Time: 17:44
 */

namespace Features\ReviewExtended;

use Constants;
use Exception;
use Features\ReviewExtended\Email\BatchReviewProcessorAlertEmail;
use Features\ReviewExtended\Model\ChunkReviewDao;
use Features\SecondPassReview\Model\TranslationEventDao;
use Features\TranslationVersions\Handlers\TranslationEventsHandler;
use Log;
use LQA\EntryCommentStruct;
use LQA\EntryDao;
use PDOException;
use ReflectionException;
use RevisionFactory;
use TransactionableTrait;
use WordCount\CounterModel;
use WordCount\WordCountStruct;

;

class BatchReviewProcessor {

    use TransactionableTrait;

    /**
     * @var ChunkReviewTranslationEventTransition[]
     */
    protected $segmentTransitionPhasesModel = [];

    /**
     * @var TranslationEventsHandler
     */
    protected $_translationEventsHandler;

    /**
     * @var CounterModel
     */
    private $jobWordCounter;

    /**
     * @throws ReflectionException
     * @throws PDOException
     */
    public function __construct( TranslationEventsHandler $eventCreator ) {
        $this->_translationEventsHandler = $eventCreator;

        $chunk = $this->_translationEventsHandler->getChunk();
        $old_wStruct = WordCountStruct::loadFromJob( $chunk );
        $this->jobWordCounter = new CounterModel( $old_wStruct );

    }

    /**
     * @throws Exception
     */
    public function process() {

        $chunk = $this->_translationEventsHandler->getChunk();

        //
        // ----------------------------------------------
        // Note 2020-06-24
        // ----------------------------------------------
        // If $chunk is null:
        //
        // log and exit
        //
        if ( null === $chunk ) {
            Log::doJsonLog( 'This batch review processor has not a associated chunk. Exiting here...' );

            return;
        }

        $project      = $chunk->getProject();
        $chunkReviews = ( new ChunkReviewDao() )->findChunkReviews( $chunk );

        //
        // ----------------------------------------------
        // Note 2020-06-24
        // ----------------------------------------------
        // If $chunkReviews is empty:
        //
        // 1) create a chunkReview
        // 2) send an alert email
        //
        if ( empty( $chunkReviews ) ) {

            $data = [
                    'id_project'  => $project->id,
                    'id_job'      => $chunk->id,
                    'password'    => $chunk->password,
                    'source_page' => 2,
            ];

            $chunkReview = ChunkReviewDao::createRecord( $data );
            $project->getFeaturesSet()->run( 'chunkReviewRecordCreated', $chunkReview, $project );
            $chunkReviews[] = $chunkReview;

            Log::doJsonLog( 'Batch review processor created a new chunkReview (id ' . $chunkReview->id . ') for chunk with id ' . $chunk->id );

            $alertEmail = new BatchReviewProcessorAlertEmail( $chunk, $chunkReview );
            $alertEmail->send();
        }

        $revisionFactory = RevisionFactory::initFromProject( $project );

        $this->segmentTransitionPhasesModel = [];
        $segmentTranslationModels           = [];

        foreach ( $this->_translationEventsHandler->getPersistedEvents() as $translationEvent ) {
            $segmentTranslationModel    = $revisionFactory->getSegmentTranslationModel( $translationEvent, $chunkReviews, $this->jobWordCounter );
            $segmentTranslationModels[] = $segmentTranslationModel;

            // here we process and count the reviewed word count and
            $this->segmentTransitionPhasesModel[] = $segmentTranslationModel->evaluateAndGetChunkReviewTranslationEventTransition();
        }

        // uow
        if ( $this->commit() ) {
            // send notification emails
            foreach ( $segmentTranslationModels as $segmentTranslationModel ) {
                $segmentTranslationModel->sendNotificationEmail();
            }
        }

    }

    /**
     * @return boolean
     * @throws Exception
     */
    protected function commit() {

        try {
            // commit the updates in a transaction
            $this->updatePassFailAndCounts();

            // if empty, no segment status changes are present
            if ( !empty( $this->jobWordCounter->getValues() ) ) {
                $newCount                = $this->jobWordCounter->updateDB( $this->jobWordCounter->getValues() );
                $chunk                   = $this->_translationEventsHandler->getChunk();
                $chunk->draft_words      = $newCount->getDraftWords();
                $chunk->new_words        = $newCount->getNewWords();
                $chunk->translated_words = $newCount->getTranslatedWords();
                $chunk->approved_words   = $newCount->getApprovedWords();
                $chunk->approved2_words  = $newCount->getApproved2Words();
                $chunk->rejected_words   = $newCount->getRejectedWords();

                $chunk->draft_raw_words      = $newCount->getDraftRawWords();
                $chunk->new_raw_words        = $newCount->getNewRawWords();
                $chunk->translated_raw_words = $newCount->getTranslatedRawWords();
                $chunk->approved_raw_words   = $newCount->getApprovedRawWords();
                $chunk->approved2_raw_words  = $newCount->getApproved2RawWords();
                $chunk->rejected_raw_words   = $newCount->getRejectedRawWords();

                // updateTodoValues for the JOB
            }

            foreach ( $this->segmentTransitionPhasesModel as $model ) {
                $this->updateFinalRevisionFlag( $model );
                $this->deleteIssues( $model );
            }

            // Commit the transaction to execute successive queries outside the transaction, enabling them to be routed to the slave databases.
            $this->commitTransaction();

            // run chunkReviewUpdated
            foreach ( $this->segmentTransitionPhasesModel as $model ) {
                foreach ( $model->getChunkReviews() as $chunkReview ) {
                    $project          = $chunkReview->getChunk()->getProject();
                    $chunkReviewModel = new ChunkReviewModel( $chunkReview );
                    $project->getFeaturesSet()->run( 'chunkReviewUpdated', $chunkReview, true, $chunkReviewModel, $project );
                }
            }

        } catch ( PDOException $e ) {
            $this->rollback();
            Log::doJsonLog( 'BatchReviewProcessor transaction failed: ' . $e->getMessage() );

            return false;
        }

        return true;

    }

    /**
     * Update chunk review is_pass and counters
     *
     * @throws Exception
     */
    private function updatePassFailAndCounts() {

        $data = [];

        // $data will contain an array of DIFF values used to update qa_chunk_review table
        foreach ( $this->segmentTransitionPhasesModel as $model ) {
            foreach ( $model->getChunkReviews() as $chunkReview ) {
                $data[ $chunkReview->id ][ 'chunkReview_partials' ] = $chunkReview;
                $data[ $chunkReview->id ][ 'penalty_points' ]       = isset( $data[ $chunkReview->id ][ 'penalty_points' ] ) ? $data[ $chunkReview->id ][ 'penalty_points' ] + $chunkReview->penalty_points : $chunkReview->penalty_points;
                $data[ $chunkReview->id ][ 'reviewed_words_count' ] = isset( $data[ $chunkReview->id ][ 'reviewed_words_count' ] ) ? $data[ $chunkReview->id ][ 'reviewed_words_count' ] + $chunkReview->reviewed_words_count : $chunkReview->reviewed_words_count;
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
     * @param ChunkReviewTranslationEventTransition $model
     *
     * @throws \Exception
     */
    private function updateFinalRevisionFlag( ChunkReviewTranslationEventTransition $model ) {
        $eventStruct = $model->getTranslationEvent()->getCurrentEvent();
        $is_revision = (int)$eventStruct->source_page > Constants::SOURCE_PAGE_TRANSLATE;

        if ( $is_revision ) {
            $unsetFinalRevision = array_merge( $model->getUnsetFinalRevision(), [ $eventStruct->source_page ] );
        }

        if ( !empty( $unsetFinalRevision ) ) {
            ( new TranslationEventDao() )->unsetFinalRevisionFlag(
                    $model->getTranslationEvent()->getChunk()->id, [ $model->getTranslationEvent()->getSegmentStruct()->id ], $unsetFinalRevision
            );
        }

        $eventStruct->final_revision = $is_revision;
        TranslationEventDao::updateStruct( $eventStruct, [ 'fields' => [ 'final_revision' ] ] );
    }

    /**
     * Delete all issues
     *
     * @param ChunkReviewTranslationEventTransition $model
     */
    private function deleteIssues( ChunkReviewTranslationEventTransition $model ) {
        foreach ( $model->getIssuesToDelete() as $issue ) {
            $issue->addComments( ( new EntryCommentStruct() )->getEntriesById( $issue->id ) );
            EntryDao::deleteEntry( $issue );
        }
    }

    public function rollback() {
        $this->rollbackTransaction();
    }

}