<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 21/01/26
 * Time: 15:40
 *
 */

namespace Model\Jobs;

use Matecat\SubFiltering\Enum\InjectableFiltersTags;

enum JobsMetadataMarshaller: string
{
    /**
     * The MT application threshold a job falls back to when neither scope stores one.
     *
     * It used to be written out at each of the six sites that need it, and they disagreed: the
     * analysis defaulted to 85 and the editor to 86. Both are consumed as a penalty of
     * `100 - $value`, so the same unset setting was priced at 15 in the analysis and applied at 14
     * in the editor.
     */
    public const int DEFAULT_MT_QUALITY_VALUE = 85;

    case CHARACTER_COUNTER_COUNT_TAGS = 'character_counter_count_tags';
    case CHARACTER_COUNTER_MODE       = 'character_counter_mode';
    case DIALECT_STRICT               = 'dialect_strict';
    case MANDATORY_ISSUES             = 'mandatory_issues';
    case PUBLIC_TM_PENALTY            = 'public_tm_penalty';
    case SUBFILTERING_HANDLERS        = 'subfiltering_handlers';
    case TM_PRIORITIZATION            = 'tm_prioritization';

    /**
     * MT tuning settings. These used to live in project metadata only, which made them
     * immutable after project creation; they are written per job so the project owner can
     * change them later, and every read falls back to project metadata for projects created
     * before the move.
     *
     * @see \Model\Jobs\JobSettingsResolver
     */
    case MT_QUALITY_VALUE_IN_EDITOR   = 'mt_quality_value_in_editor';
    case DEEPL_FORMALITY              = 'deepl_formality';
    case DEEPL_ID_GLOSSARY            = 'deepl_id_glossary';
    case DEEPL_ENGINE_TYPE            = 'deepl_engine_type';
    case LARA_STYLE                   = 'lara_style';
    case LARA_STYLE_GUIDELINE_ID      = 'lara_style_guideline_id';
    case LARA_GLOSSARIES              = 'lara_glossaries';
    case MMT_GLOSSARIES               = 'mmt_glossaries';
    case MMT_IGNORE_GLOSSARY_CASE     = 'mmt_ignore_glossary_case';
    case INTENTO_ROUTING              = 'intento_routing';
    case INTENTO_PROVIDER             = 'intento_provider';

    /** The modes the character counter knows, as NewController and job_metadata.json accept them. */
    private const array CHARACTER_COUNTER_MODES = ['google_ads', 'exclude_cjk', 'all_one'];

    /** The revision phases an issue can be made mandatory for. */
    private const array MANDATORY_ISSUES_PHASES = ['r1', 'r2'];

    /** The MT option values job_metadata.json accepts. */
    private const array DEEPL_FORMALITIES  = ['default', 'prefer_less', 'prefer_more'];
    private const array DEEPL_ENGINE_TYPES = ['prefer_quality_optimized', 'latency_optimized'];
    private const array LARA_STYLES        = ['faithful', 'fluid', 'creative'];

    public static function unMarshall(MetadataStruct $struct): mixed
    {
        return (match ($struct->key) {
            JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value,
            JobsMetadataMarshaller::DIALECT_STRICT->value,
            JobsMetadataMarshaller::MMT_IGNORE_GLOSSARY_CASE->value,
            JobsMetadataMarshaller::TM_PRIORITIZATION->value => fn() => (bool)$struct->value,
            JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value,
            JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value => fn() => (int)$struct->value,
            // Scalar options, kept as raw strings. Mirrors ProjectsMetadataMarshaller so a value
            // resolved from either scope has the same PHP type.
            JobsMetadataMarshaller::LARA_STYLE->value,
            JobsMetadataMarshaller::LARA_STYLE_GUIDELINE_ID->value,
            JobsMetadataMarshaller::DEEPL_FORMALITY->value,
            JobsMetadataMarshaller::DEEPL_ID_GLOSSARY->value,
            JobsMetadataMarshaller::DEEPL_ENGINE_TYPE->value,
            JobsMetadataMarshaller::INTENTO_ROUTING->value,
            JobsMetadataMarshaller::INTENTO_PROVIDER->value => fn() => (string)$struct->value,
            // Both glossary lists decode to arrays, so a client reads the same shape whichever
            // engine it is looking at, and can post back what it just read.
            // html_entity_decode for backward compatibility: old projects stored the JSON HTML-entity
            // encoded, because both creation controllers run the request parameter through
            // FILTER_SANITIZE_SPECIAL_CHARS before validating it.
            JobsMetadataMarshaller::LARA_GLOSSARIES->value,
            JobsMetadataMarshaller::MMT_GLOSSARIES->value => fn() => json_decode(html_entity_decode((string)$struct->value), true),
            default => fn() => json_validate((string)$struct->value) ? json_decode((string)$struct->value, true) : (string)$struct->value,
        })();
    }

    /**
     * The MT tuning settings that are persisted per job and resolved with a project-metadata
     * fallback.
     *
     * `mmt_activate_context_analyzer` is deliberately absent: it is only consumed by
     * {@see \Utils\Engines\MMT::syncMemories()}, which runs once at project creation from a
     * project row and has no job context, so changing it afterwards would have no effect.
     *
     * @return list<string>
     */
    public static function mtSettings(): array
    {
        return [
            self::MT_QUALITY_VALUE_IN_EDITOR->value,
            self::DEEPL_FORMALITY->value,
            self::DEEPL_ID_GLOSSARY->value,
            self::DEEPL_ENGINE_TYPE->value,
            self::LARA_STYLE->value,
            self::LARA_STYLE_GUIDELINE_ID->value,
            self::LARA_GLOSSARIES->value,
            self::MMT_GLOSSARIES->value,
            self::MMT_IGNORE_GLOSSARY_CASE->value,
            self::INTENTO_ROUTING->value,
            self::INTENTO_PROVIDER->value,
        ];
    }

    /**
     * The opposite of unMarshall(): the string a typed value is stored as, or null when the value is
     * not one this key accepts.
     *
     * The rules are the ones the write paths enforce — job_metadata.json for the editor, the range
     * check in UpdateJobKeysController, the options NewController accepts at creation — so a value
     * that passes here is one that could have been written in the first place.
     */
    public function marshall(mixed $value): ?string
    {
        return match ($this) {
            self::CHARACTER_COUNTER_COUNT_TAGS,
            self::DIALECT_STRICT,
            self::MMT_IGNORE_GLOSSARY_CASE,
            self::TM_PRIORITIZATION => is_bool($value) ? ($value ? '1' : '0') : null,
            self::PUBLIC_TM_PENALTY,
            self::MT_QUALITY_VALUE_IN_EDITOR => is_int($value) && $value >= 0 && $value <= 100 ? (string)$value : null,
            self::CHARACTER_COUNTER_MODE => is_string($value) && in_array($value, self::CHARACTER_COUNTER_MODES, true) ? $value : null,
            self::DEEPL_FORMALITY => is_string($value) && in_array($value, self::DEEPL_FORMALITIES, true) ? $value : null,
            self::DEEPL_ENGINE_TYPE => is_string($value) && in_array($value, self::DEEPL_ENGINE_TYPES, true) ? $value : null,
            self::LARA_STYLE => is_string($value) && in_array($value, self::LARA_STYLES, true) ? $value : null,
            self::LARA_STYLE_GUIDELINE_ID => is_string($value) && $value !== '' && mb_strlen($value) <= 255 ? $value : null,
            // The empty string is the job scope's stored "explicitly none" for these three, so a copy
            // has to carry it: dropping it would re-inherit the project's value on the new chunk.
            self::DEEPL_ID_GLOSSARY,
            self::INTENTO_ROUTING,
            self::INTENTO_PROVIDER => is_string($value) && mb_strlen($value) <= 255 ? $value : null,
            // An empty list is an answer too: "no glossary here" over a project that has one.
            self::LARA_GLOSSARIES => self::marshallList($value, fn(mixed $id): bool => is_string($id) && $id !== ''),
            self::MMT_GLOSSARIES => self::marshallList($value, fn(mixed $id): bool => is_int($id) && $id >= 1),
            self::MANDATORY_ISSUES => self::marshallList($value, fn(mixed $phase): bool => in_array($phase, self::MANDATORY_ISSUES_PHASES, true)),
            // A JSON null stores "every handler disabled", and '' and 'none' are the two spellings
            // subfiltering_handlers.json lets the editor send for the same thing. They are kept as
            // they are: a reader decodes each of them to null.
            self::SUBFILTERING_HANDLERS => match (true) {
                $value === null => 'null',
                $value === '', $value === 'none' => $value,
                default => self::marshallList($value, fn(mixed $tag): bool => is_string($tag) && InjectableFiltersTags::tryFrom($tag) !== null),
            },
        };
    }

    /**
     * Keep only the rows of a raw `key => value` map that are job metadata, each re-written in its
     * canonical form. This is what a copy goes through, so it carries what the marshaller vouches for
     * and not merely whatever the table holds.
     *
     * Two checks per row. The key has to be a case of this enum: anything else — MMT's mt_context, a
     * key a feature has since stopped reading — is not job configuration and stays where it is. The
     * value has to survive unMarshall() and marshall() back. unMarshall() alone is too lenient for
     * that: its casts turn any string into a boolean or an integer, so the scalar keys are checked on
     * the raw string first.
     *
     * @param array<string, string> $raw
     *
     * @return array<string, string>
     */
    public static function sanitizeRawMap(array $raw): array
    {
        $sanitized = [];

        foreach ($raw as $key => $rawValue) {
            $case = self::tryFrom($key);

            if ($case === null || !$case->acceptsRaw($rawValue)) {
                continue;
            }

            $value = $case->marshall(self::unMarshall(new MetadataStruct(['key' => $key, 'value' => $rawValue])));

            if ($value !== null) {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Whether the stored string is one unMarshall() reads without guessing. A boolean is stored as
     * '1' or '0', or as '' when the editor sent false through a string parameter; a penalty is a
     * plain non-negative integer. The other keys are JSON or a plain word, which marshall() checks
     * once decoded.
     */
    private function acceptsRaw(string $raw): bool
    {
        return match ($this) {
            self::CHARACTER_COUNTER_COUNT_TAGS,
            self::DIALECT_STRICT,
            self::MMT_IGNORE_GLOSSARY_CASE,
            self::TM_PRIORITIZATION => in_array($raw, ['1', '0', ''], true),
            self::PUBLIC_TM_PENALTY,
            self::MT_QUALITY_VALUE_IN_EDITOR => preg_match('/^\d{1,3}$/', $raw) === 1,
            default => true,
        };
    }

    /**
     * @param callable(mixed): bool $isValidItem
     */
    private static function marshallList(mixed $value, callable $isValidItem): ?string
    {
        if (!is_array($value) || !array_is_list($value)) {
            return null;
        }

        foreach ($value as $item) {
            if (!$isValidItem($item)) {
                return null;
            }
        }

        $encoded = json_encode($value);

        return $encoded === false ? null : $encoded;
    }

}
