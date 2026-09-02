<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 21/01/26
 * Time: 15:40
 *
 */

namespace Model\Jobs;

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

    public static function unMarshall(MetadataStruct $struct): mixed
    {
        return (match ($struct->key) {
            JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value,
            JobsMetadataMarshaller::DIALECT_STRICT->value,
            JobsMetadataMarshaller::MMT_IGNORE_GLOSSARY_CASE->value,
            JobsMetadataMarshaller::TM_PRIORITIZATION->value => fn() => (bool)$struct->value,
            JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value,
            JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value => fn() => (int)$struct->value,
            // Kept as raw strings: the engines decode `mmt_glossaries` themselves, and the
            // remaining keys are scalar options. Mirrors ProjectsMetadataMarshaller so a value
            // resolved from either scope has the same PHP type.
            JobsMetadataMarshaller::MMT_GLOSSARIES->value,
            JobsMetadataMarshaller::LARA_STYLE->value,
            JobsMetadataMarshaller::LARA_STYLE_GUIDELINE_ID->value,
            JobsMetadataMarshaller::DEEPL_FORMALITY->value,
            JobsMetadataMarshaller::DEEPL_ID_GLOSSARY->value,
            JobsMetadataMarshaller::DEEPL_ENGINE_TYPE->value,
            JobsMetadataMarshaller::INTENTO_ROUTING->value,
            JobsMetadataMarshaller::INTENTO_PROVIDER->value => fn() => (string)$struct->value,
            // backward compatibility, old projects could have JSON glossaries encoded as HTML entities
            JobsMetadataMarshaller::LARA_GLOSSARIES->value => fn() => json_decode(html_entity_decode((string)$struct->value), true),
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
     * Keys duplicated onto every new chunk when a job is split, and dropped from every chunk
     * but the first when jobs are merged.
     *
     * Job metadata is keyed by (id_job, password), so a key missing from this list silently
     * disappears from the new chunks.
     *
     * Every key {@see \Model\ProjectCreation\JobCreationService::saveJobsMetadata()} writes at
     * creation belongs here — the four below were written but never propagated, so a split dropped
     * them and the chunks fell back to the code defaults.
     *
     * @return list<string>
     */
    public static function propagatedOnSplit(): array
    {
        return array_merge(
            [
                self::CHARACTER_COUNTER_COUNT_TAGS->value,
                self::CHARACTER_COUNTER_MODE->value,
                self::SUBFILTERING_HANDLERS->value,
                self::DIALECT_STRICT->value,
                self::MANDATORY_ISSUES->value,
                self::PUBLIC_TM_PENALTY->value,
                self::TM_PRIORITIZATION->value,
            ],
            self::mtSettings()
        );
    }

}
