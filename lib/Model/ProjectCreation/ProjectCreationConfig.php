<?php

namespace Model\ProjectCreation;

use ArrayObject;

/**
 * Typed DTO for project-creation configuration.
 *
 * Holds the **INIT-ONLY** keys that controllers set on `$projectStructure`
 * before calling {@see ProjectManager::createProject()}.  Every property
 * is `readonly` because the creation pipeline never modifies these values.
 *
 * Keys that mutate a mid-pipeline (e.g. `tm_keys`, `xliff_parameters`,
 * `status`, `team`, `array_files`) are deliberately excluded and remain
 * in the mutable `$projectStructure` ArrayObject for now.
 *
 * The {@see toArrayObject()} method produces a snapshot compatible with
 * the FeatureSet hooks that receive the full `$projectStructure`.
 *
 * @see \Model\JobSplitMerge\SplitMergeProjectData  Phase-1 DTO (split/merge path)
 */
class ProjectCreationConfig
{
    // ── Identity ────────────────────────────────────────────────────

    public readonly ?int    $idProject;
    public readonly ?string $ppassword;
    public readonly ?int    $uid;
    public readonly string  $idCustomer;
    public readonly string  $owner;
    public readonly bool    $userIsLogged;
    public readonly string  $createDate;
    public readonly int     $instanceId;

    // ── Project metadata ────────────────────────────────────────────

    public readonly ?string $projectName;
    public readonly ?string $sourceLanguage;
    /** @var string[]|null */
    public readonly ?array  $targetLanguage;
    public readonly string  $jobSubject;
    public readonly ?string $dueDate;
    /** @var array<string, mixed> */
    public readonly array   $metadata;
    /** @var string[]|null  Instructions per file (V1 API only). */
    public readonly ?array  $instructions;

    // ── TM / MT configuration ───────────────────────────────────────

    public readonly ?int   $mtEngine;
    public readonly ?int   $tmsEngine;
    public readonly mixed  $privateTmKey;
    public readonly int    $pretranslate100;
    public readonly int    $pretranslate101;
    public readonly int    $onlyPrivate;
    public readonly ?string $tmPrioritization;
    /** @var array<string, int>  target-language => MT engine ID */
    public readonly array  $targetLanguageMtEngineAssociation;
    public readonly mixed  $publicTmPenalty;

    // ── Upload / environment ────────────────────────────────────────

    public readonly ?string $uploadToken;
    public readonly ?string $userIp;
    public readonly ?string $httpHost;

    // ── Options / flags ─────────────────────────────────────────────

    public readonly bool    $sanitizeProjectOptions;
    public readonly ?bool   $fromApi;
    public readonly ?string $dialectStrict;
    public readonly ?string $characterCounterMode;
    public readonly ?bool   $characterCounterCountTags;

    // ── QA / payable rates ──────────────────────────────────────────

    public readonly mixed  $qaModelTemplate;
    public readonly mixed  $qaModel;
    public readonly ?int   $payableRateModelId;
    public readonly mixed  $payableRateModel;
    public readonly mixed  $mtQeWorkflowPayableRate;

    // ── Filters / XLIFF ─────────────────────────────────────────────

    public readonly mixed  $filtersExtractionParameters;
    public readonly ?string $subfilteringHandlers;

    // ── Dynamic engine configuration ────────────────────────────────

    public readonly ?string $mmtGlossaries;
    public readonly ?string $deeplFormality;
    public readonly ?string $deeplIdGlossary;
    public readonly ?string $deeplEngineType;
    public readonly ?string $laraGlossaries;
    public readonly ?string $laraStyle;
    public readonly ?string $intentoRouting;
    public readonly ?string $intentoProvider;
    public readonly ?string $enableMtAnalysis;
    public readonly ?string $mmtActivateContextAnalyzer;

    /**
     * Catch-all for engine configuration params not covered by explicit
     * properties (e.g., future engine-specific keys from
     * {@see \Engines_AbstractEngine::getConfigurationParameters()}).
     *
     * @var array<string, mixed>
     */
    public readonly array  $dynamicEngineParams;

    // ── Constructor ─────────────────────────────────────────────────

    /**
     * Build from the raw `$projectStructure` ArrayObject.
     *
     * This is the primary factory path during Phase 2: the ProjectManager
     * constructor extracts a `ProjectCreationConfig` from the existing
     * ArrayObject while keeping backward compatibility.
     *
     * @param ArrayObject<string, mixed> $ps
     */
    public static function fromArrayObject(ArrayObject $ps): self
    {
        return new self(
            idProject:    isset($ps['id_project']) ? (int) $ps['id_project'] : null,
            ppassword:    $ps['ppassword'] ?? null,
            uid:          isset($ps['uid']) ? (int) $ps['uid'] : null,
            idCustomer:   (string) ($ps['id_customer'] ?? 'translated_user'),
            owner:        (string) ($ps['owner'] ?? ''),
            userIsLogged: (bool) ($ps['userIsLogged'] ?? false),
            createDate:   (string) ($ps['create_date'] ?? date('Y-m-d H:i:s')),
            instanceId:   (int) ($ps['instance_id'] ?? 0),

            projectName:    $ps['project_name'] ?? null,
            sourceLanguage: $ps['source_language'] ?? null,
            targetLanguage: isset($ps['target_language'])
                ? array_values(is_array($ps['target_language']) ? $ps['target_language'] : (array) $ps['target_language'])
                : null,
            jobSubject:     (string) ($ps['job_subject'] ?? 'general'),
            dueDate:        $ps['due_date'] ?? null,
            metadata:       isset($ps['metadata'])
                ? (is_array($ps['metadata']) ? $ps['metadata'] : (array) $ps['metadata'])
                : [],
            instructions:   $ps['instructions'] ?? null,

            mtEngine:   isset($ps['mt_engine']) ? (int) $ps['mt_engine'] : null,
            tmsEngine:  isset($ps['tms_engine']) ? (int) $ps['tms_engine'] : null,
            privateTmKey: $ps['private_tm_key'] ?? 0,
            pretranslate100: (int) ($ps['pretranslate_100'] ?? 0),
            pretranslate101: (int) ($ps['pretranslate_101'] ?? 1),
            onlyPrivate:     (int) ($ps['only_private'] ?? 0),
            tmPrioritization: $ps['tm_prioritization'] ?? null,
            targetLanguageMtEngineAssociation: isset($ps['target_language_mt_engine_association'])
                ? (is_array($ps['target_language_mt_engine_association'])
                    ? $ps['target_language_mt_engine_association']
                    : (array) $ps['target_language_mt_engine_association'])
                : [],
            publicTmPenalty: $ps['public_tm_penalty'] ?? null,

            uploadToken: $ps['uploadToken'] ?? null,
            userIp:      $ps['user_ip'] ?? null,
            httpHost:    $ps['HTTP_HOST'] ?? null,

            sanitizeProjectOptions: (bool) ($ps['sanitize_project_options'] ?? true),
            fromApi:                $ps['from_api'] ?? null,
            dialectStrict:          $ps['dialect_strict'] ?? null,
            characterCounterMode:      $ps['character_counter_mode'] ?? null,
            characterCounterCountTags: isset($ps['character_counter_count_tags'])
                ? (bool) $ps['character_counter_count_tags']
                : null,

            qaModelTemplate:          $ps['qa_model_template'] ?? null,
            qaModel:                  $ps['qa_model'] ?? null,
            payableRateModelId:       isset($ps['payable_rate_model_id']) ? (int) $ps['payable_rate_model_id'] : null,
            payableRateModel:         $ps['payable_rate_model'] ?? null,
            mtQeWorkflowPayableRate:  $ps['mt_qe_workflow_payable_rate'] ?? null,

            filtersExtractionParameters: $ps['filters_extraction_parameters'] ?? null,
            subfilteringHandlers:        $ps['subfiltering_handlers'] ?? null,

            mmtGlossaries:             $ps['mmt_glossaries'] ?? null,
            deeplFormality:            $ps['deepl_formality'] ?? null,
            deeplIdGlossary:           $ps['deepl_id_glossary'] ?? null,
            deeplEngineType:           $ps['deepl_engine_type'] ?? null,
            laraGlossaries:            $ps['lara_glossaries'] ?? null,
            laraStyle:                 $ps['lara_style'] ?? null,
            intentoRouting:            $ps['intento_routing'] ?? null,
            intentoProvider:           $ps['intento_provider'] ?? null,
            enableMtAnalysis:          $ps['enable_mt_analysis'] ?? null,
            mmtActivateContextAnalyzer: $ps['mmt_activate_context_analyzer'] ?? null,
        );
    }

    /**
     * @param list<string>|null         $targetLanguage
     * @param array<string, mixed>      $metadata
     * @param array<string, mixed>|null $instructions
     * @param array<string, int>        $targetLanguageMtEngineAssociation
     * @param array<string, mixed>      $dynamicEngineParams
     */
    public function __construct(
        // Identity
        ?int    $idProject    = null,
        ?string $ppassword    = null,
        ?int    $uid          = null,
        string  $idCustomer   = 'translated_user',
        string  $owner        = '',
        bool    $userIsLogged = false,
        string  $createDate   = '',
        int     $instanceId   = 0,

        // Project metadata
        ?string $projectName    = null,
        ?string $sourceLanguage = null,
        ?array  $targetLanguage = null,
        string  $jobSubject     = 'general',
        ?string $dueDate        = null,
        array   $metadata       = [],
        ?array  $instructions   = null,

        // TM/MT configuration
        ?int    $mtEngine   = null,
        ?int    $tmsEngine  = null,
        mixed   $privateTmKey = 0,
        int     $pretranslate100 = 0,
        int     $pretranslate101 = 1,
        int     $onlyPrivate     = 0,
        ?string $tmPrioritization = null,
        array   $targetLanguageMtEngineAssociation = [],
        mixed   $publicTmPenalty = null,

        // Upload / environment
        ?string $uploadToken = null,
        ?string $userIp      = null,
        ?string $httpHost    = null,

        // Options / flags
        bool    $sanitizeProjectOptions = true,
        ?bool   $fromApi      = null,
        ?string $dialectStrict = null,
        ?string $characterCounterMode      = null,
        ?bool   $characterCounterCountTags = null,

        // QA / payable rates
        mixed   $qaModelTemplate     = null,
        mixed   $qaModel             = null,
        ?int    $payableRateModelId  = null,
        mixed   $payableRateModel    = null,
        mixed   $mtQeWorkflowPayableRate = null,

        // Filters / XLIFF
        mixed   $filtersExtractionParameters = null,
        ?string $subfilteringHandlers = null,

        // Dynamic engine configuration
        ?string $mmtGlossaries             = null,
        ?string $deeplFormality            = null,
        ?string $deeplIdGlossary           = null,
        ?string $deeplEngineType           = null,
        ?string $laraGlossaries            = null,
        ?string $laraStyle                 = null,
        ?string $intentoRouting            = null,
        ?string $intentoProvider           = null,
        ?string $enableMtAnalysis          = null,
        ?string $mmtActivateContextAnalyzer = null,

        // Catch-all for unknown engine params
        array   $dynamicEngineParams = [],
    ) {
        $this->idProject    = $idProject;
        $this->ppassword    = $ppassword;
        $this->uid          = $uid;
        $this->idCustomer   = $idCustomer;
        $this->owner        = $owner;
        $this->userIsLogged = $userIsLogged;
        $this->createDate   = $createDate;
        $this->instanceId   = $instanceId;

        $this->projectName    = $projectName;
        $this->sourceLanguage = $sourceLanguage;
        $this->targetLanguage = $targetLanguage;
        $this->jobSubject     = $jobSubject;
        $this->dueDate        = $dueDate;
        $this->metadata       = $metadata;
        $this->instructions   = $instructions;

        $this->mtEngine   = $mtEngine;
        $this->tmsEngine  = $tmsEngine;
        $this->privateTmKey = $privateTmKey;
        $this->pretranslate100 = $pretranslate100;
        $this->pretranslate101 = $pretranslate101;
        $this->onlyPrivate     = $onlyPrivate;
        $this->tmPrioritization = $tmPrioritization;
        $this->targetLanguageMtEngineAssociation = $targetLanguageMtEngineAssociation;
        $this->publicTmPenalty = $publicTmPenalty;

        $this->uploadToken = $uploadToken;
        $this->userIp      = $userIp;
        $this->httpHost    = $httpHost;

        $this->sanitizeProjectOptions = $sanitizeProjectOptions;
        $this->fromApi      = $fromApi;
        $this->dialectStrict = $dialectStrict;
        $this->characterCounterMode      = $characterCounterMode;
        $this->characterCounterCountTags = $characterCounterCountTags;

        $this->qaModelTemplate     = $qaModelTemplate;
        $this->qaModel             = $qaModel;
        $this->payableRateModelId  = $payableRateModelId;
        $this->payableRateModel    = $payableRateModel;
        $this->mtQeWorkflowPayableRate = $mtQeWorkflowPayableRate;

        $this->filtersExtractionParameters = $filtersExtractionParameters;
        $this->subfilteringHandlers = $subfilteringHandlers;

        $this->mmtGlossaries             = $mmtGlossaries;
        $this->deeplFormality            = $deeplFormality;
        $this->deeplIdGlossary           = $deeplIdGlossary;
        $this->deeplEngineType           = $deeplEngineType;
        $this->laraGlossaries            = $laraGlossaries;
        $this->laraStyle                 = $laraStyle;
        $this->intentoRouting            = $intentoRouting;
        $this->intentoProvider           = $intentoProvider;
        $this->enableMtAnalysis          = $enableMtAnalysis;
        $this->mmtActivateContextAnalyzer = $mmtActivateContextAnalyzer;

        $this->dynamicEngineParams = $dynamicEngineParams;
    }

    // ── Backward compatibility ──────────────────────────────────────

    /**
     * Look up a single engine configuration value by its original
     * underscore_case key (e.g. `'mmt_glossaries'`, `'deepl_formality'`).
     *
     * Returns the value from the matching explicit property, or from
     * `$dynamicEngineParams` if not one of the ten known keys.
     * Returns null when the key is not found.
     */
    public function getEngineConfigValue(string $key): mixed
    {
        static $keyMap = null;
        if ($keyMap === null) {
            $keyMap = [
                'mmt_glossaries'               => 'mmtGlossaries',
                'deepl_formality'              => 'deeplFormality',
                'deepl_id_glossary'            => 'deeplIdGlossary',
                'deepl_engine_type'            => 'deeplEngineType',
                'lara_glossaries'              => 'laraGlossaries',
                'lara_style'                   => 'laraStyle',
                'intento_routing'              => 'intentoRouting',
                'intento_provider'             => 'intentoProvider',
                'enable_mt_analysis'           => 'enableMtAnalysis',
                'mmt_activate_context_analyzer' => 'mmtActivateContextAnalyzer',
            ];
        }

        if (isset($keyMap[$key])) {
            return $this->{$keyMap[$key]};
        }

        return $this->dynamicEngineParams[$key] ?? null;
    }

    /**
     * Produce an ArrayObject snapshot using the original underscore_case
     * keys that `$projectStructure` used. Suitable for FeatureSet hooks.
     *
     * Note: this does NOT include pipeline-write or mutable keys (segments,
     * translations, result, array_jobs, etc.). Those remain in the main
     * `$projectStructure` ArrayObject.
     *
     * @return ArrayObject<string, mixed>
     */
    public function toArrayObject(): ArrayObject
    {
        $data = [
            'id_project'        => $this->idProject,
            'ppassword'         => $this->ppassword,
            'uid'               => $this->uid,
            'id_customer'       => $this->idCustomer,
            'owner'             => $this->owner,
            'userIsLogged'      => $this->userIsLogged,
            'create_date'       => $this->createDate,
            'instance_id'       => $this->instanceId,

            'project_name'      => $this->projectName,
            'source_language'   => $this->sourceLanguage,
            'target_language'   => $this->targetLanguage,
            'job_subject'       => $this->jobSubject,
            'due_date'          => $this->dueDate,
            'metadata'          => $this->metadata,
            'instructions'      => $this->instructions,

            'mt_engine'         => $this->mtEngine,
            'tms_engine'        => $this->tmsEngine,
            'private_tm_key'    => $this->privateTmKey,
            'pretranslate_100'  => $this->pretranslate100,
            'pretranslate_101'  => $this->pretranslate101,
            'only_private'      => $this->onlyPrivate,
            'tm_prioritization' => $this->tmPrioritization,
            'target_language_mt_engine_association' => $this->targetLanguageMtEngineAssociation,
            'public_tm_penalty' => $this->publicTmPenalty,

            'uploadToken'       => $this->uploadToken,
            'user_ip'           => $this->userIp,
            'HTTP_HOST'         => $this->httpHost,

            'sanitize_project_options' => $this->sanitizeProjectOptions,
            'from_api'                 => $this->fromApi,
            'dialect_strict'           => $this->dialectStrict,
            'character_counter_mode'       => $this->characterCounterMode,
            'character_counter_count_tags' => $this->characterCounterCountTags,

            'qa_model_template'            => $this->qaModelTemplate,
            'qa_model'                     => $this->qaModel,
            'payable_rate_model_id'        => $this->payableRateModelId,
            'payable_rate_model'           => $this->payableRateModel,
            'mt_qe_workflow_payable_rate'  => $this->mtQeWorkflowPayableRate,

            'filters_extraction_parameters' => $this->filtersExtractionParameters,
            'subfiltering_handlers'         => $this->subfilteringHandlers,

            'mmt_glossaries'               => $this->mmtGlossaries,
            'deepl_formality'              => $this->deeplFormality,
            'deepl_id_glossary'            => $this->deeplIdGlossary,
            'deepl_engine_type'            => $this->deeplEngineType,
            'lara_glossaries'              => $this->laraGlossaries,
            'lara_style'                   => $this->laraStyle,
            'intento_routing'              => $this->intentoRouting,
            'intento_provider'             => $this->intentoProvider,
            'enable_mt_analysis'           => $this->enableMtAnalysis,
            'mmt_activate_context_analyzer' => $this->mmtActivateContextAnalyzer,
        ];

        // Merge any catch-all engine params
        foreach ($this->dynamicEngineParams as $key => $value) {
            $data[$key] = $value;
        }

        return new ArrayObject($data);
    }
}
