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
    public function unMarshallMmtGlossariesDecodesJsonArray(): void
    {
        // Not a raw string: the two glossary lists have to leave the marshaller with the same shape,
        // or a client reading both out of the same mt_extra object gets an array for one and a
        // string for the other, and posting back what it read fails the write schema.
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('mmt_glossaries', '[12,34]'));
        $this->assertSame([12, 34], $result);
    }

    #[Test]
    public function unMarshallMmtGlossariesDecodesHtmlEntityEncodedJson(): void
    {
        // Both creation controllers run the request parameter through FILTER_SANITIZE_SPECIAL_CHARS
        // before validating it, so old rows can still hold the entity-encoded form.
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('mmt_glossaries', '[&quot;g1&quot;]'));
        $this->assertSame(['g1'], $result);
    }

    #[Test]
    public function unMarshallMmtGlossariesDecodesEmptyList(): void
    {
        // An empty list is a stored answer — the job shadowing a project that has glossaries — and
        // has to survive as one, not collapse to null.
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('mmt_glossaries', '[]'));
        $this->assertSame([], $result);
    }

    #[Test]
    public function unMarshallMmtGlossariesReturnsNullForUndecodableValue(): void
    {
        $result = JobsMetadataMarshaller::unMarshall($this->makeStruct('mmt_glossaries', 'not json'));
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
    // marshall — the typed value back to the string it is stored as
    // =========================================================================

    #[Test]
    #[DataProvider('marshallAcceptedProvider')]
    public function marshallStoresAnAcceptedValueInItsCanonicalForm(JobsMetadataMarshaller $case, mixed $value, string $expected): void
    {
        $this->assertSame($expected, $case->marshall($value));
    }

    public static function marshallAcceptedProvider(): array
    {
        return [
            'count tags true'          => [JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS, true, '1'],
            'dialect strict false'     => [JobsMetadataMarshaller::DIALECT_STRICT, false, '0'],
            'tm prioritization true'   => [JobsMetadataMarshaller::TM_PRIORITIZATION, true, '1'],
            'penalty lower bound'      => [JobsMetadataMarshaller::PUBLIC_TM_PENALTY, 0, '0'],
            'penalty upper bound'      => [JobsMetadataMarshaller::PUBLIC_TM_PENALTY, 100, '100'],
            'counter mode google_ads'  => [JobsMetadataMarshaller::CHARACTER_COUNTER_MODE, 'google_ads', 'google_ads'],
            'counter mode exclude_cjk' => [JobsMetadataMarshaller::CHARACTER_COUNTER_MODE, 'exclude_cjk', 'exclude_cjk'],
            'counter mode all_one'     => [JobsMetadataMarshaller::CHARACTER_COUNTER_MODE, 'all_one', 'all_one'],
            'mandatory issues both'    => [JobsMetadataMarshaller::MANDATORY_ISSUES, ['r1', 'r2'], '["r1","r2"]'],
            'mandatory issues none'    => [JobsMetadataMarshaller::MANDATORY_ISSUES, [], '[]'],
            'subfiltering handlers'    => [JobsMetadataMarshaller::SUBFILTERING_HANDLERS, ['markup', 'twig'], '["markup","twig"]'],
            'subfiltering all off'     => [JobsMetadataMarshaller::SUBFILTERING_HANDLERS, null, 'null'],
            'subfiltering empty'       => [JobsMetadataMarshaller::SUBFILTERING_HANDLERS, '', ''],
            'subfiltering none'        => [JobsMetadataMarshaller::SUBFILTERING_HANDLERS, 'none', 'none'],
            'MT threshold zero'        => [JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR, 0, '0'],
            'glossary case false'      => [JobsMetadataMarshaller::MMT_IGNORE_GLOSSARY_CASE, false, '0'],
            'DeepL formality'          => [JobsMetadataMarshaller::DEEPL_FORMALITY, 'prefer_more', 'prefer_more'],
            'DeepL engine type'        => [JobsMetadataMarshaller::DEEPL_ENGINE_TYPE, 'prefer_quality_optimized', 'prefer_quality_optimized'],
            'Lara style'               => [JobsMetadataMarshaller::LARA_STYLE, 'creative', 'creative'],
            'Lara style guideline'     => [JobsMetadataMarshaller::LARA_STYLE_GUIDELINE_ID, 'g-1', 'g-1'],
            'DeepL glossary none'      => [JobsMetadataMarshaller::DEEPL_ID_GLOSSARY, '', ''],
            'Intento routing'          => [JobsMetadataMarshaller::INTENTO_ROUTING, 'best_price', 'best_price'],
            'Lara glossaries'          => [JobsMetadataMarshaller::LARA_GLOSSARIES, ['g1'], '["g1"]'],
            'MMT glossaries none'      => [JobsMetadataMarshaller::MMT_GLOSSARIES, [], '[]'],
        ];
    }

    #[Test]
    #[DataProvider('marshallRejectedProvider')]
    public function marshallRefusesAValueTheKeyDoesNotAccept(JobsMetadataMarshaller $case, mixed $value): void
    {
        $this->assertNull($case->marshall($value));
    }

    public static function marshallRejectedProvider(): array
    {
        return [
            'count tags as string'          => [JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS, '1'],
            'dialect strict as int'         => [JobsMetadataMarshaller::DIALECT_STRICT, 1],
            'penalty below range'           => [JobsMetadataMarshaller::PUBLIC_TM_PENALTY, -1],
            'penalty above range'           => [JobsMetadataMarshaller::PUBLIC_TM_PENALTY, 101],
            'penalty as string'             => [JobsMetadataMarshaller::PUBLIC_TM_PENALTY, '25'],
            'unknown counter mode'          => [JobsMetadataMarshaller::CHARACTER_COUNTER_MODE, 'foo'],
            'counter mode as list'          => [JobsMetadataMarshaller::CHARACTER_COUNTER_MODE, ['google_ads']],
            'unknown revision phase'        => [JobsMetadataMarshaller::MANDATORY_ISSUES, ['r1', 'r3']],
            'mandatory issues as object'    => [JobsMetadataMarshaller::MANDATORY_ISSUES, ['a' => 'r1']],
            'mandatory issues as string'    => [JobsMetadataMarshaller::MANDATORY_ISSUES, 'r1'],
            'unknown subfiltering handler'  => [JobsMetadataMarshaller::SUBFILTERING_HANDLERS, ['markup', 'html']],
            'subfiltering handler not text' => [JobsMetadataMarshaller::SUBFILTERING_HANDLERS, [1]],
            'subfiltering plain word'       => [JobsMetadataMarshaller::SUBFILTERING_HANDLERS, 'markup'],
            'MT threshold above range'      => [JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR, 101],
            'glossary case as int'          => [JobsMetadataMarshaller::MMT_IGNORE_GLOSSARY_CASE, 0],
            'DeepL glossary as null'        => [JobsMetadataMarshaller::DEEPL_ID_GLOSSARY, null],
            'Intento routing as int'        => [JobsMetadataMarshaller::INTENTO_ROUTING, 3],
            'MMT glossaries as object'      => [JobsMetadataMarshaller::MMT_GLOSSARIES, ['a' => 1]],
        ];
    }

    // =========================================================================
    // sanitizeRawMap — what a copy is allowed to carry
    // =========================================================================

    /**
     * Every value the write paths store, under every key, comes out exactly as it went in, so a
     * well-formed job loses nothing to the check.
     */
    #[Test]
    public function sanitizeRawMapKeepsEveryWellFormedRowUnchanged(): void
    {
        $raw = [
            JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value => '1',
            JobsMetadataMarshaller::CHARACTER_COUNTER_MODE->value       => 'google_ads',
            JobsMetadataMarshaller::DIALECT_STRICT->value               => '0',
            JobsMetadataMarshaller::MANDATORY_ISSUES->value             => '["r1","r2"]',
            JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value            => '25',
            JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value        => '["markup"]',
            JobsMetadataMarshaller::TM_PRIORITIZATION->value            => '1',
            JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value   => '0',
            JobsMetadataMarshaller::DEEPL_FORMALITY->value              => 'prefer_less',
            JobsMetadataMarshaller::DEEPL_ID_GLOSSARY->value            => 'glossary-1',
            JobsMetadataMarshaller::DEEPL_ENGINE_TYPE->value            => 'latency_optimized',
            JobsMetadataMarshaller::LARA_STYLE->value                   => 'fluid',
            JobsMetadataMarshaller::LARA_STYLE_GUIDELINE_ID->value      => 'guideline-1',
            JobsMetadataMarshaller::LARA_GLOSSARIES->value              => '["lara-g1","lara-g2"]',
            JobsMetadataMarshaller::MMT_GLOSSARIES->value               => '[1,2]',
            JobsMetadataMarshaller::MMT_IGNORE_GLOSSARY_CASE->value     => '0',
            JobsMetadataMarshaller::INTENTO_ROUTING->value              => 'best_quality',
            JobsMetadataMarshaller::INTENTO_PROVIDER->value             => '',
        ];

        $this->assertSame($raw, JobsMetadataMarshaller::sanitizeRawMap($raw));
    }

    /**
     * A job says "none here" over a project that has a value with the empty string (the DeepL
     * glossary and the Intento pair) or an empty list (both glossary lists). A split that dropped
     * those rows would hand the new chunk the project's value back.
     */
    #[Test]
    public function sanitizeRawMapCarriesTheExplicitNoneOfTheMtSettings(): void
    {
        $raw = [
            JobsMetadataMarshaller::DEEPL_ID_GLOSSARY->value => '',
            JobsMetadataMarshaller::INTENTO_PROVIDER->value  => '',
            JobsMetadataMarshaller::INTENTO_ROUTING->value   => '',
            JobsMetadataMarshaller::LARA_GLOSSARIES->value   => '[]',
            JobsMetadataMarshaller::MMT_GLOSSARIES->value    => '[]',
        ];

        $this->assertSame($raw, JobsMetadataMarshaller::sanitizeRawMap($raw));
    }

    /**
     * The editor sends a boolean false through a string parameter, which PHP stores as ''. It reads
     * back as false, and the copy writes it the way creation does.
     */
    #[Test]
    public function sanitizeRawMapRewritesAnEmptyBooleanAsZero(): void
    {
        $this->assertSame(
            [JobsMetadataMarshaller::TM_PRIORITIZATION->value => '0'],
            JobsMetadataMarshaller::sanitizeRawMap([JobsMetadataMarshaller::TM_PRIORITIZATION->value => ''])
        );
    }

    #[Test]
    #[DataProvider('sanitizeRawMapDroppedProvider')]
    public function sanitizeRawMapDropsARowThatIsNotValidJobMetadata(string $key, string $rawValue): void
    {
        $this->assertSame([], JobsMetadataMarshaller::sanitizeRawMap([$key => $rawValue]));
    }

    public static function sanitizeRawMapDroppedProvider(): array
    {
        return [
            // Check 1: the key.
            'MMT context'                      => ['mt_context', 'some context'],
            'key no code reads'                => ['retired_setting', '1'],
            // Check 2: the value. unMarshall() would cast both of these to a boolean.
            'boolean spelled as a word'        => [JobsMetadataMarshaller::DIALECT_STRICT->value, 'yes'],
            'boolean as a number other than 1' => [JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value, '2'],
            // ...and these to an integer.
            'penalty above range'              => [JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value, '150'],
            'penalty with trailing text'       => [JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value, '25abc'],
            'negative penalty'                 => [JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value, '-5'],
            'unknown counter mode'             => [JobsMetadataMarshaller::CHARACTER_COUNTER_MODE->value, 'foo'],
            'unknown revision phase'           => [JobsMetadataMarshaller::MANDATORY_ISSUES->value, '["r3"]'],
            'mandatory issues not JSON'        => [JobsMetadataMarshaller::MANDATORY_ISSUES->value, 'r1'],
            'unknown subfiltering handler'     => [JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value, '["html"]'],
            'MT threshold above range'         => [JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value, '101'],
            'MT threshold with trailing text'  => [JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value, '85%'],
            'glossary case spelled as a word'  => [JobsMetadataMarshaller::MMT_IGNORE_GLOSSARY_CASE->value, 'true'],
            'unknown DeepL formality'          => [JobsMetadataMarshaller::DEEPL_FORMALITY->value, 'casual'],
            'unknown DeepL engine type'        => [JobsMetadataMarshaller::DEEPL_ENGINE_TYPE->value, 'fast'],
            'unknown Lara style'               => [JobsMetadataMarshaller::LARA_STYLE->value, 'poetic'],
            'empty Lara style guideline'       => [JobsMetadataMarshaller::LARA_STYLE_GUIDELINE_ID->value, ''],
            'Lara glossaries not a list'       => [JobsMetadataMarshaller::LARA_GLOSSARIES->value, '"lara-g1"'],
            'Lara glossary with an empty id'   => [JobsMetadataMarshaller::LARA_GLOSSARIES->value, '[""]'],
            'MMT glossary id as a string'      => [JobsMetadataMarshaller::MMT_GLOSSARIES->value, '["1"]'],
            'MMT glossary id zero'             => [JobsMetadataMarshaller::MMT_GLOSSARIES->value, '[0]'],
            'Intento provider too long'        => [JobsMetadataMarshaller::INTENTO_PROVIDER->value, str_repeat('p', 256)],
        ];
    }

    #[Test]
    public function sanitizeRawMapKeepsTheValidRowsOfAMixedMap(): void
    {
        $this->assertSame(
            [
                JobsMetadataMarshaller::DIALECT_STRICT->value   => '1',
                JobsMetadataMarshaller::MANDATORY_ISSUES->value => '["r2"]',
            ],
            JobsMetadataMarshaller::sanitizeRawMap([
                JobsMetadataMarshaller::DIALECT_STRICT->value    => '1',
                'mt_context'                                     => 'some context',
                JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value => '150',
                JobsMetadataMarshaller::MANDATORY_ISSUES->value  => '["r2"]',
            ])
        );
    }

    #[Test]
    public function sanitizeRawMapReturnsNothingForNothing(): void
    {
        $this->assertSame([], JobsMetadataMarshaller::sanitizeRawMap([]));
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
