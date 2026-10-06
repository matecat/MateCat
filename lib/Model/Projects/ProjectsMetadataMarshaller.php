<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 20/01/26
 * Time: 17:53
 *
 */

namespace Model\Projects;

use Model\MTQE\Templates\DTO\MTQEWorkflowParams;
use Utils\Constants\TranslationStatus;

enum ProjectsMetadataMarshaller: string
{
    case MT_QUALITY_VALUE_IN_EDITOR = 'mt_quality_value_in_editor';
    case MT_EVALUATION = 'mt_evaluation';
    case MT_QE_WORKFLOW_ENABLED = 'mt_qe_workflow_enabled';
    case ICU_ENABLED = 'icu_enabled';
    case ENABLE_MT_ANALYSIS = 'enable_mt_analysis';
    case PRE_TRANSLATE_101 = 'pretranslate_101';
    case PRE_TRANSLATE_101_LOCK = 'pretranslate_101_lock';
    case PRE_TRANSLATE_100_LOCK = 'pretranslate_100_lock';
    case PRE_TRANSLATE_101_STATUS = 'pretranslate_101_status';
    case PRE_TRANSLATE_100_STATUS = 'pretranslate_100_status';
    case PROJECT_COMPLETION = 'project_completion';
    case MMT_ACTIVATE_CONTEXT_ANALYZER = 'mmt_activate_context_analyzer';
    case MMT_IGNORE_GLOSSARY_CASE = 'mmt_ignore_glossary_case';
    case FROM_API = 'from_api';
    case MT_QE_WORKFLOW_PARAMETERS = 'mt_qe_workflow_parameters';

    case FEATURES_KEY = 'features';
    case WORD_COUNT_TYPE_KEY = 'word_count_type';
    case WORD_COUNT_RAW = 'raw';
    case WORD_COUNT_EQUIVALENT = 'equivalent';
    case SPLIT_EQUIVALENT_WORD_TYPE = 'eq_word_count';
    case SPLIT_RAW_WORD_TYPE = 'raw_word_count';
    case SUBFILTERING_HANDLERS = 'subfiltering_handlers';
    case XLIFF_PARAMETERS = 'xliff_parameters';
    case FILTERS_EXTRACTION_PARAMETERS = 'filters_extraction_parameters';

    case MMT_GLOSSARIES = 'mmt_glossaries';
    case LARA_GLOSSARIES = 'lara_glossaries';
    case LARA_STYLE = 'lara_style';
    case LARA_STYLE_GUIDELINE_ID = 'lara_style_guideline_id';
    case INTENTO_ROUTING = 'intento_routing';
    case INTENTO_PROVIDER = 'intento_provider';
    case DEEPL_FORMALITY = 'deepl_formality';
    case DEEPL_ID_GLOSSARY = 'deepl_id_glossary';
    case DEEPL_ENGINE_TYPE = 'deepl_engine_type';

    case SEGMENTATION_RULE = 'segmentation_rule';
    case WPML = 'WPML';
    case CONTEXT_URL = 'context-url';

    /**
     * Statuses a pre-confirmed 100% or 101% match can be stored with,
     * accepted by the pretranslate_101_status and pretranslate_100_status options.
     *
     * @var list<string>
     */
    public const array PRE_TRANSLATE_STATUSES = [
        TranslationStatus::STATUS_TRANSLATED,
        TranslationStatus::STATUS_APPROVED,
        TranslationStatus::STATUS_APPROVED2,
    ];

    public static function unMarshall(MetadataStruct $struct): mixed
    {
        return (match ($struct->key) {
            ProjectsMetadataMarshaller::ICU_ENABLED->value,
            ProjectsMetadataMarshaller::MT_EVALUATION->value,
            ProjectsMetadataMarshaller::ENABLE_MT_ANALYSIS->value,
            ProjectsMetadataMarshaller::PRE_TRANSLATE_101->value,
            ProjectsMetadataMarshaller::PRE_TRANSLATE_101_LOCK->value,
            ProjectsMetadataMarshaller::PRE_TRANSLATE_100_LOCK->value,
            ProjectsMetadataMarshaller::PROJECT_COMPLETION->value,
            ProjectsMetadataMarshaller::MMT_ACTIVATE_CONTEXT_ANALYZER->value,
            ProjectsMetadataMarshaller::MMT_IGNORE_GLOSSARY_CASE->value,
            ProjectsMetadataMarshaller::FROM_API->value,
            ProjectsMetadataMarshaller::MT_QE_WORKFLOW_ENABLED->value,
            ProjectsMetadataMarshaller::WPML->value => fn() => (bool)$struct->value,
            ProjectsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value => fn() => (int)$struct->value,
            ProjectsMetadataMarshaller::MT_QE_WORKFLOW_PARAMETERS->value => fn() => new MTQEWorkflowParams(json_decode((string)$struct->value, true)),
            ProjectsMetadataMarshaller::MMT_GLOSSARIES->value,
            ProjectsMetadataMarshaller::SEGMENTATION_RULE->value,
            ProjectsMetadataMarshaller::LARA_STYLE->value,
            ProjectsMetadataMarshaller::LARA_STYLE_GUIDELINE_ID->value,
            ProjectsMetadataMarshaller::INTENTO_ROUTING->value,
            ProjectsMetadataMarshaller::INTENTO_PROVIDER->value,
            ProjectsMetadataMarshaller::DEEPL_FORMALITY->value,
            ProjectsMetadataMarshaller::DEEPL_ID_GLOSSARY->value,
            ProjectsMetadataMarshaller::DEEPL_ENGINE_TYPE->value,
            ProjectsMetadataMarshaller::PRE_TRANSLATE_101_STATUS->value,
            ProjectsMetadataMarshaller::PRE_TRANSLATE_100_STATUS->value,
            ProjectsMetadataMarshaller::CONTEXT_URL->value => fn() => (string)$struct->value,
            // backward compatibility, old projects could have JSON glossaries encoded as HTML entities
            ProjectsMetadataMarshaller::LARA_GLOSSARIES->value => fn() => json_decode(html_entity_decode((string)$struct->value), true),
            default => fn() => json_validate((string)$struct->value) ? json_decode((string)$struct->value, true) : (string)$struct->value,
        })();
    }

}
