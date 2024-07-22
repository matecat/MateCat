<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/06/2019
 * Time: 17:44
 */

namespace Features\ReviewExtended;

use Chunks_ChunkStruct;
use Exception;
use Features\ReviewExtended\Email\BatchReviewProcessorAlertEmail;
use Features\ReviewExtended\Model\ChunkReviewDao;
use Features\TranslationEvents\Model\TranslationEvent;
use Log;
use Projects_ProjectStruct;
use ReflectionException;
use RevisionFactory;
use WordCount\CounterModel;
use WordCount\WordCountStruct;

class BatchReviewProcessor {

    /**
     * @var CounterModel
     */
    private CounterModel $jobWordCounter;
    /**
     * @var mixed
     */
    private Chunks_ChunkStruct $chunk;

    /**
     * @var TranslationEvent[]
     */
    private array $prepared_events;

    public function __construct() {
    }

    /**
     * @param Chunks_ChunkStruct $chunk
     *
     * @return $this
     */
    public function setChunk( Chunks_ChunkStruct $chunk ): BatchReviewProcessor {
        $this->chunk          = $chunk;
        $old_wStruct          = WordCountStruct::loadFromJob( $chunk );
        $this->jobWordCounter = new CounterModel( $old_wStruct );

        return $this;
    }

    /**
     * @param array $prepared_events
     *
     * @return $this
     */
    public function setPreparedEvents( array $prepared_events ): BatchReviewProcessor {
        $this->prepared_events = $prepared_events;

        return $this;
    }

    /**
     * @throws ReflectionException
     */
    private function getOrCreateChunkReviews( Projects_ProjectStruct $project ): array {

        $chunkReviews = ( new ChunkReviewDao() )->findChunkReviews( $this->chunk );

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
                    'id_job'      => $this->chunk->id,
                    'password'    => $this->chunk->password,
                    'source_page' => 2,
            ];

            $chunkReview = ChunkReviewDao::createRecord( $data );
            $project->getFeaturesSet()->run( 'chunkReviewRecordCreated', $chunkReview, $project );
            $chunkReviews[] = $chunkReview;

            Log::doJsonLog( 'Batch review processor created a new chunkReview (id ' . $chunkReview->id . ') for chunk with id ' . $this->chunk->id );

            $alertEmail = new BatchReviewProcessorAlertEmail( $this->chunk, $chunkReview );
            $alertEmail->send();

        }

        return $chunkReviews;

    }

    /**
     * @throws Exception
     */
    public function process(): void {

        $project      = $this->chunk->getProject();
        $chunkReviews = $this->getOrCreateChunkReviews( $project );

        $revisionFactory = RevisionFactory::initFromProject( $project );

        $data = [];

        foreach ( $this->prepared_events as $translationEvent ) {

            $segmentTranslationModel = $revisionFactory->getReviewedWordCountModel( $translationEvent, $chunkReviews, $this->jobWordCounter );

            // here we process and count the reviewed word count and
            $segmentTranslationModel->evaluateChunkReviewEventTransitions();
            $segmentTranslationModel->deleteIssues();
            $segmentTranslationModel->sendNotificationEmail();

            foreach ( $segmentTranslationModel->getEvent()->getChunkReviews() as $chunkReview ) {

                // prepare data to update pass fail values
                $data[ $chunkReview->id ][ 'chunkReview_partials' ] = $chunkReview;
                $data[ $chunkReview->id ][ 'penalty_points' ]       = ( $data[ $chunkReview->id ][ 'penalty_points' ] ?? 0 ) + $chunkReview->penalty_points;
                $data[ $chunkReview->id ][ 'reviewed_words_count' ] = ( $data[ $chunkReview->id ][ 'reviewed_words_count' ] ?? 0 ) + $chunkReview->reviewed_words_count;
                $data[ $chunkReview->id ][ 'total_tte' ]            = ( $data[ $chunkReview->id ][ 'total_tte' ] ?? 0 ) + $chunkReview->total_tte;

                // send chunkReviewUpdated notifications through FeaturesSet hook
                $project          = $chunkReview->getChunk()->getProject();
                $chunkReviewModel = new ChunkReviewModel( $chunkReview );
                $project->getFeaturesSet()->run( 'chunkReviewUpdated', $chunkReview, true, $chunkReviewModel, $project );

            }

        }

        $this->updatePassFailAndCounts( $data );
        $this->updateJobWordCounter();

    }

    /**
     * @throws Exception
     */
    private function updateJobWordCounter(): void {

        // if empty, no segment status changes are present
        if ( !empty( $this->jobWordCounter->getValues() ) ) {

            $newCount                      = $this->jobWordCounter->updateDB( $this->jobWordCounter->getValues() );
            $this->chunk->draft_words      = $newCount->getDraftWords();
            $this->chunk->new_words        = $newCount->getNewWords();
            $this->chunk->translated_words = $newCount->getTranslatedWords();
            $this->chunk->approved_words   = $newCount->getApprovedWords();
            $this->chunk->approved2_words  = $newCount->getApproved2Words();
            $this->chunk->rejected_words   = $newCount->getRejectedWords();

            $this->chunk->draft_raw_words      = $newCount->getDraftRawWords();
            $this->chunk->new_raw_words        = $newCount->getNewRawWords();
            $this->chunk->translated_raw_words = $newCount->getTranslatedRawWords();
            $this->chunk->approved_raw_words   = $newCount->getApprovedRawWords();
            $this->chunk->approved2_raw_words  = $newCount->getApproved2RawWords();
            $this->chunk->rejected_raw_words   = $newCount->getRejectedRawWords();

            // updateTodoValues for the JOB
        }

    }

    /**
     * Update chunk review is_pass and counters
     *
     * @throws Exception
     */
    private function updatePassFailAndCounts( array $data ) {

        // just ONE UPDATE for each ChunkReview
        $chunkReviewDao = new ChunkReviewDao();
        foreach ( $data as $id => $datum ) {
            $chunkReviewDao->passFailCountsAtomicUpdate( $id, $datum );
        }


    }


}