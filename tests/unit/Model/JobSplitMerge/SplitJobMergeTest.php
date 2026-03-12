<?php

namespace unit\Model\JobSplitMerge;

use ArrayObject;
use Exception;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\JobSplitMerge\SplitMergeProjectData;
use Model\Projects\MetadataDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Translators\TranslatorsModel;
use Model\WordCount\CounterModel;
use PDOStatement;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;
use Utils\Shop\Cart;

/**
 * Step 12 — Tests for JobSplitMergeService::splitJob() and mergeALL()
 *
 * These methods are now testable via constructor injection and protected
 * factory methods on JobSplitMergeService.
 */
#[AllowMockObjectsWithoutExpectations]
class SplitJobMergeTest extends AbstractTest
{
    private TestableJobSplitMergeService $service;
    private IDatabase&MockObject $dbHandler;
    private FeatureSet&MockObject $features;
    private JobDao&MockObject $jobDaoMock;
    private Cart&MockObject $cartMock;
    private CounterModel&MockObject $counterModelMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbHandler = $this->createMock(IDatabase::class);
        $this->features  = $this->createMock(FeatureSet::class);
        $logger          = $this->createStub(MatecatLogger::class);

        $this->service = new TestableJobSplitMergeService($this->dbHandler, $this->features, $logger);

        $this->jobDaoMock = $this->createMock(JobDao::class);
        $this->service->setJobDao($this->jobDaoMock);

        $this->cartMock = $this->createMock(Cart::class);
        $this->service->setCart($this->cartMock);

        $this->counterModelMock = $this->createMock(CounterModel::class);
        $this->service->setCounterModel($this->counterModelMock);

        $projectDaoMock = $this->createMock(ProjectDao::class);
        $this->service->setProjectDao($projectDaoMock);

        // Default: no translator on the job being split
        $translatorModel = $this->createMock(TranslatorsModel::class);
        $translatorModel->method('getTranslator')->willReturn(null);
        $this->service->setTranslatorsModel($translatorModel);

        // Deterministic random strings
        $this->service->setRandomStrings(['pass_chunk2', 'pass_chunk3', 'pass_chunk4']);
    }

    // ── Helper: create a standard JobStruct for splitting ──

    private function makeJobToSplit(): JobStruct
    {
        $job = new JobStruct();
        $job->id = 100;
        $job->password = 'origpass';
        $job->id_project = 999;
        $job->job_first_segment = 1;
        $job->job_last_segment = 100;
        $job->source = 'en-US';
        $job->target = 'it-IT';
        $job->standard_analysis_wc = 500;
        $job->total_raw_wc = 600;
        $job->avg_post_editing_effort = 75;
        $job->total_time_to_edit = 3600;
        $job->create_date = '2025-01-01 00:00:00';
        $job->last_opened_segment = 1;
        $job->tm_keys = '[]';

        return $job;
    }

    /**
     * Helper: build a SplitMergeProjectData for splitJob with split_result pre-populated.
     */
    private function makeSplitProjectStructure(array $chunks = []): SplitMergeProjectData
    {
        $data = new SplitMergeProjectData(999);
        $data->jobToSplit     = 100;
        $data->jobToSplitPass = 'origpass';
        $data->splitResult    = new ArrayObject([
            'job_first_segment' => 1,
            'chunks'            => $chunks,
        ]);

        return $data;
    }

    private function makeTwoChunks(): array
    {
        return [
            [
                'segment_start'       => 1,
                'segment_end'         => 50,
                'last_opened_segment' => 1,
                'standard_word_count' => 250,
                'raw_word_count'      => 300,
                'eq_word_count'       => 200,
            ],
            [
                'segment_start'       => 51,
                'segment_end'         => 100,
                'last_opened_segment' => 51,
                'standard_word_count' => 250,
                'raw_word_count'      => 300,
                'eq_word_count'       => 200,
            ],
        ];
    }

    private function setupSplitJobStubs(): void
    {
        $jobToSplit = $this->makeJobToSplit();
        $this->service->setJobByIdAndPassword($jobToSplit);

        // ProjectStruct for cache invalidation (avoids DB call via getProject())
        $projectStruct = new ProjectStruct();
        $projectStruct->id = 999;
        $projectStruct->password = 'projpass';
        $this->service->setProjectForCacheInvalidation($projectStruct);

        // Mock the PDOStatement returned by getSplitJobPreparedStatement
        $stmt = $this->createMock(PDOStatement::class);
        $this->jobDaoMock->method('getSplitJobPreparedStatement')->willReturn($stmt);

        // rowCount returns 1 (success) for inserts
        $this->dbHandler->method('rowCount')->willReturn(1);
    }

    // ────────────────────────────────────────────────────────────────
    // splitJob tests
    // ────────────────────────────────────────────────────────────────

    /**
     * @throws Exception
     */
    #[Test]
    public function splitJobUpdatesFirstChunkWordCounts(): void
    {
        $this->setupSplitJobStubs();
        $chunks = $this->makeTwoChunks();
        $ps = $this->makeSplitProjectStructure($chunks);

        $this->jobDaoMock->expects($this->once())
            ->method('updateStdWcAndTotalWc')
            ->with(100, 250, 300);

        $this->service->splitJob($ps);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function splitJobCreatesChunksWithCorrectSegmentBoundaries(): void
    {
        $this->setupSplitJobStubs();
        $chunks = $this->makeTwoChunks();
        $ps = $this->makeSplitProjectStructure($chunks);

        $this->service->splitJob($ps);

        // Should have 2 entries in job_list and job_pass
        $this->assertCount(2, $ps->jobList);
        $this->assertCount(2, $ps->jobPass);
        $this->assertCount(2, $ps->jobSegments);

        // First chunk retains original password
        $this->assertEquals('origpass', $ps->jobPass[0]);
        // Second chunk gets new password
        $this->assertEquals('pass_chunk2', $ps->jobPass[1]);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function splitJobKeepsOriginalPasswordForFirstChunk(): void
    {
        $this->setupSplitJobStubs();
        $chunks = $this->makeTwoChunks();
        $ps = $this->makeSplitProjectStructure($chunks);

        // The first chunk's segment_start == job_first_segment, so password is NOT changed
        $stmtArgs = [];
        $this->jobDaoMock->method('getSplitJobPreparedStatement')
            ->willReturnCallback(function ($newJob) use (&$stmtArgs) {
                $stmtArgs[] = clone $newJob;
                return $this->createMock(PDOStatement::class);
            });

        $this->service->splitJob($ps);

        // First chunk retains original password
        $this->assertEquals('origpass', $stmtArgs[0]->password);
        // Second chunk gets a new password
        $this->assertEquals('pass_chunk2', $stmtArgs[1]->password);
    }

    #[Test]
    public function splitJobThrowsWhenRowCountIsZero(): void
    {
        $jobToSplit = $this->makeJobToSplit();
        $this->service->setJobByIdAndPassword($jobToSplit);

        $stmt = $this->createMock(PDOStatement::class);
        // Initialize the typed property to avoid "must not be accessed before initialization"
        $stmt->queryString = 'INSERT INTO jobs ...';
        $this->jobDaoMock->method('getSplitJobPreparedStatement')->willReturn($stmt);

        // rowCount returns 0 → insert failed
        $this->dbHandler->method('rowCount')->willReturn(0);

        $chunks = $this->makeTwoChunks();
        $ps = $this->makeSplitProjectStructure($chunks);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(-8);
        $this->expectExceptionMessage('Failed to insert job chunk');

        $this->service->splitJob($ps);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function splitJobInitializesWordCountForEachChunk(): void
    {
        $this->setupSplitJobStubs();
        $chunks = $this->makeTwoChunks();
        $ps = $this->makeSplitProjectStructure($chunks);

        $this->counterModelMock->expects($this->exactly(2))
            ->method('initializeJobWordCount');

        $this->service->splitJob($ps);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function splitJobEnqueuesWorkerForEachChunk(): void
    {
        $this->setupSplitJobStubs();
        $chunks = $this->makeTwoChunks();
        $ps = $this->makeSplitProjectStructure($chunks);

        $this->service->splitJob($ps);

        $enqueued = $this->service->getEnqueuedWorkers();
        $this->assertCount(2, $enqueued);
        $this->assertEquals('JOBS', $enqueued[0]['queue']);
        $this->assertEquals('JOBS', $enqueued[1]['queue']);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function splitJobDestroysJobAndAnalysisCaches(): void
    {
        $this->setupSplitJobStubs();
        $chunks = $this->makeTwoChunks();
        $ps = $this->makeSplitProjectStructure($chunks);

        $this->jobDaoMock->expects($this->atLeastOnce())
            ->method('destroyCacheByProjectId')
            ->with(999);

        $this->service->splitJob($ps);

        $this->assertTrue($this->service->wasDestroyAnalysisCacheCalled());
        $this->assertEquals(999, $this->service->getDestroyAnalysisCacheProjectId());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function splitJobDeletesCartAfterSplit(): void
    {
        $this->setupSplitJobStubs();
        $chunks = $this->makeTwoChunks();
        $ps = $this->makeSplitProjectStructure($chunks);

        $this->cartMock->expects($this->once())->method('deleteCart');

        $this->service->splitJob($ps);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function splitJobRunsPostJobSplittedFeatureHook(): void
    {
        $this->setupSplitJobStubs();
        $chunks = $this->makeTwoChunks();
        $ps = $this->makeSplitProjectStructure($chunks);

        $this->features->expects($this->once())
            ->method('run')
            ->with('postJobSplitted', $this->isInstanceOf(ArrayObject::class));

        $this->service->splitJob($ps);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function splitJobSetsSegmentRangesInJobSegments(): void
    {
        $this->setupSplitJobStubs();
        $chunks = $this->makeTwoChunks();
        $ps = $this->makeSplitProjectStructure($chunks);

        $this->service->splitJob($ps);

        $segments = $ps->jobSegments;

        // First chunk: key is "100-origpass"
        $firstKey = '100-origpass';
        $this->assertTrue($segments->offsetExists($firstKey), "Expected key '$firstKey' in jobSegments");
        $this->assertEquals(1, $segments[$firstKey][0]);
        $this->assertEquals(50, $segments[$firstKey][1]);

        // Second chunk: key is "100-pass_chunk2"
        $secondKey = '100-pass_chunk2';
        $this->assertTrue($segments->offsetExists($secondKey), "Expected key '$secondKey' in jobSegments");
        $this->assertEquals(51, $segments[$secondKey][0]);
        $this->assertEquals(100, $segments[$secondKey][1]);
    }

    // ────────────────────────────────────────────────────────────────
    // applySplit tests
    // ────────────────────────────────────────────────────────────────

    /**
     * @throws Exception
     */
    #[Test]
    public function applySplitEmptiesCartBeforeSplit(): void
    {
        $this->setupSplitJobStubs();
        $chunks = $this->makeTwoChunks();
        $ps = $this->makeSplitProjectStructure($chunks);

        $callOrder = [];
        $this->cartMock->expects($this->once())
            ->method('emptyCart')
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'emptyCart';
            });

        $this->service->applySplit($ps);

        $this->assertTrue($this->service->wasBeginTransactionCalled());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function applySplitCommitsTransaction(): void
    {
        $this->setupSplitJobStubs();
        $chunks = $this->makeTwoChunks();
        $ps = $this->makeSplitProjectStructure($chunks);

        $this->dbHandler->expects($this->once())->method('commit');

        $this->service->applySplit($ps);
    }

    // ────────────────────────────────────────────────────────────────
    // mergeALL tests
    // ────────────────────────────────────────────────────────────────

    /**
     * Helper: create an array of JobStruct chunks for merging.
     * @return JobStruct[]
     */
    private function makeJobChunksForMerge(): array
    {
        $chunk1 = new JobStruct();
        $chunk1->id = 100;
        $chunk1->password = 'pass1';
        $chunk1->id_project = 999;
        $chunk1->job_first_segment = 1;
        $chunk1->job_last_segment = 50;
        $chunk1->source = 'en-US';
        $chunk1->target = 'it-IT';
        $chunk1->total_raw_wc = 300;
        $chunk1->standard_analysis_wc = 250;
        $chunk1->avg_post_editing_effort = 50;
        $chunk1->total_time_to_edit = 1800;
        $chunk1->tm_keys = '[{"key":"abc123","r":true,"w":true}]';

        $chunk2 = new JobStruct();
        $chunk2->id = 100;
        $chunk2->password = 'pass2';
        $chunk2->id_project = 999;
        $chunk2->job_first_segment = 51;
        $chunk2->job_last_segment = 100;
        $chunk2->source = 'en-US';
        $chunk2->target = 'it-IT';
        $chunk2->total_raw_wc = 300;
        $chunk2->standard_analysis_wc = 250;
        $chunk2->avg_post_editing_effort = 25;
        $chunk2->total_time_to_edit = 1200;
        $chunk2->tm_keys = '[{"key":"abc123","r":true,"w":true}]';

        return [$chunk1, $chunk2];
    }

    private function setupMergeStubs(): void
    {
        // ProjectStruct for cache invalidation (avoids DB call via getProject())
        $projectStruct = new ProjectStruct();
        $projectStruct->id = 999;
        $projectStruct->password = 'projpass';
        $this->service->setProjectForCacheInvalidation($projectStruct);

        $this->service->setOwnerKeysResult([]);

        $metadataDao = $this->createMock(MetadataDao::class);
        $this->service->setProjectsMetadataDao($metadataDao);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function mergeALLSetsJobSegmentRangeToMinMax(): void
    {
        $this->setupMergeStubs();
        $chunks = $this->makeJobChunksForMerge();
        $ps = new SplitMergeProjectData(999);

        // Capture the job passed to updateForMerge
        $this->service->mergeALL($ps, $chunks);

        // After merge, first_job should have full segment range
        $this->assertEquals(1, $chunks[0]['job_first_segment']);
        $this->assertEquals(100, $chunks[0]['job_last_segment']);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function mergeALLSumsRawAndStandardWordCounts(): void
    {
        $this->setupMergeStubs();
        $chunks = $this->makeJobChunksForMerge();
        $ps = new SplitMergeProjectData(999);

        // Expect updateStdWcAndTotalWc with summed values: 250+250=500, 300+300=600
        $this->jobDaoMock->expects($this->once())
            ->method('updateStdWcAndTotalWc')
            ->with(100, 500, 600);

        $this->service->mergeALL($ps, $chunks);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function mergeALLSumsAvgPeeAndTimeToEdit(): void
    {
        $this->setupMergeStubs();
        $chunks = $this->makeJobChunksForMerge();
        $ps = new SplitMergeProjectData(999);

        $this->service->mergeALL($ps, $chunks);

        // 50+25 = 75, 1800+1200 = 3000
        $this->assertEquals(75, $chunks[0]['avg_post_editing_effort']);
        $this->assertEquals(3000, $chunks[0]['total_time_to_edit']);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function mergeALLCallsUpdateForMergeWithFalseWhenNoTranslator(): void
    {
        $this->setupMergeStubs();
        $chunks = $this->makeJobChunksForMerge();
        $ps = new SplitMergeProjectData(999);

        $this->service->mergeALL($ps, $chunks);

        $calls = $this->service->getUpdateForMergeCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('', $calls[0]['newPassword']);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function mergeALLCallsDeleteOnMerge(): void
    {
        $this->setupMergeStubs();
        $chunks = $this->makeJobChunksForMerge();
        $ps = new SplitMergeProjectData(999);

        $this->service->mergeALL($ps, $chunks);

        $deletes = $this->service->getDeleteOnMergeCalls();
        $this->assertCount(1, $deletes);
        $this->assertEquals(100, $deletes[0]->id);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function mergeALLInitializesWordCount(): void
    {
        $this->setupMergeStubs();
        $chunks = $this->makeJobChunksForMerge();
        $ps = new SplitMergeProjectData(999);

        $this->counterModelMock->expects($this->once())
            ->method('initializeJobWordCount')
            ->with(100, 'pass1');

        $this->service->mergeALL($ps, $chunks);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function mergeALLRunsPostJobMergedFeatureHook(): void
    {
        $this->setupMergeStubs();
        $chunks = $this->makeJobChunksForMerge();
        $ps = new SplitMergeProjectData(999);

        $this->features->expects($this->once())
            ->method('run')
            ->with(
                'postJobMerged',
                $this->isInstanceOf(ArrayObject::class),
                $this->isInstanceOf(JobStruct::class)
            );

        $this->service->mergeALL($ps, $chunks);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function mergeALLDestroysJobAndAnalysisCaches(): void
    {
        $this->setupMergeStubs();
        $chunks = $this->makeJobChunksForMerge();
        $ps = new SplitMergeProjectData(999);

        $this->jobDaoMock->expects($this->once())
            ->method('destroyCacheByProjectId')
            ->with(999);

        $this->service->mergeALL($ps, $chunks);

        $this->assertTrue($this->service->wasDestroyAnalysisCacheCalled());
        $this->assertEquals(999, $this->service->getDestroyAnalysisCacheProjectId());
    }

    /**
     * @return void
     * @throws Exception
     */
    #[Test]
    public function mergeALLCommitsTransaction(): void
    {
        $this->dbHandler->expects($this->once())->method('commit');

        // ProjectStruct for cache invalidation
        $projectStruct = new ProjectStruct();
        $projectStruct->id = 999;
        $projectStruct->password = 'projpass';
        $this->service->setProjectForCacheInvalidation($projectStruct);

        $this->service->setOwnerKeysResult([]);
        $metadataDao = $this->createMock(MetadataDao::class);
        $this->service->setProjectsMetadataDao($metadataDao);

        $chunks = $this->makeJobChunksForMerge();
        $ps = new SplitMergeProjectData(999);

        $this->service->mergeALL($ps, $chunks);
    }

    /**
     * @return void
     * @throws Exception
     */
    #[Test]
    public function mergeALLHandlesTmKeyManagerErrorGracefully(): void
    {
        // ProjectStruct for cache invalidation
        $projectStruct = new ProjectStruct();
        $projectStruct->id = 999;
        $projectStruct->password = 'projpass';
        $this->service->setProjectForCacheInvalidation($projectStruct);

        $metadataDao = $this->createMock(MetadataDao::class);
        $this->service->setProjectsMetadataDao($metadataDao);

        // getOwnerKeys will throw → method should catch and continue
        $this->service->setOwnerKeysThrows(true);

        $chunks = $this->makeJobChunksForMerge();
        $ps = new SplitMergeProjectData(999);

        // Should NOT throw — the error is caught and logged
        $this->service->mergeALL($ps, $chunks);

        // tm_keys should NOT have been updated (remains original)
        $this->assertEquals('[{"key":"abc123","r":true,"w":true}]', $chunks[0]['tm_keys']);
    }

    /**
     * @return void
     * @throws Exception
     */
    #[Test]
    public function mergeALLCleansUpChunksOptionsMetadata(): void
    {
        // ProjectStruct for cache invalidation
        $projectStruct = new ProjectStruct();
        $projectStruct->id = 999;
        $projectStruct->password = 'projpass';
        $this->service->setProjectForCacheInvalidation($projectStruct);

        $this->service->setOwnerKeysResult([]);

        $metadataDao = $this->createMock(MetadataDao::class);
        $metadataDao->expects($this->once())->method('cleanupChunksOptions');
        $this->service->setProjectsMetadataDao($metadataDao);

        $chunks = $this->makeJobChunksForMerge();
        $ps = new SplitMergeProjectData(999);

        $this->service->mergeALL($ps, $chunks);
    }
}
