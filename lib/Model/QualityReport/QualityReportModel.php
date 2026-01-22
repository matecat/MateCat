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
use Exception;
use Model\ChunksCompletion\ChunkCompletionEventDao;
use Model\DataAccess\Database;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use Model\Projects\ProjectStruct;
use Model\ReviseFeedback\FeedbackDAO;
use Model\Users\UserDao;
use Plugins\Features\ReviewExtended\IChunkReviewModel;
use Plugins\Features\ReviewExtended\ReviewUtils;
use Plugins\Features\RevisionFactory;
use ReflectionException;
use Utils\Tools\Utils;


class QualityReportModel
{

    /**
     * @var JobStruct
     */
    protected JobStruct $chunk;

    protected array $quality_report_structure = [];

    private ArrayObject $current_file;

    private ArrayObject $current_segment;

    private ArrayObject $current_issue;

    private ?ChunkReviewStruct $chunk_review = null;

    /**
     * @var ?IChunkReviewModel
     */
    private ?IChunkReviewModel $chunk_review_model = null;

    private array $all_segments = [];

    private ?string $date_format = null;

    private float $avg_time_to_edit = 0.0;
    private float $avg_edit_distance = 0.0;

    /**
     * @param JobStruct $chunk
     */
    public function __construct(JobStruct $chunk)
    {
        $this->chunk = $chunk;
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
     * @throws Exception
     */
    public function getStructure(): array
    {
        $records = QualityReportDao::getSegmentsForQualityReport($this->chunk);

        return $this->buildQualityReportStructure($records);
    }

    /**
     * @throws ReflectionException
     */
    public function getChunkReview(): ChunkReviewStruct
    {
        if ($this->chunk_review == null) {
            $this->chunk_review = (new ChunkReviewDao())->findChunkReviews($this->chunk)[0];
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
            $this->chunk_review_model = RevisionFactory::initFromProject($this->getProject())->getChunkReviewModel($this->getChunkReview());
        }

        return $this->chunk_review_model;
    }

    /**
     * @throws Exception
     */
    public function resetScore(int $event_id): void
    {
        $chunkReview = $this->getChunkReview();
        $chunkReview->undo_data = json_encode([
            'reset_by_event_id' => $event_id,
            'penalty_points' => $chunkReview->penalty_points,
            'reviewed_words_count' => $chunkReview->reviewed_words_count,
            'is_pass' => $chunkReview->is_pass
        ]);

        $chunkReview->penalty_points = 0;
        $chunkReview->reviewed_words_count = 0;
        $chunkReview->is_pass = 1;

        ChunkReviewDao::updateStruct($chunkReview, [
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

    private function __setAverages(): void
    {
        $dao = new QualityReportDao();
        $avgs = $dao->getAverages($this->getChunk());

        $this->avg_edit_distance = round($avgs['avg_edit_distance'] / 1000, 2);
        $this->avg_time_to_edit = round($avgs['avg_time_to_edit'] / 1000, 2);
    }

    /**
     * @param array $records
     *
     * @return array
     * @throws DateMalformedStringException
     * @throws Exception
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
        $chunk_reviews = (new ChunkReviewDao())->findChunkReviews($this->chunk);

        $this->quality_report_structure['chunk']['reviews'] = [];
        foreach ($chunk_reviews as $chunk_review) {
            // try to load Revision Extended but should not load the Improved ( deprecated )
            $chunkReviewModel = RevisionFactory::initFromProject($this->getProject())->getChunkReviewModel($chunk_review);

            $revisionNumber = ReviewUtils::sourcePageToRevisionNumber($chunk_review->source_page);
            $feedback = (new FeedbackDAO())->getFeedback($this->chunk->id, $chunk_review->review_password, $revisionNumber);

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
            $name = $user->fullName();
        }

        return $name;
    }

    /**
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
            // TODO: the following `round` is wrong, this should be done later in a presentation layer...
            'edit_distance' => round($record['edit_distance'] / 1000, 2),
            'time_to_edit' => round($record['time_to_edit'] / 1000, 2),
            'issues' => [],
            'qa_checks' => []
        ]);

        $this->all_segments[] = $this->current_segment;

        $this->current_file['segments'][] = $this->current_segment;
    }

    /**
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
    private function filterDate($date)
    {
        $out = $date;
        if ($this->date_format != null) {
            $datetime = new DateTime($date);
            $out = $datetime->format($this->date_format);
        }

        return $out;
    }

}