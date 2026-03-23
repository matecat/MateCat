<?php

namespace Model\ProjectCreation;

use JsonSerializable;
use Model\DataAccess\AbstractDaoObjectStruct;
use Model\Projects\ProjectsMetadataMarshaller;
use Model\Xliff\DTO\XliffRulesModel;

/**
 * Typed, closed-schema DTO used as the canonical state container for project creation.
 *
 * It carries the full lifecycle data for the pipeline: validated input, mutable
 * processing state, per-file transient data, and final creation results.
 *
 * By extending {@see AbstractDaoObjectStruct}, it enforces strict property access:
 * only declared public fields are valid, and unknown keys fail fast to prevent
 * silent state drift.
 *
 * Implements {@see JsonSerializable} to expose a stable array payload for queue
 * transport, persistence, and API responses.
 */
class ProjectStructure extends AbstractDaoObjectStruct implements JsonSerializable
{

    // ── Group A: Init-only keys (49 keys) ───────────────────────────

    // Identity
    public ?int $id_project = null;
    public ?string $ppassword = null;
    public ?int $uid = null;
    public string $id_customer = 'translated_user';
    public string $owner = '';
    public bool $userIsLogged = false;
    public string $create_date = '';
    public int $instance_id = 0;

    // Project metadata
    public ?string $project_name = null;
    public ?string $source_language = null;
    /** @var string[]|null */
    public ?array $target_language = null;
    public string $job_subject = 'general';
    public ?string $due_date = null;
    /** @var array<string, mixed> */
    public array $metadata = [];
    /** @var string[]|null */
    public ?array $instructions = null;

    // TM/MT configuration
    public ?int $mt_engine = null;
    public ?int $tms_engine = null;
    /** @var array<int, array<string, mixed>> */
    public array $private_tm_key = [];
    public int $pretranslate_100 = 0;
    public int $pretranslate_101 = 1;
    public int $only_private = 0;
    public ?string $tm_prioritization = null;
    /** @var array<string, int> */
    public array $target_language_mt_engine_association = [];
    public mixed $public_tm_penalty = null;

    // Upload / environment
    public ?string $uploadToken = null;
    public ?string $user_ip = null;
    public ?string $HTTP_HOST = null;

    // Options / flags
    public ?bool $from_api = null;
    public ?string $dialect_strict = null;
    public ?string $character_counter_mode = null;
    public ?bool $character_counter_count_tags = null;

    // QA / payable rates
    public mixed $qa_model_template = null;
    public mixed $qa_model = null;
    public ?int $payable_rate_model_id = null;
    public mixed $payable_rate_model = null;
    public mixed $mt_qe_workflow_payable_rate = null;

    // Filters / XLIFF
    public mixed $filters_extraction_parameters = null;
    public ?string $subfiltering_handlers = null;

    // Dynamic engine configuration (known keys declared explicitly)
    public ?string $mmt_glossaries = null;
    public ?string $deepl_formality = null;
    public ?string $deepl_id_glossary = null;
    public ?string $deepl_engine_type = null;
    public ?string $lara_glossaries = null;
    public ?string $lara_style = null;
    public ?string $intento_routing = null;
    public ?string $intento_provider = null;
    public ?string $enable_mt_analysis = null;
    public ?string $mmt_activate_context_analyzer = null;
    public ?bool $mmt_ignore_glossary_case = null;

    // ── Group B: Mutable pipeline keys (10 keys) ────────────────────

    public ?string $status = null;
    public ?int $id_team = null;
    public mixed $team = null;
    public ?int $id_assignee = null;
    /** @var array<int, mixed> */
    public array $tm_keys = [];
    /** @var XliffRulesModel|array<string, mixed>|mixed */
    public mixed $xliff_parameters = [];
    /** @var array<string, mixed>|null */
    public mixed $session = null;
    /** @var array<string, mixed> */
    public array $project_features = [];
    public ?bool $mt_evaluation = null;
    public int $standard_word_count = 0;

    // ── Group C: Per-file transient state (14 keys) ─────────────────

    /** @var array<int, array<int, mixed>> */
    public array $segments = [];
    /** @var array<int, array<int, mixed>> */
    public array $segments_original_data = [];
    /** @var array<int, mixed> */
    public array $segments_metadata = [];
    /** @var array<int, array<int, mixed>> */
    public array $segments_meta_data = [];
    /** @var array<string, array<int|string, TranslationTuple>> */
    public array $translations = [];
    /** @var array<int|string, array<string, mixed>> */
    public array $notes = [];
    /** @var array<int|string, array<string, mixed>> */
    public array $context_group = [];
    /** @var string[] */
    public array $array_files = [];
    /** @var array<int, array<string, mixed>> */
    public array $array_files_meta = [];
    /** @var array<int, int> */
    public array $file_id_list = [];
    /** @var array<int|string, int> */
    public array $file_segments_count = [];
    /** @var array<int, array<string, mixed>> */
    public array $current_xliff_info = [];

    // ── Group D: Output / result keys (nested structures) ───────────

    /** @var array{errors: array<int, mixed>, data: array<string, mixed>|string} */
    public array $result = ['errors' => [], 'data' => []];

    /** @var array{job_list: array<mixed>, job_pass: array<mixed>, job_segments: array<mixed>, job_languages: array<mixed>, payable_rates: array<mixed>} */
    public array $array_jobs = [
        'job_list' => [],
        'job_pass' => [],
        'job_segments' => [],
        'job_languages' => [],
        'payable_rates' => [],
    ];

    /** @var array<int, array{min_seg: int, max_seg: int}> */
    public array $job_segments = [];

    // ── Group E: Word count metadata key ────────────────────────────

    public string $word_count_type = ProjectsMetadataMarshaller::WORD_COUNT_RAW->value;

    // ── Group F: Split/merge keys ───────────────────────────────────

    public ?int $job_to_split = null;
    public ?string $job_to_split_pass = null;
    public mixed $split_result = null;
    public ?int $job_to_merge = null;

    // ── Plugin / revision pipeline keys ─────────────────────────────

    /** @var array<string, mixed> Features collected during quality framework validation (e.g. quality_framework) */
    public array $features = [];
    public bool $create_2_pass_review = false;

    // ── Error recording ────────────────────────────────────────────

    /**
     * Append a structured error entry to {@see $result}['errors'].
     */
    public function addError(int $code, string $message): void
    {
        $this->result['errors'][] = [
            'code'    => $code,
            'message' => $message,
        ];
    }

    // ── Serialization ───────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
