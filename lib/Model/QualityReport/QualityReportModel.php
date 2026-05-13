<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 2/11/16
 * Time: 8:51 PM
 */

namespace Model\QualityReport;

use ArrayObject;
use DateMalformedStringException;
use DateTime;
use DomainException;
use Exception;
use Model\ChunksCompletion\ChunkCompletionEventDao;
use Model\DataAccess\Database;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use Model\Projects\ProjectStruct;
use Model\ReviseFeedback\FeedbackDAO;
use Model\Users\UserDao;
use PDOException;
use Plugins\Features\ReviewExtended\IChunkReviewModel;
use Plugins\Features\ReviewExtended\ReviewUtils;
use Plugins\Features\RevisionFactory;
use ReflectionException;
use TypeError;


class QualityReportModel
{

    /**
     * @var JobStruct
     */
    protected JobStruct $chunk;

    /** @var array<string, mixed> */
    protected array $quality_report_structure = [];

    /** @var ArrayObject<string, mixed> */
    private ArrayObject $current_file;

    /** @var ArrayObject<string, mixed> */
    private ArrayObject $current_segment;

    /** @var ArrayObject<string, mixed> */
    private ArrayObject $current_issue;

    private ?ChunkReviewStruct $chunk_review = null;

    /**
     * @var ?IChunkReviewModel
     */
    private ?IChunkReviewModel $chunk_review_model = null;

    private ?string $date_format = null;

    private float $avg_time_to_edit = 0.0;
    private float $avg_edit_distance = 0.0;

    private QualityReportDao $qualityReportDao;
    private ChunkReviewDao $chunkReviewDao;
    private FeedbackDAO $feedbackDao;

    public function __construct(
        JobStruct $chunk,
        ?QualityReportDao $qualityReportDao = null,
        ?ChunkReviewDao $chunkReviewDao = null,
        ?FeedbackDAO $feedbackDao = null,
    ) {
        $this->chunk = $chunk;
        $this->qualityReportDao = $qualityReportDao ?? new QualityReportDao();
        $this->chunkReviewDao = $chunkReviewDao ?? new ChunkReviewDao();
        $this->feedbackDao = $feedbackDao ?? new FeedbackDAO();
    }

    public function getChunk(): JobStruct
    {
        return $this->chunk;
    }

    public function getProject(): ProjectStruct
    {
        return $this->chunk->getProject();
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    public function getStructure(): array
    {
        $records = $this->getSegmentsForQualityReport();

        return $this->buildQualityReportStructure($records);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function getChunkReview(): ChunkReviewStruct
    {
        if ($this->chunk_review == null) {
            $this->chunk_review = $this->chunkReviewDao->findChunkReviews($this->chunk)[0];
        }

        return $this->chunk_review;
    }

    /**
     * @throws Exception
     */
    public function getScore(): string
    {
        return number_format($this->getChunkReviewModel()->getScore(), 2);
    }

    /**
     * @throws Exception
     */
    public function getChunkReviewModel(): IChunkReviewModel
    {
        if ($this->chunk_review_model == null) {
            $this->chunk_review_model = $this->createRevisionFactory()->getChunkReviewModel($this->getChunkReview());
        }

        return $this->chunk_review_model;
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    public function resetScore(int $event_id): void
    {
        $chunkReview = $this->getChunkReview();
        $chunkReview->undo_data = json_encode([
            'reset_by_event_id' => $event_id,
            'penalty_points' => $chunkReview->penalty_points,
            'reviewed_words_count' => $chunkReview->reviewed_words_count,
            'is_pass' => $chunkReview->is_pass
        ]) ?: null;

        $chunkReview->penalty_points = 0;
        $chunkReview->reviewed_words_count = 0;
        $chunkReview->is_pass = true;

        $this->updateChunkReview($chunkReview, [
            'fields' => [
                'undo_data',
                'penalty_points',
                'reviewed_words_count',
                'is_pass'
            ]
        ]);
    }

    /**
     * @param string $format
     */
    public function setDateFormat(string $format): void
    {
        $this->date_format = $format;
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws PDOException
     */
    protected function getSegmentsForQualityReport(): array
    {
        return QualityReportDao::getSegmentsForQualityReport($this->chunk);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    protected function createRevisionFactory(): RevisionFactory
    {
        return RevisionFactory::initFromProject($this->getProject());
    }

    /**
     * @param ChunkReviewStruct $chunkReview
     * @param array<string, mixed> $options
     * @throws Exception
     */
    protected function updateChunkReview(ChunkReviewStruct $chunkReview, array $options): void
    {
        ChunkReviewDao::staticUpdateStruct($chunkReview, $options);
    }

    /**
     * @throws PDOException
     */
    private function __setAverages(): void
    {
        $avgs = $this->qualityReportDao->getAverages($this->getChunk());

        if ($avgs === false) {
            $this->avg_edit_distance = 0.0;
            $this->avg_time_to_edit = 0.0;
            return;
        }

        $this->avg_edit_distance = round($avgs['avg_edit_distance'] / 1000, 2);
        $this->avg_time_to_edit = round($avgs['avg_time_to_edit'] / 1000, 2);
    }

    /**
     * @param array<int, array<string, mixed>> $records
     *
     * @return array<string, mixed>
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws PDOException
     */
    protected function buildQualityReportStructure(array $records): array
    {
        $this->__setAverages();
        $this->quality_report_structure = [
            'chunk' => [
                'files' => [],
                'avg_time_to_edit' => $this->avg_time_to_edit,
                'avg_edit_distance' => $this->avg_edit_distance
            ],
            'job' => [
                'source' => $this->chunk->source,
                'target' => $this->chunk->target,
            ],
            'project' => [
                'metadata' => $this->getAndDecodePossiblyProjectMetadataJson(),
                'id' => $this->getProject()->id,
                'created_at' => $this->filterDate(
                    $this->getProject()->create_date
                )
            ]
        ];

        $this->buildFilesSegmentsNestedTree($records);
        $this->_attachReviewsData();

        return $this->quality_report_structure;
    }

    /** @return array<string, mixed>
     * @throws DomainException
     */
    protected function getAndDecodePossiblyProjectMetadataJson(): array
    {
        return $this->getProject()->getAllMetadataAsKeyValue();
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function _attachReviewsData(): void
    {
        $chunk_reviews = $this->chunkReviewDao->findChunkReviews($this->chunk);

        $this->quality_report_structure['chunk']['reviews'] = [];
        foreach ($chunk_reviews as $chunk_review) {
            // try to load Revision Extended but should not load the Improved ( deprecated )
            $chunkReviewModel = $this->createRevisionFactory()->getChunkReviewModel($chunk_review);

            $revisionNumber = ReviewUtils::sourcePageToRevisionNumber($chunk_review->source_page);
            $feedback = $this->feedbackDao->getFeedback($this->chunk->id, $chunk_review->review_password, $revisionNumber);

            $this->quality_report_structure['chunk']['reviews'][] = [
                'revision_number' => $revisionNumber,
                'feedback' => ($feedback and isset($feedback['feedback'])) ? $feedback['feedback'] : null,
                'is_pass' => ($chunk_review->is_pass !== null ? !!$chunk_review->is_pass : null),
                'score' => $chunkReviewModel->getScore(),
                'reviewer_name' => $this->getReviewerName()
            ];
        }
    }

    /**
     * @return string
     * @throws Exception
     */
    protected function getReviewerName(): string
    {
        $completion_event = ChunkCompletionEventDao::lastCompletionRecord(
            $this->chunk,
            ['is_review' => true]
        );
        $name = '';

        if (!empty($completion_event) && isset($completion_event['uid'])) {
            $userDao = new UserDao(Database::obtain());
            $user = $userDao->getByUid($completion_event['uid']);
            $name = $user?->fullName() ?? '';
        }

        return $name;
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @throws DateMalformedStringException
     */
    private function buildFilesSegmentsNestedTree(array $records): void
    {
        $current_file_id = null;
        $current_segment_id = null;
        $current_issue_id = null;

        foreach ($records as $record) {
            if ($current_file_id != $record['file_id']) {
                $this->structureNestFile($record);
            }

            if ($current_segment_id != $record['segment_id']) {
                $this->structureNestSegment($record);
            }

            if ($current_issue_id != $record['issue_id'] && $record['issue_id'] !== null) {
                $this->structureNestIssue($record);
            }

            if ($record['comment_id'] != null) {
                $this->structureNestComment($record);
            }

            $current_file_id = $record['file_id'];
            $current_segment_id = $record['segment_id'];
            $current_issue_id = $record['issue_id'];
        }
    }

    /** @param array<string, mixed> $record */
    private function structureNestSegment(array $record): void
    {
        if ($record['original_translation'] == null) {
            $original_translation = $record['translation'];
        } else {
            $original_translation = $record['original_translation'];
        }

        $this->current_segment = new ArrayObject([
            'original_translation' => $original_translation,
            'translation' => $record['translation'],
            'id' => $record['segment_id'],
            'source' => $record['segment_source'],
            'status' => $record['translation_status'],
            'edit_distance' => round($record['edit_distance'] / 1000, 2),
            'time_to_edit' => round($record['time_to_edit'] / 1000, 2),
            'issues' => [],
            'qa_checks' => []
        ]);

        $this->current_file['segments'][] = $this->current_segment;
    }

    /**
     * @param array<string, mixed> $record
     * @throws DateMalformedStringException
     */
    private function structureNestIssue(array $record): void
    {
        $this->current_issue = new ArrayObject([
            'id' => $record['issue_id'],
            'created_at' => $this->filterDate($record['issue_create_date']),
            'category' => $record['issue_category'],
            'category_options' => $record['category_options'],
            'severity' => $record['issue_severity'],

            'start_offset' => $record['issue_start_offset'],
            'end_offset' => $record['issue_end_offset'],

            'target_text' => $record['target_text'],
            'comment' => $record['issue_comment'],
            'replies_count' => $record['issue_replies_count'],
            'comments' => []
        ]);

        $this->current_segment['issues'][] = $this->current_issue;
    }

    /**
     * @param array<string, mixed> $record
     * @throws DateMalformedStringException
     */
    private function structureNestComment(array $record): void
    {
        $comment = new ArrayObject([
            'comment' => $record['comment_comment'],
            'created_at' => $this->filterDate($record['comment_create_date']),
            'uid' => $record['comment_uid']
        ]);

        $this->current_issue['comments'][] = $comment;
    }

    /** @param array<string, mixed> $record */
    private function structureNestFile(array $record): void
    {
        $this->current_file = new ArrayObject([
            'id' => $record['file_id'],
            'filename' => $record['file_filename'],
            'segments' => []
        ]);

        $this->quality_report_structure['chunk']['files'][] = $this->current_file;
    }

    /**
     * @throws DateMalformedStringException
     */
    private function filterDate(?string $date): ?string
    {
        if ($date === null) {
            return null;
        }

        if ($this->date_format !== null) {
            $datetime = new DateTime($date);
            return $datetime->format($this->date_format);
        }

        return $date;
    }

}
