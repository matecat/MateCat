<?php

namespace unit\Model\ProjectManager;

use ArrayObject;
use Exception;
use Model\DataAccess\IDatabase;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

/**
 * Step 11d/12 — Tests for JobSplitMergeService::getSplitData()
 *
 * Covers:
 *  - Input validation (num_split, requestedWordsPerSplit, VOLUME_ANALYSIS)
 *  - Error conditions (empty rows, missing segment range, insufficient words)
 *  - Count type fallback (eq_word_count → raw_word_count)
 *  - Simple even split and custom word-per-chunk distribution
 *  - Chunk boundary logic (segment_start, segment_end, last_opened_segment)
 *  - Reverse count correction on last chunk
 *  - Too-large first chunk → fewer than 2 chunks error
 *  - Result structure (totals + standard_analysis_count + chunks)
 */
#[AllowMockObjectsWithoutExpectations]
class SplitDataTest extends AbstractTest
{
    private TestableJobSplitMergeService $service;
    private JobDao&MockObject $jobDaoMock;

    protected function setUp(): void
    {
        parent::setUp();

        $dbHandler = $this->createStub(IDatabase::class);
        $features  = $this->createStub(FeatureSet::class);
        $logger    = $this->createStub(MatecatLogger::class);

        $this->service = new TestableJobSplitMergeService($dbHandler, $features, $logger);

        // Mock JobDao for getSplitData() DB calls
        $this->jobDaoMock = $this->createMock(JobDao::class);
        $this->service->setJobDao($this->jobDaoMock);

        // Default JobStruct for getJobByIdAndPassword
        $jobStruct = new JobStruct();
        $jobStruct->id = 100;
        $jobStruct->password = 'abc';
        $jobStruct->id_project = 999;
        $jobStruct->job_first_segment = 1;
        $jobStruct->job_last_segment = 10;
        $jobStruct->source = 'en-US';
        $jobStruct->target = 'it-IT';
        $jobStruct->standard_analysis_wc = 500;
        $this->service->setJobByIdAndPassword($jobStruct);
    }

    /**
     * Helper: create a projectStructure ArrayObject for getSplitData.
     */
    private function makeProjectStructure(): ArrayObject
    {
        return new ArrayObject([
            'job_to_split'      => 100,
            'job_to_split_pass' => 'abc',
            'split_result'      => null,
        ]);
    }

    /**
     * Helper: create a ShapelessConcreteStruct row simulating a DB segment row.
     */
    private function makeRow(
        int $id,
        float $rawWc,
        float $eqWc,
        float $stdWc = 0,
        int $showInCattool = 1,
        ?int $jobFirst = 1,
        ?int $jobLast = 10,
    ): ShapelessConcreteStruct {
        $row = new ShapelessConcreteStruct();
        $row->id = $id;
        $row->raw_word_count = $rawWc;
        $row->eq_word_count = $eqWc;
        $row->standard_word_count = $stdWc;
        $row->show_in_cattool = $showInCattool;
        $row->job_first_segment = $jobFirst;
        $row->job_last_segment = $jobLast;

        return $row;
    }

    /**
     * Helper: create a rollup row (last row from SQL WITH ROLLUP).
     */
    private function makeRollup(
        float $rawTotal,
        float $eqTotal,
        float $stdTotal = 0,
        ?int $jobFirst = 1,
        ?int $jobLast = 10,
    ): ShapelessConcreteStruct {
        $row = new ShapelessConcreteStruct();
        $row->id = null; // rollup row has NULL id
        $row->raw_word_count = $rawTotal;
        $row->eq_word_count = $eqTotal;
        $row->standard_word_count = $stdTotal;
        $row->show_in_cattool = null;
        $row->job_first_segment = $jobFirst;
        $row->job_last_segment = $jobLast;

        return $row;
    }

    // ── Validation errors ──

    #[Test]
    public function throwsWhenNumSplitLessThanTwo(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(-2);
        $this->expectExceptionMessage('Minimum Chunk number for split is 2');

        $this->service->getSplitData($this->makeProjectStructure(), 1);
    }

    #[Test]
    public function throwsWhenRequestedWordsCountMismatchesNumSplit(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(-3);
        $this->expectExceptionMessage('not consistent');

        $this->service->getSplitData($this->makeProjectStructure(), 3, [100, 200]);
    }

    #[Test]
    public function throwsWhenRequestedWordsButVolumeAnalysisDisabled(): void
    {
        $original = AppConfig::$VOLUME_ANALYSIS_ENABLED;

        try {
            AppConfig::$VOLUME_ANALYSIS_ENABLED = false;

            $this->expectException(Exception::class);
            $this->expectExceptionCode(-4);
            $this->expectExceptionMessage('Matecat PRO');

            $this->service->getSplitData($this->makeProjectStructure(), 2, [100, 200]);
        } finally {
            AppConfig::$VOLUME_ANALYSIS_ENABLED = $original;
        }
    }

    #[Test]
    public function throwsWhenNoSegmentsFoundForJob(): void
    {
        $this->jobDaoMock->method('getSplitData')->willReturn([]);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(-5);
        $this->expectExceptionMessage('No segments found');

        $this->service->getSplitData($this->makeProjectStructure());
    }

    #[Test]
    public function throwsWhenJobFirstSegmentEmpty(): void
    {
        $rollup = $this->makeRollup(100, 80, 90, null, 10);
        $this->jobDaoMock->method('getSplitData')->willReturn([$rollup]);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(-6);
        $this->expectExceptionMessage('Wrong job id or password');

        $this->service->getSplitData($this->makeProjectStructure());
    }

    #[Test]
    public function throwsWhenJobLastSegmentEmpty(): void
    {
        $rollup = $this->makeRollup(100, 80, 90, 1, null);
        $this->jobDaoMock->method('getSplitData')->willReturn([$rollup]);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(-6);
        $this->expectExceptionMessage('Wrong job id or password');

        $this->service->getSplitData($this->makeProjectStructure());
    }

    #[Test]
    public function throwsWhenTotalWordsInsufficientForChunks(): void
    {
        // Both eq and raw word counts are 1, but we want 3 splits
        $row1   = $this->makeRow(1, 0.5, 0.5, 0.5);
        $rollup = $this->makeRollup(1, 1, 1);

        $this->jobDaoMock->method('getSplitData')->willReturn([$row1, $rollup]);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(-6);
        $this->expectExceptionMessage('insufficient');

        $this->service->getSplitData($this->makeProjectStructure(), 3);
    }

    // ── Count type fallback ──

    #[Test]
    public function fallsBackToRawWordCountWhenEqCountBelowNumSplit(): void
    {
        // eq_word_count=1 (< 2 splits), raw_word_count=100
        $row1   = $this->makeRow(1, 50, 0.5, 10, 1);
        $row2   = $this->makeRow(2, 50, 0.5, 10, 1);
        $rollup = $this->makeRollup(100, 1, 20);

        $this->jobDaoMock->method('getSplitData')->willReturn([$row1, $row2, $rollup]);

        $result = $this->service->getSplitData($this->makeProjectStructure(), 2);
        $chunks = $result['chunks'];

        // Should have created 2 chunks using raw_word_count
        $this->assertCount(2, $chunks);
    }

    #[Test]
    public function fallsBackToEqWordCountWhenRawCountBelowNumSplit(): void
    {
        // Use raw count type, but raw=1 (< 2 splits), eq=100
        $row1   = $this->makeRow(1, 0.5, 50, 10, 1);
        $row2   = $this->makeRow(2, 0.5, 50, 10, 1);
        $rollup = $this->makeRollup(1, 100, 20);

        $this->jobDaoMock->method('getSplitData')->willReturn([$row1, $row2, $rollup]);

        $result = $this->service->getSplitData(
            $this->makeProjectStructure(),
            2,
            [],
            ProjectsMetadataDao::SPLIT_RAW_WORD_TYPE
        );
        $chunks = $result['chunks'];

        $this->assertCount(2, $chunks);
    }

    // ── Simple even split ──

    #[Test]
    public function simpleSplitCreatesEvenChunks(): void
    {
        // 4 segments, 25 eq_word_count each = 100 total, split into 2
        $rows = [];
        for ($i = 1; $i <= 4; $i++) {
            $rows[] = $this->makeRow($i, 30, 25, 20);
        }
        $rollup = $this->makeRollup(120, 100, 80);
        $rows[] = $rollup;

        $this->jobDaoMock->method('getSplitData')->willReturn($rows);

        $result = $this->service->getSplitData($this->makeProjectStructure(), 2);
        $chunks = $result['chunks'];

        $this->assertCount(2, $chunks);

        // First chunk should start at segment 1
        $this->assertEquals(1, $chunks[0]['segment_start']);
        // Second chunk should end at segment 4
        $this->assertEquals(4, $chunks[1]['segment_end']);
    }

    #[Test]
    public function simpleSplitSetsLastOpenedSegmentToFirstVisibleSegment(): void
    {
        // First segment hidden, second visible
        $row1   = $this->makeRow(1, 30, 25, 20, 0); // show_in_cattool=0
        $row2   = $this->makeRow(2, 30, 25, 20, 1); // show_in_cattool=1
        $row3   = $this->makeRow(3, 30, 25, 20, 1);
        $row4   = $this->makeRow(4, 30, 25, 20, 1);
        $rollup = $this->makeRollup(120, 100, 80);

        $this->jobDaoMock->method('getSplitData')->willReturn([$row1, $row2, $row3, $row4, $rollup]);

        $result = $this->service->getSplitData($this->makeProjectStructure(), 2);
        $chunks = $result['chunks'];

        // First chunk: last_opened_segment should be 2 (first visible)
        $this->assertEquals(2, $chunks[0]['last_opened_segment']);
    }

    #[Test]
    public function simpleSplitIntoThreeChunks(): void
    {
        // 6 segments, ~17 eq each = ~100 total, split into 3 (33 each)
        $rows = [];
        for ($i = 1; $i <= 6; $i++) {
            $rows[] = $this->makeRow($i, 20, 17, 15);
        }
        $rollup = $this->makeRollup(120, 102, 90);
        $rows[] = $rollup;

        $this->jobDaoMock->method('getSplitData')->willReturn($rows);

        $result = $this->service->getSplitData($this->makeProjectStructure(), 3);
        $chunks = $result['chunks'];

        $this->assertCount(3, $chunks);
        $this->assertEquals(1, $chunks[0]['segment_start']);
        $this->assertEquals(6, $chunks[2]['segment_end']);
    }

    // ── Custom words per split ──

    #[Test]
    public function customWordsPerSplitDistributesCorrectly(): void
    {
        // 4 segments, 25 eq each = 100 total
        // Request: chunk 1 = 30 words, chunk 2 = 70 words
        $rows = [];
        for ($i = 1; $i <= 4; $i++) {
            $rows[] = $this->makeRow($i, 30, 25, 20);
        }
        $rollup = $this->makeRollup(120, 100, 80);
        $rows[] = $rollup;

        $this->jobDaoMock->method('getSplitData')->willReturn($rows);

        $result = $this->service->getSplitData($this->makeProjectStructure(), 2, [30, 70]);
        $chunks = $result['chunks'];

        $this->assertCount(2, $chunks);
        // Chunk 0 should have ~50 eq words (2 segments × 25), since 25 < 30, then 50 >= 30 → split
        $this->assertEquals(1, $chunks[0]['segment_start']);
        $this->assertEquals(2, $chunks[0]['segment_end']);
        $this->assertEquals(3, $chunks[1]['segment_start']);
        $this->assertEquals(4, $chunks[1]['segment_end']);
    }

    #[Test]
    public function throwsWhenFirstChunkTooLargeForTwoChunks(): void
    {
        // 2 segments, 25 eq each = 50 total. Request 60 for first chunk → all goes to chunk 0
        $row1   = $this->makeRow(1, 30, 25, 20);
        $row2   = $this->makeRow(2, 30, 25, 20);
        $rollup = $this->makeRollup(60, 50, 40);

        $this->jobDaoMock->method('getSplitData')->willReturn([$row1, $row2, $rollup]);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(-7);
        $this->expectExceptionMessage('too large');

        $this->service->getSplitData($this->makeProjectStructure(), 2, [60, 40]);
    }

    // ── Result structure ──

    #[Test]
    public function resultContainsStandardAnalysisCountFromJobStruct(): void
    {
        $rows = [];
        for ($i = 1; $i <= 4; $i++) {
            $rows[] = $this->makeRow($i, 30, 25, 20);
        }
        $rollup = $this->makeRollup(120, 100, 80);
        $rows[] = $rollup;

        $this->jobDaoMock->method('getSplitData')->willReturn($rows);

        $result = $this->service->getSplitData($this->makeProjectStructure(), 2);

        // standard_analysis_count comes from jobStruct->standard_analysis_wc = 500
        $this->assertEquals(500, $result['standard_analysis_count']);
    }

    #[Test]
    public function resultContainsRollupTotalsAndChunksKey(): void
    {
        $rows = [];
        for ($i = 1; $i <= 4; $i++) {
            $rows[] = $this->makeRow($i, 30, 25, 20);
        }
        $rollup = $this->makeRollup(120, 100, 80);
        $rows[] = $rollup;

        $this->jobDaoMock->method('getSplitData')->willReturn($rows);

        $result = $this->service->getSplitData($this->makeProjectStructure(), 2);

        // Should contain totals from rollup
        $resultArr = (array)$result;
        $this->assertArrayHasKey('raw_word_count', $resultArr);
        $this->assertArrayHasKey('eq_word_count', $resultArr);
        $this->assertArrayHasKey('standard_word_count', $resultArr);
        $this->assertArrayHasKey('chunks', $resultArr);
        $this->assertArrayHasKey('standard_analysis_count', $resultArr);
    }

    #[Test]
    public function resultIsStoredInProjectStructureSplitResult(): void
    {
        $rows = [];
        for ($i = 1; $i <= 4; $i++) {
            $rows[] = $this->makeRow($i, 30, 25, 20);
        }
        $rollup = $this->makeRollup(120, 100, 80);
        $rows[] = $rollup;

        $this->jobDaoMock->method('getSplitData')->willReturn($rows);

        $ps = $this->makeProjectStructure();
        $result = $this->service->getSplitData($ps, 2);

        $this->assertSame($result, $ps['split_result']);
    }

    #[Test]
    public function resultIsArrayObject(): void
    {
        $rows = [];
        for ($i = 1; $i <= 4; $i++) {
            $rows[] = $this->makeRow($i, 30, 25, 20);
        }
        $rollup = $this->makeRollup(120, 100, 80);
        $rows[] = $rollup;

        $this->jobDaoMock->method('getSplitData')->willReturn($rows);

        $result = $this->service->getSplitData($this->makeProjectStructure(), 2);

        $this->assertInstanceOf(ArrayObject::class, $result);
    }

    // ── Chunk word count correction ──

    #[Test]
    public function lastChunkGetsRemainingWordsViaReverseCountCorrection(): void
    {
        // 4 segments with eq_word_count = 25 each
        // Rollup says total = 105 (not 100). The extra 5 should go to last chunk.
        $rows = [];
        for ($i = 1; $i <= 4; $i++) {
            $rows[] = $this->makeRow($i, 30, 25, 20);
        }
        $rollup = $this->makeRollup(125, 105, 85);
        $rows[] = $rollup;

        $this->jobDaoMock->method('getSplitData')->willReturn($rows);

        $result = $this->service->getSplitData($this->makeProjectStructure(), 2);
        $chunks = $result['chunks'];

        // Total eq across chunks should equal rollup total (105)
        $totalEq = 0;
        foreach ($chunks as $c) {
            $totalEq += $c['eq_word_count'];
        }
        $this->assertEquals(105, $totalEq);
    }

    // ── Edge: all segments hidden (show_in_cattool=0) ──

    #[Test]
    public function lastOpenedSegmentIsZeroWhenAllSegmentsHidden(): void
    {
        $row1   = $this->makeRow(1, 30, 50, 20, 0);
        $row2   = $this->makeRow(2, 30, 50, 20, 0);
        $rollup = $this->makeRollup(60, 100, 40);

        $this->jobDaoMock->method('getSplitData')->willReturn([$row1, $row2, $rollup]);

        $result = $this->service->getSplitData($this->makeProjectStructure(), 2);
        $chunks = $result['chunks'];

        // All hidden → last_opened_segment stays at 0
        foreach ($chunks as $c) {
            $this->assertEquals(0, $c['last_opened_segment']);
        }
    }

    // ── Edge: exactly 2 segments, 2 splits ──

    #[Test]
    public function exactlyTwoSegmentsTwoSplitsCreatesOneSegmentPerChunk(): void
    {
        $row1   = $this->makeRow(1, 50, 50, 30);
        $row2   = $this->makeRow(2, 50, 50, 30);
        $rollup = $this->makeRollup(100, 100, 60);

        $this->jobDaoMock->method('getSplitData')->willReturn([$row1, $row2, $rollup]);

        $result = $this->service->getSplitData($this->makeProjectStructure(), 2);
        $chunks = $result['chunks'];

        $this->assertCount(2, $chunks);
        $this->assertEquals(1, $chunks[0]['segment_start']);
        $this->assertEquals(1, $chunks[0]['segment_end']);
        $this->assertEquals(2, $chunks[1]['segment_start']);
        $this->assertEquals(2, $chunks[1]['segment_end']);
    }
}
