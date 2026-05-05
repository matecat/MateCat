<?php

namespace Model\ProjectCreation;

use Controller\API\Commons\Exceptions\AuthenticationError;
use Exception;
use Model\Concerns\LogsMessages;
use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Segments\SegmentDao;
use Model\Segments\SegmentMetadataDao;
use Model\Segments\SegmentMetadataStruct;
use Model\Segments\SegmentOriginalDataDao;
use Model\Segments\SegmentOriginalDataStruct;
use Utils\Constants\XliffTranslationStatus;
use Utils\Logger\MatecatLogger;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;

/**
 * Encapsulates segment storage logic that was previously embedded in
 * {@see ProjectManager}.
 *
 * This class is responsible for:
 *  - Reserving sequence IDs for segments and tracking min/max boundaries
 *  - Processing and persisting segment original data maps
 *  - Saving segment metadata records
 *  - Bulk-inserting segment rows via SegmentDao
 *  - Linking segment IDs to notes, contexts, and translations
 *  - Building the segments_metadata analysis array
 *  - Cleaning segments_metadata (removing non-cattool segments)
 *
 * All mutations to projectStructure are performed on the ProjectStructure passed
 * to the public methods, which is the same mutable structure used by ProjectManager.
 */
class SegmentStorageService
{
    use LogsMessages;


    /**
     * Tracks first/last segment IDs across all files.
     * Written by storeSegments(), read by ProjectManager::_createJobs().
     *
     * @var array{job_first_segment?: int, job_last_segment?: int}
     */
    private array $minMaxSegmentsId = [];

    public function __construct(
        private readonly IDatabase $dbHandler,
        private readonly FeatureSet $features,
        MatecatLogger $logger,
        private readonly ProjectManagerModel $projectManagerModel,
    ) {
        $this->logger = $logger;
    }

    // ── Public API ──────────────────────────────────────────────────

    /**
     * Get the accumulated min/max segment ID boundaries.
     *
     * @return array{job_first_segment?: int, job_last_segment?: int}
     */
    public function getMinMaxSegmentsId(): array
    {
        return $this->minMaxSegmentsId;
    }

    /**
     * Store segments in a single file: reserve IDs, persist original data
     * and metadata, bulk-insert segment rows, and link IDs to notes/contexts/translations.
     *
     * @param string|int $fid
     * @param ProjectStructure $projectStructure
     *
     * @throws Exception
     */
    public function storeSegments(string|int $fid, ProjectStructure $projectStructure): void
    {
        if (count($projectStructure->segments[$fid]) == 0) {
            return;
        }

        $this->log("Segments: Total Rows to insert: " . count($projectStructure->segments[$fid]));
        $sequenceIds = $this->dbHandler->nextSequence(Database::SEQ_ID_SEGMENT, count($projectStructure->segments[$fid]));
        $this->log("Id sequence reserved.");

        // Update/Initialize the min-max sequences id
        if (!isset($this->minMaxSegmentsId['job_first_segment'])) {
            $this->minMaxSegmentsId['job_first_segment'] = (int)reset($sequenceIds);
        }

        // Update the last id; if there is another cycle, this value gets overwritten
        $this->minMaxSegmentsId['job_last_segment'] = (int)end($sequenceIds);

        $segments_metadata = [];
        foreach ($sequenceIds as $position => $id_segment) {
            $segments_metadata[] = $this->prepareAndPersistSegment($position, $id_segment, $fid, $projectStructure);
        }

        $segmentsDao = $this->createSegmentDao();
        // split the query in to chunks if there are too many segments
        $segmentsDao->createList(array_values($projectStructure->segments[$fid]));

        // free memory
        $projectStructure->segments[$fid] = [];

        // Link segment IDs to notes, contexts, and translations
        // so downstream code can insert related records.
        $this->linkSegmentIdsToRelatedData($segments_metadata, $projectStructure);

        // merge segments_metadata for every file in the project
        $projectStructure->segments_metadata = array_merge(
            $projectStructure->segments_metadata,
            $segments_metadata
        );
    }

    /**
     * Assign the reserved ID to the segment, persist original data and metadata,
     * increment the file segment counter, and build the analysis metadata array.
     *
     * @param int $position Index within the file's segment list
     * @param int|string $id_segment Reserved sequence ID
     * @param int|string $fid File ID
     * @param ProjectStructure $projectStructure
     *
     * @return array<string, mixed> The segment analysis metadata row
     * @throws AuthenticationError
     * @throws NotFoundException
     * @throws ValidationError
     * @throws EndQueueException
     * @throws ReQueueException
     */
    private function prepareAndPersistSegment(
        int $position,
        int|string $id_segment,
        int|string $fid,
        ProjectStructure $projectStructure,
    ): array {
        $projectStructure->segments[$fid][$position]->id = $id_segment;

        /** @var SegmentOriginalDataStruct $segmentOriginalDataStruct */
        $segmentOriginalDataStruct = $projectStructure->segments_original_data[$fid][$position] ?? new SegmentOriginalDataStruct(
        ); // If not set, create an empty struct to be safe. Avoid 'Call to a member function getMap() on null'

        $originalDataMap = $segmentOriginalDataStruct->getMap();
        if (!empty($originalDataMap)) {
            // We add two filters here (sanitizeOriginalDataMap and correctTagErrors)
            // to allow the correct tag handling by the plugins
            $map = $this->features->filter('sanitizeOriginalDataMap', $originalDataMap);

            // persist an original data map if present
            $this->insertOriginalDataRecord($id_segment, $map);

            $projectStructure->segments[$fid][$position]->segment = $this->features->filter(
                'correctTagErrors',
                $projectStructure->segments[$fid][$position]->segment,
                $map
            );
        }

        /** @var ?SegmentMetadataStruct $segmentMetadataStruct */
        $segmentMetadataStruct = $projectStructure->segments_meta_data[$fid][$position] ?? null;

        if ($segmentMetadataStruct !== null) {
            $this->saveSegmentMetadata($id_segment, $segmentMetadataStruct);
        }

        if (!isset($projectStructure->file_segments_count[$fid])) {
            $projectStructure->file_segments_count[$fid] = 0;
        }
        $projectStructure->file_segments_count[$fid]++;

        $metadata = [
            'id' => $id_segment,
            'internal_id' => SegmentExtractor::sanitizedUnitId($projectStructure->segments[$fid][$position]->internal_id, (int)$fid),
            'segment' => $projectStructure->segments[$fid][$position]->segment,
            'segment_hash' => $projectStructure->segments[$fid][$position]->segment_hash,
            'raw_word_count' => $projectStructure->segments[$fid][$position]->raw_word_count,
            'xliff_mrk_id' => $projectStructure->segments[$fid][$position]->xliff_mrk_id,
            'show_in_cattool' => $projectStructure->segments[$fid][$position]->show_in_cattool,
            'additional_params' => null,
            'file_id' => $fid,
        ];

        /*
         * This hook allows plugins to manipulate data analysis content, should be not allowed to change existing data
         * but only to eventually add new fields
         */
        return $this->features->filter('appendFieldToAnalysisObject', $metadata, $projectStructure);
    }

    /**
     * Remove segments with show_in_cattool == false from segments_metadata.
     * Called before inserting pre-translations.
     *
     * @param ProjectStructure $projectStructure
     */
    public function cleanSegmentsMetadata(ProjectStructure $projectStructure): void
    {
        $projectStructure->segments_metadata = array_values(
            array_filter(
                $projectStructure->segments_metadata,
                function ($value) {
                    return $value['show_in_cattool'] == 1;
                }
            )
        );
    }

    /**
     * Insert pre-translated segments as translations.
     *
     * This method is a dumb bulk insert — it reads pre-computed QA scalars
     * from each {@see TranslationTuple} and builds SQL values for insertion.
     * QA consistency checks are performed upstream by {@see QAProcessor::process()}.
     *
     * @param JobStruct $job The job these translations belong to
     * @param ProjectStructure $projectStructure The mutable project structure
     *
     * @throws Exception
     */
    public function insertPreTranslations(JobStruct $job, ProjectStructure $projectStructure): void
    {
        $jid = $job->id;
        $this->cleanSegmentsMetadata($projectStructure);

        $rawRates = $projectStructure->array_jobs['payable_rates'][$jid] ?? null;
        $payable_rates = is_string($rawRates) ? json_decode($rawRates, true) : $rawRates;

        $createSecondPassReview = false;

        $query_translations_values = [];
        foreach ($projectStructure->translations as $struct) {
            if (empty($struct)) {
                continue;
            }

            foreach ($struct as $translationTuple) {
                $rule = $translationTuple->rule;

                if (XliffTranslationStatus::isFinalState($translationTuple->state)) {
                    $createSecondPassReview = true;
                }

                /* WARNING: do not change the order of the keys */
                $sql_values = [
                    'id_segment'             => $translationTuple->segmentId,
                    'id_job'                 => $jid,
                    'segment_hash'           => $translationTuple->segmentHash,
                    'status'                 => $rule->asEditorStatus(),
                    'translation'            => $translationTuple->translationLayer0,
                    'suggestion'             => $translationTuple->suggestionLayer0,
                    'locked'                 => 0,
                    'match_type'             => $rule->asMatchType(),
                    'eq_word_count'          => $rule->asEquivalentWordCount($translationTuple->rawWordCount, $payable_rates),
                    'serialized_errors_list' => $translationTuple->serializedErrors,
                    'warning'                => $translationTuple->warning,
                    'suggestion_match'       => null,
                    'standard_word_count'    => $rule->asStandardWordCount($translationTuple->rawWordCount, $payable_rates),
                    'version_number'         => 0,
                ];

                $query_translations_values[] = $sql_values;
            }
        }

        // Executing the Query
        if (!empty($query_translations_values)) {
            $this->projectManagerModel->insertPreTranslations($query_translations_values);
        }

        if ($createSecondPassReview) {
            $projectStructure->create_2_pass_review = true;
        }
    }

    // ── Factory methods (overridable in tests) ──────────────────────

    /**
     * Create a new SegmentDao instance.
     */
    protected function createSegmentDao(): SegmentDao
    {
        return new SegmentDao();
    }

    /**
     * Persist an original data map for a segment.
     * Wraps the static DAO call so tests can override.
     *
     * @param int $id_segment
     * @param array<string, mixed> $map
     */
    protected function insertOriginalDataRecord(int $id_segment, array $map): void
    {
        SegmentOriginalDataDao::insertRecord($id_segment, $map);
    }

    /**
     * Persist a single segment metadata record.
     * Protected so test subclasses can override to capture calls.
     */
    protected function persistSegmentMetadata(SegmentMetadataStruct $metadataStruct): void
    {
        SegmentMetadataDao::save($metadataStruct);
    }

    // ── Private helpers ─────────────────────────────────────────────

    /**
     * Link segment IDs to notes, contexts, and translation entries.
     *
     * After bulk-inserting segment rows we know their database IDs.
     * This method walks the metadata array and stamps those IDs into
     * the corresponding notes, context-group, and translation structures
     * so downstream code can insert the related records.
     *
     * @param array<int, array<string, mixed>> $segmentsMetadata
     * @param ProjectStructure $projectStructure
     */
    private function linkSegmentIdsToRelatedData(array $segmentsMetadata, ProjectStructure $projectStructure): void
    {
        if (
            empty($projectStructure->notes) &&
            empty($projectStructure->translations) &&
            empty($projectStructure->context_group)
        ) {
            return;
        }

        // internal counter for the segmented translations ( mrk in target )
        $array_internal_segmentation_counter = [];

        foreach ($segmentsMetadata as $row) {
            $this->setSegmentIdForNotes($row, $projectStructure);
            $this->setSegmentIdForContexts($row, $projectStructure);

            // Link translation entries
            if (!isset($projectStructure->translations[$row['internal_id']])) {
                continue;
            }

            if (!array_key_exists($row['internal_id'], $array_internal_segmentation_counter)) {
                // if we don't have segmentation, we have no mrk ID,
                // so work with positional indexes ( should be only one row )
                if (empty($row['xliff_mrk_id'])) {
                    $array_internal_segmentation_counter[$row['internal_id']] = 0;
                } else {
                    // we have the mark id use them
                    $array_internal_segmentation_counter[$row['internal_id']] = $row['xliff_mrk_id'];
                }
            } elseif (empty($row['xliff_mrk_id'])) {
                // if we don't have segmentation, we have no mrk ID,
                // so work with positional indexes
                // (should be only one row but if we are here, let's increment it)
                $array_internal_segmentation_counter[$row['internal_id']]++;
            } else {
                // we have the mark id use them
                $array_internal_segmentation_counter[$row['internal_id']] = $row['xliff_mrk_id'];
            }

            // set this var only for easy reading
            $short_var_counter = $array_internal_segmentation_counter[$row['internal_id']];

            if (!isset($projectStructure->translations[$row['internal_id']][$short_var_counter])) {
                continue;
            }

            $tuple = $projectStructure->translations[$row['internal_id']][$short_var_counter];
            $tuple->segmentId = (int)$row['id'];
            $tuple->segmentHash = $row['segment_hash'];
            $tuple->fileId = (int)$row['file_id'];
        }
    }

    /**
     * Validate and persist segment metadata if the struct has valid key/value.
     */
    protected function saveSegmentMetadata(int $id_segment, ?SegmentMetadataStruct $metadataStruct = null): void
    {
        if ($metadataStruct !== null &&
            isset($metadataStruct->meta_key) && $metadataStruct->meta_key !== '' &&
            isset($metadataStruct->meta_value) && $metadataStruct->meta_value !== ''
        ) {
            $metadataStruct->id_segment = $id_segment;
            $this->persistSegmentMetadata($metadataStruct);
        }
    }

    /**
     * Link segment ID to notes entries for later insertion.
     *
     * @param array<string, mixed> $row
     * @param ProjectStructure $projectStructure
     */
    private function setSegmentIdForNotes(array $row, ProjectStructure $projectStructure): void
    {
        $internal_id = $row['internal_id'];

        if (isset($projectStructure->notes[$internal_id])) {
            if (count($projectStructure->notes[$internal_id]['json']) != 0) {
                $projectStructure->notes[$internal_id]['json_segment_ids'][] = $row['id'];
            } else {
                $projectStructure->notes[$internal_id]['segment_ids'][] = $row['id'];
            }
        }
    }

    /**
     * Link segment ID to context-group entries for later insertion.
     *
     * @param array<string, mixed> $row
     * @param ProjectStructure $projectStructure
     */
    private function setSegmentIdForContexts(array $row, ProjectStructure $projectStructure): void
    {
        $internal_id = $row['internal_id'];

        if (isset($projectStructure->context_group[$internal_id])) {
            $projectStructure->context_group[$internal_id]['context_json_segment_ids'][] = $row['id'];
        }
    }
}
