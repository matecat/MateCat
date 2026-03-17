<?php

namespace Model\ProjectCreation;

use Controller\API\Commons\Exceptions\AuthenticationError;
use Exception;
use InvalidArgumentException;
use Matecat\SubFiltering\MateCatFilter;
use Matecat\SubFiltering\Utils\DataRefReplacer;
use Matecat\XliffParser\XliffParser;
use Matecat\XliffParser\XliffUtils\XliffProprietaryDetect;
use Model\Concerns\LogsMessages;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Model\FeaturesBase\FeatureSet;
use Model\Files\FilesPartsDao;
use Model\Files\FilesPartsStruct;
use Model\Files\MetadataDao;
use Model\FilesStorage\AbstractFilesStorage;
use Model\FilesStorage\S3FilesStorage;
use Model\Segments\SegmentMetadataStruct;
use Model\Segments\SegmentOriginalDataStruct;
use Model\Segments\SegmentStruct;
use Model\Xliff\DTO\XliffRulesModel;
use ReflectionException;
use RuntimeException;
use Throwable;
use Utils\Engines\EnginesFactory;
use Utils\Engines\MyMemory;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;
use Utils\Tools\CatUtils;

/**
 * Encapsulates the segment extraction logic that was previously embedded in
 * {@see ProjectManager::extractSegments()} and its helper methods.
 *
 * This class is responsible for:
 *  - Parsing XLIFF file content
 *  - Extracting segments from both seg-source (segmented) and non-seg-source branches
 *  - Building SegmentStruct, metadata, and original-data structures
 *  - Managing alternative translations (alt-trans)
 *  - Extracting notes and context-group data
 *  - Computing segment hashes
 *  - Tracking word count and segment counters
 *
 * All mutations to projectStructure are performed on the ProjectStructure DTO passed
 * to {@see extract()}, which is the same mutable structure used by ProjectManager.
 */
class SegmentExtractor
{
    use LogsMessages;

    /**
     * Configuration for segment notes handling
     */
    private const int SEGMENT_NOTES_LIMIT    = 10;
    private const int SEGMENT_NOTES_MAX_SIZE = 65535;

    /**
     * Accumulated word count across all files processed by this instance.
     */
    private int $filesWordCount = 0;

    /**
     * Total number of trans-units processed (including non-translatable ones).
     */
    private int $totalSegments = 0;

    /**
     * Counter for segments visible in the CAT tool (show_in_cattool == 1).
     */
    private int $showInCattoolSegsCounter = 0;

    public function __construct(
        private readonly ProjectStructure $config,
        private readonly MateCatFilter    $filter,
        private readonly FeatureSet       $features,
        private readonly MetadataDao      $filesMetadataDao,
        MatecatLogger                     $logger,
    ) {
        $this->logger = $logger;
    }

    /**
     * The project ID from config, guaranteed non-null once {@see extract()} is called.
     * Cached locally to avoid repeated null checks on the readonly DTO property.
     */
    private int $idProject;

    /**
     * The source language from config, guaranteed non-null once {@see extract()} is called.
     */
    private string $sourceLanguage;

    // ── Public API ──────────────────────────────────────────────────

    /**
     * Extract segments from a single XLIFF file and populate the projectStructure.
     *
     * This is the main entry point, equivalent to the former
     * ProjectManager::_extractSegments().
     *
     * @param int $fid
     * @param array<string, mixed> $file_info
     * @param ProjectStructure $projectStructure
     *
     * @throws Exception
     */
    public function extract(int $fid, array $file_info, ProjectStructure $projectStructure): void
    {
        // Cache config values that must be set by the time extraction runs.
        // These are nullable in the DTO (a project may not exist yet at config build time),
        // but are always set before extractSegments() is called in the pipeline.
        if ($this->config->id_project === null || $this->config->source_language === null) {
            throw new RuntimeException('idProject and sourceLanguage must be set before extraction');
        }
        $this->idProject      = $this->config->id_project;
        $this->sourceLanguage = $this->config->source_language;

        $xliff_file_content = $this->getXliffFileContent($file_info['path_cached_xliff']);

        if ($xliff_file_content === false) {
            throw new RuntimeException("Failed to read XLIFF file: " . $file_info['path_cached_xliff']);
        }

        // create Structure for multiple files
        $projectStructure->segments[$fid] = [];
        $projectStructure->segments_original_data[$fid] = [];
        $projectStructure->segments_meta_data[$fid] = [];

        $xliffParser = new XliffParser();

        try {
            $xliff     = $xliffParser->xliffToArray($xliff_file_content);
            $xliffInfo = (new XliffProprietaryDetect())->getInfoByStringData($xliff_file_content);
            $projectStructure->current_xliff_info[$fid] = $xliffInfo;
        } catch (Throwable $e) {
            throw new Exception("Failed to parse " . $file_info['original_filename'], ($e->getCode() != 0 ? $e->getCode() : ProjectCreationError::XLIFF_PARSE_FAILURE->value), $e);
        }

        // Checking that parsing went well
        if (isset($xliff['parser-errors']) or !isset($xliff['files'])) {
            $this->log("Failed to parse " . $file_info['original_filename'] . join("\n", $xliff['parser-errors']));
            throw new Exception("Failed to parse " . $file_info['original_filename'], ProjectCreationError::XLIFF_PARSE_FAILURE->value);
        }

        // needed to check if a file has only one segment
        // for correctness: we could have more tag files in the xliff
        $_fileCounter_Show_In_Cattool = 0;

        // Creating the Query
        foreach ($xliff['files'] as $xliff_file) {
            $_fileCounter_Show_In_Cattool += $this->processXliffFile($xliff_file, $fid, $projectStructure);
        }

        // use generic
        if (count($projectStructure->segments[$fid]) == 0 || $_fileCounter_Show_In_Cattool == 0) {
            $this->log("Segment import - no segments found in {$file_info[ 'original_filename' ]}\n");
            throw new Exception($file_info['original_filename'], ProjectCreationError::NO_TRANSLATABLE_TEXT->value);
        } else {
            // increment global counter
            $this->showInCattoolSegsCounter += $_fileCounter_Show_In_Cattool;
        }
    }

    /**
     * Process a single <file> element from the parsed XLIFF structure.
     *
     * Handles metadata persistence, file-parts creation, and delegates
     * to the seg-source / non-seg-source trans-unit processing branches.
     *
     * @param array<string, mixed> $xliff_file
     * @param int $fid
     * @param ProjectStructure $projectStructure
     *
     * @return int Number of segments marked as show-in-cattool in this file element
     * @throws ReflectionException
     * @throws Exception
     */
    private function processXliffFile(array $xliff_file, int $fid, ProjectStructure $projectStructure): int
    {
        // save external-file attribute
        if (isset($xliff_file['attr']['external-file'])) {
            $externalFile = $xliff_file['attr']['external-file'];
            $this->filesMetadataDao->insert($this->idProject, $fid, 'mtc:references', $externalFile);
        }

        // save x-jsont* datatype
        if (isset($xliff_file['attr']['data-type'])) {
            $dataType = $xliff_file['attr']['data-type'];

            if (str_contains($dataType, 'x-jsont')) {
                $this->filesMetadataDao->insert($this->idProject, $fid, 'data-type', $dataType);
            }
        }

        if (!array_key_exists('trans-units', $xliff_file)) {
            return 0;
        }

        // files-part
        $filePartsId = null;
        if (isset($xliff_file['attr']['original'])) {
            $filesPartsStruct           = new FilesPartsStruct();
            $filesPartsStruct->id_file  = $fid;
            $filesPartsStruct->tag_key  = 'original';
            $filesPartsStruct->tag_value = $xliff_file['attr']['original'];

            $filePartsId = (new FilesPartsDao())->insert($filesPartsStruct);

            // save `custom` meta data
            if (isset($xliff_file['attr']['custom']) and !empty($xliff_file['attr']['custom'])) {
                $this->filesMetadataDao->bulkInsert($this->idProject, $fid, $xliff_file['attr']['custom'], $filePartsId);
            }
        }

        $fileCounterShowInCattool = 0;

        foreach ($xliff_file['trans-units'] as $xliff_trans_unit) {
            if (!isset($xliff_trans_unit['attr']['translate'])) {
                $xliff_trans_unit['attr']['translate'] = 'yes';
            }

            if ($xliff_trans_unit['attr']['translate'] == "no") {
                // No segments to translate — skip this trans-unit entirely
                continue;
            }

            $this->manageAlternativeTranslations($xliff_trans_unit, $xliff_file['attr']);

            $trans_unit_reference = self::sanitizedUnitId($xliff_trans_unit['attr']['id'], $fid);

            $dataRefMap = $this->buildDataRefMap($xliff_trans_unit);

            // If the XLIFF is already segmented (has <seg-source>)
            if (isset($xliff_trans_unit['seg-source'])) {
                $fileCounterShowInCattool += $this->processSegSourceTransUnit(
                    $xliff_trans_unit, $trans_unit_reference, $dataRefMap, $fid, $filePartsId, $projectStructure,
                );
            } else {
                $fileCounterShowInCattool += $this->processNonSegSourceTransUnit(
                    $xliff_trans_unit, $trans_unit_reference, $dataRefMap, $fid, $filePartsId, $projectStructure,
                );
            }
        }

        $this->totalSegments += count($xliff_file['trans-units']);

        return $fileCounterShowInCattool;
    }

    /**
     * Process a segmented (seg-source) trans-unit.
     *
     * Iterates over mrk elements in seg-source, handles pre-translations with
     * Unicode entity restoration and trimming, and appends segments.
     *
     * @param array<string, mixed> $xliff_trans_unit
     * @param string $trans_unit_reference
     * @param array<string, string> $dataRefMap
     * @param int $fid
     * @param int|null $filePartsId
     * @param ProjectStructure $projectStructure
     *
     * @return int Number of show-in-cattool segments produced
     * @throws AuthenticationError
     * @throws NotFoundException
     * @throws ValidationError
     * @throws EndQueueException
     * @throws ReQueueException
     * @throws Exception
     */
    private function processSegSourceTransUnit(
        array       $xliff_trans_unit,
        string      $trans_unit_reference,
        array       $dataRefMap,
        int         $fid,
        ?int        $filePartsId,
        ProjectStructure $projectStructure,
    ): int {
        $cattoolCount = 0;

        foreach ($xliff_trans_unit['seg-source'] as $position => $seg_source) {
            // reset flag because if the first mrk of the seg-source is not translatable the rest of
            // mrk in the list will not be too!!!
            $show_in_cattool = 1;

            $wordCount = CatUtils::segment_raw_word_count($seg_source['raw-content'], $this->sourceLanguage, $this->filter);
            $wordCount = $this->features->filter('wordCount', $wordCount);

            // init tags
            $seg_source['mrk-ext-prec-tags'] = '';
            $seg_source['mrk-ext-succ-tags'] = '';

            if (empty($wordCount)) {
                $show_in_cattool = 0;
            } else {
                $extract_external                = $this->stripExternal($seg_source['raw-content']);
                $seg_source['mrk-ext-prec-tags'] = $extract_external['prec'];
                $seg_source['mrk-ext-succ-tags'] = $extract_external['succ'];
                $seg_source['raw-content']       = $extract_external['seg'];

                if (isset($xliff_trans_unit['seg-target'][$position]['raw-content'])) {
                    $preTranslation = $this->detectPreTranslation(
                        $extract_external['seg'],
                        $xliff_trans_unit['seg-target'][$position]['raw-content'],
                        $xliff_trans_unit,
                        $fid,
                        $position,
                        $projectStructure,
                    );

                    if ($preTranslation !== null) {
                        if (!isset($projectStructure->translations[$trans_unit_reference])) {
                            $projectStructure->translations[$trans_unit_reference] = [];
                        }

                        $projectStructure->translations[$trans_unit_reference][$seg_source['mid']] =
                            new TranslationTuple($preTranslation['target'], $xliff_trans_unit, $position);

                        // seg-source and target translation can have different mrk id
                        // override the seg-source surrounding mrk-id with them of target
                        $seg_source['mrk-ext-prec-tags'] = $preTranslation['target_extract_external']['prec'];
                        $seg_source['mrk-ext-succ-tags'] = $preTranslation['target_extract_external']['succ'];
                    }
                }
            }

            $counters = $this->buildAndAppendSegment(
                fid: $fid,
                filePartsId: $filePartsId,
                xliff_trans_unit: $xliff_trans_unit,
                rawContent: $seg_source['raw-content'],
                dataRefMap: $dataRefMap,
                wordCount: $wordCount,
                showInCattool: $show_in_cattool,
                projectStructure: $projectStructure,
                xliffMrkId: $seg_source['mid'],
                xliffExtPrecTags: $seg_source['ext-prec-tags'],
                xliffMrkExtPrecTags: $seg_source['mrk-ext-prec-tags'],
                xliffMrkExtSuccTags: $seg_source['mrk-ext-succ-tags'],
                xliffExtSuccTags: $seg_source['ext-succ-tags'],
            );

            // increment the counter for not empty segments
            $cattoolCount += $counters['show_in_cattool'];
        } // end foreach seg-source

        $this->extractNotesAndContexts($xliff_trans_unit, $fid, $projectStructure);

        return $cattoolCount;
    }

    /**
     * Process a non-segmented (no seg-source) trans-unit.
     *
     * Handles word count, external tag stripping, pre-translation detection,
     * notes/context extraction, and segment creation.
     *
     * @param array<string, mixed> $xliff_trans_unit
     * @param string $trans_unit_reference
     * @param array<string, string> $dataRefMap
     * @param int $fid
     * @param int|null $filePartsId
     * @param ProjectStructure $projectStructure
     *
     * @return int Number of show-in-cattool segments produced (0 or 1)
     * @throws Exception
     */
    private function processNonSegSourceTransUnit(
        array       $xliff_trans_unit,
        string      $trans_unit_reference,
        array       $dataRefMap,
        int         $fid,
        ?int        $filePartsId,
        ProjectStructure $projectStructure,
    ): int {
        $show_in_cattool = 1;

        $wordCount = CatUtils::segment_raw_word_count($xliff_trans_unit['source']['raw-content'], $this->sourceLanguage, $this->filter);

        $prec_tags = null;
        $succ_tags = null;
        if (empty($wordCount)) {
            $show_in_cattool = 0;
        } else {
            $extract_external                                = $this->stripExternal($xliff_trans_unit['source']['raw-content']);
            $prec_tags                                       = empty($extract_external['prec']) ? null : $extract_external['prec'];
            $succ_tags                                       = empty($extract_external['succ']) ? null : $extract_external['succ'];
            $xliff_trans_unit['source']['raw-content']       = $extract_external['seg'];

            if (isset($xliff_trans_unit['target']['raw-content'])) {
                $preTranslation = $this->detectPreTranslation(
                    $extract_external['seg'],
                    $xliff_trans_unit['target']['raw-content'],
                    $xliff_trans_unit,
                    $fid,
                    null,
                    $projectStructure,
                );

                if ($preTranslation !== null) {
                    if (!isset($projectStructure->translations[$trans_unit_reference])) {
                        $projectStructure->translations[$trans_unit_reference] = [];
                    }

                    $projectStructure->translations[$trans_unit_reference][] =
                        new TranslationTuple($preTranslation['target'], $xliff_trans_unit);
                }
            }
        }

        $this->extractNotesAndContexts($xliff_trans_unit, $fid, $projectStructure);

        $counters = $this->buildAndAppendSegment(
            fid: $fid,
            filePartsId: $filePartsId,
            xliff_trans_unit: $xliff_trans_unit,
            rawContent: $xliff_trans_unit['source']['raw-content'],
            dataRefMap: $dataRefMap,
            wordCount: $wordCount,
            showInCattool: $show_in_cattool,
            projectStructure: $projectStructure,
            xliffExtPrecTags: $prec_tags,
            xliffExtSuccTags: $succ_tags,
        );

        return $counters['show_in_cattool'];
    }

    // ── Counter getters ─────────────────────────────────────────────

    public function getFilesWordCount(): int
    {
        return $this->filesWordCount;
    }

    public function getTotalSegments(): int
    {
        return $this->totalSegments;
    }

    public function getShowInCattoolSegsCounter(): int
    {
        return $this->showInCattoolSegsCounter;
    }

    // ── Public static helpers (shared with ProjectManager) ──────────

    /**
     * Build a sanitized unit ID from the trans-unit ID and file ID.
     *
     * Public static because it is also necessary for ProjectManager::_storeSegments().
     */
    public static function sanitizedUnitId(string $trans_unitID, int $fid): string
    {
        return $fid . "|" . $trans_unitID;
    }

    /**
     * Extract state and state-qualifier from a trans-unit's target attributes.
     *
     * Public static because it is also necessary by ProjectManager::_insertPreTranslations().
     *
     * @param array<string, mixed> $trans_unit The parsed trans-unit
     * @param int|null             $position   mrk position (for seg-target), null for non-segmented
     *
     * @return array{state: ?string, state-qualifier: ?string}
     */
    public static function getTargetStatesFromTransUnit(array $trans_unit, ?int $position = null): array
    {
        // state handling
        $state          = null;
        $stateQualifier = null;

        if (isset($trans_unit['seg-target'][$position]['attr']) and isset($trans_unit['seg-target'][$position]['attr']['state'])) {
            $state = $trans_unit['seg-target'][$position]['attr']['state'];
        } elseif (isset($trans_unit['target']['attr']) and isset($trans_unit['target']['attr']['state'])) {
            $state = $trans_unit['target']['attr']['state'];
        }

        if (isset($trans_unit['seg-target'][$position]['attr']) and isset($trans_unit['seg-target'][$position]['attr']['state-qualifier'])) {
            $stateQualifier = $trans_unit['seg-target'][$position]['attr']['state-qualifier'];
        } elseif (isset($trans_unit['target']['attr']) and isset($trans_unit['target']['attr']['state-qualifier'])) {
            $stateQualifier = $trans_unit['target']['attr']['state-qualifier'];
        }

        return ['state' => $state, 'state-qualifier' => $stateQualifier];
    }

    // ── Private helpers ─────────────────────────────────────────────

    /**
     * Extract the sizeRestriction value from a trans-unit's attributes.
     *
     * Returns the value as an int if present and > 0, null otherwise.
     *
     * @param array<string, mixed> $xliff_trans_unit
     */
    private function getSizeRestrictionValue(array $xliff_trans_unit): ?int
    {
        if (isset($xliff_trans_unit['attr']['sizeRestriction']) and $xliff_trans_unit['attr']['sizeRestriction'] > 0) {
            return (int)$xliff_trans_unit['attr']['sizeRestriction'];
        }

        return null;
    }

    /**
     * Build a dataRef map from the trans-unit's original-data entries.
     *
     * @param array<string, mixed> $xliff_trans_unit
     *
     * @return array<string, string> Map of data-ref ID to raw content
     */
    private function buildDataRefMap(array $xliff_trans_unit): array
    {
        $dataRefMap = [];

        if (isset($xliff_trans_unit['original-data']) and !empty($xliff_trans_unit['original-data'])) {
            foreach ($xliff_trans_unit['original-data'] as $datum) {
                if (isset($datum['attr']['id'])) {
                    $dataRefMap[$datum['attr']['id']] = $datum['raw-content'];
                }
            }
        }

        return $dataRefMap;
    }

    /**
     * Detect whether a target qualifies as a pre-translation.
     *
     * Applies the full pipeline: populatePreTranslations feature gate,
     * unicode entity restoration, trim+strip, isTranslated check, and
     * layer-0 conversion.
     *
     * @param string $sourceRawContent
     * @param string $targetRawContent
     * @param array<string, mixed> $xliff_trans_unit
     * @param int $fid
     * @param int|null $position
     * @param ProjectStructure $projectStructure
     *
     * @return array<string, mixed>|null Null if not a valid pre-translation
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws NotFoundException
     * @throws ReQueueException
     * @throws ValidationError
     * @throws Exception
     */
    private function detectPreTranslation(
        string      $sourceRawContent,
        string      $targetRawContent,
        array       $xliff_trans_unit,
        int         $fid,
        ?int        $position,
        ProjectStructure $projectStructure,
    ): ?array {
        if (!$this->features->filter('populatePreTranslations', true)) {
            return null;
        }

        $stateValues = self::getTargetStatesFromTransUnit($xliff_trans_unit, $position);

        $target_extract_external = $this->stripExternal($targetRawContent);

        // restore unicode entities before html_entity_decode comparison
        $sourceRawContent               = CatUtils::restoreUnicodeEntitiesToOriginalValues($sourceRawContent);
        $target_extract_external['seg'] = CatUtils::restoreUnicodeEntitiesToOriginalValues($target_extract_external['seg']);

        $src = CatUtils::trimAndStripFromAnHtmlEntityDecoded($sourceRawContent);
        $trg = CatUtils::trimAndStripFromAnHtmlEntityDecoded($target_extract_external['seg']);

        if (!$this->isTranslated($src, $trg, $fid, $stateValues['state'], $stateValues['state-qualifier'], $projectStructure) || empty($trg)) {
            return null;
        }

        return [
            'target'                  => $this->filter->fromRawXliffToLayer0($target_extract_external['seg']),
            'target_extract_external' => $target_extract_external,
        ];
    }

    /**
     * Build a SegmentStruct, its metadata, and original-data struct, then
     * append everything to the projectStructure arrays, and update counters.
     *
     * @param int $fid
     * @param int|null $filePartsId
     * @param array<string, mixed> $xliff_trans_unit
     * @param string $rawContent
     * @param array<string, string> $dataRefMap
     * @param float $wordCount
     * @param int $showInCattool
     * @param ProjectStructure $projectStructure
     * @param string|null $xliffMrkId
     * @param string|null $xliffExtPrecTags
     * @param string|null $xliffMrkExtPrecTags
     * @param string|null $xliffMrkExtSuccTags
     * @param string|null $xliffExtSuccTags
     * @return array{word_count: float, show_in_cattool: int}
     * @throws Exception
     */
    private function buildAndAppendSegment(
        int         $fid,
        ?int        $filePartsId,
        array       $xliff_trans_unit,
        string      $rawContent,
        array       $dataRefMap,
        float       $wordCount,
        int         $showInCattool,
        ProjectStructure $projectStructure,
        ?string     $xliffMrkId = null,
        ?string     $xliffExtPrecTags = null,
        ?string     $xliffMrkExtPrecTags = null,
        ?string     $xliffMrkExtSuccTags = null,
        ?string     $xliffExtSuccTags = null,
    ): array {
        // --- Segment metadata (sizeRestriction) ---
        $metadataStruct  = new SegmentMetadataStruct();
        $sizeRestriction = $this->getSizeRestrictionValue($xliff_trans_unit);
        if ($sizeRestriction !== null) {
            $metadataStruct->meta_key   = 'sizeRestriction';
            $metadataStruct->meta_value = (string)$sizeRestriction;
        }
        $projectStructure->segments_meta_data[$fid][] = $metadataStruct;

        // --- Segment original data ---
        $segmentOriginalDataStruct = (new SegmentOriginalDataStruct())->setMap($dataRefMap);
        $projectStructure->segments_original_data[$fid][] = $segmentOriginalDataStruct;

        // --- Segment hash ---
        $segmentHash = $this->createSegmentHash($rawContent, $dataRefMap, $sizeRestriction);

        // --- SegmentStruct ---
        $segStruct = new SegmentStruct([
            'id_file'                => $fid,
            'id_file_part'           => $filePartsId,
            'id_project'             => $this->idProject,
            'internal_id'            => $xliff_trans_unit['attr']['id'],
            'xliff_mrk_id'           => $xliffMrkId,
            'xliff_ext_prec_tags'    => $xliffExtPrecTags,
            'xliff_mrk_ext_prec_tags' => $xliffMrkExtPrecTags,
            'segment'                => $this->filter->fromRawXliffToLayer0($rawContent),
            'segment_hash'           => $segmentHash,
            'xliff_mrk_ext_succ_tags' => $xliffMrkExtSuccTags,
            'xliff_ext_succ_tags'    => $xliffExtSuccTags,
            'raw_word_count'         => $wordCount,
            'show_in_cattool'        => $showInCattool,
        ]);

        $projectStructure->segments[$fid][] = $segStruct;

        // --- Update counters ---
        $this->filesWordCount += (int)$wordCount;

        return ['word_count' => $wordCount, 'show_in_cattool' => $showInCattool];
    }

    /**
     * Compute a segment hash, incorporating original-data and sizeRestriction
     * when present to avoid collisions.
     *
     * @param array<string, string>|null $dataRefMap
     */
    private function createSegmentHash(string $rawContent, ?array $dataRefMap = null, ?int $sizeRestriction = null): string
    {
        $segmentToBeHashed = $rawContent;

        if (!empty($dataRefMap)) {
            $dataRefReplacer   = new DataRefReplacer($dataRefMap);
            $segmentToBeHashed = $dataRefReplacer->replace($rawContent);
        }

        if (!empty($sizeRestriction)) {
            $segmentToBeHashed .= '{"sizeRestriction": ' . $sizeRestriction . '}';
        }

        return md5($segmentToBeHashed);
    }

    /**
     * Read XLIFF file content from a local filesystem or S3.
     *
     * @throws Exception
     */
    private function getXliffFileContent(string $xliffFilePath): false|string
    {
        if (AbstractFilesStorage::isOnS3()) {
            $s3Client = S3FilesStorage::getStaticS3Client();

            if ($s3Client->hasEncoder()) {
                $encoder = $s3Client->getEncoder();
                if ($encoder !== null) {
                    $xliffFilePath = $encoder->decode($xliffFilePath);
                }
            }

            return $s3Client->openItem(['bucket' => S3FilesStorage::getFilesStorageBucket(), 'key' => $xliffFilePath]);
        }

        return file_get_contents($xliffFilePath);
    }

    /**
     * Strip external tags from a segment.
     *
     * Currently, disabled — always returns the segment unchanged with null prec/succ.
     *
     * @return array<string, mixed>
     */
    private function stripExternal(string $segment): array
    {
        // Definitely DISABLED
        return ['prec' => null, 'seg' => $segment, 'succ' => null];
    }

    /**
     * Extract notes and context-group data from a trans-unit.
     *
     * Wraps addNotesToProjectStructure() and addTUnitContextsToProjectStructure()
     * with consistent error handling.
     *
     * @param array<string, mixed> $xliff_trans_unit
     * @param int $fid
     * @param ProjectStructure $projectStructure
     *
     * @throws Exception
     */
    private function extractNotesAndContexts(array $xliff_trans_unit, int $fid, ProjectStructure $projectStructure): void
    {
        try {
            $this->addNotesToProjectStructure($xliff_trans_unit, $fid, $projectStructure);
            $this->addTUnitContextsToProjectStructure($xliff_trans_unit, $fid, $projectStructure);
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage(), ProjectCreationError::NO_TRANSLATABLE_TEXT->value);
        }
    }

    /**
     * Add notes from a trans-unit to the projectStructure.
     *
     * @param array<string, mixed> $trans_unit
     * @param int $fid
     * @param ProjectStructure $projectStructure
     *
     * @throws Exception
     */
    private function addNotesToProjectStructure(array $trans_unit, int $fid, ProjectStructure $projectStructure): void
    {
        $internal_id = self::sanitizedUnitId($trans_unit['attr']['id'], $fid);
        if (isset($trans_unit['notes'])) {
            if (count($trans_unit['notes']) > self::SEGMENT_NOTES_LIMIT) {
                throw new Exception('File upload failed: a segment can have a maximum of ' . self::SEGMENT_NOTES_LIMIT . ' notes.', ProjectCreationError::TOO_MANY_NOTES->value);
            }

            foreach ($trans_unit['notes'] as $note) {
                $this->initNestedArray('notes', $internal_id, $projectStructure);

                $noteKey     = null;
                $noteContent = null;

                if (isset($note['json'])) {
                    $noteContent = $note['json'];
                    $noteKey     = 'json';
                } elseif (isset($note['raw-content'])) {
                    $noteContent = $note['raw-content'];
                    $noteKey     = 'entries';
                }

                if ($noteKey === null) {
                    continue;
                }

                if (strlen($noteContent) > self::SEGMENT_NOTES_MAX_SIZE) {
                    throw new Exception(' you reached the maximum size for a single segment note (' . self::SEGMENT_NOTES_MAX_SIZE . ' bytes)');
                }

                if (!isset($projectStructure->notes[$internal_id]['entries'])) {
                    $projectStructure->notes[$internal_id]['from'] = [];
                    $projectStructure->notes[$internal_id]['from']['entries'] = [];
                    $projectStructure->notes[$internal_id]['from']['json'] = [];
                    $projectStructure->notes[$internal_id]['entries'] = [];
                    $projectStructure->notes[$internal_id]['json'] = [];
                    $projectStructure->notes[$internal_id]['json_segment_ids'] = [];
                    $projectStructure->notes[$internal_id]['segment_ids'] = [];
                }

                $projectStructure->notes[$internal_id][$noteKey][] = $noteContent;

                // import segments metadata from the `from` attribute
                if (isset($note['from'])) {
                    $projectStructure->notes[$internal_id]['from'][$noteKey][] = $note['from'];
                } else {
                    $projectStructure->notes[$internal_id]['from'][$noteKey][] = 'NO_FROM';
                }
            }
        }
    }

    /**
     * Add context-group data from a trans-unit to the projectStructure.
     *
     * @param array<string, mixed> $trans_unit
     * @param int $fid
     * @param ProjectStructure $projectStructure
     */
    private function addTUnitContextsToProjectStructure(array $trans_unit, int $fid, ProjectStructure $projectStructure): void
    {
        $internal_id = self::sanitizedUnitId($trans_unit['attr']['id'], $fid);
        if (isset($trans_unit['context-group'])) {
            $this->initNestedArray('context_group', $internal_id, $projectStructure);

            if (!isset($projectStructure->context_group[$internal_id]['context_json'])) {
                $projectStructure->context_group[$internal_id]['context_json'] = $trans_unit['context-group'];
                $projectStructure->context_group[$internal_id]['context_json_segment_ids'] = []; // because of mrk tags, the same context can be owned by different segments
            }
        }
    }

    /**
     * Initialize a nested array entry in projectStructure if it does not already exist.
     *
     * @param string $key
     * @param string $id
     * @param ProjectStructure $projectStructure
     */
    private function initNestedArray(string $key, string $id, ProjectStructure $projectStructure): void
    {
        switch ($key) {
            case 'notes':
                if (!array_key_exists($id, $projectStructure->notes)) {
                    $projectStructure->notes[$id] = [];
                }
                return;

            case 'context_group':
                if (!array_key_exists($id, $projectStructure->context_group)) {
                    $projectStructure->context_group[$id] = [];
                }
                return;

            default:
                throw new InvalidArgumentException('Invalid nested array key.');
        }
    }

    /**
     * Decide if a source/target pair should be considered translated,
     * based on user-defined XLIFF rules.
     *
     * @param string|null $source
     * @param string|null $target
     * @param int|null $file_id
     * @param string|null $state
     * @param string|null $stateQualifier
     * @param ProjectStructure      $projectStructure
     * @return bool
     * @throws Exception
     */
    private function isTranslated(
        ?string     $source,
        ?string     $target,
        ?int        $file_id,
        ?string     $state,
        ?string     $stateQualifier,
        ProjectStructure $projectStructure,
    ): bool {
        /** @var XliffRulesModel $configModel */
        $configModel = $projectStructure->xliff_parameters;
        $rule        = $configModel->getMatchingRule(
            $projectStructure->current_xliff_info[$file_id]['version'],
            $state,
            $stateQualifier
        );

        return $rule->isTranslated($source ?? '', $target ?? '');
    }

    /**
     * Manage alternative translations (alt-trans) for a trans-unit.
     *
     * Sends matching alt-trans entries to the TM engine for each writable key.
     *
     * @param array<string, mixed>       $xliff_trans_unit
     * @param array<string, mixed>|null  $xliff_file_attributes
     *
     * @throws Exception
     */
    private function manageAlternativeTranslations(array $xliff_trans_unit, ?array $xliff_file_attributes): void
    {
        $privateTmKeys = $this->config->private_tm_key;

        // Source and target language are mandatory, moreover do not set matches on public area
        if (
            !isset($xliff_trans_unit['alt-trans']) ||
            empty($xliff_file_attributes['source-language']) ||
            empty($xliff_file_attributes['target-language']) ||
            empty($privateTmKeys) ||
            $this->features->filter('doNotManageAlternativeTranslations', true, $xliff_trans_unit, $xliff_file_attributes)
        ) {
            return;
        }

        // set the contribution for every key in the job belonging to the user
        $engine = EnginesFactory::getInstance(1, MyMemory::class);
        $config = $engine->getConfigStruct();

        foreach ($privateTmKeys as $tm_info) {
            if ($tm_info['w'] == 1) {
                $config['id_user'][] = $tm_info['key'];
            }
        }

        $config['source'] = $xliff_file_attributes['source-language'];
        $config['target'] = $xliff_file_attributes['target-language'];
        $config['email']  = AppConfig::$MYMEMORY_API_KEY;

        foreach ($xliff_trans_unit['alt-trans'] as $altTrans) {
            if (!empty($altTrans['attr']['match-quality']) && (float) $altTrans['attr']['match-quality'] < 50) {
                continue;
            }

            $source_extract_external = '';

            // Wrong alt-trans tag
            if ((empty($xliff_trans_unit['source'] /* theoretically impossible empty source */) && empty($altTrans['source'])) || empty($altTrans['target'])) {
                continue;
            }

            if (!empty($xliff_trans_unit['source'])) {
                $source_extract_external = $this->stripExternal($xliff_trans_unit['source']['raw-content']); // XXX to remove function
            }

            // Override with the alt-trans source value
            if (!empty($altTrans['source'])) {
                $source_extract_external = $this->stripExternal($altTrans['source']); // XXX to remove function
            }

            $target_extract_external = $this->stripExternal($altTrans['target']); // XXX to remove function

            // wrong alt-trans content: source == target
            if (is_array($source_extract_external) && isset($source_extract_external['seg']) && $source_extract_external['seg'] == $target_extract_external['seg']) {
                continue;
            }

            $config['segment']        = is_array($source_extract_external) && isset($source_extract_external['seg'])
                ? $this->filter->fromRawXliffToLayer0($source_extract_external['seg'])
                : '';
            $config['translation']    = $this->filter->fromRawXliffToLayer0($target_extract_external['seg']);
            $config['context_after']  = null;
            $config['context_before'] = null;

            if (!empty($altTrans['attr']['match-quality'])) {
                // get the Props
                $config['prop'] = json_encode([
                    "match-quality" => $altTrans['attr']['match-quality']
                ]);
            }

            $engine->set($config);
        }
    }
}
