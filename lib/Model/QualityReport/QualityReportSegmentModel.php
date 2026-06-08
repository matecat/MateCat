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
use Matecat\SubFiltering\MateCatFilter;
use Model\Comments\BaseCommentStruct;
use Model\Comments\CommentDao;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao;
use Model\LQA\CategoryDao;
use Model\LQA\CategoryStruct;
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
use Utils\Tools\CatUtils;

class QualityReportSegmentModel
{

    protected JobStruct $chunk;

    /** @var ChunkReviewStruct[]|null */
    protected ?array $_chunkReviews = null;

    private ChunkReviewDao $chunkReviewDao;
    private SegmentDao $segmentDao;
    private QualityReportDao $qualityReportDao;
    private EntryCommentDao $entryCommentDao;
    private CommentDao $commentDao;

    public function __construct(
        JobStruct $chunk,
        ?ChunkReviewDao $chunkReviewDao = null,
        ?SegmentDao $segmentDao = null,
        ?QualityReportDao $qualityReportDao = null,
        ?EntryCommentDao $entryCommentDao = null,
        ?CommentDao $commentDao = null
    ) {
        $this->chunk = $chunk;
        $this->chunkReviewDao = $chunkReviewDao ?? new ChunkReviewDao();
        $this->segmentDao = $segmentDao ?? new SegmentDao();
        $this->qualityReportDao = $qualityReportDao ?? new QualityReportDao();
        $this->entryCommentDao = $entryCommentDao ?? new EntryCommentDao();
        $this->commentDao = $commentDao ?? new CommentDao();
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
        if (isset($options['filter']['issue_category']) && $options['filter']['issue_category'] != 'all') {
            $idQaModel = $this->chunk->getProject()->id_qa_model;
            if ($idQaModel !== null) {
                $subCategories = (new CategoryDao())->findByIdModelAndIdParent(
                    $idQaModel,
                    $options['filter']['issue_category']
                );

                if (!empty($subCategories) > 0) {
                    $options['filter']['issue_category'] = array_map(function (CategoryStruct $subcat) {
                        return $subcat->id;
                    }, $subCategories);
                }
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
     * @throws Exception
     * @throws DivisionByZeroError
     * @throws TypeError
     */
    protected function _commonSegmentAssignments(QualityReportSegmentStruct $seg, MateCatFilter $Filter, FeatureSet $featureSet, JobStruct $chunk, bool $isForUI = false): void
    {
        $seg->warnings = $seg->getLocalWarning($featureSet, $chunk);
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
            $comment->templateMessage();
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

        $featureSet = new FeatureSet();

        $featureSet->loadForProject($this->chunk->getProject());
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

        $translationVersionDao = new TranslationVersionDao;
        $all_events = $translationVersionDao->getAllRelevantEvents($segmentIds, $chunkId);
        $history_events = $translationVersionDao->historyEvents($segment_ids, $chunkId);


        $segments = [];

        foreach ($data as $index => $seg) {
            $dataRefMap = (new SegmentOriginalDataDao())->getSegmentDataRefMap($seg->sid);
            $metadataDao = new MetadataDao();

            /** @var MateCatFilter $Filter */
            $Filter = MateCatFilter::getInstance(
                $featureSet,
                $this->chunk->source,
                $this->chunk->target,
                $dataRefMap,
                $metadataDao->getSubfilteringCustomHandlers($chunkId, $chunkPassword)
            );

            $seg->dataRefMap = $dataRefMap;

            $this->_commonSegmentAssignments($seg, $Filter, $featureSet, $this->chunk, $isForUI);
            $this->_assignIssues($seg, $issues, $issue_comments);
            $this->_assignComments($seg, $comments);
            $this->_populateLastTranslationAndRevision($seg, $Filter, $all_events,  $isForUI);
            $this->_populateHistory($seg, $Filter, $history_events,$issues ?? [], $isForUI);

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
     * @param array $events An array of SegmentEventsStruct objects representing the events related to the segment.
     * @param array $issues An array of issue objects to associate with the events, filtered by segment and version.
     * @param bool $isForUI Indicates whether the translation should be processed for UI display purposes.
     *
     * @return void
     */
    protected function _populateHistory(
        QualityReportSegmentStruct $seg,
        MateCatFilter $Filter,
        array $events = [],
        array $issues = [],
        bool $isForUI = false
    )
    {
        $elements = [];

        $eventsForThisSegment = array_filter($events, function (HistoryElementStruct $event) use ($seg) {
            return $event->id_segment == $seg->sid;
        });

        /** @var HistoryElementStruct $event */
        foreach ($eventsForThisSegment as $event) {
            $translation = ($isForUI) ? $Filter->fromLayer0ToLayer2($event->translation) : $event->translation;

            $elements[] = [
                'status' => $event->status,
                'date' => $event->creation_date ?? $event->create_date,
                'revision_number' => ReviewUtils::sourcePageToRevisionNumber($event->source_page),
                'source_page' => $event->source_page,
                'version_number' => $event->version_number,
                'translation' => $translation,
                'issues' => array_filter($issues, function ($issue) use ($event) {
                    return
                        $issue->deleted_at === null &&
                        $event->id_segment == $issue->segment_id &&
                        $event->version_number == $issue->translation_version
                    ;
                })
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

                $translation = ($isForUI) ? $Filter->fromLayer0ToLayer2($event->translation) : $event->translation;

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
