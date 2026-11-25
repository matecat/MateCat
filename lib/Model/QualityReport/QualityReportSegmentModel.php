<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 07/09/2018
 * Time: 11:21
 */

namespace Model\QualityReport;

use DivisionByZeroError;
use Exception;
use Matecat\ICU\MessagePatternValidator;
use Matecat\SubFiltering\MateCatFilter;
use Model\Comments\BaseCommentStruct;
use Model\Comments\CommentDao;
use Model\DataAccess\IDatabase;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao;
use Model\Projects\MetadataDao as ProjectMetadataDao;
use Model\Projects\ProjectDao;
use Model\LQA\CategoryDao;
use Model\LQA\CategoryStruct;
use PDOException;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use Model\LQA\EntryCommentDao;
use Model\Segments\SegmentDao;
use Model\Segments\SegmentOriginalDataDao;
use Plugins\Features\ReviewExtended\ReviewUtils;
use Plugins\Features\TranslationVersions\Model\TranslationVersionDao;
use ReflectionException;
use RuntimeException;
use TypeError;
use Utils\Constants\SourcePages;
use Utils\Constants\TranslationStatus;
use Utils\LQA\ICUSourceSegmentDetector;
use Utils\Tools\CatUtils;

class QualityReportSegmentModel
{

    protected JobStruct $chunk;

    private IDatabase $database;

    /** @var ChunkReviewStruct[]|null */
    protected ?array $_chunkReviews = null;

    private ChunkReviewDao $chunkReviewDao;
    private SegmentDao $segmentDao;
    private QualityReportDao $qualityReportDao;
    private EntryCommentDao $entryCommentDao;
    private CommentDao $commentDao;
    private CategoryDao $categoryDao;

    public function __construct(
        JobStruct $chunk,
        IDatabase $database,
        ?ChunkReviewDao $chunkReviewDao = null,
        ?SegmentDao $segmentDao = null,
        ?QualityReportDao $qualityReportDao = null,
        ?EntryCommentDao $entryCommentDao = null,
        ?CommentDao $commentDao = null,
        ?CategoryDao $categoryDao = null
    ) {
        $this->chunk = $chunk;
        $this->database = $database;
        $this->chunkReviewDao = $chunkReviewDao ?? new ChunkReviewDao($database);
        $this->segmentDao = $segmentDao ?? new SegmentDao($database);
        $this->qualityReportDao = $qualityReportDao ?? new QualityReportDao($database);
        $this->entryCommentDao = $entryCommentDao ?? new EntryCommentDao($database);
        $this->commentDao = $commentDao ?? new CommentDao($database);
        $this->categoryDao = $categoryDao ?? new CategoryDao($database);
    }

    /**
     * @param int $step
     * @param int $ref_segment
     * @param string $where
     * @param array<string, mixed> $options
     *
     * @return array<int, int>
     * @throws Exception
     */
    public function getSegmentsIdForQR($step, int $ref_segment, $where = "after", $options = [])
    {
        if (self::selectsSpecificCategories($options['filter']['issue_category'] ?? null)) {
            $idQaModel = $this->chunk->getProject(new ProjectDao($this->database))->id_qa_model;
            if ($idQaModel !== null) {
                $options['filter']['issue_category'] = $this->expandCategoriesToSubcategories(
                    (int)$idQaModel,
                    $options['filter']['issue_category']
                );
            }
        }

        /**
         * Validate revision_number param
         */
        if (!empty($options['filter']) && in_array(($options['filter'] ['status'] ?? ''), TranslationStatus::$REVISION_STATUSES)) {
            if (isset($options['filter']['revision_number'])) {
                $validRevisionNumbers = array_map(function ($chunkReview) {
                    return ReviewUtils::sourcePageToRevisionNumber($chunkReview->source_page);
                }, $this->_getChunkReviews());

                if (!in_array((int)$options['filter']['revision_number'], $validRevisionNumbers)) {
                    $options['filter']['revision_number'] = 1;
                }
            }
        }

        $segments_id = $this->segmentDao->getSegmentsIdForQR(
            $this->chunk,
            $step,
            $ref_segment,
            $where,
            $options
        );

        return $segments_id;
    }

    /**
     * Whether the category filter names specific categories, as opposed to none or "Any".
     *
     * @param mixed $issueCategory
     *
     * @return bool
     */
    private static function selectsSpecificCategories(mixed $issueCategory): bool
    {
        $values = (array)$issueCategory;

        return $values !== []
            && $values !== ['']
            && !in_array(SegmentDao::ISSUE_CATEGORY_ALL, $values, true);
    }

    /**
     * Replace each selected category with its subcategories, or keep it when it has none.
     *
     * The filter takes one id or a list of them. Issues are filed against leaf categories, so a
     * parent alone would match nothing. If no valid id is left, the value is returned untouched,
     * so the query still filters and matches nothing instead of losing the filter.
     *
     * @param int $idQaModel
     * @param mixed $issueCategory
     *
     * @return mixed list<int> when at least one id is valid, the input otherwise
     * @throws PDOException
     */
    private function expandCategoriesToSubcategories(int $idQaModel, mixed $issueCategory): mixed
    {
        $ids = self::positiveCategoryIds($issueCategory);
        if (empty($ids)) {
            return $issueCategory;
        }

        $expanded = [];
        foreach ($ids as $id) {
            $subCategoryIds = array_map(
                fn(CategoryStruct $subCategory): int => (int)$subCategory->id,
                $this->categoryDao->findByIdModelAndIdParent($idQaModel, $id)
            );
            array_push($expanded, ...($subCategoryIds ?: [$id]));
        }

        return array_values(array_unique($expanded));
    }

    /**
     * The distinct positive integer ids in a category filter; anything else is dropped.
     *
     * @param mixed $issueCategory one id or a list of them, as ints or digit strings
     *
     * @return list<int>
     */
    private static function positiveCategoryIds(mixed $issueCategory): array
    {
        $ids = [];
        foreach ((array)$issueCategory as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $ids[] = (int)$id;
            }
        }

        return array_values(array_unique(array_filter($ids, fn(int $id): bool => $id > 0)));
    }

    /**
     * @throws Exception
     * @throws DivisionByZeroError
     * @throws TypeError
     */
    protected function _commonSegmentAssignments(
        QualityReportSegmentStruct $seg,
        MateCatFilter $Filter,
        FeatureSet $featureSet,
        JobStruct $chunk,
        bool $isForUI = false,
        bool $sourceContainsIcu = false
    ): void {
        $seg->warnings = $seg->getLocalWarning($featureSet, $chunk, $Filter, $sourceContainsIcu);
        $seg->pee = $seg->getPEE();
        $seg->ice_modified = $seg->isICEModified();
        $seg->secs_per_word = round($seg->getSecsPerWord());
        $seg->parsed_time_to_edit = CatUtils::parse_time_to_edit(min($seg->time_to_edit, PHP_INT_MAX));

        if ($isForUI) {
            $seg->segment = $Filter->fromLayer0ToLayer2($seg->segment);
            $seg->translation = $Filter->fromLayer0ToLayer2($seg->translation ?? '');
            $seg->suggestion = $Filter->fromLayer0ToLayer2($seg->suggestion ?? '');
        }
    }

    /**
     * @param ShapelessConcreteStruct[] $issues
     * @param array<int, array<int, mixed>> $issue_comments
     */
    protected function _assignIssues(QualityReportSegmentStruct $seg, array $issues, array $issue_comments): void
    {
        foreach ($issues as $issue) {
            $issue->revision_number = ReviewUtils::sourcePageToRevisionNumber($issue->source_page);

            if (isset($issue_comments[$issue->issue_id])) {
                $issue->comments = $issue_comments[$issue->issue_id];
            }

            if ($issue->segment_id == $seg->sid) {
                $seg->issues[] = $issue;
            }
        }
    }

    /**
     * @param BaseCommentStruct[] $comments
     * @throws RuntimeException
     * @throws ReflectionException
     * @throws Exception
     */
    protected function _assignComments(QualityReportSegmentStruct $seg, array $comments): void
    {
        foreach ($comments as $comment) {
            $comment->templateMessage($this->commentDao);
            if ($comment->id_segment == $seg->sid) {
                $seg->comments[] = $comment;
            }
        }
    }

    /**
     * If the results are needed from UI return a layer2 presentation,
     * otherwise return just plain text (layer 0)
     *
     * @param list<int|string> $segment_ids
     * @param bool $isForUI
     *
     * @return QualityReportSegmentStruct[]
     * @throws Exception
     * @throws DivisionByZeroError
     * @throws TypeError
     */
    public function getSegmentsForQR(array $segment_ids, $isForUI = false)
    {
        if ($this->chunk->id === null || $this->chunk->password === null) {
            return [];
        }

        $segmentIds = array_values(array_map('intval', $segment_ids));

        $chunkId = $this->chunk->id;
        $chunkPassword = $this->chunk->password;

        $data = $this->segmentDao->getSegmentsForQr($segmentIds, $chunkId, $chunkPassword);

        $featureSet = new FeatureSet($this->database);

        $project = $this->chunk->getProject(new ProjectDao($this->database));
        $featureSet->loadForProject($project);
        $issue_comments = [];

         $issues = $this->qualityReportDao->getIssuesBySegments($segmentIds, $chunkId);
         if (!empty($issues)) {
             $issue_comments = $this->entryCommentDao->fetchCommentsGroupedByIssueIds(
                 array_values(array_map(function ($issue): int {
                     return (int)$issue->issue_id;
                 }, $issues))
             );
         }

        $comments = $this->commentDao->getThreadsBySegments($segmentIds, $chunkId);

        $translationVersionDao = new TranslationVersionDao($this->database);
        $all_events = $translationVersionDao->getAllRelevantEvents($segmentIds, $chunkId);
        $history_events = $translationVersionDao->historyEvents($segment_ids, $chunkId);

        $segments = [];

        $subfilteringHandlers = (new MetadataDao($this->database))->getSubfilteringCustomHandlers($chunkId, $chunkPassword);
        $icuEnabled = (new ProjectMetadataDao($this->database))->isIcuEnabled((int)$project->id);

        foreach ($data as $index => $seg) {
            $dataRefMap = (new SegmentOriginalDataDao($this->database))->getSegmentDataRefMap($seg->sid);

            // The source decides, as in the editor: with ICU enabled and an ICU source, only the
            // ICU-compliant handlers may run, or single_curly_brackets locks the ICU arguments.
            $sourceContainsIcu = $icuEnabled && ICUSourceSegmentDetector::sourceContainsIcu(
                new MessagePatternValidator($this->chunk->source, $seg->segment),
                $icuEnabled
            );

            /** @var MateCatFilter $Filter */
            $Filter = MateCatFilter::getInstance(
                $featureSet,
                $this->chunk->source,
                $this->chunk->target,
                $dataRefMap,
                $subfilteringHandlers,
                $sourceContainsIcu
            );

            $seg->dataRefMap = $dataRefMap;

            $this->_commonSegmentAssignments($seg, $Filter, $featureSet, $this->chunk, $isForUI, $sourceContainsIcu);
            $this->_assignIssues($seg, $issues, $issue_comments);
            $this->_assignComments($seg, $comments);
            $this->_populateLastTranslationAndRevision($seg, $Filter, $all_events,  $isForUI);
            $this->_populateHistory($seg, $Filter, $history_events, $issues, $isForUI);

            $seg->pee_translation_revise = $seg->getPEEBwtTranslationRevise();
            $seg->pee_translation_suggestion = $seg->getPEEBwtTranslationSuggestion();

            $segments[$index] = $seg;
        }

        return $segments;
    }

    /**
     * Populates the history for a given quality report segment by organizing events and associated issues.
     *
     * @param QualityReportSegmentStruct $seg The segment structure where the history will be populated.
     * @param MateCatFilter $Filter The filter used to process translations for UI rendering.
     * @param HistoryElementStruct[] $events An array of SegmentEventsStruct objects representing the events related to the segment.
     * @param ShapelessConcreteStruct[] $issues An array of issue objects to associate with the events, filtered by segment and version.
     * @param bool $isForUI Indicates whether the translation should be processed for UI display purposes.
     *
     * @throws Exception
     *
     * @return void
     */
    protected function _populateHistory(
        QualityReportSegmentStruct $seg,
        MateCatFilter $Filter,
        array $events = [],
        array $issues = [],
        bool $isForUI = false
    ): void
    {
        $elements = [];

        $eventsForThisSegment = array_filter($events, function (HistoryElementStruct $event) use ($seg) {
            return $event->id_segment == $seg->sid;
        });

        // A version keeps its number through every confirmation that does not change the text, so
        // one (version, step) pair can hold several events — a second R2 approve, for one. Its
        // issues belong to that version once, so they go on the last of those events only.
        $lastEventOfVersionAndStep = [];
        foreach ($eventsForThisSegment as $index => $event) {
            $lastEventOfVersionAndStep[$event->version_number . '-' . $event->source_page] = $index;
        }

        /** @var HistoryElementStruct $event */
        foreach ($eventsForThisSegment as $index => $event) {
            $translation = ($isForUI) ? $Filter->fromLayer0ToLayer2($event->translation) : $event->translation;
            $ownsTheIssues = $lastEventOfVersionAndStep[$event->version_number . '-' . $event->source_page] === $index;

            $elements[] = [
                'status' => $event->status,
                // The event's own time. creation_date is set only on the version-0 row, which has no
                // event; a versions row's creation_date is when its text was replaced, not saved.
                'date' => $event->create_date ?? $event->creation_date,
                'revision_number' => ReviewUtils::sourcePageToRevisionNumber($event->source_page),
                'source_page' => $event->source_page,
                'version_number' => $event->version_number,
                'translation' => $translation,
                'issues' => !$ownsTheIssues ? [] : array_values(array_filter($issues, function ($issue) use ($event) {
                    return
                        $issue->deleted_at === null &&
                        $event->id_segment == $issue->segment_id &&
                        $event->version_number == $issue->translation_version &&
                        $event->source_page == $issue->source_page
                    ;
                }))
            ];
        }

        $seg->history = $elements;
    }

    /**
     * @return ChunkReviewStruct[]
     * @throws Exception
     */
    protected function _getChunkReviews(): array
    {
        if (is_null($this->_chunkReviews)) {
            $this->_chunkReviews = $this->chunkReviewDao->findChunkReviews($this->chunk);
        }

        return $this->_chunkReviews;
    }

    /**
     * @param QualityReportSegmentStruct $seg
     * @param MateCatFilter $Filter
     * @param SegmentEventsStruct[] $events
     * @param bool $isForUI
     *
     * @throws Exception
     * @throws TypeError
     */
    protected function _populateLastTranslationAndRevision(
        QualityReportSegmentStruct $seg,
        MateCatFilter $Filter,
        array $events,
        bool $isForUI = false
    ): void {
        // If the segment is pre-translated (maybe from a previously XLIFF file) and NOT modified
        if (
            $seg->getTmAnalysisStatus() == 'SKIPPED' &&
            !$this->isSegmentEventInArray($seg->sid, $events)
        ) {
            $seg->last_revisions = [];
            $seg->is_pre_translated = true;
            switch ($seg->status) {
                case TranslationStatus::STATUS_APPROVED:
                    $seg->last_revisions[] = [
                        'revision_number' => 1,
                        'translation' => ($isForUI) ? $Filter->fromLayer0ToLayer2($seg->translation ?? '') : ($seg->translation ?? '')
                    ];
                    break;
                case TranslationStatus::STATUS_APPROVED2:
                    $seg->last_revisions[] = [
                        'revision_number' => 2,
                        'translation' => ($isForUI) ? $Filter->fromLayer0ToLayer2($seg->translation ?? '') : ($seg->translation ?? '')
                    ];
                    break;
                case TranslationStatus::STATUS_TRANSLATED:
                    $seg->last_translation = ($isForUI) ? $Filter->fromLayer0ToLayer2($seg->translation ?? '') : ($seg->translation ?? '');
                    break;
                default:
                    $seg->is_pre_translated = false; // unreachable condition
                    break;
            }
        } elseif (TranslationStatus::isNotInitialStatus($seg->status)) {
            foreach ($events as $event) {
                if ($seg->sid != $event->id_segment) {
                    continue;
                }

                $translation = ($isForUI) ? $Filter->fromLayer0ToLayer2($event->translation ?? '') : ($event->translation ?? '');

                if ($event->source_page == SourcePages::SOURCE_PAGE_TRANSLATE) {
                    $seg->last_translation = $translation;
                } else {
                    $seg->last_revisions[] = [
                        'revision_number' => ReviewUtils::sourcePageToRevisionNumber($event->source_page),
                        'translation' => $translation
                    ];
                }
            }
        }
    }

    /**
     * @param SegmentEventsStruct[] $haystack_events
     * @param int $needle_segment_id
     *
     * @return bool
     */
    protected function isSegmentEventInArray(int $needle_segment_id, array $haystack_events): bool
    {
        foreach ($haystack_events as $event) {
            if ($event->id_segment == $needle_segment_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param int $needle_segment_id
     * @param int $needle_source_page
     * @param SegmentEventsStruct[] $haystack_events
     *
     * @return mixed|null
     */
    protected function filterEvent(int $needle_segment_id, int $needle_source_page, array $haystack_events)
    {
        foreach ($haystack_events as $event) {
            if ($event->id_segment == $needle_segment_id && $event->source_page == $needle_source_page) {
                return $event;
            }
        }

        return null;
    }

}
