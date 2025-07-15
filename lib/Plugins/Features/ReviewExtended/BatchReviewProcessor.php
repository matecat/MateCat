<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/06/2019
 * Time: 17:44
 */

namespace Plugins\Features\ReviewExtended;

use Exception;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\Projects\ProjectStruct;
use Model\WordCount\CounterModel;
use Model\WordCount\WordCountStruct;
use Plugins\Features\ReviewExtended\Email\BatchReviewProcessorAlertEmail;
use Plugins\Features\RevisionFactory;
use Plugins\Features\TranslationEvents\Model\TranslationEvent;
use ReflectionException;
use Utils\Logger\Log;

class BatchReviewProcessor {

    /**
     * @var CounterModel
     */
    private CounterModel $jobWordCounter;
    /**
     * @var mixed
     */
    private JobStruct $chunk;

    /**
     * @var TranslationEvent[]
     */
    private array $prepared_events;

    public function __construct() {
    }

    /**
     * @param JobStruct $chunk
     *
     * @return $this
     */
    public function setChunk( JobStruct $chunk ): BatchReviewProcessor {
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
    private function getOrCreateChunkReviews( ProjectStruct $project ): array {

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
            ( new ChunkReviewModel( $chunkReview ) )->recountAndUpdatePassFailResult( $project );
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

            $segmentTranslationModel = new ReviewedWordCountModel( $translationEvent, $this->jobWordCounter, $chunkReviews );

            // here we process and count the reviewed word count and
            $segmentTranslationModel->evaluateChunkReviewEventTransitions();
            $segmentTranslationModel->deleteIssues();
            $segmentTranslationModel->sendNotificationEmail();

            foreach ( $segmentTranslationModel->getEvent()->getChunkReviewsPartials() as $chunkReview ) {

                // send chunkReviewUpdated notifications through FeaturesSet hook
                $project          = $chunkReview->getChunk()->getProject();
                $chunkReviewModel = new ChunkReviewModel( $chunkReview );
                $chunkReviewModel->updateChunkReviewCountersAndPassFail( $chunkReview->penalty_points, $chunkReview->reviewed_words_count, $chunkReview->total_tte, $project );

            }

        }

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

}