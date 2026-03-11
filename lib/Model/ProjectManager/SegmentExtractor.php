<?php

namespace Model\ProjectManager;

use ArrayObject;
use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Matecat\SubFiltering\Utils\DataRefReplacer;
use Matecat\XliffParser\XliffParser;
use Matecat\XliffParser\XliffUtils\XliffProprietaryDetect;
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
use Throwable;
use Utils\Engines\EnginesFactory;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;
use Utils\Tools\CatUtils;
use View\API\Commons\Error;

/**
 * Encapsulates the segment extraction logic that was previously embedded in
 * {@see ProjectManager::_extractSegments()} and its helper methods.
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
 * All mutations to projectStructure are performed on the ArrayObject passed
 * to {@see extract()}, which is the same mutable structure used by ProjectManager.
 */
class SegmentExtractor
{
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
        private readonly MateCatFilter $filter,
        private readonly FeatureSet    $features,
        private readonly MetadataDao   $filesMetadataDao,
        private readonly MatecatLogger $logger,
    ) {
    }

    // ── Public API ──────────────────────────────────────────────────

    /**
     * Extract segments from a single XLIFF file and populate the projectStructure.
     *
     * This is the main entry point, equivalent to the former
     * ProjectManager::_extractSegments().
     *
     * @param int         $fid              File ID
     * @param array       $file_info        Must contain 'path_cached_xliff' and 'original_filename'
     * @param ArrayObject $projectStructure The mutable project structure (segments, translations, notes, etc.)
     *
     * @throws Exception
     */
    public function extract(int $fid, array $file_info, ArrayObject $projectStructure): void
    {
        $xliff_file_content = $this->getXliffFileContent($file_info['path_cached_xliff']);

        // create Structure for multiple files
        $projectStructure['segments']->offsetSet($fid, new ArrayObject([]));
        $projectStructure['segments-original-data']->offsetSet($fid, new ArrayObject([]));
        $projectStructure['file-part-id']->offsetSet($fid, new ArrayObject([]));
        $projectStructure['segments-meta-data']->offsetSet($fid, new ArrayObject([]));

        $xliffParser = new XliffParser();

        try {
            $xliff     = $xliffParser->xliffToArray($xliff_file_content);
            $xliffInfo = (new XliffProprietaryDetect())->getInfoByStringData($xliff_file_content);
            $projectStructure['current-xliff-info'][$fid] = $xliffInfo;
        } catch (Throwable $e) {
            throw new Exception("Failed to parse " . $file_info['original_filename'], ($e->getCode() != 0 ? $e->getCode() : -4), $e);
        }

        // Checking that parsing went well
        if (isset($xliff['parser-errors']) or !isset($xliff['files'])) {
            $this->log("Failed to parse " . $file_info['original_filename'] . join("\n", $xliff['parser-errors']));
            throw new Exception("Failed to parse " . $file_info['original_filename'], -4);
        }

        // needed to check if a file has only one segment
        // for correctness: we could have more tag files in the xliff
        $_fileCounter_Show_In_Cattool = 0;

        // Creating the Query
        foreach ($xliff['files'] as $xliff_file) {
            // save external-file attribute
            if (isset($xliff_file['attr']['external-file'])) {
                $externalFile = $xliff_file['attr']['external-file'];
                $this->filesMetadataDao->insert($projectStructure['id_project'], $fid, 'mtc:references', $externalFile);
            }

            // save x-jsont* datatype
            if (isset($xliff_file['attr']['data-type'])) {
                $dataType = $xliff_file['attr']['data-type'];

                if (str_contains($dataType, 'x-jsont')) {
                    $this->filesMetadataDao->insert($projectStructure['id_project'], $fid, 'data-type', $dataType);
                }
            }

            if (!array_key_exists('trans-units', $xliff_file)) {
                continue;
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
                    $this->filesMetadataDao->bulkInsert($projectStructure['id_project'], $fid, $xliff_file['attr']['custom'], $filePartsId);
                }
            }

            foreach ($xliff_file['trans-units'] as $xliff_trans_unit) {
                // initialize flag
                $show_in_cattool = 1;

                if (!isset($xliff_trans_unit['attr']['translate'])) {
                    $xliff_trans_unit['attr']['translate'] = 'yes';
                }

                if ($xliff_trans_unit['attr']['translate'] == "no") {
                    // No segments to translate — skip this trans-unit entirely
                    continue;
                }

                $this->manageAlternativeTranslations($xliff_trans_unit, $xliff_file['attr'], $projectStructure);

                $trans_unit_reference = self::sanitizedUnitId($xliff_trans_unit['attr']['id'], $fid);

                $dataRefMap = [];

                if (isset($xliff_trans_unit['original-data']) and !empty($xliff_trans_unit['original-data'])) {
                    $segmentOriginalData = $xliff_trans_unit['original-data'];
                    foreach ($segmentOriginalData as $datum) {
                        if (isset($datum['attr']['id'])) {
                            $dataRefMap[$datum['attr']['id']] = $datum['raw-content'];
                        }
                    }
                }

                // If the XLIFF is already segmented (has <seg-source>)
                if (isset($xliff_trans_unit['seg-source'])) {
                    foreach ($xliff_trans_unit['seg-source'] as $position => $seg_source) {
                        // rest flag because if the first mrk of the seg-source is not translatable the rest of
                        // mrk in the list will not be too!!!
                        $show_in_cattool = 1;

                        $wordCount = CatUtils::segment_raw_word_count($seg_source['raw-content'], $projectStructure['source_language'], $this->filter);
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
                                if ($this->features->filter('populatePreTranslations', true)) {
                                    $stateValues = self::getTargetStatesFromTransUnit($xliff_trans_unit, $position);

                                    $target_extract_external = $this->stripExternal($xliff_trans_unit['seg-target'][$position]['raw-content']);

                                    //
                                    // -----------------------------------------------
                                    // NOTE 2020-06-16
                                    // -----------------------------------------------
                                    //
                                    // before calling html_entity_decode function we convert
                                    // all unicode entities with no corresponding HTML entity
                                    //
                                    $extract_external['seg']         = CatUtils::restoreUnicodeEntitiesToOriginalValues($extract_external['seg']);
                                    $target_extract_external['seg']  = CatUtils::restoreUnicodeEntitiesToOriginalValues($target_extract_external['seg']);

                                    // we don't want THE CONTENT OF TARGET TAG IF PRESENT and EQUAL TO SOURCE???
                                    // AND IF IT IS ONLY A CHAR? like "*" ?
                                    // we can't distinguish if it is translated or not
                                    // this means that we lose the tags id inside the target if different from source
                                    $src = CatUtils::trimAndStripFromAnHtmlEntityDecoded($extract_external['seg']);
                                    $trg = CatUtils::trimAndStripFromAnHtmlEntityDecoded($target_extract_external['seg']);

                                    if ($this->isTranslated(
                                            $src,
                                            $trg,
                                            $fid,
                                            $stateValues['state'],
                                            $stateValues['state-qualifier'],
                                            $projectStructure,
                                        ) && !empty($trg)
                                    ) { // treat 0,1,2... as translated content!

                                        $target = $this->filter->fromRawXliffToLayer0($target_extract_external['seg']);

                                        // add an empty string to avoid casting to int: 0001 -> 1
                                        // useful for idiom internal xliff id
                                        if (!$projectStructure['translations']->offsetExists($trans_unit_reference)) {
                                            $projectStructure['translations']->offsetSet($trans_unit_reference, new ArrayObject());
                                        }

                                        /**
                                         * Trans-Unit
                                         * @see http://docs.oasis-open.org/xliff/v1.2/os/xliff-core.html#trans-unit
                                         */
                                        $projectStructure['translations'][$trans_unit_reference]->offsetSet(
                                            $seg_source['mid'],
                                            new ArrayObject([
                                                2 => $target,
                                                4 => $xliff_trans_unit,
                                                6 => $position, // this value is the mrk positional order
                                            ])
                                        );

                                        // seg-source and target translation can have different mrk id
                                        // override the seg-source surrounding mrk-id with them of target
                                        $seg_source['mrk-ext-prec-tags'] = $target_extract_external['prec'];
                                        $seg_source['mrk-ext-succ-tags'] = $target_extract_external['succ'];
                                    }
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
                        $_fileCounter_Show_In_Cattool += $counters['show_in_cattool'];
                    } // end foreach seg-source

                    try {
                        $this->addNotesToProjectStructure($xliff_trans_unit, $fid, $projectStructure);
                        $this->addTUnitContextsToProjectStructure($xliff_trans_unit, $fid, $projectStructure);
                    } catch (Exception $exception) {
                        throw new Exception($exception->getMessage(), -1);
                    }
                } else {
                    $wordCount = CatUtils::segment_raw_word_count($xliff_trans_unit['source']['raw-content'], $projectStructure['source_language'], $this->filter);

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
                            $stateValues = self::getTargetStatesFromTransUnit($xliff_trans_unit);

                            $target_extract_external = $this->stripExternal($xliff_trans_unit['target']['raw-content']);

                            if ($this->isTranslated(
                                    $xliff_trans_unit['source']['raw-content'],
                                    $target_extract_external['seg'],
                                    $fid,
                                    $stateValues['state'],
                                    $stateValues['state-qualifier'],
                                    $projectStructure,
                                ) && !empty($target_extract_external['seg'])
                            ) {
                                $target = $this->filter->fromRawXliffToLayer0($target_extract_external['seg']);

                                // add an empty string to avoid casting to int: 0001 -> 1
                                // useful for idiom internal xliff id
                                if (!$projectStructure['translations']->offsetExists($trans_unit_reference)) {
                                    $projectStructure['translations']->offsetSet($trans_unit_reference, new ArrayObject());
                                }

                                /**
                                 * Trans-Unit
                                 * @see http://docs.oasis-open.org/xliff/v1.2/os/xliff-core.html#trans-unit
                                 */
                                $projectStructure['translations'][$trans_unit_reference]->append(
                                    new ArrayObject([
                                        2 => $target,
                                        4 => $xliff_trans_unit,
                                    ])
                                );
                            }
                        }
                    }

                    try {
                        $this->addNotesToProjectStructure($xliff_trans_unit, $fid, $projectStructure);
                        $this->addTUnitContextsToProjectStructure($xliff_trans_unit, $fid, $projectStructure);
                    } catch (Exception $exception) {
                        throw new Exception(
                            $exception->getMessage(),
                            $exception->getCode() ?? -1
                        );
                    }

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

                    // increment the counter for not empty segments
                    $_fileCounter_Show_In_Cattool += $counters['show_in_cattool'];
                }
            }

            $this->totalSegments += count($xliff_file['trans-units']);
        }

        // use generic
        if (count($projectStructure['segments'][$fid]) == 0 || $_fileCounter_Show_In_Cattool == 0) {
            $this->log("Segment import - no segments found in {$file_info[ 'original_filename' ]}\n");
            throw new Exception($file_info['original_filename'], -1);
        } else {
            // increment global counter
            $this->showInCattoolSegsCounter += $_fileCounter_Show_In_Cattool;
        }
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
     * Public static because it is also needed by ProjectManager::_storeSegments().
     */
    public static function sanitizedUnitId(string $trans_unitID, string $fid): string
    {
        return $fid . "|" . $trans_unitID;
    }

    /**
     * Extract state and state-qualifier from a trans-unit's target attributes.
     *
     * Public static because it is also needed by ProjectManager::_insertPreTranslations().
     *
     * @param array    $trans_unit The parsed trans-unit
     * @param int|null $position   mrk position (for seg-target), null for non-segmented
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

    private function log(string $_msg, ?Throwable $exception = null): void
    {
        if (!$exception) {
            $this->logger->debug($_msg);
        } else {
            $this->logger->debug($_msg, (new Error($exception))->render(true));
        }
    }

    /**
     * Extract the sizeRestriction value from a trans-unit's attributes.
     *
     * Returns the value as an int if present and > 0, null otherwise.
     */
    private function getSizeRestrictionValue(array $xliff_trans_unit): ?int
    {
        if (isset($xliff_trans_unit['attr']['sizeRestriction']) and $xliff_trans_unit['attr']['sizeRestriction'] > 0) {
            return (int)$xliff_trans_unit['attr']['sizeRestriction'];
        }

        return null;
    }

    /**
     * Build a SegmentStruct, its metadata, and original-data struct, then
     * append everything to the projectStructure arrays and update counters.
     *
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
        ArrayObject $projectStructure,
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
            $metadataStruct->meta_value = $sizeRestriction;
        }
        $projectStructure['segments-meta-data'][$fid]->append($metadataStruct);

        // --- Segment original data ---
        $segmentOriginalDataStruct = (new SegmentOriginalDataStruct())->setMap($dataRefMap);
        $projectStructure['segments-original-data'][$fid]->append($segmentOriginalDataStruct);

        // --- Segment hash ---
        $segmentHash = $this->createSegmentHash($rawContent, $dataRefMap, $sizeRestriction);

        // --- SegmentStruct ---
        $segStruct = new SegmentStruct([
            'id_file'                => $fid,
            'id_file_part'           => $filePartsId,
            'id_project'             => $projectStructure['id_project'],
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

        $projectStructure['segments'][$fid]->append($segStruct);

        // --- Update counters ---
        $this->filesWordCount += $wordCount;

        return ['word_count' => $wordCount, 'show_in_cattool' => $showInCattool];
    }

    /**
     * Compute a segment hash, incorporating original-data and sizeRestriction
     * when present to avoid collisions.
     */
    private function createSegmentHash(string $rawContent, ?array $dataRefMap = null, ?int $sizeRestriction = null): string
    {
        $segmentToBeHashed = $rawContent;

        if (!empty($dataRefMap)) {
            $dataRefReplacer   = new DataRefReplacer($dataRefMap);
            $segmentToBeHashed = $dataRefReplacer->replace($rawContent);
        }

        if (!empty($sizeRestriction)) {
            $segmentToBeHashed .= $segmentToBeHashed . '{"sizeRestriction": ' . $sizeRestriction . '}';
        }

        return md5($segmentToBeHashed);
    }

    /**
     * Read XLIFF file content from local filesystem or S3.
     *
     * @throws Exception
     */
    private function getXliffFileContent(string $xliff_file_content): false|string
    {
        if (AbstractFilesStorage::isOnS3()) {
            $s3Client = S3FilesStorage::getStaticS3Client();

            if ($s3Client->hasEncoder()) {
                $xliff_file_content = $s3Client->getEncoder()->decode($xliff_file_content);
            }

            return $s3Client->openItem(['bucket' => S3FilesStorage::getFilesStorageBucket(), 'key' => $xliff_file_content]);
        }

        return file_get_contents($xliff_file_content);
    }

    /**
     * Strip external tags from a segment.
     *
     * Currently disabled — always returns the segment unchanged with null prec/succ.
     */
    private function stripExternal(string $segment): array
    {
        // Definitely DISABLED
        return ['prec' => null, 'seg' => $segment, 'succ' => null];
    }

    /**
     * Add notes from a trans-unit to the projectStructure.
     *
     * @throws Exception
     */
    private function addNotesToProjectStructure(array $trans_unit, int $fid, ArrayObject $projectStructure): void
    {
        $internal_id = self::sanitizedUnitId($trans_unit['attr']['id'], $fid);
        if (isset($trans_unit['notes'])) {
            if (count($trans_unit['notes']) > self::SEGMENT_NOTES_LIMIT) {
                throw new Exception('File upload failed: a segment can have a maximum of ' . self::SEGMENT_NOTES_LIMIT . ' notes.', -44);
            }

            foreach ($trans_unit['notes'] as $note) {
                $this->initArrayObject('notes', $internal_id, $projectStructure);

                $noteKey     = null;
                $noteContent = null;

                if (isset($note['json'])) {
                    $noteContent = $note['json'];
                    $noteKey     = 'json';
                } elseif (isset($note['raw-content'])) {
                    $noteContent = $note['raw-content'];
                    $noteKey     = 'entries';
                }

                if (strlen($noteContent) > self::SEGMENT_NOTES_MAX_SIZE) {
                    throw new Exception(' you reached the maximum size for a single segment note (' . self::SEGMENT_NOTES_MAX_SIZE . ' bytes)');
                }

                if (!$projectStructure['notes'][$internal_id]->offsetExists('entries')) {
                    $projectStructure['notes'][$internal_id]->offsetSet('from', new ArrayObject());
                    $projectStructure['notes'][$internal_id]['from']->offsetSet('entries', new ArrayObject());
                    $projectStructure['notes'][$internal_id]['from']->offsetSet('json', new ArrayObject());
                    $projectStructure['notes'][$internal_id]->offsetSet('entries', new ArrayObject());
                    $projectStructure['notes'][$internal_id]->offsetSet('json', new ArrayObject());
                    $projectStructure['notes'][$internal_id]->offsetSet('json_segment_ids', []);
                    $projectStructure['notes'][$internal_id]->offsetSet('segment_ids', []);
                }

                $projectStructure['notes'][$internal_id][$noteKey]->append($noteContent);

                // import segments metadata from the `from` attribute
                if (isset($note['from'])) {
                    $projectStructure['notes'][$internal_id]['from'][$noteKey]->append($note['from']);
                } else {
                    $projectStructure['notes'][$internal_id]['from'][$noteKey]->append('NO_FROM');
                }
            }
        }
    }

    /**
     * Add context-group data from a trans-unit to the projectStructure.
     */
    private function addTUnitContextsToProjectStructure(array $trans_unit, int $fid, ArrayObject $projectStructure): void
    {
        $internal_id = self::sanitizedUnitId($trans_unit['attr']['id'], $fid);
        if (isset($trans_unit['context-group'])) {
            $this->initArrayObject('context-group', $internal_id, $projectStructure);

            if (!$projectStructure['context-group'][$internal_id]->offsetExists('context_json')) {
                $projectStructure['context-group'][$internal_id]->offsetSet('context_json', $trans_unit['context-group']);
                $projectStructure['context-group'][$internal_id]->offsetSet('context_json_segment_ids', []); // because of mrk tags, same context can be owned by different segments
            }
        }
    }

    /**
     * Initialize a nested ArrayObject in projectStructure if it does not already exist.
     */
    private function initArrayObject(string $key, string $id, ArrayObject $projectStructure): void
    {
        if (!$projectStructure[$key]->offsetExists($id)) {
            $projectStructure[$key]->offsetSet($id, new ArrayObject());
        }
    }

    /**
     * Decide if a source/target pair should be considered translated,
     * based on user-defined XLIFF rules.
     */
    private function isTranslated(
        ?string     $source,
        ?string     $target,
        ?int        $file_id,
        ?string     $state,
        ?string     $stateQualifier,
        ArrayObject $projectStructure,
    ): bool {
        /** @var XliffRulesModel $configModel */
        $configModel = $projectStructure['xliff_parameters'];
        $rule        = $configModel->getMatchingRule(
            $projectStructure['current-xliff-info'][$file_id]['version'],
            $state,
            $stateQualifier
        );

        return $rule->isTranslated($source, $target);
    }

    /**
     * Manage alternative translations (alt-trans) for a trans-unit.
     *
     * Sends matching alt-trans entries to the TM engine for each writable key.
     */
    private function manageAlternativeTranslations(array $xliff_trans_unit, ?array $xliff_file_attributes, ArrayObject $projectStructure): void
    {
        // Source and target language are mandatory, moreover do not set matches on public area
        if (
            !isset($xliff_trans_unit['alt-trans']) ||
            empty($xliff_file_attributes['source-language']) ||
            empty($xliff_file_attributes['target-language']) ||
            count($projectStructure['private_tm_key']) == 0 ||
            $this->features->filter('doNotManageAlternativeTranslations', true, $xliff_trans_unit, $xliff_file_attributes)
        ) {
            return;
        }

        // set the contribution for every key in the job belonging to the user
        $engine = EnginesFactory::getInstance(1);
        $config = $engine->getConfigStruct();

        if (count($projectStructure['private_tm_key']) != 0) {
            foreach ($projectStructure['private_tm_key'] as $tm_info) {
                if ($tm_info['w'] == 1) {
                    $config['id_user'][] = $tm_info['key'];
                }
            }
        }

        $config['source'] = $xliff_file_attributes['source-language'];
        $config['target'] = $xliff_file_attributes['target-language'];
        $config['email']  = AppConfig::$MYMEMORY_API_KEY;

        foreach ($xliff_trans_unit['alt-trans'] as $altTrans) {
            if (!empty($altTrans['attr']['match-quality']) && $altTrans['attr']['match-quality'] < '50') {
                continue;
            }

            $source_extract_external = '';

            // Wrong alt-trans tag
            if ((empty($xliff_trans_unit['source'] /* theoretically impossible empty source */) && empty($altTrans['source'])) || empty($altTrans['target'])) {
                continue;
            }

            if (!empty($xliff_trans_unit['source'])) {
                $source_extract_external = $this->stripExternal($xliff_trans_unit['source']['raw-content']); // WIP to remove function
            }

            // Override with the alt-trans source value
            if (!empty($altTrans['source'])) {
                $source_extract_external = $this->stripExternal($altTrans['source']); // WIP to remove function
            }

            $target_extract_external = $this->stripExternal($altTrans['target']); // WIP to remove function

            // wrong alt-trans content: source == target
            if ($source_extract_external['seg'] == $target_extract_external['seg']) {
                continue;
            }

            $config['segment']        = $this->filter->fromRawXliffToLayer0($this->filter->fromLayer0ToLayer1($source_extract_external['seg']));
            $config['translation']    = $this->filter->fromRawXliffToLayer0($this->filter->fromLayer0ToLayer1($target_extract_external['seg']));
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
