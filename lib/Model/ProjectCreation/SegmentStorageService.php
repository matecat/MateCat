<?php

namespace Model\ProjectCreation;

use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\Concerns\LogsMessages;
use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\ChunkDao;
use Model\Jobs\JobStruct;
use Model\Segments\SegmentDao;
use Model\Segments\SegmentMetadataDao;
use Model\Segments\SegmentMetadataStruct;
use Model\Segments\SegmentOriginalDataDao;
use Model\Segments\SegmentOriginalDataStruct;
use Model\Xliff\DTO\XliffRulesModel;
use ReflectionException;
use Utils\Constants\XliffTranslationStatus;
use Utils\Logger\MatecatLogger;
use Utils\LQA\QA;

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

    private IDatabase $dbHandler;
    private FeatureSet $features;
    private MateCatFilter $filter;
    private ProjectManagerModel $projectManagerModel;

    /**
     * Tracks first/last segment IDs across all files.
     * Written by storeSegments(), read by ProjectManager::_createJobs().
     *
     * @var array{job_first_segment?: int, job_last_segment?: int}
     */
    private array $minMaxSegmentsId = [];

    public function __construct(
        IDatabase            $dbHandler,
        FeatureSet           $features,
        MatecatLogger        $logger,
        MateCatFilter        $filter,
        ProjectManagerModel  $projectManagerModel,
    ) {
        $this->dbHandler            = $dbHandler;
        $this->features             = $features;
        $this->logger               = $logger;
        $this->filter               = $filter;
        $this->projectManagerModel  = $projectManagerModel;
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
     * Store segments for a single file: reserve IDs, persist original data
     * and metadata, bulk-insert segment rows, and link IDs to notes/contexts/translations.
     *
     * @param string|int          $fid
     * @param ProjectStructure    $projectStructure
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
            $this->minMaxSegmentsId['job_first_segment'] = (int) reset($sequenceIds);
        }

        // Update the last id; if there is another cycle this value gets overwritten
        $this->minMaxSegmentsId['job_last_segment'] = (int) end($sequenceIds);

        $segments_metadata = [];
        foreach ($sequenceIds as $position => $id_segment) {
            // $projectStructure->segments[$fid][$position] is a \Model\Segments\SegmentStruct
            $projectStructure->segments[$fid][$position]->id = $id_segment;

            /** @var SegmentOriginalDataStruct $segmentOriginalDataStruct */
            $segmentOriginalDataStruct = $projectStructure->segments_original_data[$fid][$position] ?? new SegmentOriginalDataStruct(
            ); // If not set, create an empty struct to be safe. Avoid 'Call to a member function getMap() on null'

            $originalDataMap = $segmentOriginalDataStruct->getMap();
            if (!empty($originalDataMap)) {
                // We add two filters here (sanitizeOriginalDataMap and correctTagErrors)
                // to allow the correct tag handling by the plugins
                $map = $this->features->filter('sanitizeOriginalDataMap', $originalDataMap);

                // persist original data map if present
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

            $_metadata = [
                'id'                => $id_segment,
                'internal_id'       => SegmentExtractor::sanitizedUnitId($projectStructure->segments[$fid][$position]->internal_id, (int)$fid),
                'segment'           => $projectStructure->segments[$fid][$position]->segment,
                'segment_hash'      => $projectStructure->segments[$fid][$position]->segment_hash,
                'raw_word_count'    => $projectStructure->segments[$fid][$position]->raw_word_count,
                'xliff_mrk_id'      => $projectStructure->segments[$fid][$position]->xliff_mrk_id,
                'show_in_cattool'   => $projectStructure->segments[$fid][$position]->show_in_cattool,
                'additional_params' => null,
                'file_id'           => $fid,
            ];

            /*
             * This hook allows plugins to manipulate data analysis content, should be not allowed to change existing data
             * but only to eventually add new fields
             */
            $_metadata = $this->features->filter('appendFieldToAnalysisObject', $_metadata, $projectStructure);

            $segments_metadata[] = $_metadata;
        }

        $segmentsDao = $this->createSegmentDao();
        // split the query in to chunks if there are too much segments
        $segmentsDao->createList(array_values($projectStructure->segments[$fid]));

        // free memory
        $projectStructure->segments[$fid] = [];

        // Here we make a query for the last inserted segments. This is the point where we
        // can read the id of the segments table to reference it in other inserts in other tables.
        if (!(
            empty($projectStructure->notes) &&
            empty($projectStructure->translations) &&
            empty($projectStructure->context_group)
        )) {
            // internal counter for the segmented translations ( mrk in target )
            $array_internal_segmentation_counter = [];

            foreach ($segments_metadata as $row) {
                // The following call is to save `id_segment` for notes,
                // to be used later to insert the record in notes table.
                $this->setSegmentIdForNotes($row, $projectStructure);
                $this->setSegmentIdForContexts($row, $projectStructure);

                // The following block of code is for translations
                if (isset($projectStructure->translations[$row['internal_id']])) {
                    if (!array_key_exists($row['internal_id'], $array_internal_segmentation_counter)) {
                        // if we don't have segmentation, we have not mrk ID,
                        // so work with positional indexes ( should be only one row )
                        if (empty($row['xliff_mrk_id'])) {
                            $array_internal_segmentation_counter[$row['internal_id']] = 0;
                        } else {
                            // we have the mark id use them
                            $array_internal_segmentation_counter[$row['internal_id']] = $row['xliff_mrk_id'];
                        }
                    } elseif (empty($row['xliff_mrk_id'])) {
                        // if we don't have segmentation, we have not mrk ID,
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
                    $tuple->segmentId   = (int) $row['id'];
                    $tuple->internalId  = $row['internal_id'];
                    $tuple->segmentHash = $row['segment_hash'];
                    $tuple->fileId      = (int) $row['file_id'];

                    // Remove an existent translation, we won't send these segment to the analysis because it is marked as locked
                    /*
                     * Commented because of
                     *
                     * https://app.asana.com/0/1134617950425092/1202822242420298
                     */
                    // unset( $segments_metadata[ $k ] );
                }
            }
        }

        // merge segments_metadata for every file in the project
        $projectStructure->segments_metadata = array_merge(
            $projectStructure->segments_metadata,
            $segments_metadata
        );
    }

    /**
     * Remove segments with show_in_cattool == false from segments_metadata.
     * Called before inserting pre-translations.
     *
     * @param ProjectStructure $projectStructure
     */
    public function cleanSegmentsMetadata(ProjectStructure $projectStructure): void
    {
        $projectStructure->segments_metadata = array_values(array_filter(
            $projectStructure->segments_metadata,
            function ($value) {
                return $value['show_in_cattool'] == 1;
            }
        ));
    }

    /**
     * Process pre-translated segments from XLIFF and insert them as translations.
     *
     * For each translation entry, this method:
     *  - Looks up the segment from the DB
     *  - Resolves XLIFF state/state-qualifier to determine editor status and match type
     *  - Runs QA checks on the translation
     *  - Builds an SQL values array for bulk insert
     *  - Sets the create_2_pass_review flag when a final-state translation is found
     *
     * @param JobStruct            $job              The job these translations belong to
     * @param ProjectStructure     $projectStructure The mutable project structure
     *
     * @throws Exception
     */
    public function insertPreTranslations(JobStruct $job, ProjectStructure $projectStructure): void
    {
        $jid = $job->id;
        $this->cleanSegmentsMetadata($projectStructure);

        // Hoist invariant lookups outside the loop to avoid N+1 queries
        $segmentDao = $this->createSegmentDao();

        $chunks = $this->getChunksByJobId((int)$jid);

        if (empty($chunks)) {
            throw new Exception("No Job found!!! $jid");
        }

        $chunk = $chunks[0];

        $rawRates = $projectStructure->array_jobs['payable_rates'][$jid] ?? null;
        $payable_rates = is_string($rawRates) ? json_decode($rawRates, true) : $rawRates;

        $createSecondPassReview = false;

        $query_translations_values = [];
        foreach ($projectStructure->translations as $struct) {
            if (empty($struct)) {
                continue;
            }

            // array of segmented translations
            foreach ($struct as $translationTuple) {
                $segment = $segmentDao->getById($translationTuple->segmentId);

                // This condition is meant to debug an issue with the segment id that returns false from dao.
                // SegmentDao::getById returns false if the id is not found in the database
                // Skip the segment and lose the translation if the segment id is not found in the database
                if (!$segment) {
                    continue;
                }

                /** @var XliffRulesModel $configModel */
                $configModel = $projectStructure->xliff_parameters;
                $stateValues = SegmentExtractor::getTargetStatesFromTransUnit($translationTuple->transUnit, $translationTuple->mrkPosition);

                $rule = $configModel->getMatchingRule(
                    $projectStructure->current_xliff_info[$translationTuple->fileId]['version'],
                    $stateValues['state'],
                    $stateValues['state-qualifier']
                );

                if (XliffTranslationStatus::isFinalState($stateValues['state'])) {
                    $createSecondPassReview = true;
                }

                // Use QA to get a target segment
                $source = $segment->segment;
                $target = $translationTuple->target;

                $source = $this->filter->fromLayer0ToLayer1($source);
                $target = $this->filter->fromLayer0ToLayer1($target);

                $check = new QA($source, $target);
                $check->setFeatureSet($this->features);
                $check->setSourceSegLang($chunk->source);
                $check->setTargetSegLang($chunk->target);
                $check->performConsistencyCheck();

                if (!$check->thereAreErrors()) {
                    $translation = $check->getTrgNormalized();
                } else {
                    $translation = $check->getTargetSeg();
                }

                /* WARNING: do not change the order of the keys */
                $sql_values = [
                    'id_segment'            => $translationTuple->segmentId,
                    'id_job'                => $jid,
                    'segment_hash'          => $translationTuple->segmentHash,
                    'status'                => $rule->asEditorStatus(),
                    'translation'           => $this->filter->fromLayer1ToLayer0($translation),
                    'suggestion'            => $this->filter->fromLayer1ToLayer0($translation),
                    'locked'                => 0, // not allowed to change locked status for pre-translations
                    'match_type'            => $rule->asMatchType(),
                    'eq_word_count'         => $rule->asEquivalentWordCount($segment->raw_word_count, $payable_rates),
                    'serialized_errors_list' => ($check->thereAreErrors()) ? $check->getErrorsJSON() : '',
                    'warning'               => ($check->thereAreErrors()) ? 1 : 0,
                    'suggestion_match'      => null,
                    'standard_word_count'   => $rule->asStandardWordCount($segment->raw_word_count, $payable_rates),
                    'version_number'        => 0,
                ];

                $query_translations_values[] = $sql_values;
            }
        }

        // Executing the Query
        if (!empty($query_translations_values)) {
            $this->projectManagerModel->insertPreTranslations($query_translations_values);
        }

        // We do not create Chunk reviews since this is a task for postProjectCreate
        // Create a R2 for the job is state is 'final',
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
     * Look up job chunks by job ID.
     * Protected so test subclasses can override to avoid DB access.
     *
     * @return JobStruct[]
     * @throws ReflectionException
     */
    protected function getChunksByJobId(int $jobId): array
    {
        return ChunkDao::getByJobID($jobId);
    }

    /**
     * Persist an original data map for a segment.
     * Wraps the static DAO call so tests can override.
     *
     * @param int                    $id_segment
     * @param array<string, mixed>   $map
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
     * Validate and persist segment metadata if the struct has valid key/value.
     */
    protected function saveSegmentMetadata(int $id_segment, ?SegmentMetadataStruct $metadataStruct = null): void
    {
        if ($metadataStruct !== null and
            isset($metadataStruct->meta_key) and $metadataStruct->meta_key !== '' and
            isset($metadataStruct->meta_value) and $metadataStruct->meta_value !== ''
        ) {
            $metadataStruct->id_segment = (string)$id_segment;
            $this->persistSegmentMetadata($metadataStruct);
        }
    }

    /**
     * Link segment ID to notes entries for later insertion.
     *
     * @param array<string, mixed>   $row
     * @param ProjectStructure       $projectStructure
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
     * @param array<string, mixed>   $row
     * @param ProjectStructure       $projectStructure
     */
    private function setSegmentIdForContexts(array $row, ProjectStructure $projectStructure): void
    {
        $internal_id = $row['internal_id'];

        if (isset($projectStructure->context_group[$internal_id])) {
            $projectStructure->context_group[$internal_id]['context_json_segment_ids'][] = $row['id'];
        }
    }
}
