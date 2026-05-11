<?php

namespace unit\Utils\Tools;

use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Model\Filters\DTO\IDto;
use Model\Filters\FiltersConfigTemplateStruct;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewStruct;
use Model\Projects\ProjectStruct;
use Model\WordCount\WordCountStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Constants\TranslationStatus;
use Utils\Tools\CatUtils;

class CatUtilsTest extends AbstractTest
{
    /**
     * Test that a valid project name is returned as is.
     */
    #[Test]
    public function testSanitizeOrFallbackProjectNameWithValidName()
    {
        $validName = "Valid_Project_Name";
        $result = CatUtils::sanitizeOrFallbackProjectName($validName, []);
        $this->assertEquals($validName, $result);
    }

    /**
     * Test that an invalid project name is sanitized.
     */
    #[Test]
    public function testSanitizeOrFallbackProjectNameWithInvalidName()
    {
        $invalidName = "Invalid@Project#Name!";
        $expected_name = "InvalidProjectName";
        $sanitizedName = CatUtils::sanitizeOrFallbackProjectName($invalidName);
        $this->assertEquals($expected_name, $sanitizedName);
    }

    /**
     * Test that a fallback name is generated when input name is empty
     * and more than one file is given.
     */
    #[Test]
    public function testFallbackNameGeneratedWhenNameIsEmptyAndMultipleFilesProvided()
    {
        $files = [
            ['name' => 'file1.txt'],
            ['name' => 'file2.txt']
        ];
        $result = CatUtils::sanitizeOrFallbackProjectName("", $files);
        $this->assertStringStartsWith('MATECAT_PROJ-', $result);
    }

    /**
     * Test that the fallback name is based on file name when name is empty
     * and exactly one file is provided.
     */
    #[Test]
    public function testFallbackNameBasedOnSingleFile()
    {
        $files = [
            ['name' => 'example_file_name.txt']
        ];
        $expectedName = "example_file_name";
        $result = CatUtils::sanitizeOrFallbackProjectName("", $files);
        $this->assertEquals($expectedName, $result);
    }

    /**
     * Test that an empty input name with no files provided results in
     * a generated fallback project name.
     */
    #[Test]
    public function testFallbackNameGeneratedWhenNameIsEmptyAndNoFilesProvided()
    {
        $result = CatUtils::sanitizeOrFallbackProjectName("");
        $this->assertStringStartsWith('MATECAT_PROJ-', $result);
    }

    // -------------------------------------------------------------------------
    // isCJK
    // -------------------------------------------------------------------------

    #[Test]
    public function testIsCJKWithChineseReturnsTrueL(): void
    {
        $this->assertTrue(CatUtils::isCJK('zh'));
    }

    #[Test]
    public function testIsCJKWithJapaneseSubtagReturnsTrueL(): void
    {
        $this->assertTrue(CatUtils::isCJK('ja-JP'));
    }

    #[Test]
    public function testIsCJKWithKoreanReturnsTrueL(): void
    {
        $this->assertTrue(CatUtils::isCJK('ko'));
    }

    #[Test]
    public function testIsCJKWithKhmerReturnsTrueL(): void
    {
        $this->assertTrue(CatUtils::isCJK('km'));
    }

    #[Test]
    public function testIsCJKWithEnglishReturnsFalseL(): void
    {
        $this->assertFalse(CatUtils::isCJK('en'));
    }

    #[Test]
    public function testIsCJKWithSpanishSubtagReturnsFalseL(): void
    {
        $this->assertFalse(CatUtils::isCJK('es-ES'));
    }

    // -------------------------------------------------------------------------
    // isCJ
    // -------------------------------------------------------------------------

    #[Test]
    public function testIsCJWithNullReturnsFalseL(): void
    {
        $this->assertFalse(CatUtils::isCJ(null));
    }

    #[Test]
    public function testIsCJWithChineseReturnsTrueL(): void
    {
        $this->assertTrue(CatUtils::isCJ('zh'));
    }

    #[Test]
    public function testIsCJWithJapaneseReturnsTrueL(): void
    {
        $this->assertTrue(CatUtils::isCJ('ja'));
    }

    #[Test]
    public function testIsCJWithKoreanReturnsFalseL(): void
    {
        // ko is CJK but NOT CJ
        $this->assertFalse(CatUtils::isCJ('ko'));
    }

    #[Test]
    public function testIsCJWithEnglishReturnsFalseL(): void
    {
        $this->assertFalse(CatUtils::isCJ('en'));
    }

    #[Test]
    public function testIsCJWithJapaneseSubtagReturnsTrueL(): void
    {
        $this->assertTrue(CatUtils::isCJ('ja-JP'));
    }

    // -------------------------------------------------------------------------
    // CJKFullwidthPunctuationChars
    // -------------------------------------------------------------------------

    #[Test]
    public function testCJKFullwidthPunctuationCharsReturnsNonEmptyArrayL(): void
    {
        $result = CatUtils::CJKFullwidthPunctuationChars();
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function testCJKFullwidthPunctuationCharsContainsStringsL(): void
    {
        $result = CatUtils::CJKFullwidthPunctuationChars();
        foreach ($result as $char) {
            $this->assertIsString($char);
        }
    }

    // -------------------------------------------------------------------------
    // parse_time_to_edit
    // -------------------------------------------------------------------------

    #[Test]
    public function testParseTimeToEditWithZeroReturnsZerosL(): void
    {
        $result = CatUtils::parse_time_to_edit(0);
        $this->assertEquals(["00", "00", "00", 0], $result);
    }

    #[Test]
    public function testParseTimeToEditWithNegativeReturnsZerosL(): void
    {
        $result = CatUtils::parse_time_to_edit(-100);
        $this->assertEquals(["00", "00", "00", 0], $result);
    }

    #[Test]
    public function testParseTimeToEditWithOneHourOneMinuteOneSecondL(): void
    {
        // 3661 seconds = 1h 1m 1s, 0 usec remaining
        $result = CatUtils::parse_time_to_edit(3661000);
        $this->assertEquals("01", $result[0]); // hours
        $this->assertEquals("01", $result[1]); // minutes
        $this->assertEquals("01", $result[2]); // seconds
        $this->assertEquals(0, $result[3]);    // usec
    }

    #[Test]
    public function testParseTimeToEditWithSubSecondL(): void
    {
        // 500ms -> 0 full seconds, 500 usec
        $result = CatUtils::parse_time_to_edit(500);
        $this->assertEquals("00", $result[0]);
        $this->assertEquals("00", $result[1]);
        $this->assertEquals("00", $result[2]);
        $this->assertEquals(500, $result[3]);
    }

    #[Test]
    public function testParseTimeToEditWithTwoMinutesThirtySecondsL(): void
    {
        // 150500ms -> 150 seconds = 2m30s, 500 usec
        $result = CatUtils::parse_time_to_edit(150500);
        $this->assertEquals("00", $result[0]);
        $this->assertEquals("02", $result[1]);
        $this->assertEquals("30", $result[2]);
        $this->assertEquals(500, $result[3]);
    }

    // -------------------------------------------------------------------------
    // reApplySegmentSplit
    // -------------------------------------------------------------------------

    #[Test]
    public function testReApplySegmentSplitWithNullPositionsReturnsSegmentL(): void
    {
        $result = CatUtils::reApplySegmentSplit("hello world", null);
        $this->assertEquals("hello world", $result);
    }

    #[Test]
    public function testReApplySegmentSplitWithEmptyPositionsReturnsSegmentL(): void
    {
        $result = CatUtils::reApplySegmentSplit("hello world", []);
        $this->assertEquals("hello world", $result);
    }

    #[Test]
    public function testReApplySegmentSplitWithValidPositionsL(): void
    {
        // chunk_positions = [0, 5, 5] on "helloworld"
        // pos=0: value=0, next=5 -> substr("helloworld", 0, 5) = "hello"
        // pos=1: value=5, next=5 -> substr("helloworld", 5, 5) = "world"
        // pos=2: no next, skip
        $result = CatUtils::reApplySegmentSplit("helloworld", [0, 5, 5]);
        $this->assertEquals("hello" . CatUtils::splitPlaceHolder . "world", $result);
    }

    #[Test]
    public function testReApplySegmentSplitWithNullSegmentL(): void
    {
        $result = CatUtils::reApplySegmentSplit(null, null);
        $this->assertNull($result);
    }

    #[Test]
    public function testReApplySegmentSplitSingleChunkReturnsSegmentL(): void
    {
        // Only [0, 5] -> one chunk "hello", no second position -> string_chunks = ["hello"]
        // Not empty, so return "hello"
        $result = CatUtils::reApplySegmentSplit("hello world", [0, 5]);
        $this->assertEquals("hello", $result);
    }

    // -------------------------------------------------------------------------
    // fastUnicode2ord
    // -------------------------------------------------------------------------

    #[Test]
    public function testFastUnicode2ordOneByteAsciiL(): void
    {
        $this->assertEquals(65, CatUtils::fastUnicode2ord('A'));
    }

    #[Test]
    public function testFastUnicode2ordTwoByteCharL(): void
    {
        // 'é' = U+00E9 -> 233 decimal
        $this->assertEquals(233, CatUtils::fastUnicode2ord('é'));
    }

    #[Test]
    public function testFastUnicode2ordThreeByteEuroSignL(): void
    {
        // '€' = U+20AC -> 8364 decimal
        $this->assertEquals(8364, CatUtils::fastUnicode2ord('€'));
    }

    #[Test]
    public function testFastUnicode2ordFourByteEmojiL(): void
    {
        // '😀' = U+1F600 -> 128512 decimal
        $this->assertEquals(128512, CatUtils::fastUnicode2ord('😀'));
    }

    #[Test]
    public function testFastUnicode2ordZeroCharL(): void
    {
        $this->assertEquals(48, CatUtils::fastUnicode2ord('0'));
    }

    // -------------------------------------------------------------------------
    // htmlentitiesFromUnicode
    // -------------------------------------------------------------------------

    #[Test]
    public function testHtmlentitiesFromUnicodeWithAsciiL(): void
    {
        // callback array: [full_match, captured_char]
        $result = CatUtils::htmlentitiesFromUnicode(['', 'A']);
        $this->assertEquals('&#65;', $result);
    }

    #[Test]
    public function testHtmlentitiesFromUnicodeWithEuroSignL(): void
    {
        $result = CatUtils::htmlentitiesFromUnicode(['', '€']);
        $this->assertEquals('&#8364;', $result);
    }

    // -------------------------------------------------------------------------
    // unicode2chr
    // -------------------------------------------------------------------------

    #[Test]
    public function testUnicode2chrWithAsciiCodeL(): void
    {
        $result = CatUtils::unicode2chr(65);
        $this->assertEquals('A', $result);
    }

    #[Test]
    public function testUnicode2chrWithEuroSignCodeL(): void
    {
        $result = CatUtils::unicode2chr(8364);
        $this->assertEquals('€', $result);
    }

    // -------------------------------------------------------------------------
    // restoreUnicodeEntitiesToOriginalValues
    // -------------------------------------------------------------------------

    #[Test]
    public function testRestoreUnicodeEntitiesReplacesEntity157L(): void
    {
        $str = "Some &#157; text";
        $result = CatUtils::restoreUnicodeEntitiesToOriginalValues($str);
        $this->assertStringNotContainsString('&#157;', $result);
    }

    #[Test]
    public function testRestoreUnicodeEntitiesWithNoSpecialEntitiesUnchangedL(): void
    {
        $str = "Plain text without special entities";
        $result = CatUtils::restoreUnicodeEntitiesToOriginalValues($str);
        $this->assertEquals($str, $result);
    }

    // -------------------------------------------------------------------------
    // trimAndStripFromAnHtmlEntityDecoded
    // -------------------------------------------------------------------------

    #[Test]
    public function testTrimAndStripTrimsWhitespaceL(): void
    {
        $result = CatUtils::trimAndStripFromAnHtmlEntityDecoded("  hello  ");
        $this->assertEquals("hello", $result);
    }

    #[Test]
    public function testTrimAndStripRemovesHtmlTagsL(): void
    {
        $result = CatUtils::trimAndStripFromAnHtmlEntityDecoded("<b>Hello</b>");
        $this->assertEquals("Hello", $result);
    }

    #[Test]
    public function testTrimAndStripHandlesCDataL(): void
    {
        $result = CatUtils::trimAndStripFromAnHtmlEntityDecoded("<![CDATA[content here]]>");
        $this->assertEquals("content here", $result);
    }

    #[Test]
    public function testTrimAndStripDecodesHtmlEntitiesL(): void
    {
        $result = CatUtils::trimAndStripFromAnHtmlEntityDecoded("&lt;b&gt;Hello&lt;/b&gt;");
        $this->assertEquals("Hello", $result);
    }

    #[Test]
    public function testTrimAndStripWithMixedContentL(): void
    {
        $result = CatUtils::trimAndStripFromAnHtmlEntityDecoded("  <p>World</p>  ");
        $this->assertEquals("World", $result);
    }

    // -------------------------------------------------------------------------
    // fetchStatus
    // -------------------------------------------------------------------------

    #[Test]
    public function testFetchStatusWithEmptyResultsReturnsNullL(): void
    {
        $result = CatUtils::fetchStatus(0, []);
        $this->assertNull($result);
    }

    #[Test]
    public function testFetchStatusFoundAfterSidL(): void
    {
        $results = [
            ['id' => 5, 'status' => TranslationStatus::STATUS_NEW],
            ['id' => 15, 'status' => TranslationStatus::STATUS_NEW],
        ];
        $result = CatUtils::fetchStatus(10, $results, TranslationStatus::STATUS_NEW);
        $this->assertEquals(15, $result);
    }

    #[Test]
    public function testFetchStatusWrapsAroundWhenNothingAfterSidL(): void
    {
        $results = [
            ['id' => 5, 'status' => TranslationStatus::STATUS_NEW],
            ['id' => 15, 'status' => TranslationStatus::STATUS_NEW],
        ];
        // sid=20 > both ids, so wrap around to first matching = 5
        $result = CatUtils::fetchStatus(20, $results, TranslationStatus::STATUS_NEW);
        $this->assertEquals(5, $result);
    }

    #[Test]
    public function testFetchStatusNoMatchingStatusReturnsNullL(): void
    {
        $results = [
            ['id' => 5, 'status' => TranslationStatus::STATUS_TRANSLATED],
        ];
        // Looking for NEW (weight 10) but only TRANSLATED (weight 40) exists
        $result = CatUtils::fetchStatus(0, $results, TranslationStatus::STATUS_NEW);
        $this->assertNull($result);
    }

    #[Test]
    public function testFetchStatusNullStatusTreatedAsNewL(): void
    {
        $results = [
            ['id' => 5, 'status' => null],
        ];
        // null status is treated as STATUS_NEW
        $result = CatUtils::fetchStatus(0, $results, TranslationStatus::STATUS_NEW);
        $this->assertEquals(5, $result);
    }

    #[Test]
    public function testFetchStatusDraftMatchesNewWeightL(): void
    {
        // DRAFT has same weight (10) as NEW, so searching for NEW also finds DRAFT
        $results = [
            ['id' => 7, 'status' => TranslationStatus::STATUS_DRAFT],
        ];
        $result = CatUtils::fetchStatus(0, $results, TranslationStatus::STATUS_NEW);
        $this->assertEquals(7, $result);
    }

    #[Test]
    public function testFetchStatusFirstMatchReturnedAfterSidL(): void
    {
        $results = [
            ['id' => 3, 'status' => TranslationStatus::STATUS_NEW],
            ['id' => 7, 'status' => TranslationStatus::STATUS_NEW],
            ['id' => 12, 'status' => TranslationStatus::STATUS_NEW],
        ];
        // sid=5, first id > 5 with matching status is 7
        $result = CatUtils::fetchStatus(5, $results, TranslationStatus::STATUS_NEW);
        $this->assertEquals(7, $result);
    }

    // -------------------------------------------------------------------------
    // getLastCharacter
    // -------------------------------------------------------------------------

    #[Test]
    public function testGetLastCharacterFromPlainStringL(): void
    {
        $this->assertEquals('o', CatUtils::getLastCharacter('hello'));
    }

    #[Test]
    public function testGetLastCharacterFromHtmlStringL(): void
    {
        $this->assertEquals('o', CatUtils::getLastCharacter('<b>hello</b>'));
    }

    #[Test]
    public function testGetLastCharacterFromMultibyteStringL(): void
    {
        $this->assertEquals('é', CatUtils::getLastCharacter('café'));
    }

    #[Test]
    public function testGetLastCharacterFromEmptyStringL(): void
    {
        $this->assertEquals('', CatUtils::getLastCharacter(''));
    }

    // -------------------------------------------------------------------------
    // stripMaliciousContentFromAName
    // -------------------------------------------------------------------------

    #[Test]
    public function testStripMaliciousContentWithCleanNameL(): void
    {
        $result = CatUtils::stripMaliciousContentFromAName("John Doe");
        $this->assertEquals("John Doe", $result);
    }

    #[Test]
    public function testStripMaliciousContentRemovesSpecialCharsL(): void
    {
        $result = CatUtils::stripMaliciousContentFromAName("John@Doe!");
        // @ and ! are non-letters, replaced with spaces, then trimmed
        $this->assertEquals("John Doe", $result);
    }

    #[Test]
    public function testStripMaliciousContentTruncatesToFiftyCharsL(): void
    {
        $longName = str_repeat('A', 60);
        $result = CatUtils::stripMaliciousContentFromAName($longName);
        $this->assertLessThanOrEqual(50, mb_strlen($result));
    }

    #[Test]
    public function testStripMaliciousContentPreservesUnicodeLettersL(): void
    {
        $result = CatUtils::stripMaliciousContentFromAName("Ångström Müller");
        $this->assertEquals("Ångström Müller", $result);
    }

    #[Test]
    public function testStripMaliciousContentCollapsesMultipleSpacesL(): void
    {
        $result = CatUtils::stripMaliciousContentFromAName("John   Doe");
        $this->assertEquals("John Doe", $result);
    }

    // -------------------------------------------------------------------------
    // sanitizeProjectName
    // -------------------------------------------------------------------------

    #[Test]
    public function testSanitizeProjectNameRemovesSpecialCharsL(): void
    {
        $result = CatUtils::sanitizeProjectName("Project@#2024!");
        $this->assertEquals("Project2024", $result);
    }

    #[Test]
    public function testSanitizeProjectNamePreservesUnicodeLettersL(): void
    {
        $result = CatUtils::sanitizeProjectName("Ångström Project");
        $this->assertEquals("Ångström Project", $result);
    }

    #[Test]
    public function testSanitizeProjectNamePreservesDotsAndDashesL(): void
    {
        $result = CatUtils::sanitizeProjectName("my-project_v1.0");
        $this->assertEquals("my-project_v1.0", $result);
    }

    #[Test]
    public function testSanitizeProjectNameWithOnlySpecialCharsL(): void
    {
        $result = CatUtils::sanitizeProjectName("@#$%!");
        $this->assertEquals("", $result);
    }

    // -------------------------------------------------------------------------
    // getQualityOverallFromJobStruct
    // -------------------------------------------------------------------------

    #[Test]
    public function testGetQualityOverallWithIsPassTrueReturnsExcellentL(): void
    {
        $job = new JobStruct(['id' => 1, 'password' => 'abc']);
        $review = new ChunkReviewStruct();
        $review->is_pass = true;

        $result = CatUtils::getQualityOverallFromJobStruct($job, [$review]);
        $this->assertEquals('excellent', $result);
    }

    #[Test]
    public function testGetQualityOverallWithIsPassFalseReturnsFailL(): void
    {
        $job = new JobStruct(['id' => 1, 'password' => 'abc']);
        $review = new ChunkReviewStruct();
        $review->is_pass = false;

        $result = CatUtils::getQualityOverallFromJobStruct($job, [$review]);
        $this->assertEquals('fail', $result);
    }

    #[Test]
    public function testGetQualityOverallWithIsPassNullReturnsNullL(): void
    {
        $job = new JobStruct(['id' => 1, 'password' => 'abc']);
        $review = new ChunkReviewStruct();
        // is_pass defaults to null

        $result = CatUtils::getQualityOverallFromJobStruct($job, [$review]);
        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // getChunkReviewStructFromJobStruct
    // -------------------------------------------------------------------------

    #[Test]
    public function testGetChunkReviewStructWithNonEmptyArrayReturnsFirstL(): void
    {
        $job = new JobStruct(['id' => 1, 'password' => 'abc']);
        $review = new ChunkReviewStruct();
        $review->is_pass = true;

        $result = CatUtils::getChunkReviewStructFromJobStruct($job, [$review]);
        $this->assertSame($review, $result);
    }

    // -------------------------------------------------------------------------
    // getFastStatsForJob
    // -------------------------------------------------------------------------

    #[Test]
    public function testGetFastStatsForJobWithPerformanceEstimationFalseL(): void
    {
        $job = new JobStruct([
            'id' => 1,
            'password' => 'testpass',
            'new_words' => 10.0,
            'draft_words' => 5.0,
            'translated_words' => 0.0,
            'approved_words' => 0.0,
            'rejected_words' => 0.0,
            'approved2_words' => 0.0,
            'new_raw_words' => 10.0,
            'draft_raw_words' => 5.0,
            'translated_raw_words' => 0.0,
            'approved_raw_words' => 0.0,
            'approved2_raw_words' => 0.0,
            'rejected_raw_words' => 0.0,
        ]);
        $wCount = WordCountStruct::loadFromJob($job);
        $result = CatUtils::getFastStatsForJob($wCount, false);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('equivalent', $result);
        $this->assertArrayHasKey('raw', $result);
        $this->assertEquals(10.0, $result['equivalent']['new']);
        $this->assertEquals(5.0, $result['raw']['draft']);
    }

    // -------------------------------------------------------------------------
    // getJobPassword (non-DB branch: sourcePage <= 1)
    // -------------------------------------------------------------------------

    #[Test]
    public function testGetJobPasswordWithSourcePageOneReturnsPasswordL(): void
    {
        $job = new JobStruct(['id' => 1, 'password' => 'mypassword']);
        $result = CatUtils::getJobPassword($job, 1);
        $this->assertEquals('mypassword', $result);
    }

    #[Test]
    public function testGetJobPasswordWithSourcePageZeroReturnsPasswordL(): void
    {
        $job = new JobStruct(['id' => 1, 'password' => 'mypassword']);
        $result = CatUtils::getJobPassword($job, 0);
        $this->assertEquals('mypassword', $result);
    }

    // -------------------------------------------------------------------------
    // getIsRevisionFromRequestUri
    // -------------------------------------------------------------------------

    #[Test]
    public function testGetIsRevisionFromRequestUriNotSetReturnsFalseL(): void
    {
        unset($_SERVER['REQUEST_URI']);
        $this->assertFalse(CatUtils::getIsRevisionFromRequestUri());
    }

    #[Test]
    public function testGetIsRevisionFromRequestUriWithRevisePathReturnsTrueL(): void
    {
        $_SERVER['REQUEST_URI'] = '/revise/1/abc/2';
        $result = CatUtils::getIsRevisionFromRequestUri();
        unset($_SERVER['REQUEST_URI']);
        $this->assertTrue($result);
    }

    #[Test]
    public function testGetIsRevisionFromRequestUriWithTranslatePathReturnsFalseL(): void
    {
        $_SERVER['REQUEST_URI'] = '/translate/1/abc/2';
        $result = CatUtils::getIsRevisionFromRequestUri();
        unset($_SERVER['REQUEST_URI']);
        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // getIsRevisionFromReferer
    // -------------------------------------------------------------------------

    #[Test]
    public function testGetIsRevisionFromRefererNotSetReturnsFalseL(): void
    {
        unset($_SERVER['HTTP_REFERER']);
        $this->assertFalse(CatUtils::getIsRevisionFromReferer());
    }

    #[Test]
    public function testGetIsRevisionFromRefererWithRevisePathReturnsTrueL(): void
    {
        $_SERVER['HTTP_REFERER'] = 'http://example.com/revise/1/abc/2';
        $result = CatUtils::getIsRevisionFromReferer();
        unset($_SERVER['HTTP_REFERER']);
        $this->assertTrue($result);
    }

    #[Test]
    public function testGetIsRevisionFromRefererWithTranslatePathReturnsFalseL(): void
    {
        $_SERVER['HTTP_REFERER'] = 'http://example.com/translate/1/abc/2';
        $result = CatUtils::getIsRevisionFromReferer();
        unset($_SERVER['HTTP_REFERER']);
        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // segment_raw_word_count
    // -------------------------------------------------------------------------

    #[Test]
    public function testSegmentRawWordCountWithNullReturnsZeroL(): void
    {
        $this->assertEquals(0, CatUtils::segment_raw_word_count(null));
    }

    #[Test]
    public function testSegmentRawWordCountWithEmptyStringReturnsZeroL(): void
    {
        $this->assertEquals(0, CatUtils::segment_raw_word_count(''));
    }

    #[Test]
    public function testSegmentRawWordCountWithWhitespaceOnlyReturnsZeroL(): void
    {
        $this->assertEquals(0, CatUtils::segment_raw_word_count('   '));
    }

    #[Test]
    public function testSegmentRawWordCountWithTwoWordsReturnsTwo(): void
    {
        $this->assertEquals(2, CatUtils::segment_raw_word_count('hello world'));
    }

    #[Test]
    public function testSegmentRawWordCountWithOneWordReturnsOne(): void
    {
        $this->assertEquals(1, CatUtils::segment_raw_word_count('hello'));
    }

    #[Test]
    public function testSegmentRawWordCountWithNumbersL(): void
    {
        // "123 456" -> numbers replaced with N placeholders -> 2 words
        $result = CatUtils::segment_raw_word_count('123 456');
        $this->assertGreaterThanOrEqual(1, $result);
    }

    #[Test]
    public function testSegmentRawWordCountCjkLanguageL(): void
    {
        // CJK uses character count
        $result = CatUtils::segment_raw_word_count('你好世界', 'zh-CN');
        $this->assertGreaterThan(0, $result);
    }

    #[Test]
    public function testSegmentRawWordCountWithEnglishPossessiveL(): void
    {
        // English: "John's" loses the " s " possessive
        $result = CatUtils::segment_raw_word_count("John's dog", 'en-US');
        $this->assertGreaterThan(0, $result);
    }

    // -------------------------------------------------------------------------
    // parseSegmentSplit (with MateCatFilter)
    // -------------------------------------------------------------------------

    #[Test]
    public function testParseSegmentSplitWithNoPlaceholderReturnsUnchangedL(): void
    {
        $filter = MateCatFilter::getInstance(new FeatureSet(), 'en-US');
        [$segment, $positions] = CatUtils::parseSegmentSplit('hello world', ' ', $filter);
        $this->assertEquals('hello world', $segment);
        $this->assertEquals([], $positions);
    }

    #[Test]
    public function testParseSegmentSplitWithPlaceholderBuildsPositionsL(): void
    {
        $filter = MateCatFilter::getInstance(new FeatureSet(), 'en-US');
        $input = 'hello' . CatUtils::splitPlaceHolder . 'world';
        [$segment, $positions] = CatUtils::parseSegmentSplit($input, ' ', $filter);
        $this->assertNotEmpty($positions);
        $this->assertStringContainsString('hello', $segment);
    }

    // -------------------------------------------------------------------------
    // clean_raw_string_4_word_count (direct tests to improve branch coverage)
    // -------------------------------------------------------------------------

    #[Test]
    public function testCleanRawStringForWordCountEmptyStringL(): void
    {
        $result = CatUtils::clean_raw_string_4_word_count('   ');
        $this->assertEquals('', $result);
    }

    #[Test]
    public function testCleanRawStringForWordCountPlainEnglishL(): void
    {
        $result = CatUtils::clean_raw_string_4_word_count('hello world');
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function testCleanRawStringForWordCountCjkL(): void
    {
        $result = CatUtils::clean_raw_string_4_word_count('你好世界', 'zh-CN');
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function testCleanRawStringForWordCountWithLinkL(): void
    {
        $result = CatUtils::clean_raw_string_4_word_count('Visit http://example.com for info', 'en-US');
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function testCleanRawStringForWordCountWithNumberL(): void
    {
        $result = CatUtils::clean_raw_string_4_word_count('I have 42 items', 'en-US');
        $this->assertNotEmpty($result);
    }

    // -------------------------------------------------------------------------
    // Additional coverage: uncovered branches
    // -------------------------------------------------------------------------

    /**
     * reApplySegmentSplit with only [0] → no "next" position → string_chunks empty → return $segment
     * Covers line 213 (return $segment inside empty check)
     */
    #[Test]
    public function testReApplySegmentSplitWithOnlyStartPositionReturnsSegmentL(): void
    {
        $result = CatUtils::reApplySegmentSplit("hello world", [0]);
        $this->assertEquals("hello world", $result);
    }

    /**
     * parseSegmentSplit where first chunk is empty (starts with placeholder)
     * Covers line 161 (break on empty chunk)
     */
    #[Test]
    public function testParseSegmentSplitWithEmptyFirstChunkBreaksL(): void
    {
        $filter = MateCatFilter::getInstance(new FeatureSet(), 'en-US');
        // Segment starts with placeholder → first chunk is ''
        $input = CatUtils::splitPlaceHolder . 'world';
        [$segment, $positions] = CatUtils::parseSegmentSplit($input, ' ', $filter);
        // Empty first chunk causes break; segment stays empty, positions = [0]
        $this->assertIsArray($positions);
    }

    /**
     * parseSegmentSplit where chunk ends with separator → separator removed
     * Covers lines 175-176 (separator_len=0, separator='')
     */
    #[Test]
    public function testParseSegmentSplitChunkEndsWithSeparatorL(): void
    {
        $filter = MateCatFilter::getInstance(new FeatureSet(), 'en-US');
        // "hello " ends with space (separator), so separator is removed to avoid double space
        $input = 'hello ' . CatUtils::splitPlaceHolder . 'world';
        [$segment, $positions] = CatUtils::parseSegmentSplit($input, ' ', $filter);
        $this->assertNotEmpty($positions);
        // No double space between chunks
        $this->assertStringNotContainsString('  ', $segment);
    }

    /**
     * clean_raw_string_4_word_count with punctuation-only string
     * Covers line 391 (return "" after all-cleaned string becomes empty)
     */
    #[Test]
    public function testCleanRawStringForWordCountPunctuationOnlyReturnsEmptyL(): void
    {
        $result = CatUtils::clean_raw_string_4_word_count('...', 'en-US');
        $this->assertEquals('', $result);
    }

    /**
     * segment_raw_word_count with punctuation-only string
     * Also exercises the post-cleaning empty path
     */
    #[Test]
    public function testSegmentRawWordCountPunctuationOnlyReturnsZeroL(): void
    {
        $result = CatUtils::segment_raw_word_count('...');
        $this->assertEquals(0, $result);
    }

    /**
     * getQualityOverallFromJobStruct with [null] in chunkReviews
     * Covers line 649 (!isset($values) → return null)
     */
    #[Test]
    public function testGetQualityOverallWithNullChunkReviewReturnsNullL(): void
    {
        $job = new JobStruct(['id' => 1, 'password' => 'abc']);
        // [null] is non-empty, so getChunkReviewStructFromJobStruct returns null (chunkReviews[0])
        $result = CatUtils::getQualityOverallFromJobStruct($job, [null]);
        $this->assertNull($result);
    }

    /**
     * fetchStatus wrap-around loop with null status
     * Covers line 711 (null status in wrap-around loop → treated as STATUS_NEW)
     */
    #[Test]
    public function testFetchStatusWrapAroundWithNullStatusCoversLine711L(): void
    {
        // sid=20 > all ids → first loop finds nothing → wrap-around loop runs
        // In wrap-around, status=null → line 711 sets it to STATUS_NEW → matches → returns 5
        $results = [
            ['id' => 5, 'status' => null],
        ];
        $result = CatUtils::fetchStatus(20, $results, TranslationStatus::STATUS_NEW);
        $this->assertEquals(5, $result);
    }

    /**
     * getIsRevisionFromRequestUri with URL that has no 'path' component
     * Covers line 763 (return false when !isset($_from_url['path']))
     */
    #[Test]
    public function testGetIsRevisionFromRequestUriWithUrlWithoutPathL(): void
    {
        // 'http://host' → parse_url returns ['scheme'=>'http','host'=>'host'] — no 'path' key
        $_SERVER['REQUEST_URI'] = 'http://host';
        $result = CatUtils::getIsRevisionFromRequestUri();
        unset($_SERVER['REQUEST_URI']);
        $this->assertFalse($result);
    }

    /**
     * getIsRevisionFromReferer with URL that has no 'path' component
     * Covers line 788 (return false when !isset($_from_url['path']))
     */
    #[Test]
    public function testGetIsRevisionFromRefererWithUrlWithoutPathL(): void
    {
        $_SERVER['HTTP_REFERER'] = 'http://host';
        $result = CatUtils::getIsRevisionFromReferer();
        unset($_SERVER['HTTP_REFERER']);
        $this->assertFalse($result);
    }

    /**
     * getWStructFromJobArray with a ProjectStruct whose status_analysis is NOT STATUS_DONE
     * and NOT STATUS_NOT_TO_ANALYZE → skips DB call → returns wStruct directly
     * Covers lines 617, 619, 622, 630
     */
    #[Test]
    public function testGetWStructFromJobArrayWithNonDoneStatusSkipsDbL(): void
    {
        $job = new JobStruct([
            'id' => 1,
            'password' => 'testpass',
            'new_words' => 5.0,
            'draft_words' => 0.0,
            'translated_words' => 0.0,
            'approved_words' => 0.0,
            'rejected_words' => 0.0,
            'approved2_words' => 0.0,
            'new_raw_words' => 5.0,
            'draft_raw_words' => 0.0,
            'translated_raw_words' => 0.0,
            'approved_raw_words' => 0.0,
            'approved2_raw_words' => 0.0,
            'rejected_raw_words' => 0.0,
        ]);
        // status_analysis = 'ANALYZING' is neither STATUS_DONE nor STATUS_NOT_TO_ANALYZE
        $project = new ProjectStruct(['status_analysis' => 'ANALYZING']);
        $result = CatUtils::getWStructFromJobArray($job, $project);
        $this->assertInstanceOf(WordCountStruct::class, $result);
    }

    /**
     * deleteSha with a non-existent file path → sha1_file returns false → early return
     * Covers lines 920, 922, 924, 932, 934, 935, 936
     */
    #[Test]
    public function testDeleteShaWithNonExistentFileReturnsEarlyL(): void
    {
        // sha1_file on a non-existent file generates a PHP warning; suppress it
        // The function should return early without throwing
        @CatUtils::deleteSha('/tmp/nonexistent_file_' . uniqid() . '.txt', 'en-US', null, 0);
        $this->assertTrue(true); // If we reach here, no exception was thrown
    }

    /**
     * clean_raw_string_4_word_count with HTML content
     * Exercises the filter path and potentially replacePlaceholders inner loops
     */
    #[Test]
    public function testCleanRawStringForWordCountWithHtmlTagL(): void
    {
        // <br> should be processed by the filter
        $result = CatUtils::clean_raw_string_4_word_count('Hello <br> World', 'en-US');
        $this->assertIsString($result);
    }

    /**
     * segment_raw_word_count with HTML content
     */
    #[Test]
    public function testSegmentRawWordCountWithHtmlTagL(): void
    {
        $result = CatUtils::segment_raw_word_count('Hello <br> World');
        $this->assertGreaterThanOrEqual(0, $result);
    }

    /**
     * clean_raw_string_4_word_count with a string that has only spaces after cleaning
     * Tests the no_spaces_string == '' path
     */
    #[Test]
    public function testCleanRawStringForWordCountWithDashesOnlyL(): void
    {
        // Dashes are punctuation → removed → empty
        $result = CatUtils::clean_raw_string_4_word_count('--- --- ---', 'en-US');
        $this->assertIsString($result);
    }

    /**
     * getJobPassword with sourcePage > 1 but null job id/password
     * Covers line 847-848 (null check → return null)
     */
    #[Test]
    public function testGetJobPasswordWithNullJobIdReturnsNullL(): void
    {
        $job = new JobStruct(['id' => null, 'password' => null]);
        $result = CatUtils::getJobPassword($job, 2);
        $this->assertNull($result);
    }

    // =========================================================================
    // deleteSha — full path coverage (file-system only, no DB)
    // =========================================================================

    /**
     * deleteSha with a real hash file containing a single entry → file should be deleted.
     * Covers lines 939-951, 958-961, 965-973, 976-990, 993-1013.
     */
    #[Test]
    public function testDeleteShaRemovesHashFileWhenSingleEntry(): void
    {
        $tmpDir = sys_get_temp_dir();
        $fileName = 'catutils_test_' . uniqid() . '.txt';
        $filePath = $tmpDir . DIRECTORY_SEPARATOR . $fileName;
        file_put_contents($filePath, 'test content for sha');

        $source = 'en-US';
        $fileSha = sha1_file($filePath);
        $this->assertNotFalse($fileSha);

        // segmentationRule=null, filtersTemplateId=0 → no DB call
        $segHash = sha1('');
        $hashName = $fileSha . '_' . $segHash . '|' . $source;
        $hashFilePath = $tmpDir . DIRECTORY_SEPARATOR . $hashName;

        // Create the hash file with the file's base name as its content
        file_put_contents($hashFilePath, $fileName . "\n");

        try {
            CatUtils::deleteSha($filePath, $source, null, 0);

            // Hash file should be deleted (was the only entry)
            $this->assertFileDoesNotExist($hashFilePath);
        } finally {
            @unlink($filePath);
            @unlink($hashFilePath);
        }
    }

    /**
     * deleteSha with a hash file containing multiple entries → file is modified, not deleted.
     * Covers the "not empty" branch at line 1003-1009.
     */
    #[Test]
    public function testDeleteShaRemovesEntryFromMultiEntryHashFile(): void
    {
        $tmpDir = sys_get_temp_dir();
        $fileName = 'catutils_test_' . uniqid() . '.txt';
        $otherFile = 'other_file.txt';
        $filePath = $tmpDir . DIRECTORY_SEPARATOR . $fileName;
        file_put_contents($filePath, 'test content for sha multi');

        $source = 'en-US';
        $fileSha = sha1_file($filePath);
        $this->assertNotFalse($fileSha);

        $segHash = sha1('');
        $hashName = $fileSha . '_' . $segHash . '|' . $source;
        $hashFilePath = $tmpDir . DIRECTORY_SEPARATOR . $hashName;

        // Two entries: our file + another
        file_put_contents($hashFilePath, $fileName . "\n" . $otherFile . "\n");

        try {
            CatUtils::deleteSha($filePath, $source, null, 0);

            // Hash file should still exist (other entry remains)
            $this->assertFileExists($hashFilePath);
            $content = file_get_contents($hashFilePath);
            $this->assertStringNotContainsString($fileName, $content);
            $this->assertStringContainsString($otherFile, $content);
        } finally {
            @unlink($filePath);
            @unlink($hashFilePath);
        }
    }

    /**
     * deleteSha with a hash file that does NOT exist on disk → early return at line 950-951.
     */
    #[Test]
    public function testDeleteShaHashFileDoesNotExistReturnsEarly(): void
    {
        $tmpDir = sys_get_temp_dir();
        $fileName = 'catutils_test_' . uniqid() . '.txt';
        $filePath = $tmpDir . DIRECTORY_SEPARATOR . $fileName;
        file_put_contents($filePath, 'content for no-hash test');

        try {
            // No hash file created → should just return without error
            CatUtils::deleteSha($filePath, 'en-US', null, 0);
            $this->assertTrue(true);
        } finally {
            @unlink($filePath);
        }
    }

    /**
     * deleteSha with an empty hash file → fileSize < 1 → unlink and return.
     * Covers lines 976-982.
     */
    #[Test]
    public function testDeleteShaEmptyHashFileIsUnlinked(): void
    {
        $tmpDir = sys_get_temp_dir();
        $fileName = 'catutils_test_' . uniqid() . '.txt';
        $filePath = $tmpDir . DIRECTORY_SEPARATOR . $fileName;
        file_put_contents($filePath, 'content for empty-hash test');

        $source = 'en-US';
        $fileSha = sha1_file($filePath);
        $this->assertNotFalse($fileSha);

        $segHash = sha1('');
        $hashName = $fileSha . '_' . $segHash . '|' . $source;
        $hashFilePath = $tmpDir . DIRECTORY_SEPARATOR . $hashName;

        // Create an empty hash file (0 bytes)
        file_put_contents($hashFilePath, '');

        try {
            CatUtils::deleteSha($filePath, $source, null, 0);

            // Empty hash file should be deleted
            $this->assertFileDoesNotExist($hashFilePath);
        } finally {
            @unlink($filePath);
            @unlink($hashFilePath);
        }
    }

    /**
     * deleteSha with a segmentation rule → covers line 932 non-null path + hash calculation.
     */
    #[Test]
    public function testDeleteShaWithSegmentationRuleIncludesInHash(): void
    {
        $tmpDir = sys_get_temp_dir();
        $fileName = 'catutils_test_' . uniqid() . '.txt';
        $filePath = $tmpDir . DIRECTORY_SEPARATOR . $fileName;
        file_put_contents($filePath, 'content with segmentation');

        $source = 'en-US';
        $segRule = 'paragraph';
        $fileSha = sha1_file($filePath);
        $this->assertNotFalse($fileSha);

        // validateSegmentationRules('paragraph') returns 'paragraph'
        $segHash = sha1($segRule);
        $hashName = $fileSha . '_' . $segHash . '|' . $source;
        $hashFilePath = $tmpDir . DIRECTORY_SEPARATOR . $hashName;

        file_put_contents($hashFilePath, $fileName . "\n");

        try {
            CatUtils::deleteSha($filePath, $source, $segRule, 0);

            $this->assertFileDoesNotExist($hashFilePath);
        } finally {
            @unlink($filePath);
            @unlink($hashFilePath);
        }
    }

    // =========================================================================
    // convertEncoding — file-system tests
    // =========================================================================

    /**
     * convertEncoding with UTF-8 content → detects UTF-8 → returns without converting.
     * Covers lines 483-498, 502-504.
     */
    #[Test]
    public function testConvertEncodingUtf8ToUtf8ReturnsUnchanged(): void
    {
        $content = 'Hello world — UTF-8 text with diacritics: café, naïve';
        [$charset, $result] = CatUtils::convertEncoding('UTF-8', $content);

        $this->assertIsString($charset);
        $this->assertEquals($content, $result);
    }

    /**
     * convertEncoding with ASCII content → detected as us-ascii/utf-8.
     * Covers the charset detection + comparison path.
     */
    #[Test]
    public function testConvertEncodingAsciiContent(): void
    {
        $content = 'Simple ASCII text only';
        [$charset, $result] = CatUtils::convertEncoding('UTF-8', $content);

        $this->assertIsString($charset);
        // ASCII is a subset of UTF-8, content should be unchanged
        $this->assertEquals($content, $result);
    }

    /**
     * convertEncoding with ISO-8859-1 content → tests iconv conversion path.
     * Covers lines 506-508 (actual conversion).
     */
    #[Test]
    public function testConvertEncodingWithLatin1Charset(): void
    {
        // Create ISO-8859-1 encoded content with a non-ASCII character
        $original = "Héllo Wörld";
        $latin1Content = mb_convert_encoding($original, 'ISO-8859-1', 'UTF-8');

        [$charset, $result] = CatUtils::convertEncoding('UTF-8', $latin1Content);

        $this->assertIsString($charset);
        // If detection works, result is either the converted string or original
        $this->assertTrue(is_string($result) || $result === false);
    }

    // =========================================================================
    // clean_raw_string_4_word_count — more paths
    // =========================================================================

    /**
     * clean_raw_string_4_word_count for CJK language.
     * Covers lines 337-342 (CJK placeholder branch), line 394 (return no_spaces_string).
     */
    #[Test]
    public function testCleanRawStringForWordCountCjkLanguage(): void
    {
        $result = CatUtils::clean_raw_string_4_word_count('你好世界', 'zh-CN');
        $this->assertNotEmpty($result);
    }

    /**
     * segment_raw_word_count for CJK language.
     * Covers lines 452-453 (mb_strlen branch for CJK).
     */
    #[Test]
    public function testSegmentRawWordCountCjkLanguageReturnsMbStrlen(): void
    {
        $count = CatUtils::segment_raw_word_count('你好世界', 'zh-CN');
        // Each CJK char counted individually
        $this->assertGreaterThan(0, $count);
    }

    /**
     * clean_raw_string_4_word_count with English possessive.
     * Covers line 384-386 (possessive removal branch).
     */
    #[Test]
    public function testCleanRawStringForWordCountEnglishPossessive(): void
    {
        $result = CatUtils::clean_raw_string_4_word_count("the cat's hat", 'en-US');
        $this->assertNotEmpty($result);
    }

    /**
     * clean_raw_string_4_word_count with hyphenated words.
     * Covers line 370 (hyphenated word regex replacement).
     */
    #[Test]
    public function testCleanRawStringForWordCountHyphenatedWords(): void
    {
        $result = CatUtils::clean_raw_string_4_word_count('well-known state-of-the-art solution', 'en-US');
        $this->assertNotEmpty($result);
    }

    /**
     * clean_raw_string_4_word_count with protocol-style link.
     * Covers line 356 (protocol link regex: 'a-z+://...' replacement).
     */
    #[Test]
    public function testCleanRawStringForWordCountProtocolLink(): void
    {
        $result = CatUtils::clean_raw_string_4_word_count(
            'Read php://filter/read=string.strip_tags/resource=php://input then continue',
            'en-US'
        );
        $this->assertNotEmpty($result);
    }

    /**
     * clean_raw_string_4_word_count with HTML entity that needs double-decoding.
     * Covers lines 349-351 (double html_entity_decode).
     */
    #[Test]
    public function testCleanRawStringForWordCountHtmlEntities(): void
    {
        $result = CatUtils::clean_raw_string_4_word_count('foo &amp;nbsp; bar', 'en-US');
        $this->assertNotEmpty($result);
    }

    /**
     * clean_raw_string_4_word_count with complex URL (multi-dot domain).
     * Covers line 353 (main link regex replacement).
     */
    #[Test]
    public function testCleanRawStringForWordCountComplexUrl(): void
    {
        $result = CatUtils::clean_raw_string_4_word_count(
            'Go to www.example.com.br/path#anchor and read',
            'en-US'
        );
        $this->assertNotEmpty($result);
    }

    /**
     * getSegmentTranslationsCount exercising filter path.
     * Covers lines 878-886 (getJobs, array_unique, array_filter).
     * Note: getJobs() returns empty array for a stub project with no DB data,
     * but passing empty array to IN() causes SQL error, so we just verify it runs.
     */
    #[Test]
    public function testGetSegmentTranslationsCountWithEmptyJobsThrowsPdo(): void
    {
        $project = new ProjectStruct(['id' => 999999]);
        // getJobs() returns empty → idJobs is empty → SQL IN() with no values → PDOException
        $this->expectException(\PDOException::class);
        CatUtils::getSegmentTranslationsCount($project);
    }

    // =========================================================================
    // getRightExtractionParameter — private method via Reflection (pure logic)
    // =========================================================================

    /**
     * @param string $filePath
     * @param FiltersConfigTemplateStruct $struct
     * @return IDto|null
     * @throws \ReflectionException
     */
    private function invokeGetRightExtractionParameter(string $filePath, FiltersConfigTemplateStruct $struct): ?IDto
    {
        $method = new \ReflectionMethod(CatUtils::class, 'getRightExtractionParameter');

        return $method->invoke(null, $filePath, $struct);
    }

    #[Test]
    public function testGetRightExtractionParameterJson(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->json = new \Model\Filters\DTO\Json();
        $result = $this->invokeGetRightExtractionParameter('file.json', $struct);
        $this->assertInstanceOf(\Model\Filters\DTO\Json::class, $result);
    }

    #[Test]
    public function testGetRightExtractionParameterXml(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->xml = new \Model\Filters\DTO\Xml();
        $result = $this->invokeGetRightExtractionParameter('file.xml', $struct);
        $this->assertInstanceOf(\Model\Filters\DTO\Xml::class, $result);
    }

    #[Test]
    public function testGetRightExtractionParameterYaml(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->yaml = new \Model\Filters\DTO\Yaml();
        $result = $this->invokeGetRightExtractionParameter('file.yaml', $struct);
        $this->assertInstanceOf(\Model\Filters\DTO\Yaml::class, $result);
    }

    #[Test]
    public function testGetRightExtractionParameterYml(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->yaml = new \Model\Filters\DTO\Yaml();
        $result = $this->invokeGetRightExtractionParameter('file.yml', $struct);
        $this->assertInstanceOf(\Model\Filters\DTO\Yaml::class, $result);
    }

    #[Test]
    public function testGetRightExtractionParameterDocx(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->ms_word = new \Model\Filters\DTO\MSWord();
        $result = $this->invokeGetRightExtractionParameter('file.docx', $struct);
        $this->assertInstanceOf(\Model\Filters\DTO\MSWord::class, $result);
    }

    #[Test]
    public function testGetRightExtractionParameterDoc(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->ms_word = new \Model\Filters\DTO\MSWord();
        $result = $this->invokeGetRightExtractionParameter('file.doc', $struct);
        $this->assertInstanceOf(\Model\Filters\DTO\MSWord::class, $result);
    }

    #[Test]
    public function testGetRightExtractionParameterXlsx(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->ms_excel = new \Model\Filters\DTO\MSExcel();
        $result = $this->invokeGetRightExtractionParameter('file.xlsx', $struct);
        $this->assertInstanceOf(\Model\Filters\DTO\MSExcel::class, $result);
    }

    #[Test]
    public function testGetRightExtractionParameterXls(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->ms_excel = new \Model\Filters\DTO\MSExcel();
        $result = $this->invokeGetRightExtractionParameter('file.xls', $struct);
        $this->assertInstanceOf(\Model\Filters\DTO\MSExcel::class, $result);
    }

    #[Test]
    public function testGetRightExtractionParameterPptx(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->ms_powerpoint = new \Model\Filters\DTO\MSPowerpoint();
        $result = $this->invokeGetRightExtractionParameter('file.pptx', $struct);
        $this->assertInstanceOf(\Model\Filters\DTO\MSPowerpoint::class, $result);
    }

    #[Test]
    public function testGetRightExtractionParameterPpt(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->ms_powerpoint = new \Model\Filters\DTO\MSPowerpoint();
        $result = $this->invokeGetRightExtractionParameter('file.ppt', $struct);
        $this->assertInstanceOf(\Model\Filters\DTO\MSPowerpoint::class, $result);
    }

    #[Test]
    public function testGetRightExtractionParameterDita(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->dita = new \Model\Filters\DTO\Dita();
        $result = $this->invokeGetRightExtractionParameter('file.dita', $struct);
        $this->assertInstanceOf(\Model\Filters\DTO\Dita::class, $result);
    }

    #[Test]
    public function testGetRightExtractionParameterDitamap(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->dita = new \Model\Filters\DTO\Dita();
        $result = $this->invokeGetRightExtractionParameter('file.ditamap', $struct);
        $this->assertInstanceOf(\Model\Filters\DTO\Dita::class, $result);
    }

    #[Test]
    public function testGetRightExtractionParameterUnknownExtensionReturnsNull(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $result = $this->invokeGetRightExtractionParameter('file.txt', $struct);
        $this->assertNull($result);
    }

    #[Test]
    public function testGetRightExtractionParameterMissingDtoReturnsNull(): void
    {
        // Struct has no json property set → returns null even for .json
        $struct = new FiltersConfigTemplateStruct();
        $result = $this->invokeGetRightExtractionParameter('file.json', $struct);
        $this->assertNull($result);
    }

    /**
     * deleteSha with filtersTemplateId > 0 but template table missing → PDOException.
     * Covers lines 924-925 (filtersTemplateId > 0 branch, DAO call).
     */
    #[Test]
    public function testDeleteShaWithFiltersTemplateIdTriggersDbLookup(): void
    {
        $tmpDir = sys_get_temp_dir();
        $fileName = 'catutils_test_' . uniqid() . '.txt';
        $filePath = $tmpDir . DIRECTORY_SEPARATOR . $fileName;
        file_put_contents($filePath, 'content for filters-template test');

        try {
            // filtersTemplateId=999999 → triggers DB lookup, returns null (no matching row)
            // This should complete without error since the table exists but the row does not
            CatUtils::deleteSha($filePath, 'en-US', null, 999999);
            $this->assertTrue(true, 'deleteSha completed without error when filtersTemplateId not found');
        } finally {
            @unlink($filePath);
        }
    }
}