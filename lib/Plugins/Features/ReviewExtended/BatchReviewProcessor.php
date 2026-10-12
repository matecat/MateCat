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
use Model\Users\UserStruct;
use Model\WordCount\CounterModel;
use Model\WordCount\WordCountStruct;
use PDOException;
use Plugins\Features\ReviewExtended\Email\BatchReviewProcessorAlertEmail;
use RuntimeException;
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

    /**
     * @param UserStruct $actingUser Whoever triggered this batch. Required: every construction site
     *                               is either an authenticated controller or a worker that resolved
     *                               the user who enqueued the job, and the chunk-review updates this
     *                               class performs are attributed to them.
     */
    public function __construct(
        private readonly ChunkReviewDao $chunkReviewDao,
        private readonly UserStruct $actingUser,
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
        $this->jobWordCounter = $jobWordCounter ?? new CounterModel($this->chunkReviewDao->getDatabaseHandler(), $old_wStruct);

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
            (new ChunkReviewModel($chunkReview, $this->chunkReviewDao->getDatabaseHandler()))->recountAndUpdatePassFailResult($project, $this->actingUser);
            $chunkReviews[] = $chunkReview;

            LoggerFactory::doJsonLog('Batch review processor created a new chunkReview (id ' . $chunkReview->id . ') for chunk with id ' . $this->chunk->id);

            // After the commit. send() enqueues to ActiveMQ rather than talking SMTP, so this is not
            // a slow call — but enqueuing inside the transaction means a rollback still delivers the
            // alert, and the worker can dequeue before the row it describes is visible. It also keeps
            // one more network round trip out of the job-wide row locks held above.
            $alertEmail = new BatchReviewProcessorAlertEmail($this->chunk, $chunkReview);
            $this->chunkReviewDao->getDatabaseHandler()->onCommit(static fn() => $alertEmail->send());
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

        // qa_chunk_reviews before qa_entries, once, for the whole batch.
        //
        // deleteIssues() below writes qa_entries, and TranslationIssueModel locks qa_chunk_reviews
        // before touching qa_entries — so acquiring in the opposite order here is an ABBA deadlock
        // against any concurrent issue create/delete on the same job. That became reachable when the
        // job lock stopped being a Redis advisory lock (which never joined InnoDB's wait-for graph)
        // and became row locks held to commit. translate() rolls back and rethrows, so the loser is
        // a failed segment save for an ordinary user.
        //
        // Hoisting it also makes the find-then-create in getOrCreateChunkReviews() atomic — the
        // id_job gap lock keeps a second request out of the window between findChunkReviews() and
        // createRecord() — and saves re-locking the same rows once per chunk review per event.
        $this->chunkReviewDao->lockByJobId(
            $this->chunk->id ?? throw new RuntimeException('Missing chunk id')
        );

        $chunkReviews = $this->getOrCreateChunkReviews($project);

        foreach ($this->prepared_events as $translationEvent) {
            $segmentTranslationModel = ($this->reviewedWordCountModelFactory)($translationEvent, $this->jobWordCounter, $chunkReviews);

            $segmentTranslationModel->evaluateChunkReviewEventTransitions();
            $segmentTranslationModel->deleteIssues();
            // Deferred for the same reason as the alert above: no notification for a save that ends
            // up rolled back. Safe to move past the commit — _sendNotificationEmail() reads the
            // in-memory event/segment/chunk plus users and routes, never the chunk-review counters —
            // and the user/project lookups now see committed state.
            $this->chunkReviewDao->getDatabaseHandler()->onCommit(static fn() => $segmentTranslationModel->sendNotificationEmail());

            foreach ($segmentTranslationModel->getEvent()->getChunkReviewsPartials() as $chunkReview) {
                $project = $chunkReview->getChunk(new JobDao($this->chunkReviewDao->getDatabaseHandler()))->getProject(new ProjectDao($this->chunkReviewDao->getDatabaseHandler()));
                $chunkReviewModel = ($this->chunkReviewModelFactory)($chunkReview);
                $chunkReviewModel->updateChunkReviewCountersAndPassFail($chunkReview->penalty_points ?? 0.0, $chunkReview->reviewed_words_count, $chunkReview->total_tte, $project, $this->actingUser);
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