<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 17/10/2018
 * Time: 18:53
 */


namespace Plugins\Features\ReviewExtended;

use Exception;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\FeaturesBase\Hook\Event\Run\ChunkReviewUpdatedEvent;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use Model\LQA\ModelDao;
use Model\LQA\ModelStruct;
use Model\Projects\ProjectStruct;
use Model\Users\UserStruct;

class ChunkReviewModel implements IChunkReviewModel
{

    /**
     * @var ChunkReviewStruct
     */
    protected ChunkReviewStruct $chunk_review;

    /**
     * @var JobStruct
     */
    protected JobStruct $chunk;

    /**
     * @return JobStruct
     */
    public function getChunk(): JobStruct
    {
        return $this->chunk;
    }

    protected IDatabase $database;

    /**
     * @param ChunkReviewStruct $chunk_review
     * @param IDatabase $database
     */
    public function __construct(ChunkReviewStruct $chunk_review, IDatabase $database)
    {
        $this->chunk_review = $chunk_review;
        $this->chunk = $this->chunk_review->getChunk(new JobDao($database));
        $this->database = $database;
    }

    /**
     * adds penalty_points and updates pass fail result
     *
     * @param float $penalty_points
     *
     * @param ProjectStruct $projectStruct
     * @param UserStruct $actingUser
     *
     * @throws Exception
     */
    public function addPenaltyPoints(float $penalty_points, ProjectStruct $projectStruct, UserStruct $actingUser): void
    {
        $this->updateChunkReviewCountersAndPassFail($penalty_points, 0, 0, $projectStruct, $actingUser);
    }

    /**
     * subtract penalty_points and updates pass fail result
     *
     * @param float $penalty_points
     *
     * @param ProjectStruct $projectStruct
     * @param UserStruct $actingUser
     *
     * @throws Exception
     */
    public function subtractPenaltyPoints(float $penalty_points, ProjectStruct $projectStruct, UserStruct $actingUser): void
    {
        $this->updateChunkReviewCountersAndPassFail(-$penalty_points, 0, 0, $projectStruct, $actingUser);
    }

    /**
     * Update chunk review
     *
     * Warning, integer parameters are expected signed (+/-) for increment or decrement
     *
     * @throws Exception
     */
    public function updateChunkReviewCountersAndPassFail(float $penalty_points, int $reviewed_word_count, int $tte, ProjectStruct $projectStruct, UserStruct $actingUser): void
    {
        $data = [
            'chunkReview' => $this->chunk_review,
            'penalty_points' => $penalty_points,
            'reviewed_words_count' => $reviewed_word_count,
            'total_tte' => $tte,
        ];

        $this->_updatePassFailResult($projectStruct, $data, $actingUser);
    }

    /**
     * Returns the calculated score
     */
    public function getScore(): float
    {
        if ($this->chunk_review->reviewed_words_count == 0) {
            return 0;
        }

        return $this->chunk_review->penalty_points / $this->chunk_review->reviewed_words_count * 1000;
    }

    /**
     * @return float|null
     */
    public function getPenaltyPoints(): ?float
    {
        return $this->chunk_review->penalty_points;
    }

    public function getReviewedWordsCount(): int
    {
        return $this->chunk_review->reviewed_words_count;
    }

    /**
     * Used only by ChunkReviewModel::[subtractPenaltyPoints, addPenaltyPoints]
     *
     * @param ProjectStruct $project
     * @param array{chunkReview: ChunkReviewStruct, penalty_points?: float, reviewed_words_count: int, total_tte: int} $data
     * @param UserStruct $actingUser
     *
     * @throws Exception
     */
    protected function _updatePassFailResult(ProjectStruct $project, array $data, UserStruct $actingUser): void
    {
        $chunkReviewDao = new ChunkReviewDao($this->database);

        // The delta itself is applied by a single self-referential statement under the row lock, so
        // it cannot lose an update to another delta. The lock is still taken here to serialize
        // against the *absolute* writers (recountAndUpdatePassFailResult, resetScore,
        // alterChunkReviewStruct), which read then write and would otherwise overwrite this delta.
        $chunkReviewDao->lockByJobId((int)$this->chunk_review->id_job);

        $chunkReviewDao->passFailCountsAtomicUpdate((int)$this->chunk_review->id, $data);

        // Deferred past the commit for two reasons. Correctness: busting while the transaction is
        // still open lets a concurrent reader repopulate the cache from the pre-commit row, and that
        // stale value then outlives the commit for the whole TTL — the same "displayed score is
        // wrong" symptom this PR exists to fix, moved from the database into Redis. Lock hold time:
        // these are three Redis round trips, and until now they ran with the job-wide row locks held
        // on the highest-volume write path in the product.
        $chunkReview = $this->chunk_review;
        $this->database->onCommit(static fn() => $chunkReviewDao->destroyCachesFor($chunkReview));

        FeatureSet::forProject($project, $this->database)->dispatch(new ChunkReviewUpdatedEvent(
            $this->chunk_review,
            1,
            $this,
            $project,
            $actingUser
        ));
    }

    /**
     * Returns the proper limit for the current review stage.
     *
     * @param ModelStruct $lqa_model
     *
     * @return int
     * @throws Exception
     */
    public function getQALimit(ModelStruct $lqa_model): int
    {
        return ReviewUtils::filterLQAModelLimit($lqa_model, $this->chunk_review->source_page);
    }

    /**
     *
     * Used to recount total in qa_chunk reviews in case of: [ split/merge/chunk record created/disaster recovery ]
     *
     * Used in AbstractRevisionFeature::postJobMerged and AbstractRevisionFeature::postJobSplitted
     *
     * @param ProjectStruct $project
     *
     * @throws Exception
     */
    public function recountAndUpdatePassFailResult(ProjectStruct $project, UserStruct $actingUser): void
    {
        $chunkReviewDao = new ChunkReviewDao($this->database);

        // This method is a read-modify-write that ends in an *absolute* value, so it must not
        // interleave with a concurrent delta: taking the row locks before the aggregate reads below
        // means a delta either lands before the sums are taken (and is included in them) or waits
        // until this transaction commits. Locking here rather than at each call site covers every
        // caller of the recount — BatchReviewProcessor, split/merge, and the repair CLI alike.
        $chunkReviewDao->lockByJobId((int)$this->chunk_review->id_job);

        $this->applyRecount(
            $chunkReviewDao,
            $chunkReviewDao->getReviewedWordsCountForSecondPass($this->chunk, $this->chunk_review->source_page),
            $project,
            $actingUser
        );
    }

    /**
     * Same recount, deriving reviewed_words_count from the final revision records rather than from the
     * segments' current status.
     *
     * The status derivation used above only answers the question for the top phase of a job, so on a job
     * with both R1 and R2 it recounts R1 towards zero as R2 approves. This entry point exists for the
     * repair tasks, which have to be correct for any job shape. The split/merge callers deliberately keep
     * the older derivation for now, so that re-partitioning a job does not silently change its numbers as
     * a side effect of this fix; that path needs its own change.
     *
     * @throws Exception
     */
    public function recountAndUpdatePassFailResultFromFinalRevisions(ProjectStruct $project, UserStruct $actingUser): void
    {
        $chunkReviewDao = new ChunkReviewDao($this->database);

        // Same ordering requirement as the recount above: lock before the aggregate reads.
        $chunkReviewDao->lockByJobId((int)$this->chunk_review->id_job);

        $this->applyRecount(
            $chunkReviewDao,
            $chunkReviewDao->getReviewedWordsCountFromFinalRevisions($this->chunk, (int)$this->chunk_review->source_page),
            $project,
            $actingUser
        );
    }

    /**
     * The body shared by both recount entry points. Callers must already hold the job's chunk review row
     * locks and must have computed $reviewedWordsCount while holding them.
     *
     * @throws Exception
     */
    private function applyRecount(ChunkReviewDao $chunkReviewDao, int $reviewedWordsCount, ProjectStruct $project, UserStruct $actingUser): void
    {
        /**
         * Count penalty points based on this source_page
         */
        $this->chunk_review->penalty_points = $chunkReviewDao->getPenaltyPointsForChunk($this->chunk, $this->chunk_review->source_page);
        $this->chunk_review->reviewed_words_count = $reviewedWordsCount;
        $this->chunk_review->total_tte = $chunkReviewDao->countTimeToEdit($this->chunk, $this->chunk_review->source_page);

        // No LQA model means no pass/fail verdict, and NULL is how that third state is already
        // represented: is_pass is a nullable tinyint, and QualitySummary reads NULL as "no score"
        // rather than as a failure. This used to write true, asserting a verdict that was never
        // computed and making a model-less chunk indistinguishable from a genuinely passing one --
        // while passFailCountsAtomicUpdate(), the high-volume delta writer, left the same case NULL.
        // Assigned rather than skipped, so rows carrying a stale 1 converge on the next recount.
        $lqaModel = $project->id_qa_model !== null ? (new ModelDao($this->database))->findById($project->id_qa_model) : null;
        $this->chunk_review->is_pass = $lqaModel !== null ? ($this->getScore() <= $this->getQALimit($lqaModel)) : null;

        $chunkReviewDao = new ChunkReviewDao($this->database);
        $update_result = $chunkReviewDao->updateStruct($this->chunk_review, [
                'fields' => [
                    'reviewed_words_count',
                    'is_pass',
                    'penalty_points',
                    'total_tte'
                ]
            ]
        );
        $chunkReview = $this->chunk_review;
        $this->database->onCommit(static fn() => $chunkReviewDao->destroyCachesFor($chunkReview));

        // Dispatched inside the transaction on purpose, unlike the cache bust above: a plugin
        // listener may write rows that have to be atomic with this counter update, so deferring the
        // dispatch past the commit would quietly break that guarantee.
        // External call by Plugins
        FeatureSet::forProject($project, $this->database)->dispatch(new ChunkReviewUpdatedEvent(
            $this->chunk_review,
            $update_result,
            $this,
            $project,
            $actingUser
        ));
    }


}
