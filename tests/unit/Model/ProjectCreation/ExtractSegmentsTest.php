<?php

namespace unit\Model\ProjectCreation;

use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use Model\ProjectCreation\TranslationTuple;
use Model\Segments\SegmentStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

/**
 * Unit tests for {@see \Model\ProjectCreation\ProjectManager::extractSegments()}.
 *
 * These tests provide a safety net before refactoring the duplicated code
 * in the seg-source and non-seg-source branches.
 *
 * Test coverage breakdown:
 *
 * | Area                   | Tests | What's covered                                                                                                                   |
 * |------------------------|-------|----------------------------------------------------------------------------------------------------------------------------------|
 * | **Seg-source path**    | 11    | Segment count, mrk IDs, internal ID, word count, show_in_cattool, translations, original data, metadata, sizeRestriction,        |
 * |                        |       | segment hash, content filtering, file ID                                                                                         |
 * | **Non-seg-source path**| 8     | Segment count, null mrk fields, word count, show_in_cattool, translations, metadata, sizeRestriction, internal ID,               |
 * |                        |       | content filtering, file ID                                                                                                       |
 * | **Notes & context**    | 4     | Note extraction, structure, `from` attribute, context-group                                                                      |
 * | **Edge cases**         | 8     | Empty file exception, xliff-info population, segment hash format, existing sdlxliff fixture with mrk+notes                      |
 *
 * @see REFACTORING_PLAN.md — Step 0
 */
class ExtractSegmentsTest extends AbstractTest
{
    private const string FIXTURES_DIR = TEST_DIR . '/resources/files/xliff/';

    private TestableProjectManager $pm;
    private FeatureSet $featureSet;
    private MateCatFilter $filter;
    private MetadataDao $metadataDao;
    private MatecatLogger $logger;
    private string $originalFileStorageMethod;

    /**
     * @throws Exception
     */
    public function setUp(): void
    {
        parent::setUp();

        // Force local filesystem mode to avoid S3 calls in getXliffFileContent
        $this->originalFileStorageMethod = AppConfig::$FILE_STORAGE_METHOD;
        AppConfig::$FILE_STORAGE_METHOD  = 'fs';

        $this->featureSet = new FeatureSet();
        /** @var MatecatFilter $filter */
        $filter = MateCatFilter::getInstance($this->featureSet, 'en-US', 'it-IT');
        $this->filter = $filter;
        $this->metadataDao = $this->createStub(MetadataDao::class);
        $this->logger = $this->createStub(MatecatLogger::class);

        $this->pm = new TestableProjectManager();
        $this->pm->initForTest(
            $this->filter,
            $this->featureSet,
            $this->metadataDao,
            $this->logger,
        );
    }

    public function tearDown(): void
    {
        // Restore the original storage method
        AppConfig::$FILE_STORAGE_METHOD = $this->originalFileStorageMethod;
        parent::tearDown();
    }

    // =========================================================================
    // 0a. Seg-source path tests
    // =========================================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function testSegSourceCreatesCorrectNumberOfSegments(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'seg-source-simple.xliff',
            'original_filename' => 'seg-source-simple.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();

        // The file has 1 trans-unit with 2 <mrk> segments
        $segments = $ps->segments[$fid];
        $this->assertCount(2, $segments);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testSegSourceSegmentsHaveCorrectMrkIds(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'seg-source-simple.xliff',
            'original_filename' => 'seg-source-simple.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();
        $segments = $ps->segments[$fid];

        /** @var SegmentStruct $seg0 */
        $seg0 = $segments[0];
        /** @var SegmentStruct $seg1 */
        $seg1 = $segments[1];

        $this->assertEquals('0', $seg0->xliff_mrk_id);
        $this->assertEquals('1', $seg1->xliff_mrk_id);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testSegSourceSegmentsHaveCorrectInternalId(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'seg-source-simple.xliff',
            'original_filename' => 'seg-source-simple.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();
        $segments = $ps->segments[$fid];

        /** @var SegmentStruct $seg0 */
        $seg0 = $segments[0];
        $this->assertEquals('tu1', $seg0->internal_id);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testSegSourceWordCountIsPositive(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'seg-source-simple.xliff',
            'original_filename' => 'seg-source-simple.xliff',
        ]);

        $this->assertGreaterThan(0, $this->pm->getFilesWordCount());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testSegSourceShowInCattoolCounterIncremented(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'seg-source-simple.xliff',
            'original_filename' => 'seg-source-simple.xliff',
        ]);

        // Both segments have content, so show_in_cattool should be 2
        $this->assertEquals(2, $this->pm->getShowInCattoolSegsCounter());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testSegSourceTotalSegmentsIncremented(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'seg-source-simple.xliff',
            'original_filename' => 'seg-source-simple.xliff',
        ]);

        // 1 trans-unit in the file
        $this->assertEquals(1, $this->pm->getTotalSegments());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testSegSourcePreTranslationsAreStored(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'seg-source-simple.xliff',
            'original_filename' => 'seg-source-simple.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();

        // The trans-unit has both source and target with mrk tags
        // Translations should be stored with the unit reference key
        $translations = $ps->translations;
        $this->assertGreaterThan(0, count($translations));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testSegSourceSegmentsOriginalDataCreated(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'seg-source-simple.xliff',
            'original_filename' => 'seg-source-simple.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();

        // segments-original-data should have entries for this fid
        $this->assertArrayHasKey($fid, $ps->segments_original_data);

        // seg-source branch always appends a SegmentOriginalDataStruct for each mrk
        $originalData = $ps->segments_original_data[$fid];
        $this->assertCount(2, $originalData);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testSegSourceSegmentsMetaDataCreated(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'seg-source-simple.xliff',
            'original_filename' => 'seg-source-simple.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();

        // segments-meta-data should have entries for this fid (one per mrk)
        $this->assertArrayHasKey($fid, $ps->segments_meta_data);
        $this->assertCount(2, $ps->segments_meta_data[$fid]);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testSegSourceWithSizeRestrictionStoresMetadata(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'seg-source-with-size-restriction.xliff',
            'original_filename' => 'seg-source-with-size-restriction.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();
        $metaData = $ps->segments_meta_data[$fid];

        // Both mrk segments should have sizeRestriction metadata
        $foundSizeRestriction = false;
        foreach ($metaData as $meta) {
            if (isset($meta->meta_key) && $meta->meta_key === 'sizeRestriction') {
                $foundSizeRestriction = true;
                $this->assertEquals('80', $meta->meta_value);
            }
        }

        $this->assertTrue($foundSizeRestriction, 'sizeRestriction metadata not found');
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testSegSourceSegmentHashDiffersWithSizeRestriction(): void
    {
        // Extract without sizeRestriction
        $fid1 = 1;
        $this->pm->callExtractSegments($fid1, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'seg-source-simple.xliff',
            'original_filename' => 'seg-source-simple.xliff',
        ]);

        $hashWithout = $this->pm->getTestProjectStructure()->segments[$fid1][0]->segment_hash;

        // Create a new PM instance for the second test
        $pm2 = new TestableProjectManager();
        $pm2->initForTest($this->filter, $this->featureSet, $this->metadataDao, $this->logger);

        $fid2 = 2;
        $pm2->callExtractSegments($fid2, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'seg-source-with-size-restriction.xliff',
            'original_filename' => 'seg-source-with-size-restriction.xliff',
        ]);

        $hashWith = $pm2->getTestProjectStructure()->segments[$fid2][0]->segment_hash;

        // Same source text but different sizeRestriction should produce different hashes
        $this->assertNotEquals($hashWithout, $hashWith);
    }

    /**
     * Verify that seg-source translations are keyed by mrk ID (not numeric).
     * This locks behavior before extracting resolveAndStorePreTranslation().
     *
     * @throws Exception
     */
    #[Test]
    public function testSegSourceTranslationsKeyedByMrkId(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'seg-source-simple.xliff',
            'original_filename' => 'seg-source-simple.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();

        // The reference key is "{fid}|{tu_id}"
        $unitRef = $fid . '|tu1';
        $this->assertArrayHasKey($unitRef, $ps->translations);

        $tuplesByMrk = $ps->translations[$unitRef];

        // mrk mid="0" and mid="1" should be used as keys
        $this->assertArrayHasKey('0', $tuplesByMrk, 'mrk mid=0 should be a key');
        $this->assertArrayHasKey('1', $tuplesByMrk, 'mrk mid=1 should be a key');
        $this->assertCount(2, $tuplesByMrk);
    }

    /**
     * Verify that seg-source TranslationTuples have mrkPosition set.
     *
     * @throws Exception
     */
    #[Test]
    public function testSegSourceTranslationTupleHasMrkPosition(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'seg-source-simple.xliff',
            'original_filename' => 'seg-source-simple.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();
        $unitRef = $fid . '|tu1';
        $tuplesByMrk = $ps->translations[$unitRef];

        /** @var TranslationTuple $tuple0 */
        $tuple0 = $tuplesByMrk['0'];
        /** @var TranslationTuple $tuple1 */
        $tuple1 = $tuplesByMrk['1'];

        $this->assertSame(0, $tuple0->mrkPosition);
        $this->assertSame(1, $tuple1->mrkPosition);
    }

    // =========================================================================
    // 0b. Non-seg-source path tests
    // =========================================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function testNoSegSourceCreatesCorrectNumberOfSegments(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'no-seg-source-simple.xliff',
            'original_filename' => 'no-seg-source-simple.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();
        $segments = $ps->segments[$fid];

        // 2 trans-units, each without seg-source → 2 segments
        $this->assertCount(2, $segments);
    }

    /**
     * Verify that non-seg-source translations are appended (numeric key).
     * This locks behavior before extracting resolveAndStorePreTranslation().
     *
     * @throws Exception
     */
    #[Test]
    public function testNoSegSourceTranslationsAppendedWithNumericKey(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'no-seg-source-simple.xliff',
            'original_filename' => 'no-seg-source-simple.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();

        // tu1 has target (state="translated"), tu2 has no target
        $unitRef = $fid . '|tu1';
        $this->assertArrayHasKey($unitRef, $ps->translations);

        $tuples = $ps->translations[$unitRef];

        // Appended with [], so key should be numeric 0
        $this->assertArrayHasKey(0, $tuples);
        $this->assertCount(1, $tuples);
    }

    /**
     * Verify that non-seg-source TranslationTuple has null mrkPosition.
     *
     * @throws Exception
     */
    #[Test]
    public function testNoSegSourceTranslationTupleHasNullMrkPosition(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'no-seg-source-simple.xliff',
            'original_filename' => 'no-seg-source-simple.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();
        $unitRef = $fid . '|tu1';
        $tuples = $ps->translations[$unitRef];

        /** @var TranslationTuple $tuple */
        $tuple = $tuples[0];

        $this->assertNull($tuple->mrkPosition);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testNoSegSourceSegmentsHaveNullMrkId(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'no-seg-source-simple.xliff',
            'original_filename' => 'no-seg-source-simple.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();
        $segments = $ps->segments[$fid];

        /** @var SegmentStruct $seg0 */
        $seg0 = $segments[0];
        $this->assertNull($seg0->xliff_mrk_id);
        $this->assertNull($seg0->xliff_mrk_ext_prec_tags);
        $this->assertNull($seg0->xliff_mrk_ext_succ_tags);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testNoSegSourceWordCountIsPositive(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'no-seg-source-simple.xliff',
            'original_filename' => 'no-seg-source-simple.xliff',
        ]);

        $this->assertGreaterThan(0, $this->pm->getFilesWordCount());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testNoSegSourceShowInCattoolCounterIncremented(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'no-seg-source-simple.xliff',
            'original_filename' => 'no-seg-source-simple.xliff',
        ]);

        // Both trans-units have source content
        $this->assertEquals(2, $this->pm->getShowInCattoolSegsCounter());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testNoSegSourcePreTranslationsStoredForTranslatedTarget(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'no-seg-source-simple.xliff',
            'original_filename' => 'no-seg-source-simple.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();

        // tu1 has target with state="translated", tu2 has no target
        // XliffRulesModel with default rules: "translated" state should be treated as translated
        $translations = $ps->translations;
        $this->assertGreaterThan(0, count($translations));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testNoSegSourceSegmentsMetaDataCreated(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'no-seg-source-simple.xliff',
            'original_filename' => 'no-seg-source-simple.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();
        $this->assertArrayHasKey($fid, $ps->segments_meta_data);
        $this->assertCount(2, $ps->segments_meta_data[$fid]);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testNoSegSourceWithSizeRestrictionStoresMetadata(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'with-size-restriction.xliff',
            'original_filename' => 'with-size-restriction.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();
        $metaData = $ps->segments_meta_data[$fid];

        $foundSizeRestriction = false;
        foreach ($metaData as $meta) {
            if (isset($meta->meta_key) && $meta->meta_key === 'sizeRestriction') {
                $foundSizeRestriction = true;
                $this->assertEquals('100', $meta->meta_value);
            }
        }

        $this->assertTrue($foundSizeRestriction, 'sizeRestriction metadata not found');
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testNoSegSourceSegmentHasCorrectInternalId(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'no-seg-source-simple.xliff',
            'original_filename' => 'no-seg-source-simple.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();
        $segments = $ps->segments[$fid];

        /** @var SegmentStruct $seg0 */
        $seg0 = $segments[0];
        $this->assertEquals('tu1', $seg0->internal_id);

        /** @var SegmentStruct $seg1 */
        $seg1 = $segments[1];
        $this->assertEquals('tu2', $seg1->internal_id);
    }

    // =========================================================================
    // 0b. Notes and context-group tests
    // =========================================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function testNotesAreExtracted(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'with-notes-and-context.xliff',
            'original_filename' => 'with-notes-and-context.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();
        $notes = $ps->notes;

        // Both trans-units have notes
        $this->assertGreaterThan(0, count($notes));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testNotesHaveCorrectStructure(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'with-notes-and-context.xliff',
            'original_filename' => 'with-notes-and-context.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();
        $notes = $ps->notes;

        // tu1 has 2 notes. The key is "{fid}|{tu_id}"
        $tu1Key = $fid . '|tu1';
        $this->assertArrayHasKey($tu1Key, $notes, "Notes key '$tu1Key' should exist");

        $tu1Notes = $notes[$tu1Key];
        $this->assertArrayHasKey('entries', $tu1Notes);
        $this->assertCount(2, $tu1Notes['entries']);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testNotesFromAttributeIsPreserved(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'with-notes-and-context.xliff',
            'original_filename' => 'with-notes-and-context.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();
        $notes = $ps->notes;

        $tu1Key = $fid . '|tu1';
        $tu1From = $notes[$tu1Key]['from']['entries'];

        // First note has no 'from' → 'NO_FROM'
        // Second note has from="developer"
        $this->assertEquals('NO_FROM', $tu1From[0]);
        $this->assertEquals('developer', $tu1From[1]);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testContextGroupIsExtracted(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'with-notes-and-context.xliff',
            'original_filename' => 'with-notes-and-context.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();
        $contextGroup = $ps->context_group;

        $tu1Key = $fid . '|tu1';
        $this->assertArrayHasKey($tu1Key, $contextGroup, "Context-group key '$tu1Key' should exist");

        $tu1Ctx = $contextGroup[$tu1Key];
        $this->assertArrayHasKey('context_json', $tu1Ctx);
        $this->assertArrayHasKey('context_json_segment_ids', $tu1Ctx);
    }

    // =========================================================================
    // Edge cases
    // =========================================================================

    #[Test]
    public function testEmptyFileThrowsException(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'xliff_test_');
        file_put_contents(
            $tmpFile,
            '<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2"><file original="empty.txt" source-language="en-US" target-language="it-IT" datatype="plaintext"><body></body></file></xliff>'
        );

        $this->expectException(Exception::class);
        $this->expectExceptionCode(-1);

        try {
            $this->pm->callExtractSegments(99, [
                'path_cached_xliff' => $tmpFile,
                'original_filename' => 'empty.xliff',
            ]);
        } finally {
            unlink($tmpFile);
        }
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testXliffInfoIsPopulated(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'seg-source-simple.xliff',
            'original_filename' => 'seg-source-simple.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();

        // current-xliff-info should have an entry for this fid
        $this->assertArrayHasKey($fid, $ps->current_xliff_info);
        $this->assertArrayHasKey('version', $ps->current_xliff_info[$fid]);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testSegSourceSegmentContentIsFilteredToLayer0(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'seg-source-simple.xliff',
            'original_filename' => 'seg-source-simple.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();
        $segments = $ps->segments[$fid];

        /** @var SegmentStruct $seg0 */
        $seg0 = $segments[0];

        // The segment content should be a non-empty string (filtered from raw XLIFF to Layer 0)
        $this->assertNotEmpty($seg0->segment);
        $this->assertIsString($seg0->segment);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testNoSegSourceSegmentContentIsFilteredToLayer0(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'no-seg-source-simple.xliff',
            'original_filename' => 'no-seg-source-simple.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();
        $segments = $ps->segments[$fid];

        /** @var SegmentStruct $seg0 */
        $seg0 = $segments[0];

        $this->assertNotEmpty($seg0->segment);
        $this->assertIsString($seg0->segment);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testSegmentHashIsNotEmpty(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'no-seg-source-simple.xliff',
            'original_filename' => 'no-seg-source-simple.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();
        $segments = $ps->segments[$fid];

        /** @var SegmentStruct $seg0 */
        $seg0 = $segments[0];

        $this->assertNotEmpty($seg0->segment_hash);
        $this->assertEquals(32, strlen($seg0->segment_hash), 'segment_hash should be an md5 (32 hex chars)');
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testSegSourceFileIdIsSet(): void
    {
        $fid = 42;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'seg-source-simple.xliff',
            'original_filename' => 'seg-source-simple.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();
        $segments = $ps->segments[$fid];

        /** @var SegmentStruct $seg0 */
        $seg0 = $segments[0];
        $this->assertEquals($fid, $seg0->id_file);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testNoSegSourceFileIdIsSet(): void
    {
        $fid = 42;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'no-seg-source-simple.xliff',
            'original_filename' => 'no-seg-source-simple.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();
        $segments = $ps->segments[$fid];

        /** @var SegmentStruct $seg0 */
        $seg0 = $segments[0];
        $this->assertEquals($fid, $seg0->id_file);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testExistingXliffWithMrkAndNotes(): void
    {
        // Use the existing test fixture that has seg-source with mrk and a note
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'sdlxliff-with-mrk-and-note.xlf.sdlxliff',
            'original_filename' => 'sdlxliff-with-mrk-and-note.xlf.sdlxliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();
        $segments = $ps->segments[$fid];

        // The sdlxliff has 1 trans-unit with 2 mrk segments
        $this->assertCount(2, $segments);

        // Verify notes are extracted
        $notes = $ps->notes;
        $this->assertGreaterThan(0, count($notes));
    }

    // =========================================================================
    // translate="no" handling
    // =========================================================================

    /**
     * totalSegments must count only translatable trans-units.
     * The fixture has 4 trans-units: 2 with translate="yes" (default), 2 with translate="no".
     * Only 2 should be counted.
     *
     * @throws Exception
     */
    #[Test]
    public function testTotalSegmentsExcludesTranslateNoUnits(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'with-translate-no.xliff',
            'original_filename' => 'with-translate-no.xliff',
        ]);

        // 4 trans-units total, but 2 have translate="no" — only 2 should be counted
        $this->assertEquals(2, $this->pm->getTotalSegments());
    }

    /**
     * Segments array should only contain translatable trans-units.
     * translate="no" units must be skipped entirely — no segment structs created.
     *
     * @throws Exception
     */
    #[Test]
    public function testTranslateNoUnitsAreNotExtractedAsSegments(): void
    {
        $fid = 1;
        $this->pm->callExtractSegments($fid, [
            'path_cached_xliff' => self::FIXTURES_DIR . 'with-translate-no.xliff',
            'original_filename' => 'with-translate-no.xliff',
        ]);

        $ps = $this->pm->getTestProjectStructure();
        $segments = $ps->segments[$fid];

        // Only tu1 ("Hello world") and tu3 ("Goodbye world") should produce segments
        $this->assertCount(2, $segments);

        /** @var SegmentStruct $seg0 */
        $seg0 = $segments[0];
        $this->assertEquals('tu1', $seg0->internal_id);

        /** @var SegmentStruct $seg1 */
        $seg1 = $segments[1];
        $this->assertEquals('tu3', $seg1->internal_id);
    }
}

