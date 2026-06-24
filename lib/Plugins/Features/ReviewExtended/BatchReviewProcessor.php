<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/06/2019
 * Time: 17:44
 */

namespace Plugins\Features\ReviewExtended;

use Closure;
use Exception;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\WordCount\CounterModel;
use Model\WordCount\WordCountStruct;
use PDOException;
use Plugins\Features\ReviewExtended\Email\BatchReviewProcessorAlertEmail;
use Plugins\Features\TranslationEvents\Model\TranslationEvent;
use ReflectionException;
use TypeError;
use Utils\Logger\LoggerFactory;

class BatchReviewProcessor
{

    /**
     * @var CounterModel
     */
    private CounterModel $jobWordCounter;
    /**
     * @var JobStruct
     */
    private JobStruct $chunk;

    /**
     * @var TranslationEvent[]
     */
    private array $prepared_events;

    /** @var Closure(TranslationEvent, CounterModel, ChunkReviewStruct[]): ReviewedWordCountModel */
    private Closure $reviewedWordCountModelFactory;

    /** @var Closure(ChunkReviewStruct): ChunkReviewModel */
    private Closure $chunkReviewModelFactory;

    public function __construct(
        private readonly ChunkReviewDao $chunkReviewDao,
        ?Closure $reviewedWordCountModelFactory = null,
        ?Closure $chunkReviewModelFactory = null,
    ) {
        $this->reviewedWordCountModelFactory = $reviewedWordCountModelFactory
            ?? fn(TranslationEvent $event, CounterModel $counter, array $reviews) => new ReviewedWordCountModel($event, $counter, $reviews, $this->chunkReviewDao->getDatabaseHandler());
        $this->chunkReviewModelFactory = $chunkReviewModelFactory
            ?? fn(ChunkReviewStruct $cr) => new ChunkReviewModel($cr, $this->chunkReviewDao->getDatabaseHandler());
    }

    /**
     * @param JobStruct $chunk
     * @param CounterModel|null $jobWordCounter
     *
     * @return $this
     * @throws TypeError
     */
    public function setChunk(JobStruct $chunk, ?CounterModel $jobWordCounter = null): BatchReviewProcessor
    {
        $this->chunk = $chunk;
        $old_wStruct = WordCountStruct::loadFromJob($chunk);
        $this->jobWordCounter = $jobWordCounter ?? new CounterModel($old_wStruct);

        return $this;
    }

    /**
     * @param TranslationEvent[] $prepared_events
     *
     * @return $this
     */
    public function setPreparedEvents(array $prepared_events): BatchReviewProcessor
    {
        $this->prepared_events = $prepared_events;

        return $this;
    }

    /**
     * @return ChunkReviewStruct[]
     * @throws ReflectionException
     * @throws Exception
     * @throws PDOException
     * @throws TypeError
     */
    private function getOrCreateChunkReviews(ProjectStruct $project): array
    {
        $chunkReviews = $this->chunkReviewDao->findChunkReviews($this->chunk);

        //
        // ----------------------------------------------
        // Note 2020-06-24
        // ----------------------------------------------
        // If $chunkReviews is empty:
        //
        // 1) create a chunkReview
        // 2) send an alert email
        //
        if (empty($chunkReviews)) {
            $data = [
                'id_project' => $project->id,
                'id_job' => $this->chunk->id,
                'password' => $this->chunk->password,
                'source_page' => 2,
            ];

            $chunkReview = $this->chunkReviewDao->createRecord($data);
            (new ChunkReviewModel($chunkReview, $this->chunkReviewDao->getDatabaseHandler()))->recountAndUpdatePassFailResult($project);
            $chunkReviews[] = $chunkReview;

            LoggerFactory::doJsonLog('Batch review processor created a new chunkReview (id ' . $chunkReview->id . ') for chunk with id ' . $this->chunk->id);

            $alertEmail = new BatchReviewProcessorAlertEmail($this->chunk, $chunkReview);
            $alertEmail->send();
        }

        return $chunkReviews;
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    public function process(): void
    {
        $project = $this->chunk->getProject(new ProjectDao($this->chunkReviewDao->getDatabaseHandler()));
        $chunkReviews = $this->getOrCreateChunkReviews($project);

        foreach ($this->prepared_events as $translationEvent) {
            $segmentTranslationModel = ($this->reviewedWordCountModelFactory)($translationEvent, $this->jobWordCounter, $chunkReviews);

            $segmentTranslationModel->evaluateChunkReviewEventTransitions();
            $segmentTranslationModel->deleteIssues();
            $segmentTranslationModel->sendNotificationEmail();

            foreach ($segmentTranslationModel->getEvent()->getChunkReviewsPartials() as $chunkReview) {
                $project = $chunkReview->getChunk(new JobDao($this->chunkReviewDao->getDatabaseHandler()))->getProject(new ProjectDao($this->chunkReviewDao->getDatabaseHandler()));
                $chunkReviewModel = ($this->chunkReviewModelFactory)($chunkReview);
                $chunkReviewModel->updateChunkReviewCountersAndPassFail($chunkReview->penalty_points ?? 0.0, $chunkReview->reviewed_words_count, $chunkReview->total_tte, $project);
            }
        }

        $this->updateJobWordCounter();
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    private function updateJobWordCounter(): void
    {
        // if empty, no segment status changes are present
        if (!empty($this->jobWordCounter->getValues())) {
            $newCount = $this->jobWordCounter->updateDB($this->jobWordCounter->getValues());
            $this->chunk->draft_words = $newCount->getDraftWords();
            $this->chunk->new_words = $newCount->getNewWords();
            $this->chunk->translated_words = $newCount->getTranslatedWords();
            $this->chunk->approved_words = $newCount->getApprovedWords();
            $this->chunk->approved2_words = $newCount->getApproved2Words();
            $this->chunk->rejected_words = $newCount->getRejectedWords();

            $this->chunk->draft_raw_words = (int)$newCount->getDraftRawWords();
            $this->chunk->new_raw_words = (int)$newCount->getNewRawWords();
            $this->chunk->translated_raw_words = (int)$newCount->getTranslatedRawWords();
            $this->chunk->approved_raw_words = (int)$newCount->getApprovedRawWords();
            $this->chunk->approved2_raw_words = (int)$newCount->getApproved2RawWords();
            $this->chunk->rejected_raw_words = (int)$newCount->getRejectedRawWords();
            // updateTodoValues for the JOB
        }
    }

}