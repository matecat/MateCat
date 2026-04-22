<?php

namespace unit\Model\ProjectCreation;

use Exception;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\ProjectCreation\ProjectStructure;
use Model\ProjectCreation\QAProcessor;
use Model\ProjectCreation\SegmentStorageService;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;


/**
 * Unit tests for job-file linking and pre-translation insertion
 * in {@see \Model\ProjectCreation\JobCreationService}.
 */
class LinkFilesAndPreTranslationsTest extends AbstractTest
{
    private TestableJobCreationService $service;
    private MatecatLogger $logger;

    private bool $oldStateEmailSendFlag;

    /**
     * @throws Exception
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->oldStateEmailSendFlag = AppConfig::$SEND_ERR_MAIL_REPORT;
        AppConfig::$SEND_ERR_MAIL_REPORT = false;

        $featureSet = $this->createStub(FeatureSet::class);
        $this->logger = $this->createStub(MatecatLogger::class);
        $this->service = new TestableJobCreationService($featureSet, $this->logger);
    }

    protected function tearDown(): void
    {
        AppConfig::$SEND_ERR_MAIL_REPORT = $this->oldStateEmailSendFlag;
        parent::tearDown();
    }

    private function makeJob(int $id, string $source = 'en-US', string $target = 'it-IT'): JobStruct
    {
        $job = new JobStruct();
        $job->id = $id;
        $job->password = 'pwd123';
        $job->source = $source;
        $job->target = $target;
        return $job;
    }

    private function makeProjectStructure(array $overrides = []): ProjectStructure
    {
        return new ProjectStructure(array_merge([
            'id_project' => 999,
            'source_language' => 'en-US',
            'target_language' => ['it-IT'],
            'result' => ['errors' => []],
        ], $overrides));
    }

    private function makeQAProcessorStub(): QAProcessor
    {
        return $this->createStub(QAProcessor::class);
    }

    // =========================================================================
    // linkFilesToJob
    // =========================================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function linkFilesToJobInsertsFileJobForEachFile(): void
    {
        $ps = $this->makeProjectStructure();
        $ps->file_id_list = [10, 20, 30];
        $job = $this->makeJob(42);

        $this->service->linkFilesAndInsertPreTranslations([$job], $ps, null, $this->createStub(SegmentStorageService::class), $this->makeQAProcessorStub());

        $this->assertCount(3, $this->service->insertFilesJobCalls);
        $this->assertSame([42, 10], $this->service->insertFilesJobCalls[0]);
        $this->assertSame([42, 20], $this->service->insertFilesJobCalls[1]);
        $this->assertSame([42, 30], $this->service->insertFilesJobCalls[2]);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function linkFilesToJobHandlesEmptyFileList(): void
    {
        $ps = $this->makeProjectStructure();
        $ps->file_id_list = [];
        $job = $this->makeJob(42);

        $this->service->linkFilesAndInsertPreTranslations([$job], $ps, null, $this->createStub(SegmentStorageService::class), $this->makeQAProcessorStub());

        $this->assertCount(0, $this->service->insertFilesJobCalls);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function linkFilesToJobSkipsGdriveWhenSessionIsNull(): void
    {
        $ps = $this->makeProjectStructure();
        $ps->file_id_list = [10];
        $job = $this->makeJob(42);

        // Should not throw — GDrive path skipped when session is null
        $this->service->linkFilesAndInsertPreTranslations([$job], $ps, null, $this->createStub(SegmentStorageService::class), $this->makeQAProcessorStub());

        $this->assertCount(1, $this->service->insertFilesJobCalls);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function linkFilesToJobLinksFilesForMultipleJobs(): void
    {
        $ps = $this->makeProjectStructure();
        $ps->file_id_list = [10, 20];
        $job1 = $this->makeJob(1);
        $job2 = $this->makeJob(2);

        $this->service->linkFilesAndInsertPreTranslations([$job1, $job2], $ps, null, $this->createStub(SegmentStorageService::class), $this->makeQAProcessorStub());

        $this->assertCount(4, $this->service->insertFilesJobCalls);
        $this->assertSame([1, 10], $this->service->insertFilesJobCalls[0]);
        $this->assertSame([1, 20], $this->service->insertFilesJobCalls[1]);
        $this->assertSame([2, 10], $this->service->insertFilesJobCalls[2]);
        $this->assertSame([2, 20], $this->service->insertFilesJobCalls[3]);
    }

    // =========================================================================
    // insertPreTranslations
    // =========================================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function insertPreTranslationsSkipsWhenNoTranslations(): void
    {
        $ps = $this->makeProjectStructure();
        $ps->translations = [];
        $job = $this->makeJob(42);

        $sss = $this->createMock(SegmentStorageService::class);
        $sss->expects($this->never())->method('insertPreTranslations');

        $this->service->linkFilesAndInsertPreTranslations([$job], $ps, null, $sss, $this->makeQAProcessorStub());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function insertPreTranslationsDelegatesToSegmentStorageService(): void
    {
        $ps = $this->makeProjectStructure();
        $ps->translations = ['some_translation_data'];
        $ps->file_id_list = [];
        $job = $this->makeJob(42);

        $sss = $this->createMock(SegmentStorageService::class);
        $sss->expects($this->once())
            ->method('insertPreTranslations')
            ->with($job, $ps);

        $this->service->linkFilesAndInsertPreTranslations([$job], $ps, null, $sss, $this->makeQAProcessorStub());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function insertPreTranslationsRecordsErrorAndPropagatesException(): void
    {
        $ps = $this->makeProjectStructure();
        $ps->translations = ['some_translation_data'];
        $ps->file_id_list = [];
        $job = $this->makeJob(42);

        $sss = $this->createStub(SegmentStorageService::class);
        $sss->method('insertPreTranslations')
            ->willThrowException(new Exception('DB connection lost', 500));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('DB connection lost');
        $this->expectExceptionCode(500);

        $this->service->linkFilesAndInsertPreTranslations([$job], $ps, null, $sss, $this->makeQAProcessorStub());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function insertPreTranslationsAbortsForeachOnFirstFailure(): void
    {
        $ps = $this->makeProjectStructure();
        $ps->translations = ['some_translation_data'];
        $ps->file_id_list = [];
        $job1 = $this->makeJob(1);
        $job2 = $this->makeJob(2);

        $callCount = 0;
        $sss = $this->createStub(SegmentStorageService::class);
        $sss->method('insertPreTranslations')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    throw new Exception('First job failed', 100);
                }
            });

        try {
            $this->service->linkFilesAndInsertPreTranslations([$job1, $job2], $ps, null, $sss, $this->makeQAProcessorStub());
            $this->fail('Expected exception was not thrown');
        } catch (Exception $e) {
            $this->assertSame('First job failed', $e->getMessage());
            // Only 1 call — second job was never attempted
            $this->assertSame(1, $callCount);
        }
    }
}
