<?php

namespace unit\Model\Jobs;

use Model\Jobs\JobsMetadataMarshaller;
use Model\Jobs\MetadataStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

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
    public function enumHasExactlySixCases(): void
    {
        $this->assertCount(6, JobsMetadataMarshaller::cases());
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
        ];
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
