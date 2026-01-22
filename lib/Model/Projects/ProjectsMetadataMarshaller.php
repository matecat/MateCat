<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 20/01/26
 * Time: 17:53
 *
 */

namespace Model\Projects;

enum ProjectsMetadataMarshaller: string
{
    case MT_QUALITY_VALUE_IN_EDITOR = 'mt_quality_value_in_editor';
    case MT_EVALUATION = 'mt_evaluation';
    case MT_QE_WORKFLOW_ENABLED = 'mt_qe_workflow_enabled';
    case ICU_ENABLED = 'icu_enabled';
    case ENABLE_MT_ANALYSIS = 'enable_mt_analysis';
    case PRE_TRANSLATE_101 = 'pretranslate_101';
    case PROJECT_COMPLETION = 'project_completion';
    case MMT_ACTIVATE_CONTEXT_ANALYZER = 'mmt_activate_context_analyzer';
    case MMT_IGNORE_GLOSSARY_CASE = 'mmt_ignore_glossary_case';
    case FROM_API = 'from_api';

    public static function unMarshall(MetadataStruct $struct): mixed
    {
        return (match ($struct->key) {
            ProjectsMetadataMarshaller::ICU_ENABLED->value,
            ProjectsMetadataMarshaller::MT_EVALUATION->value,
            ProjectsMetadataMarshaller::ENABLE_MT_ANALYSIS->value,
            ProjectsMetadataMarshaller::PRE_TRANSLATE_101->value,
            ProjectsMetadataMarshaller::PROJECT_COMPLETION->value,
            ProjectsMetadataMarshaller::MMT_ACTIVATE_CONTEXT_ANALYZER->value,
            ProjectsMetadataMarshaller::MMT_IGNORE_GLOSSARY_CASE->value,
            ProjectsMetadataMarshaller::FROM_API->value,
            ProjectsMetadataMarshaller::MT_QE_WORKFLOW_ENABLED->value => fn() => (bool)$struct->value,
            ProjectsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value => fn() => (int)$struct->value,
            default => fn() => json_validate((string)$struct->value) ? json_decode((string)$struct->value, true) : (string)$struct->value,
        })();
    }

}