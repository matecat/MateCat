<?php

namespace Matecat\Core\Model\Jobs;

use Matecat\TestHelpers\AbstractTest;
use Model\Jobs\JobsMetadataMarshaller;
use Model\Jobs\MetadataStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Utils\Constants\EngineConstants;

/**
 * Tests for {@see JobsMetadataMarshaller} enum.
 *
 * Covers:
 * - All enum case string values
 * - {@see JobsMetadataMarshaller::unMarshall()} for every match branch
 * - Edge cases in the default (JSON / plain string) branch
 */
class JobsMetadataMarshallerTest extends AbstractTest
{
    // =========================================================================
    // Enum case values
    // =========================================================================

    #[Test]
    public function enumHasOneCasePerKnownKey(): void
    {
        // Seven job-only keys plus the eleven MT settings that moved off the project.
        $this->assertCount(18, JobsMetadataMarshaller::cases());
    }

    #[Test]
    #[DataProvider('enumCaseValueProvider')]
    public function enumCaseHasExpectedStringValue(JobsMetadataMarshaller $case, string $expectedValue): void
    {
        $this->assertSame($expectedValue, $case->value);
    }

    public static function enumCaseValueProvider(): array
    {
        return [
            'CHARACTER_COUNTER_COUNT_TAGS' => [JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS, 'character_counter_count_tags'],
            'CHARACTER_COUNTER_MODE'       => [JobsMetadataMarshaller::CHARACTER_COUNTER_MODE, 'character_counter_mode'],
            'DIALECT_STRICT'               => [JobsMetadataMarshaller::DIALECT_STRICT, 'dialect_strict'],
            'PUBLIC_TM_PENALTY'            => [JobsMetadataMarshaller::PUBLIC_TM_PENALTY, 'public_tm_penalty'],
            'SUBFILTERING_HANDLERS'        => [JobsMetadataMarshaller::SUBFILTERING_HANDLERS, 'subfiltering_handlers'],
            'TM_PRIORITIZATION'            => [JobsMetadataMarshaller::TM_PRIORITIZATION, 'tm_prioritization'],
            'MT_QUALITY_VALUE_IN_EDITOR'   => [JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR, 'mt_quality_value_in_editor'],
            'DEEPL_FORMALITY'              => [JobsMetadataMarshaller::DEEPL_FORMALITY, 'deepl_formality'],
            'DEEPL_ID_GLOSSARY'            => [JobsMetadataMarshaller::DEEPL_ID_GLOSSARY, 'deepl_id_glossary'],
            'DEEPL_ENGINE_TYPE'            => [JobsMetadataMarshaller::DEEPL_ENGINE_TYPE, 'deepl_engine_type'],
            'LARA_STYLE'                   => [JobsMetadataMarshaller::LARA_STYLE, 'lara_style'],
            'LARA_STYLE_GUIDELINE_ID'      => [JobsMetadataMarshaller::LARA_STYLE_GUIDELINE_ID, 'lara_style_guideline_id'],
            'LARA_GLOSSARIES'              => [JobsMetadataMarshaller::LARA_GLOSSARIES, 'lara_glossaries'],
            'MMT_GLOSSARIES'               => [JobsMetadataMarshaller::MMT_GLOSSARIES, 'mmt_glossaries'],
            'MMT_IGNORE_GLOSSARY_CASE'     => [JobsMetadataMarshaller::MMT_IGNORE_GLOSSARY_CASE, 'mmt_ignore_glossary_case'],
            'INTENTO_ROUTING'              => [JobsMetadataMarshaller::INTENTO_ROUTING, 'intento_routing'],
            'INTENTO_PROVIDER'             => [JobsMetadataMarshaller::INTENTO_PROVIDER, 'intento_provider'],
        ];
    }

    // =========================================================================
    // Key lists
    // =========================================================================

    #[Test]
    public function mtSettingsAreAllDeclaredCases(): void
    {
        $cases = array_map(static fn(JobsMetadataMarshaller $case): string => $case->value, JobsMetadataMarshaller::cases());

        foreach (JobsMetadataMarshaller::mtSettings() as $key) {
            $this->assertContains($key, $cases, "mtSettings() lists '$key', which is not an enum case");
        }
    }

    #[Test]
    public function mtSettingsMatchTheEngineConfigurationParametersThatCanChangeAfterCreation(): void
    {
        $engineKeys = [];
        foreach (EngineConstants::getAvailableEnginesList() as $engineName) {
            $engineKeys = array_merge($engineKeys, $engineName::getConfigurationParameters());
        }

        // Every engine parameter is job-scoped except these two, which the analysis was priced on
        // (enable_mt_analysis) or which is only ever consumed once at creation time
        // (mmt_activate_context_analyzer, read by MMT::syncMemories() from a project row).
        $expected = array_values(array_diff(
            array_unique($engineKeys),
            ['enable_mt_analysis', 'mmt_activate_context_analyzer']
        ));

        sort($expected);

        $actual = array_values(array_diff(
            JobsMetadataMarshaller::mtSettings(),
            // The MT application threshold is not an engine parameter: it is read from the project
            // metadata blob, so no engine declares it.
            [JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value]
        ));
        sort($actual);

        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function propagatedOnSplitCarriesEveryMtSetting(): void
    {
        $propagated = JobsMetadataMarshaller::propagatedOnSplit();

        foreach (JobsMetadataMarshaller::mtSettings() as $key) {
            // A key missing here silently disappears from every chunk a split creates.
            $this->assertContains($key, $propagated);
        }
    }

    #[Test]
    public function propagatedOnSplitKeepsTheKeysItCarriedBeforeTheMtSettingsMoved(): void
    {
        $propagated = JobsMetadataMarshaller::propagatedOnSplit();

        $this->assertContains(JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value, $propagated);
        $this->assertContains(JobsMetadataMarshaller::CHARACTER_COUNTER_MODE->value, $propagated);
        $this->assertContains(JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value, $propagated);
    }

    #[Test]
    public function propagatedOnSplitHasNoDuplicates(): void
    {
        // Both call sites iterate the list and write/delete one row per entry, so a duplicate would
        // mean redundant statements.
        $propagated = JobsMetadataMarshaller::propagatedOnSplit();

        $this->assertSame(array_values(array_unique($propagated)), $propagated);
    }

    // =========================================================================
    // unMarshall — MT settings
    // =========================================================================

    #[Test]
    #[DataProvider('integerCastProvider')]
    public function unMarshallMtQualityValueInEditorCastsToInt(mixed $rawValue, int $expected): void
    {
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('mt_quality_value_in_editor', $rawValue));
        $this->assertSame($expected, $result);
    }

    #[Test]
    #[DataProvider('booleanTruthyProvider')]
    public function unMarshallMmtIgnoreGlossaryCaseTruthyReturnsTrue(mixed $rawValue): void
    {
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('mmt_ignore_glossary_case', $rawValue));
        $this->assertTrue($result);
    }

    #[Test]
    #[DataProvider('booleanFalsyProvider')]
    public function unMarshallMmtIgnoreGlossaryCaseFalsyReturnsFalse(mixed $rawValue): void
    {
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('mmt_ignore_glossary_case', $rawValue));
        $this->assertFalse($result);
    }

    #[Test]
    #[DataProvider('stringSettingProvider')]
    public function unMarshallStringSettingsAreLeftAsStrings(string $key, mixed $rawValue, string $expected): void
    {
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct($key, $rawValue));
        $this->assertSame($expected, $result);
    }

    public static function stringSettingProvider(): array
    {
        return [
            'deepl_formality'         => ['deepl_formality', 'prefer_more', 'prefer_more'],
            'deepl_engine_type'       => ['deepl_engine_type', 'latency_optimized', 'latency_optimized'],
            'deepl_id_glossary'       => ['deepl_id_glossary', 'abc-123', 'abc-123'],
            'lara_style'              => ['lara_style', 'creative', 'creative'],
            'lara_style_guideline_id' => ['lara_style_guideline_id', 'guideline-7', 'guideline-7'],
            'intento_provider'        => ['intento_provider', 'ai.text.translate.google', 'ai.text.translate.google'],
            'intento_routing'         => ['intento_routing', 'best_quality', 'best_quality'],
            // The engine json_decodes this one itself, so the raw JSON has to survive un-marshalling.
            'mmt_glossaries'          => ['mmt_glossaries', '[12,34]', '[12,34]'],
            // A numeric-looking style id must not become an int: the engines send it as a string.
            'numeric guideline id'    => ['lara_style_guideline_id', 42, '42'],
        ];
    }

    #[Test]
    public function unMarshallLaraGlossariesDecodesJsonArray(): void
    {
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('lara_glossaries', '["one","two"]'));
        $this->assertSame(['one', 'two'], $result);
    }

    #[Test]
    public function unMarshallLaraGlossariesDecodesHtmlEntityEncodedJson(): void
    {
        // Old projects stored the JSON HTML-entity encoded; the project-side marshaller decodes it,
        // so the job side has to agree or the same stored value would resolve to two different types.
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('lara_glossaries', '[&quot;one&quot;]'));
        $this->assertSame(['one'], $result);
    }

    #[Test]
    public function unMarshallLaraGlossariesReturnsNullForUndecodableValue(): void
    {
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('lara_glossaries', 'not json'));
        $this->assertNull($result);
    }

    #[Test]
    public function enumIsBackedByString(): void
    {
        $case = JobsMetadataMarshaller::from('public_tm_penalty');
        $this->assertSame(JobsMetadataMarshaller::PUBLIC_TM_PENALTY, $case);
    }

    #[Test]
    public function tryFromReturnsNullForUnknownValue(): void
    {
        $this->assertNull(JobsMetadataMarshaller::tryFrom('nonexistent_key'));
    }

    // =========================================================================
    // unMarshall — boolean branch (CHARACTER_COUNTER_COUNT_TAGS)
    // =========================================================================

    #[Test]
    #[DataProvider('booleanTruthyProvider')]
    public function unMarshallCharacterCounterCountTagsTruthyReturnsTrue(mixed $rawValue): void
    {
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('character_counter_count_tags', $rawValue));
        $this->assertTrue($result);
    }

    #[Test]
    #[DataProvider('booleanFalsyProvider')]
    public function unMarshallCharacterCounterCountTagsFalsyReturnsFalse(mixed $rawValue): void
    {
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('character_counter_count_tags', $rawValue));
        $this->assertFalse($result);
    }

    // =========================================================================
    // unMarshall — boolean branch (DIALECT_STRICT)
    // =========================================================================

    #[Test]
    #[DataProvider('booleanTruthyProvider')]
    public function unMarshallDialectStrictTruthyReturnsTrue(mixed $rawValue): void
    {
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('dialect_strict', $rawValue));
        $this->assertTrue($result);
    }

    #[Test]
    #[DataProvider('booleanFalsyProvider')]
    public function unMarshallDialectStrictFalsyReturnsFalse(mixed $rawValue): void
    {
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('dialect_strict', $rawValue));
        $this->assertFalse($result);
    }

    // =========================================================================
    // unMarshall — boolean branch (TM_PRIORITIZATION)
    // =========================================================================

    #[Test]
    #[DataProvider('booleanTruthyProvider')]
    public function unMarshallTmPrioritizationTruthyReturnsTrue(mixed $rawValue): void
    {
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('tm_prioritization', $rawValue));
        $this->assertTrue($result);
    }

    #[Test]
    #[DataProvider('booleanFalsyProvider')]
    public function unMarshallTmPrioritizationFalsyReturnsFalse(mixed $rawValue): void
    {
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('tm_prioritization', $rawValue));
        $this->assertFalse($result);
    }

    // =========================================================================
    // unMarshall — integer branch (PUBLIC_TM_PENALTY)
    // =========================================================================

    #[Test]
    #[DataProvider('integerCastProvider')]
    public function unMarshallPublicTmPenaltyCastsToInt(mixed $rawValue, int $expected): void
    {
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('public_tm_penalty', $rawValue));
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
    // unMarshall — default branch: valid JSON → decoded
    // =========================================================================

    #[Test]
    public function unMarshallSubfilteringHandlersDecodesJsonArray(): void
    {
        $json = json_encode([['handler' => 'xliff']]);
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('subfiltering_handlers', $json));
        $this->assertSame([['handler' => 'xliff']], $result);
    }

    #[Test]
    public function unMarshallSubfilteringHandlersDecodesEmptyJsonArray(): void
    {
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('subfiltering_handlers', '[]'));
        $this->assertSame([], $result);
    }

    #[Test]
    public function unMarshallCharacterCounterModeDecodesJsonStringValue(): void
    {
        // A JSON string like '"target"' is valid JSON — json_validate returns true
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('character_counter_mode', '"target"'));
        $this->assertSame('target', $result);
    }

    #[Test]
    public function unMarshallDefaultBranchDecodesJsonObject(): void
    {
        $json = json_encode(['key' => 'value', 'nested' => ['a' => 1]]);
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('subfiltering_handlers', $json));
        $this->assertSame(['key' => 'value', 'nested' => ['a' => 1]], $result);
    }

    #[Test]
    public function unMarshallDefaultBranchDecodesJsonNull(): void
    {
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('character_counter_mode', 'null'));
        $this->assertNull($result);
    }

    #[Test]
    public function unMarshallDefaultBranchDecodesJsonBoolean(): void
    {
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('character_counter_mode', 'true'));
        $this->assertTrue($result);
    }

    #[Test]
    public function unMarshallDefaultBranchDecodesJsonNumber(): void
    {
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('character_counter_mode', '42'));
        $this->assertSame(42, $result);
    }

    // =========================================================================
    // unMarshall — default branch: invalid JSON → plain string
    // =========================================================================

    #[Test]
    public function unMarshallDefaultBranchReturnsPlainStringForInvalidJson(): void
    {
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('character_counter_mode', 'target'));
        $this->assertSame('target', $result);
    }

    #[Test]
    public function unMarshallDefaultBranchReturnsEmptyStringForEmptyValue(): void
    {
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('character_counter_mode', ''));
        $this->assertSame('', $result);
    }

    #[Test]
    public function unMarshallDefaultBranchCastsNullToEmptyString(): void
    {
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('character_counter_mode', null));
        // null cast to string is '', which is not valid JSON → returns ''
        $this->assertSame('', $result);
    }

    #[Test]
    public function unMarshallDefaultBranchCastsIntToString(): void
    {
        // An int value in the default branch: (string)123 = '123', which is valid JSON
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('character_counter_mode', 123));
        $this->assertSame(123, $result);
    }

    // =========================================================================
    // unMarshall — unknown key falls into default branch
    // =========================================================================

    #[Test]
    public function unMarshallUnknownKeyWithValidJsonDecodesIt(): void
    {
        $json = json_encode(['foo' => 'bar']);
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('some_unknown_key', $json));
        $this->assertSame(['foo' => 'bar'], $result);
    }

    #[Test]
    public function unMarshallUnknownKeyWithPlainStringReturnsString(): void
    {
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('some_unknown_key', 'plain text'));
        $this->assertSame('plain text', $result);
    }

    // =========================================================================
    // Shared data providers
    // =========================================================================

    public static function booleanTruthyProvider(): array
    {
        return [
            'string 1'   => ['1'],
            'int 1'      => [1],
            'string yes' => ['yes'],
            'true'       => [true],
            'int 42'     => [42],
        ];
    }

    public static function booleanFalsyProvider(): array
    {
        return [
            'string 0'     => ['0'],
            'int 0'        => [0],
            'empty string' => [''],
            'null'         => [null],
            'false'        => [false],
        ];
    }

    // =========================================================================
    // Helper
    // =========================================================================

    private function makeStruct(string $key, mixed $value): MetadataStruct
    {
        $struct        = new MetadataStruct();
        $struct->id_job   = 1;
        $struct->password = 'test';
        $struct->key      = $key;
        $struct->value    = $value;

        return $struct;
    }
}
