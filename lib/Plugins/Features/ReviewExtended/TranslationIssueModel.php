<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 23/10/2018
 * Time: 11:36
 */

namespace Plugins\Features\ReviewExtended;

use Exception;
use Model\Exceptions\ValidationError;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use Model\LQA\EntryDao;
use Model\LQA\EntryStruct;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Plugins\Features\TranslationVersions\Model\TranslationVersionDao;
use Plugins\Features\TranslationVersions\Model\TranslationVersionStruct;
use TypeError;
use Utils\Tools\Utils;

class TranslationIssueModel
{

    /**
     * @var ProjectStruct
     */
    protected ProjectStruct $project;

    /** @var array<mixed>|null */
    private ?array $diff = null;

    /**
     * @var EntryStruct
     */
    protected EntryStruct $issue;

    /**
     * @var ChunkReviewStruct
     */
    protected ChunkReviewStruct $chunk_review;

    /**
     * @var JobStruct
     */
    protected JobStruct $chunk;

    private ChunkReviewDao $chunkReviewDao;
    private EntryDao $entryDao;
    private TranslationVersionDao $translationVersionDao;

    /**
     * @param int                   $id_job
     * @param string                $password
     * @param EntryStruct           $issue
     * @param ChunkReviewDao        $chunkReviewDao
     * @param EntryDao              $entryDao
     * @param TranslationVersionDao $translationVersionDao
     * @param ProjectDao            $projectDao
     *
     * @throws Exception
     * @throws TypeError
     */
    public function __construct(
        int $id_job,
        string $password,
        EntryStruct $issue,
        ChunkReviewDao $chunkReviewDao,
        EntryDao $entryDao,
        TranslationVersionDao $translationVersionDao,
        ProjectDao $projectDao
    ) {
        $this->issue = $issue;
        $this->chunkReviewDao = $chunkReviewDao;
        $this->entryDao = $entryDao;
        $this->translationVersionDao = $translationVersionDao;

        $review = $this->chunkReviewDao->findByReviewPasswordAndJobId($password, $id_job);

        if ($review === null) {
            throw new Exception('ChunkReview not found for job ' . $id_job);
        }
        $this->chunk_review = $review;
        $this->chunk = $this->chunk_review->getChunk();
        $this->project = $this->chunk->getProject($projectDao);
    }

    /**
     * This method optionally saves the diff between versions if this is being received from the post params.
     * This change was introduced for the new revision, in which issues have to come with a diff object because
     * selection is referred to the difference between segments.
     *
     * @param array<mixed>|null $diff
     */
    public function setDiff(?array $diff = null): void
    {
        $this->diff = $diff;
    }

    /**
     * @param EntryStruct $oldStruct
     *
     * @return EntryStruct
     * @throws Exception
     * @throws TypeError
     */
    public function editFrom(EntryStruct $oldStruct): EntryStruct
    {
        $this->setDefaultIssueValues();

        if (!empty($this->diff)) {
            $this->saveDiff();
        }

        $this->issue->ensureStartAndStopPositionAreOrdered();
        $this->issue->setDefaults();
        $this->entryDao->modifyEntry($this->issue);

        // update score
        $penaltyPointDiff = $this->issue->penalty_points - $oldStruct->penalty_points;

        $chunk_review_model = $this->createChunkReviewModel($this->chunk_review);

        if($penaltyPointDiff < 0){
            $chunk_review_model->subtractPenaltyPoints(-$penaltyPointDiff, $this->project);
        } elseif($penaltyPointDiff > 0){
            $chunk_review_model->addPenaltyPoints($penaltyPointDiff, $this->project);
        }

        return $this->issue;
    }


    /**
     * Inserts the struct in database and updates review result
     *
     * @return EntryStruct
     * @throws ValidationError
     * @throws Exception
     * @throws TypeError
     */
    public function save(): EntryStruct
    {
        $this->setDefaultIssueValues();

        if (!empty($this->diff)) {
            $this->saveDiff();
        }

        $this->issue->ensureStartAndStopPositionAreOrdered();
        $this->issue->setDefaults();
        $this->entryDao->createEntry($this->issue);

        $chunk_review_model = $this->createChunkReviewModel($this->chunk_review);
        $chunk_review_model->addPenaltyPoints($this->issue->penalty_points ?? 0.0, $this->project);

        return $this->issue;
    }

    /**
     *
     */
    private function setDefaultIssueValues(): void
    {
        if (is_null($this->issue->start_node)) {
            $this->issue->start_node = 0;
        }

        if (is_null($this->issue->end_node)) {
            $this->issue->end_node = 0;
        }
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    private function saveDiff(): void
    {
        $string_to_save = json_encode($this->diff) ?: null;

        /**
         * in order to save diff we need to lookup for current version in segment_translations.
         */
        $struct = new TranslationVersionStruct();
        $struct->id_job = $this->issue->id_job;
        $struct->id_segment = $this->issue->id_segment;
        $struct->creation_date = Utils::mysqlTimestamp(time());
        $struct->is_review = true;
        $struct->version_number = $this->issue->translation_version;
        $struct->raw_diff = $string_to_save;

        $version_record = $this->translationVersionDao->getVersionNumberForTranslation(
            $struct->id_job,
            $struct->id_segment,
            $struct->version_number
        );

        if (!$version_record) {
            $this->translationVersionDao->insertStruct($struct);
        } else {
            // in case the record exists, we have to update it with the diff anyway
            $version_record->raw_diff = $string_to_save;
            $this->translationVersionDao->updateStruct($version_record, ['fields' => ['raw_diff']]);
        }
    }

    /**
     * @throws Exception
     */
    public function delete(): void
    {
        $this->entryDao->deleteEntry($this->issue);

        //
        // ---------------------------------------------------
        // Note 2020-06-24
        // ---------------------------------------------------
        //
        // $this->chunkReview may not refer to the chunk review associated to issue source page
        //
        $chunkJobId = $this->chunk->id ?? throw new Exception('Missing chunk job id');
        $chunkPassword = $this->chunk->password ?? throw new Exception('Missing chunk password');
        $chunkReview = $this->chunkReviewDao->findByIdJobAndPasswordAndSourcePage($chunkJobId, $chunkPassword, $this->issue->source_page);

        if ($chunkReview === null) {
            throw new Exception('ChunkReview not found for delete operation');
        }
        $chunk_review_model = $this->createChunkReviewModel($chunkReview);
        $this->subtractPenaltyPoints($chunk_review_model);
    }

    protected function createChunkReviewModel(ChunkReviewStruct $chunkReview): ChunkReviewModel
    {
        return new ChunkReviewModel($chunkReview, $this->chunkReviewDao->getDatabaseHandler());
    }

    /**
     * Check if penalty points are >= 0
     * to avoid to persist negative values
     *
     * @param ChunkReviewModel $chunk_review_model
     *
     * @throws Exception
     */
    protected function subtractPenaltyPoints(ChunkReviewModel $chunk_review_model): void
    {
        $penaltyPoints = $this->issue->penalty_points ?? 0.0;
        if (($chunk_review_model->getPenaltyPoints() - $penaltyPoints) >= 0) {
            $chunk_review_model->subtractPenaltyPoints($penaltyPoints, $this->project);
        }
    }
}
