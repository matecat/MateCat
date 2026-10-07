<?php

namespace Model\Projects;

use DateMalformedStringException;
use InvalidArgumentException;
use DateTime;
use JsonSerializable;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;
use Model\Jobs\JobsMetadataMarshaller;
use Utils\Constants\TranslationStatus;
use stdClass;
use TypeError;
use Utils\Validation\UserSuppliedName;

/**
 * @phpstan-type HydrationInput object{
 *     id?: int|null,
 *     uid?: int,
 *     name: string,
 *     is_default?: bool,
 *     id_team: int,
 *     segmentation_rule?: object|null,
 *     pretranslate: object{
 *         match_101: object{enabled: bool, status?: string, lock?: bool},
 *         match_100: object{enabled: bool, status?: string, lock?: bool},
 *     },
 *     tm_prioritization?: bool,
 *     dialect_strict?: bool|null,
 *     public_tm_penalty?: int,
 *     get_public_matches: bool,
 *     mt?: mixed,
 *     tm?: list<object>|null,
 *     payable_rate_template_id?: int|null,
 *     qa_model_template_id?: int|null,
 *     filters_template_id?: int|null,
 *     xliff_config_template_id?: int|null,
 *     character_counter_count_tags?: bool,
 *     character_counter_mode?: string|null,
 *     subject?: string|null,
 *     subfiltering_handlers?: mixed,
 *     source_language?: string|null,
 *     target_language?: list<string>|null,
 *     mt_quality_value_in_editor?: int|null,
 *     icu_enabled?: bool,
 *     mandatory_issues?: list<string>|null,
 * }
 */
class ProjectTemplateStruct extends AbstractDaoSilentStruct implements IDaoStruct, JsonSerializable
{
    public ?int $id = null;
    public string $name = "";
    public bool $is_default = false;
    public int $uid = 0;
    public int $id_team = 0;
    public bool $tag_projection = true;
    public ?string $segmentation_rule = null;
    public ?string $mt = null;
    public ?string $tm = null;
    public int $public_tm_penalty = 0;
    public int $payable_rate_template_id = 0;
    public int $qa_model_template_id = 0;
    public int $filters_template_id = 0;
    public int $xliff_config_template_id = 0;
    public bool $pretranslate_100 = false;
    public bool $pretranslate_101 = true;
    public bool $tm_prioritization = false;
    public bool $dialect_strict = false;
    public bool $get_public_matches = true;
    public string $created_at;
    public ?string $modified_at = null;
    public ?string $subject = null;
    public ?string $source_language = null;
    public ?string $target_language = null;
    public bool $character_counter_count_tags = false;
    public ?string $character_counter_mode = null;
    public ?string $subfiltering_handlers = null;
    public ?int $mt_quality_value_in_editor = null;
    public bool $icu_enabled = true;
    public ?string $mandatory_issues = null;
    public bool $pretranslate_101_lock = true;
    public bool $pretranslate_100_lock = false;
    public string $pretranslate_101_status = TranslationStatus::STATUS_APPROVED;
    public string $pretranslate_100_status = TranslationStatus::STATUS_TRANSLATED;

    /**
     * @phpstan-param HydrationInput $decodedObject
     * @param int $uid
     * @param int|null $id
     *
     * @return $this
     * @throws InvalidArgumentException when the name is empty or will not fit the column
     * @throws TypeError
     */
    public function hydrateFromJSON(object $decodedObject, int $uid, ?int $id = null): ProjectTemplateStruct
    {
        $this->id = $decodedObject->id ?? $id;
        $this->uid = $decodedObject->uid ?? $uid;
        // Here rather than in the controller, so create and update are covered by one call: both
        // hydrate through this method. The schema bounds the length, but only the composed form
        // lets UNIQUE(uid, name) see a clash between two spellings of the same name.
        $this->name = UserSuppliedName::validated($decodedObject->name, 'name', UserSuppliedName::TEMPLATE_NAME_MAX_LENGTH);
        $this->is_default = (isset($decodedObject->is_default)) ? $decodedObject->is_default : false;
        $this->id_team = $decodedObject->id_team;
        $this->segmentation_rule = (!empty($decodedObject->segmentation_rule)) ? (json_encode($decodedObject->segmentation_rule) ?: null) : null;
        $this->tm_prioritization = $decodedObject->tm_prioritization ?? false;
        $this->dialect_strict = $decodedObject->dialect_strict ?? false;
        $this->public_tm_penalty = $decodedObject->public_tm_penalty ?? 0;
        $this->get_public_matches = $decodedObject->get_public_matches;
        // json_encode(null) is the string "null", which getMt() would decode back to null
        $this->mt = isset($decodedObject->mt) ? (json_encode($decodedObject->mt) ?: null) : null;
        $this->tm = (!empty($decodedObject->tm)) ? (json_encode($decodedObject->tm) ?: null) : null;
        $this->payable_rate_template_id = $decodedObject->payable_rate_template_id ?? 0;
        $this->qa_model_template_id = $decodedObject->qa_model_template_id ?? 0;
        $this->filters_template_id = $decodedObject->filters_template_id ?? 0;
        $this->xliff_config_template_id = $decodedObject->xliff_config_template_id ?? 0;
        $this->character_counter_count_tags = $decodedObject->character_counter_count_tags ?? false;
        $this->character_counter_mode = $decodedObject->character_counter_mode ?? null;
        $this->subject = $decodedObject->subject ?? null;
        $this->subfiltering_handlers = json_encode($decodedObject->subfiltering_handlers ?? null) ?: null;
        $this->source_language = $decodedObject->source_language ?? null;
        $this->target_language = (!empty($decodedObject->target_language)) ? serialize($decodedObject->target_language) : null;
        $this->mt_quality_value_in_editor = (!empty($decodedObject->mt_quality_value_in_editor)) ? (int)$decodedObject->mt_quality_value_in_editor : null;
        $this->icu_enabled = $decodedObject->icu_enabled ?? true;
        $this->mandatory_issues = (($decodedObject->mandatory_issues ?? null) !== null) ? (json_encode($decodedObject->mandatory_issues) ?: null) : null;

        // The JSON groups the pre-confirm options by match; the struct keeps one flat property per column.
        $match101 = $decodedObject->pretranslate->match_101;
        $match100 = $decodedObject->pretranslate->match_100;
        $this->pretranslate_101 = $match101->enabled;
        $this->pretranslate_101_status = $match101->status ?? TranslationStatus::STATUS_APPROVED;
        $this->pretranslate_101_lock = $match101->lock ?? true;
        $this->pretranslate_100 = $match100->enabled;
        $this->pretranslate_100_status = $match100->status ?? TranslationStatus::STATUS_TRANSLATED;
        $this->pretranslate_100_lock = $match100->lock ?? false;

        return $this;
    }

    /**
     * @return object
     */
    public function getSegmentationRule(): object
    {
        if (!empty($this->segmentation_rule)) {
            return json_decode($this->segmentation_rule);
        }

        return new stdClass();
    }

    /**
     * @return object
     */
    public function getMt(): object
    {
        if (!empty($this->mt)) {
            return json_decode($this->mt);
        }

        return new stdClass();
    }

    /**
     * @return list<mixed>
     */
    public function getTm(): array
    {
        if (!empty($this->tm)) {
            return json_decode($this->tm);
        }

        return [];
    }

    /**
     * @return list<string>
     */
    public function getTargetLanguage(): array
    {
        if (empty($this->target_language)) {
            return [];
        }

        return unserialize($this->target_language, ['allowed_classes' => false]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSubfilteringHandlers(): ?array
    {
        if (!empty($this->subfiltering_handlers)) {
            return json_decode($this->subfiltering_handlers, true);
        }

        return [];
    }

    /**
     * @return array<string>|null
     */
    public function getMandatoryIssues(): ?array
    {
        if (!empty($this->mandatory_issues)) {
            return json_decode($this->mandatory_issues, true);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     * @throws DateMalformedStringException
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => (int)$this->id,
            'name' => $this->name,
            'is_default' => $this->is_default,
            'uid' => $this->uid,
            'id_team' => $this->id_team,
            'segmentation_rule' => $this->getSegmentationRule(),
            'mt' => $this->getMt(),
            'tm' => $this->getTm(),
            'payable_rate_template_id' => $this->payable_rate_template_id ?: 0,
            'qa_model_template_id' => $this->qa_model_template_id ?: 0,
            'filters_template_id' => $this->filters_template_id ?: 0,
            'xliff_config_template_id' => $this->xliff_config_template_id ?: 0,
            'get_public_matches' => $this->get_public_matches,
            'public_tm_penalty' => $this->public_tm_penalty ?: 0,
            'pretranslate' => [
                'match_101' => [
                    'enabled' => $this->pretranslate_101,
                    'status' => $this->pretranslate_101_status,
                    'lock' => $this->pretranslate_101_lock,
                ],
                'match_100' => [
                    'enabled' => $this->pretranslate_100,
                    'status' => $this->pretranslate_100_status,
                    'lock' => $this->pretranslate_100_lock,
                ],
            ],
            'tm_prioritization' => $this->tm_prioritization,
            'dialect_strict' => $this->dialect_strict,
            'mt_quality_value_in_editor' => $this->mt_quality_value_in_editor,
            'character_counter_count_tags' => $this->character_counter_count_tags,
            'character_counter_mode' => $this->character_counter_mode,
            'subject' => $this->subject,
            JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value => $this->getSubfilteringHandlers(),
            'source_language' => $this->source_language,
            'target_language' => $this->getTargetLanguage(),
            'created_at' => (new DateTime($this->created_at))->format(DATE_RFC822),
            'modified_at' => $this->modified_at !== null ? (new DateTime($this->modified_at))->format(DATE_RFC822) : null,
            'icu_enabled' => $this->icu_enabled,
            'mandatory_issues' => $this->getMandatoryIssues(),
        ];
    }
}