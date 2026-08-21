<?php

namespace Matecat\Core\Utils\Tools;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Utils\Tools\CatUtils;

class CatUtilsMethodsTest extends AbstractTest
{
    // ─── isCJK ───────────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('isCJKProvider')]
    public function testIsCJK(string $langCode, bool $expected): void
    {
        self::assertSame($expected, CatUtils::isCJK($langCode));
    }

    public static function isCJKProvider(): array
    {
        return [
            'Chinese simplified'   => ['zh-CN', true],
            'Chinese traditional'  => ['zh-TW', true],
            'Japanese'             => ['ja-JP', true],
            'Korean'               => ['ko-KR', true],
            'Khmer'                => ['km', true],
            'English'              => ['en-US', false],
            'Italian'              => ['it-IT', false],
            'French'               => ['fr-FR', false],
            'bare zh'              => ['zh', true],
        ];
    }

    // ─── isCJ ────────────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('isCJProvider')]
    public function testIsCJ(?string $langCode, bool $expected): void
    {
        self::assertSame($expected, CatUtils::isCJ($langCode));
    }

    public static function isCJProvider(): array
    {
        return [
            'Chinese'  => ['zh-CN', true],
            'Japanese' => ['ja', true],
            'Korean'   => ['ko-KR', false],  // ko is NOT CJ (only CJK)
            'null'     => [null, false],
            'English'  => ['en', false],
        ];
    }

    // ─── sanitizeProjectName ─────────────────────────────────────────────

    #[Test]
    #[DataProvider('sanitizeProjectNameProvider')]
    public function testSanitizeProjectName(string $input, string $expected): void
    {
        self::assertSame($expected, CatUtils::sanitizeProjectName($input));
    }

    /**
     * The allowlist that used to delete every character outside `. - _ \p{L} \p{N} \s` is gone: a
     * project name is stored as it was typed, and each output escapes for itself. Only control
     * characters and whitespace are still normalised.
     */
    public static function sanitizeProjectNameProvider(): array
    {
        return [
            'valid name unchanged'      => ['My_Project-123', 'My_Project-123'],
            'punctuation kept'          => ['Hello@World#!', 'Hello@World#!'],
            'ampersand kept'            => ['Acme & Co (2024)', 'Acme & Co (2024)'],
            'unicode letters kept'      => ['Progetto Ñoño', 'Progetto Ñoño'],
            'dots preserved'            => ['file.name.v2', 'file.name.v2'],
            'spaces preserved'          => ['My Project', 'My Project'],
            'empty string stays empty'  => ['', ''],
            'symbols are a valid name'  => ['@#$%^&', '@#$%^&'],
            'control characters go'     => ["Report\r\n2024", 'Report 2024'],
            'cut to the column width'   => [str_repeat('p', 240), str_repeat('p', 200)],
        ];
    }

    // ─── stripMaliciousContentFromAName ──────────────────────────────────

    #[Test]
    #[DataProvider('stripMaliciousProvider')]
    public function testStripMaliciousContentFromAName(string $input, string $expected): void
    {
        self::assertSame($expected, CatUtils::stripMaliciousContentFromAName($input));
    }

    /**
     * The `\P{L}` strip is gone. It replaced every character that is not a Unicode letter with a
     * space, which cost `O'Brien`, `Jean-Luc` and `J.R.` their punctuation and any name with a digit
     * in it its digit. Markup is no longer flattened either — it is stored as typed and escaped by
     * whichever output prints it, the same way every other name is.
     */
    public static function stripMaliciousProvider(): array
    {
        return [
            'normal name unchanged'       => ['John Doe', 'John Doe'],
            'markup stored as typed'      => ['<script>alert("xss")</script>', '<script>alert("xss")</script>'],
            'numbers kept'                => ['John123', 'John123'],
            'punctuation kept'            => ['John!@#Doe', 'John!@#Doe'],
            'apostrophe kept'             => ["O'Brien", "O'Brien"],
            'hyphen kept'                 => ['Jean-Luc', 'Jean-Luc'],
            'initials keep their dots'    => ['J.R. Ewing', 'J.R. Ewing'],
            'cut to the column width'     => [str_repeat('A', 150), str_repeat('A', 100)],
            'double spaces collapsed'     => ['John   Doe', 'John Doe'],
            'unicode letters preserved'   => ['José García', 'José García'],
            'non latin script preserved'  => ['李明', '李明'],
            'control characters go'       => ["John\r\nDoe", 'John Doe'],
        ];
    }

    // ─── getLastCharacter ────────────────────────────────────────────────

    #[Test]
    #[DataProvider('getLastCharacterProvider')]
    public function testGetLastCharacter(string $input, string $expected): void
    {
        self::assertSame($expected, CatUtils::getLastCharacter($input));
    }

    public static function getLastCharacterProvider(): array
    {
        return [
            'simple string'            => ['hello', 'o'],
            'with trailing html tag'   => ['hello<br>', 'o'],
            'wrapping html'            => ['<p>world</p>', 'd'],
            'unicode char'             => ['café', 'é'],
            'single char'              => ['X', 'X'],
            'empty string'             => ['', ''],
        ];
    }

    // ─── reApplySegmentSplit ─────────────────────────────────────────────

    #[Test]
    public function testReApplySegmentSplitWithNullPositionsReturnsSegment(): void
    {
        self::assertSame('hello world', CatUtils::reApplySegmentSplit('hello world', null));
    }

    #[Test]
    public function testReApplySegmentSplitWithEmptyPositionsReturnsSegment(): void
    {
        self::assertSame('hello world', CatUtils::reApplySegmentSplit('hello world', []));
    }

    #[Test]
    public function testReApplySegmentSplitWithNullSegment(): void
    {
        self::assertNull(CatUtils::reApplySegmentSplit(null, null));
    }

    #[Test]
    public function testReApplySegmentSplitReconstructsWithPlaceholder(): void
    {
        // simulate a segment "hello world" split at position 5 (after "hello") + 1 space
        $segment = 'hello world';
        $positions = [0, 6, 5];

        $result = CatUtils::reApplySegmentSplit($segment, $positions);
        self::assertIsString($result);
        self::assertStringContainsString(CatUtils::splitPlaceHolder, $result);
    }

    // ─── fetchStatus ─────────────────────────────────────────────────────

    #[Test]
    public function testFetchStatusReturnsNullForEmptyResults(): void
    {
        self::assertNull(CatUtils::fetchStatus(1, []));
    }

    #[Test]
    public function testFetchStatusFindsNextSegmentAfterSid(): void
    {
        $results = [
            ['id' => 1, 'status' => 'NEW'],
            ['id' => 2, 'status' => 'NEW'],
            ['id' => 3, 'status' => 'TRANSLATED'],
        ];

        // Looking for next NEW after sid=1 -> should find id=2
        self::assertSame(2, CatUtils::fetchStatus(1, $results, 'NEW'));
    }

    #[Test]
    public function testFetchStatusWrapsAroundWhenNoSegmentAfterSid(): void
    {
        $results = [
            ['id' => 1, 'status' => 'NEW'],
            ['id' => 2, 'status' => 'TRANSLATED'],
            ['id' => 3, 'status' => 'TRANSLATED'],
        ];

        // Looking for next NEW after sid=2 -> wraps to id=1
        self::assertSame(1, CatUtils::fetchStatus(2, $results, 'NEW'));
    }

    #[Test]
    public function testFetchStatusHandlesNullStatus(): void
    {
        $results = [
            ['id' => 1, 'status' => null],
            ['id' => 2, 'status' => null],
        ];

        // null status treated as NEW
        self::assertSame(2, CatUtils::fetchStatus(1, $results, 'NEW'));
    }

    // ─── restoreUnicodeEntitiesToOriginalValues ──────────────────────────

    #[Test]
    public function testRestoreUnicodeEntitiesConverts157(): void
    {
        $input = 'Hello &#157; World';
        $result = CatUtils::restoreUnicodeEntitiesToOriginalValues($input);
        self::assertStringNotContainsString('&#157;', $result);
        self::assertStringContainsString('Hello', $result);
        self::assertStringContainsString('World', $result);
    }

    #[Test]
    public function testRestoreUnicodeEntitiesLeavesOtherEntitiesAlone(): void
    {
        $input = 'Hello &#169; World';
        $result = CatUtils::restoreUnicodeEntitiesToOriginalValues($input);
        // &#169; (copyright) should remain untouched
        self::assertStringContainsString('&#169;', $result);
    }

    // ─── trimAndStripFromAnHtmlEntityDecoded ─────────────────────────────

    #[Test]
    #[DataProvider('trimAndStripProvider')]
    public function testTrimAndStripFromAnHtmlEntityDecoded(string $input, string $expected): void
    {
        self::assertSame($expected, CatUtils::trimAndStripFromAnHtmlEntityDecoded($input));
    }

    public static function trimAndStripProvider(): array
    {
        return [
            'plain text unchanged'    => ['Hello World', 'Hello World'],
            'html tags stripped'      => ['<p>Hello</p>', 'Hello'],
            'entities decoded+strip'  => ['&lt;tag&gt;', ''],  // decoded to <tag>, then stripped
            'trimmed'                 => ['  hello  ', 'hello'],
            'CDATA extracted'         => ['<![CDATA[inner content]]>', 'inner content'],
            'nested tags stripped'    => ['<div><span>text</span></div>', 'text'],
        ];
    }

    // ─── parse_time_to_edit edge cases ───────────────────────────────────

    #[Test]
    public function testParseTimeToEditNegativeInput(): void
    {
        self::assertSame(['00', '00', '00', 0], CatUtils::parse_time_to_edit(-500));
    }

    #[Test]
    public function testParseTimeToEditOneHour(): void
    {
        // 3,600,000 ms = 1 hour exactly
        self::assertSame(['01', '00', '00', 0], CatUtils::parse_time_to_edit(3600000));
    }

    #[Test]
    public function testParseTimeToEditWithMilliseconds(): void
    {
        // 61,500 ms = 1 min, 1 sec, 500 ms
        self::assertSame(['00', '01', '01', 500], CatUtils::parse_time_to_edit(61500));
    }
}
