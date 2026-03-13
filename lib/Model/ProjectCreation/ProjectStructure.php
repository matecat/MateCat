<?php

namespace Model\ProjectCreation;

use ArrayAccess;
use ArrayObject;
use DomainException;
use JsonSerializable;
use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\ArrayAccessTrait;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use Model\Xliff\DTO\XliffRulesModel;

/**
 * Typed DTO replacing {@see \Model\DataAccess\RecursiveArrayObject} as the
 * single data bus for the project creation pipeline.
 *
 * Extends {@see AbstractDaoObjectStruct} for strict property enforcement
 * (throws {@see DomainException} on unknown direct property access) and
 * implements {@see ArrayAccess} via {@see ArrayAccessTrait} so existing
 * `$projectStructure['key']` syntax continues working unchanged.
 *
 * Unknown keys written via array-access syntax are stored in a private
 * overflow map ({@see $dynamicProperties}) — this supports the dynamic
 * engine configuration loop in controllers.
 *
 * @implements ArrayAccess<string, mixed>
 */
class ProjectStructure extends AbstractDaoObjectStruct implements ArrayAccess, JsonSerializable
{
    use ArrayAccessTrait {
        ArrayAccessTrait::offsetSet as private traitOffsetSet;
        ArrayAccessTrait::offsetGet as private traitOffsetGet;
        ArrayAccessTrait::offsetExists as private traitOffsetExists;
    }

    // ── Group A: Init-only keys (49 keys) ───────────────────────────

    // Identity
    public ?int    $id_project = null;
    public ?string $ppassword = null;
    public ?int    $uid = null;
    public string  $id_customer = 'translated_user';
    public string  $owner = '';
    public bool    $userIsLogged = false;
    public string  $create_date = '';
    public int     $instance_id = 0;

    // Project metadata
    public ?string $project_name = null;
    public ?string $source_language = null;
    /** @var string[]|null */
    public ?array  $target_language = null;
    public string  $job_subject = 'general';
    public ?string $due_date = null;
    /** @var ArrayObject<string, mixed>|array<string, mixed> */
    public ArrayObject|array $metadata = [];
    /** @var string[]|null */
    public ?array  $instructions = null;

    // TM/MT configuration
    public ?int    $mt_engine = null;
    public ?int    $tms_engine = null;
    public mixed   $private_tm_key = 0;
    public int     $pretranslate_100 = 0;
    public int     $pretranslate_101 = 1;
    public int     $only_private = 0;
    public ?string $tm_prioritization = null;
    /** @var array<string, int> */
    public array   $target_language_mt_engine_association = [];
    public mixed   $public_tm_penalty = null;

    // Upload / environment
    public ?string $uploadToken = null;
    public ?string $user_ip = null;
    public ?string $HTTP_HOST = null;

    // Options / flags
    public bool    $sanitize_project_options = true;
    public ?bool   $from_api = null;
    public ?string $dialect_strict = null;
    public ?string $character_counter_mode = null;
    public ?bool   $character_counter_count_tags = null;

    // QA / payable rates
    public mixed   $qa_model_template = null;
    public mixed   $qa_model = null;
    public ?int    $payable_rate_model_id = null;
    public mixed   $payable_rate_model = null;
    public mixed   $mt_qe_workflow_payable_rate = null;

    // Filters / XLIFF
    public mixed   $filters_extraction_parameters = null;
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
    public ?bool   $mmt_ignore_glossary_case = null;

    // ── Group B: Mutable pipeline keys (10 keys) ────────────────────

    public ?string $status = null;
    public ?int    $id_team = null;
    public mixed   $team = null;
    public ?int    $id_assignee = null;
    /** @var array<int, mixed> */
    public array   $tm_keys = [];
    /** @var XliffRulesModel|ArrayObject<string, mixed>|array<string, mixed>|mixed */
    public mixed $xliff_parameters = [];
    /** @var array<string, mixed>|null */
    public mixed   $session = null;
    /** @var array<string, mixed> */
    public array   $project_features = [];
    public ?bool   $mt_evaluation = null;
    public int     $standard_word_count = 0;

    // ── Group C: Per-file transient state (14 keys) ─────────────────
    //
    // The first 9 properties below are typed ArrayObject|array because
    // SegmentExtractor::extract() and SegmentStorageService assign
    // ArrayObject instances at runtime (with nested ArrayObject children).
    // They default to [] so freshly constructed DTOs serialise cleanly.
    // A future refactoring may convert the pipeline to plain arrays.

    /** @var ArrayObject<int, ArrayObject<int, mixed>>|array<int, array<int, mixed>> */
    public ArrayObject|array $segments = [];
    /** @var ArrayObject<int, ArrayObject<int, mixed>>|array<int, array<int, mixed>> */
    public ArrayObject|array $segments_original_data = [];
    /** @var ArrayObject<int, mixed>|array<int, mixed> */
    public ArrayObject|array $segments_metadata = [];
    /** @var ArrayObject<int, ArrayObject<int, mixed>>|array<int, array<int, mixed>> */
    public ArrayObject|array $segments_meta_data = [];
    /** @var ArrayObject<int, mixed>|array<int, mixed> */
    public ArrayObject|array $file_part_id = [];
    /** @var ArrayObject<int, mixed>|array<int, mixed> */
    public ArrayObject|array $file_metadata = [];
    /** @var ArrayObject<int, mixed>|array<int, mixed> */
    public ArrayObject|array $translations = [];
    /** @var ArrayObject<int, mixed>|array<int, mixed> */
    public ArrayObject|array $notes = [];
    /** @var ArrayObject<int, mixed>|array<int, mixed> */
    public ArrayObject|array $context_group = [];
    /** @var string[] */
    public array   $array_files = [];
    /** @var array<int, array<string, mixed>> */
    public array   $array_files_meta = [];
    /** @var array<int, int> */
    public array   $file_id_list = [];
    /** @var array<int|string, int> */
    public array   $file_segments_count = [];
    /** @var array<int|string, mixed>|null */
    public ?array  $current_xliff_info = null;

    // ── Group D: Output / result keys (nested structures) ───────────

    /** @var array{errors: array<int, mixed>, data: array<string, mixed>|string} */
    public array   $result = ['errors' => [], 'data' => []];

    /** @var array{job_list: array<mixed>, job_pass: array<mixed>, job_segments: array<mixed>, job_languages: array<mixed>, payable_rates: array<mixed>} */
    public array   $array_jobs = [
        'job_list'      => [],
        'job_pass'      => [],
        'job_segments'  => [],
        'job_languages' => [],
        'payable_rates' => [],
    ];

    /** @var array<int, array{min_seg: int, max_seg: int}> */
    public array   $job_segments = [];

    // ── Group E: Word count metadata key ────────────────────────────

    public string  $word_count_type = ProjectsMetadataDao::WORD_COUNT_RAW;

    // ── Group F: Split/merge keys ───────────────────────────────────

    public ?int    $job_to_split = null;
    public ?string $job_to_split_pass = null;
    public mixed   $split_result = null;
    public ?int    $job_to_merge = null;

    // ── Group G: Dead code candidates ───────────────────────────────

    public ?string $dictation = null;
    public ?string $show_whitespace = null;
    public ?string $character_counter = null;
    public ?string $ai_assistant = null;

    // ── Dynamic properties overflow map ─────────────────────────────

    /**
     * Stores unknown keys written via array-access syntax.
     * Used by the dynamic engine configuration loop in controllers
     * and for backward-compatible keys like `private_tm_user`/`private_tm_pass`.
     *
     * @var array<string, mixed>
     */
    private array $dynamicProperties = [];

    // ── Constructor ─────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $array_params
     */
    public function __construct(array $array_params = [])
    {
        foreach ($array_params as $property => $value) {
            if (property_exists($this, $property)) {
                $this->$property = $value;
            } else {
                $this->dynamicProperties[$property] = $value;
            }
        }
    }

    // ── ArrayAccess overrides (overflow map support) ────────────────

    /**
     * Sets a value via array-access syntax.
     * Known properties are set directly; unknown keys go to the overflow map.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (property_exists($this, (string) $offset)) {
            $this->$offset = $value;
        } else {
            $this->dynamicProperties[(string) $offset] = $value;
        }
    }

    /**
     * Gets a value via array-access syntax.
     * Known properties are returned directly; unknown keys are looked up
     * in the overflow map.
     *
     * @throws DomainException if the key is not found anywhere
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (property_exists($this, (string) $offset)) {
            return $this->$offset;
        }
        if (array_key_exists((string) $offset, $this->dynamicProperties)) {
            return $this->dynamicProperties[(string) $offset];
        }

        throw new DomainException('Unknown property ' . $offset);
    }

    /**
     * Checks if a key exists via array-access syntax.
     * Checks both declared properties and the overflow map.
     */
    public function offsetExists(mixed $offset): bool
    {
        return property_exists($this, (string) $offset)
            || array_key_exists((string) $offset, $this->dynamicProperties);
    }

    // ── Serialization ───────────────────────────────────────────────

    /**
     * Extends the base toArray() to include dynamic properties.
     *
     * @param string[]|null $mask
     * @return array<string, mixed>
     */
    public function toArray(array $mask = null, object $class = null): array
    {
        $base = parent::toArray($mask, $class);

        return array_merge($base, $this->dynamicProperties);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
