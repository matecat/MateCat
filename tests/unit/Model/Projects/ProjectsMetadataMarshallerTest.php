<?php

namespace unit\Model\Projects;

use Model\MTQE\Templates\DTO\MTQEWorkflowParams;
use Model\Projects\MetadataStruct;
use Model\Projects\ProjectsMetadataMarshaller;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

/**
 * Tests for {@see ProjectsMetadataMarshaller} enum.
 *
 * Covers:
 * - All enum case string values
 * - {@see ProjectsMetadataMarshaller::unMarshall()} for every match branch
 * - Edge cases in the default (JSON / plain string) branch
 */
class ProjectsMetadataMarshallerTest extends AbstractTest
{
    // =========================================================================
    // Enum case values
    // =========================================================================

    #[Test]
    public function enumHasExactlyTwentyCases(): void
    {
        $this->assertCount(20, ProjectsMetadataMarshaller::cases());
    }

    #[Test]
    #[DataProvider('enumCaseValueProvider')]
    public function enumCaseHasExpectedStringValue(ProjectsMetadataMarshaller $case, string $expectedValue): void
    {
        $this->assertSame($expectedValue, $case->value);
    }

    public static function enumCaseValueProvider(): array
    {
        return [
            'MT_QUALITY_VALUE_IN_EDITOR'    => [ProjectsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR, 'mt_quality_value_in_editor'],
            'MT_EVALUATION'                 => [ProjectsMetadataMarshaller::MT_EVALUATION, 'mt_evaluation'],
            'MT_QE_WORKFLOW_ENABLED'        => [ProjectsMetadataMarshaller::MT_QE_WORKFLOW_ENABLED, 'mt_qe_workflow_enabled'],
            'ICU_ENABLED'                   => [ProjectsMetadataMarshaller::ICU_ENABLED, 'icu_enabled'],
            'ENABLE_MT_ANALYSIS'            => [ProjectsMetadataMarshaller::ENABLE_MT_ANALYSIS, 'enable_mt_analysis'],
            'PRE_TRANSLATE_101'             => [ProjectsMetadataMarshaller::PRE_TRANSLATE_101, 'pretranslate_101'],
            'PROJECT_COMPLETION'            => [ProjectsMetadataMarshaller::PROJECT_COMPLETION, 'project_completion'],
            'MMT_ACTIVATE_CONTEXT_ANALYZER' => [ProjectsMetadataMarshaller::MMT_ACTIVATE_CONTEXT_ANALYZER, 'mmt_activate_context_analyzer'],
            'MMT_IGNORE_GLOSSARY_CASE'      => [ProjectsMetadataMarshaller::MMT_IGNORE_GLOSSARY_CASE, 'mmt_ignore_glossary_case'],
            'FROM_API'                      => [ProjectsMetadataMarshaller::FROM_API, 'from_api'],
            'MT_QE_WORKFLOW_PARAMETERS'     => [ProjectsMetadataMarshaller::MT_QE_WORKFLOW_PARAMETERS, 'mt_qe_workflow_parameters'],
            'FEATURES_KEY'                  => [ProjectsMetadataMarshaller::FEATURES_KEY, 'features'],
            'WORD_COUNT_TYPE_KEY'           => [ProjectsMetadataMarshaller::WORD_COUNT_TYPE_KEY, 'word_count_type'],
            'WORD_COUNT_RAW'               => [ProjectsMetadataMarshaller::WORD_COUNT_RAW, 'raw'],
            'WORD_COUNT_EQUIVALENT'         => [ProjectsMetadataMarshaller::WORD_COUNT_EQUIVALENT, 'equivalent'],
            'SPLIT_EQUIVALENT_WORD_TYPE'    => [ProjectsMetadataMarshaller::SPLIT_EQUIVALENT_WORD_TYPE, 'eq_word_count'],
            'SPLIT_RAW_WORD_TYPE'           => [ProjectsMetadataMarshaller::SPLIT_RAW_WORD_TYPE, 'raw_word_count'],
            'SUBFILTERING_HANDLERS'         => [ProjectsMetadataMarshaller::SUBFILTERING_HANDLERS, 'subfiltering_handlers'],
            'XLIFF_PARAMETERS'              => [ProjectsMetadataMarshaller::XLIFF_PARAMETERS, 'xliff_parameters'],
            'FILTERS_EXTRACTION_PARAMETERS' => [ProjectsMetadataMarshaller::FILTERS_EXTRACTION_PARAMETERS, 'filters_extraction_parameters'],
        ];
    }

    #[Test]
    public function enumIsBackedByString(): void
    {
        $case = ProjectsMetadataMarshaller::from('icu_enabled');
        $this->assertSame(ProjectsMetadataMarshaller::ICU_ENABLED, $case);
    }

    #[Test]
    public function tryFromReturnsNullForUnknownValue(): void
    {
        $this->assertNull(ProjectsMetadataMarshaller::tryFrom('nonexistent_key'));
    }

    // =========================================================================
    // unMarshall -- boolean branch (all 9 boolean keys)
    // =========================================================================

    #[Test]
    #[DataProvider('booleanKeyTruthyProvider')]
    public function unMarshallBooleanKeyWithTruthyValueReturnsTrue(string $key, mixed $rawValue): void
    {
        $result = ProjectsMetadataMarshaller::unMarshall($this->makeStruct($key, $rawValue));
        $this->assertTrue($result);
    }

    #[Test]
    #[DataProvider('booleanKeyFalsyProvider')]
    public function unMarshallBooleanKeyWithFalsyValueReturnsFalse(string $key, mixed $rawValue): void
    {
        $result = ProjectsMetadataMarshaller::unMarshall($this->makeStruct($key, $rawValue));
        $this->assertFalse($result);
    }

    public static function booleanKeyTruthyProvider(): array
    {
        $keys = [
            'icu_enabled',
            'mt_evaluation',
            'enable_mt_analysis',
            'pretranslate_101',
            'project_completion',
            'mmt_activate_context_analyzer',
            'mmt_ignore_glossary_case',
            'from_api',
            'mt_qe_workflow_enabled',
        ];

        $truthyValues = [
            'string 1'   => '1',
            'int 1'      => 1,
            'string yes' => 'yes',
            'true'       => true,
            'int 42'     => 42,
        ];

        $cases = [];
        foreach ($keys as $key) {
            foreach ($truthyValues as $label => $value) {
                $cases["$key / $label"] = [$key, $value];
            }
        }

        return $cases;
    }

    public static function booleanKeyFalsyProvider(): array
    {
        $keys = [
            'icu_enabled',
            'mt_evaluation',
            'enable_mt_analysis',
            'pretranslate_101',
            'project_completion',
            'mmt_activate_context_analyzer',
            'mmt_ignore_glossary_case',
            'from_api',
            'mt_qe_workflow_enabled',
        ];

        $falsyValues = [
            'string 0'     => '0',
            'int 0'        => 0,
            'empty string' => '',
            'null'         => null,
            'false'        => false,
        ];

        $cases = [];
        foreach ($keys as $key) {
            foreach ($falsyValues as $label => $value) {
                $cases["$key / $label"] = [$key, $value];
            }
        }

        return $cases;
    }

    // =========================================================================
    // unMarshall -- integer branch (MT_QUALITY_VALUE_IN_EDITOR)
    // =========================================================================

    #[Test]
    #[DataProvider('integerCastProvider')]
    public function unMarshallMtQualityValueInEditorCastsToInt(mixed $rawValue, int $expected): void
    {
        $result = ProjectsMetadataMarshaller::unMarshall($this->makeStruct('mt_quality_value_in_editor', $rawValue));
        $this->assertSame($expected, $result);
    }

    public static function integerCastProvider(): array
    {
        return [
            'string 10'    => ['10', 10],
            'string 0'     => ['0', 0],
            'int 25'       => [25, 25],
            'float 3.7'    => [3.7, 3],
            'string -5'    => ['-5', -5],
            'empty string' => ['', 0],
            'null'         => [null, 0],
            'true'         => [true, 1],
            'false'        => [false, 0],
        ];
    }

    // =========================================================================
    // unMarshall -- MTQEWorkflowParams branch (MT_QE_WORKFLOW_PARAMETERS)
    // =========================================================================

    #[Test]
    public function unMarshallMtQeWorkflowParametersReturnsInstance(): void
    {
        $params = [
            'analysis_ignore_100'           => true,
            'analysis_ignore_101'           => true,
            'confirm_best_quality_mt'       => false,
            'lock_best_quality_mt'          => true,
            'best_quality_mt_analysis_status' => 'TRANSLATED',
            'qe_model_version'              => 2,
        ];
        $json   = json_encode($params);
        $result = ProjectsMetadataMarshaller::unMarshall($this->makeStruct('mt_qe_workflow_parameters', $json));

        $this->assertInstanceOf(MTQEWorkflowParams::class, $result);
        $this->assertTrue($result->analysis_ignore_100);
        $this->assertTrue($result->analysis_ignore_101);
        $this->assertFalse($result->confirm_best_quality_mt);
        $this->assertTrue($result->lock_best_quality_mt);
        $this->assertSame('TRANSLATED', $result->best_quality_mt_analysis_status);
        $this->assertSame(2, $result->qe_model_version);
    }

    #[Test]
    public function unMarshallMtQeWorkflowParametersWithDefaultsReturnsInstanceWithDefaults(): void
    {
        $json   = json_encode([]);
        $result = ProjectsMetadataMarshaller::unMarshall($this->makeStruct('mt_qe_workflow_parameters', $json));

        $this->assertInstanceOf(MTQEWorkflowParams::class, $result);
        $this->assertFalse($result->analysis_ignore_100);
        $this->assertFalse($result->analysis_ignore_101);
        $this->assertTrue($result->confirm_best_quality_mt);
        $this->assertFalse($result->lock_best_quality_mt);
        $this->assertSame('APPROVED', $result->best_quality_mt_analysis_status);
        $this->assertSame(3, $result->qe_model_version);
    }

    // =========================================================================
    // unMarshall -- default branch: valid JSON -> decoded
    // =========================================================================

    #[Test]
    public function unMarshallSubfilteringHandlersDecodesJsonArray(): void
    {
        $json   = json_encode([['handler' => 'xliff']]);
        $result = ProjectsMetadataMarshaller::unMarshall($this->makeStruct('subfiltering_handlers', $json));
        $this->assertSame([['handler' => 'xliff']], $result);
    }

    #[Test]
    public function unMarshallSubfilteringHandlersDecodesEmptyJsonArray(): void
    {
        $result = ProjectsMetadataMarshaller::unMarshall($this->makeStruct('subfiltering_handlers', '[]'));
        $this->assertSame([], $result);
    }

    #[Test]
    public function unMarshallFeaturesKeyDecodesJsonObject(): void
    {
        $json   = json_encode(['feature_a' => true, 'feature_b' => false]);
        $result = ProjectsMetadataMarshaller::unMarshall($this->makeStruct('features', $json));
        $this->assertSame(['feature_a' => true, 'feature_b' => false], $result);
    }

    #[Test]
    public function unMarshallXliffParametersDecodesJsonObject(): void
    {
        $json   = json_encode(['param1' => 'value1', 'nested' => ['a' => 1]]);
        $result = ProjectsMetadataMarshaller::unMarshall($this->makeStruct('xliff_parameters', $json));
        $this->assertSame(['param1' => 'value1', 'nested' => ['a' => 1]], $result);
    }

    #[Test]
    public function unMarshallFiltersExtractionParametersDecodesJsonObject(): void
    {
        $json   = json_encode(['extraction' => 'config']);
        $result = ProjectsMetadataMarshaller::unMarshall($this->makeStruct('filters_extraction_parameters', $json));
        $this->assertSame(['extraction' => 'config'], $result);
    }

    #[Test]
    public function unMarshallDefaultBranchDecodesJsonNull(): void
    {
        $result = ProjectsMetadataMarshaller::unMarshall($this->makeStruct('word_count_type', 'null'));
        $this->assertNull($result);
    }

    #[Test]
    public function unMarshallDefaultBranchDecodesJsonBoolean(): void
    {
        $result = ProjectsMetadataMarshaller::unMarshall($this->makeStruct('word_count_type', 'true'));
        $this->assertTrue($result);
    }

    #[Test]
    public function unMarshallDefaultBranchDecodesJsonNumber(): void
    {
        $result = ProjectsMetadataMarshaller::unMarshall($this->makeStruct('eq_word_count', '42'));
        $this->assertSame(42, $result);
    }

    // =========================================================================
    // unMarshall -- default branch: invalid JSON -> plain string
    // =========================================================================

    #[Test]
    public function unMarshallDefaultBranchReturnsPlainStringForInvalidJson(): void
    {
        $result = ProjectsMetadataMarshaller::unMarshall($this->makeStruct('word_count_type', 'raw'));
        $this->assertSame('raw', $result);
    }

    #[Test]
    public function unMarshallDefaultBranchReturnsEmptyStringForEmptyValue(): void
    {
        $result = ProjectsMetadataMarshaller::unMarshall($this->makeStruct('features', ''));
        $this->assertSame('', $result);
    }

    #[Test]
    public function unMarshallDefaultBranchCastsNullToEmptyString(): void
    {
        $result = ProjectsMetadataMarshaller::unMarshall($this->makeStruct('features', null));
        // null cast to string is '', which is not valid JSON -> returns ''
        $this->assertSame('', $result);
    }

    #[Test]
    public function unMarshallDefaultBranchCastsIntToString(): void
    {
        // An int value in the default branch: (string)123 = '123', which is valid JSON
        $result = ProjectsMetadataMarshaller::unMarshall($this->makeStruct('raw_word_count', 123));
        $this->assertSame(123, $result);
    }

    // =========================================================================
    // unMarshall -- unknown key falls into default branch
    // =========================================================================

    #[Test]
    public function unMarshallUnknownKeyWithValidJsonDecodesIt(): void
    {
        $json   = json_encode(['foo' => 'bar']);
        $result = ProjectsMetadataMarshaller::unMarshall($this->makeStruct('some_unknown_key', $json));
        $this->assertSame(['foo' => 'bar'], $result);
    }

    #[Test]
    public function unMarshallUnknownKeyWithPlainStringReturnsString(): void
    {
        $result = ProjectsMetadataMarshaller::unMarshall($this->makeStruct('some_unknown_key', 'plain text'));
        $this->assertSame('plain text', $result);
    }

    // =========================================================================
    // Helper
    // =========================================================================

    private function makeStruct(string $key, mixed $value): MetadataStruct
    {
        $struct             = new MetadataStruct();
        $struct->id_project = 1;
        $struct->key        = $key;
        $struct->value      = $value;

        return $struct;
    }
}
