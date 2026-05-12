<?php

namespace unit\Workers\TMAnalysisV2;

use Exception;
use Model\FeaturesBase\FeatureSet;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use TestHelpers\AbstractTest;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\AnalysisRedisServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\ProjectCompletionRepositoryInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service\ProjectCompletionService;

class ProjectCompletionServiceUnitTest extends AbstractTest
{
    private function makeRedisStub(array $wordCounts = [], bool $lockAcquired = true): AnalysisRedisServiceInterface
    {
        $redis = $this->createStub(AnalysisRedisServiceInterface::class);
        $redis->method('getProjectWordCounts')->willReturn($wordCounts);
        $redis->method('acquireCompletionLock')->willReturn($lockAcquired);

        return $redis;
    }

    private function makeFeatureSet(): FeatureSet
    {
        return $this->createStub(FeatureSet::class);
    }

    // ── Early return paths ─────────────────────────────────────────────

    #[Test]
    public function tryCloseProject_returns_early_when_project_segments_empty(): void
    {
        $redis = $this->makeRedisStub(['project_segments' => 0, 'num_analyzed' => 0]);
        $repo = $this->createMock(ProjectCompletionRepositoryInterface::class);
        $repo->expects($this->never())->method('beginTransaction');

        $service = new ProjectCompletionService($redis, $repo);
        $service->tryCloseProject(1, 'pass', 'queue', $this->makeFeatureSet());
    }

    #[Test]
    public function tryCloseProject_returns_early_when_not_all_segments_analyzed(): void
    {
        $redis = $this->makeRedisStub(['project_segments' => 10, 'num_analyzed' => 5]);
        $repo = $this->createMock(ProjectCompletionRepositoryInterface::class);
        $repo->expects($this->never())->method('beginTransaction');

        $service = new ProjectCompletionService($redis, $repo);
        $service->tryCloseProject(1, 'pass', 'queue', $this->makeFeatureSet());
    }

    #[Test]
    public function tryCloseProject_still_fires_when_num_analyzed_exceeds_project_segments(): void
    {
        // Defensive: if num_analyzed > project_segments (double-count from issue #4),
        // the <= 0 condition still triggers completion instead of leaving the project stuck.
        $redis = $this->createMock(AnalysisRedisServiceInterface::class);
        $redis->method('getProjectWordCounts')->willReturn(['project_segments' => 10, 'num_analyzed' => 12]);
        $redis->method('acquireCompletionLock')->willReturn(true);
        $redis->expects($this->once())->method('removeProjectFromQueue')->with('queue', 99);

        $repo = $this->createMock(ProjectCompletionRepositoryInterface::class);
        $repo->expects($this->once())->method('beginTransaction');
        $repo->method('getProjectSegmentsTranslationSummary')->willReturn([
            ['id_job' => 1, 'password' => 'abc', 'eq_wc' => 500, 'st_wc' => 600],
            ['id_job' => null, 'password' => null, 'eq_wc' => 500, 'st_wc' => 600],
        ]);
        $repo->expects($this->once())->method('updateProjectAnalysisStatus')
            ->with(99, 'DONE', 500.0, 600.0);
        $repo->method('getProjectJobIds')->willReturn([
            ['id' => 1, 'password' => 'abc'],
        ]);
        $repo->expects($this->once())->method('commit');
        $repo->expects($this->once())->method('destroyAllCaches')->with(99, 'secret');

        $service = new ProjectCompletionService($redis, $repo);
        $service->tryCloseProject(99, 'secret', 'queue', $this->makeFeatureSet());
    }

    #[Test]
    public function tryCloseProject_returns_early_when_lock_not_acquired(): void
    {
        $redis = $this->makeRedisStub(['project_segments' => 10, 'num_analyzed' => 10], false);
        $repo = $this->createMock(ProjectCompletionRepositoryInterface::class);
        $repo->expects($this->never())->method('beginTransaction');

        $service = new ProjectCompletionService($redis, $repo);
        $service->tryCloseProject(1, 'pass', 'queue', $this->makeFeatureSet());
    }

    // ── Happy path ─────────────────────────────────────────────────────

    #[Test]
    public function tryCloseProject_happy_path_commits_and_destroys_caches(): void
    {
        $redis = $this->createMock(AnalysisRedisServiceInterface::class);
        $redis->method('getProjectWordCounts')->willReturn(['project_segments' => 10, 'num_analyzed' => 10]);
        $redis->method('acquireCompletionLock')->willReturn(true);
        $redis->expects($this->once())->method('removeProjectFromQueue')->with('queue', 100);

        $repo = $this->createMock(ProjectCompletionRepositoryInterface::class);
        $repo->expects($this->once())->method('beginTransaction');
        $repo->method('getProjectSegmentsTranslationSummary')->willReturn([
            ['id_job' => 1, 'password' => 'abc', 'eq_wc' => 500, 'st_wc' => 600],
            ['id_job' => null, 'password' => null, 'eq_wc' => 500, 'st_wc' => 600], // rollup row
        ]);
        $repo->expects($this->once())->method('updateProjectAnalysisStatus')
            ->with(100, 'DONE', 500.0, 600.0);
        $repo->method('getProjectJobIds')->willReturn([
            ['id' => 1, 'password' => 'abc'],
        ]);
        $repo->expects($this->once())->method('updateJobStandardWordCount')->with(1, 600.0);
        $repo->expects($this->once())->method('initializeJobWordCount')->with(1, 'abc');
        $repo->expects($this->once())->method('commit');
        $repo->expects($this->once())->method('destroyAllCaches')->with(100, 'secret');

        $service = new ProjectCompletionService($redis, $repo);
        $service->tryCloseProject(100, 'secret', 'queue', $this->makeFeatureSet());
    }

    #[Test]
    public function tryCloseProject_distributes_st_wc_evenly_across_multiple_jobs(): void
    {
        $redis = $this->makeRedisStub(['project_segments' => 5, 'num_analyzed' => 5]);

        $repo = $this->createStub(ProjectCompletionRepositoryInterface::class);
        $repo->method('getProjectSegmentsTranslationSummary')->willReturn([
            ['id_job' => 1, 'password' => 'a', 'eq_wc' => 100, 'st_wc' => 200],
            ['id_job' => 2, 'password' => 'b', 'eq_wc' => 100, 'st_wc' => 200],
            ['id_job' => null, 'password' => null, 'eq_wc' => 200, 'st_wc' => 400], // rollup
        ]);
        $repo->method('getProjectJobIds')->willReturn([
            ['id' => 1, 'password' => 'a'],
            ['id' => 2, 'password' => 'b'],
        ]);

        $jobWcCalls = [];
        $repo->method('updateJobStandardWordCount')->willReturnCallback(
            function (int $jobId, float $stWc) use (&$jobWcCalls) {
                $jobWcCalls[] = [$jobId, $stWc];
            }
        );

        $service = new ProjectCompletionService($redis, $repo);
        $service->tryCloseProject(1, 'p', 'q', $this->makeFeatureSet());

        // 400 / 2 jobs = 200 each
        $this->assertCount(2, $jobWcCalls);
        $this->assertEquals(200.0, $jobWcCalls[0][1]);
        $this->assertEquals(200.0, $jobWcCalls[1][1]);
    }

    // ── Error/rollback path ────────────────────────────────────────────

    #[Test]
    public function tryCloseProject_rolls_back_and_releases_lock_on_exception(): void
    {
        $redis = $this->createMock(AnalysisRedisServiceInterface::class);
        $redis->method('getProjectWordCounts')->willReturn(['project_segments' => 10, 'num_analyzed' => 10]);
        $redis->method('acquireCompletionLock')->willReturn(true);
        $redis->expects($this->once())->method('releaseCompletionLock')->with(100);
        $redis->expects($this->never())->method('removeProjectFromQueue');

        $repo = $this->createMock(ProjectCompletionRepositoryInterface::class);
        $repo->method('getProjectSegmentsTranslationSummary')
            ->willThrowException(new RuntimeException('DB gone'));
        $repo->expects($this->once())->method('rollback');
        $repo->expects($this->never())->method('commit');

        $service = new ProjectCompletionService($redis, $repo);
        $service->tryCloseProject(100, 'pass', 'queue', $this->makeFeatureSet());
    }

    #[Test]
    public function tryCloseProject_handles_rollback_failure_gracefully(): void
    {
        $redis = $this->createMock(AnalysisRedisServiceInterface::class);
        $redis->method('getProjectWordCounts')->willReturn(['project_segments' => 10, 'num_analyzed' => 10]);
        $redis->method('acquireCompletionLock')->willReturn(true);
        $redis->expects($this->once())->method('releaseCompletionLock');

        $repo = $this->createStub(ProjectCompletionRepositoryInterface::class);
        $repo->method('getProjectSegmentsTranslationSummary')
            ->willThrowException(new RuntimeException('DB gone'));
        $repo->method('rollback')
            ->willThrowException(new RuntimeException('No active transaction'));

        $service = new ProjectCompletionService($redis, $repo);
        // Should not throw — double-fault is caught
        $service->tryCloseProject(100, 'pass', 'queue', $this->makeFeatureSet());
    }

    #[Test]
    public function tryCloseProject_feature_hook_exception_does_not_prevent_cache_destruction(): void
    {
        $redis = $this->makeRedisStub(['project_segments' => 5, 'num_analyzed' => 5]);

        $repo = $this->createMock(ProjectCompletionRepositoryInterface::class);
        $repo->method('getProjectSegmentsTranslationSummary')->willReturn([
            ['id_job' => 1, 'password' => 'a', 'eq_wc' => 100, 'st_wc' => 100],
            ['id_job' => null, 'password' => null, 'eq_wc' => 100, 'st_wc' => 100],
        ]);
        $repo->method('getProjectJobIds')->willReturn([['id' => 1, 'password' => 'a']]);
        $repo->expects($this->once())->method('destroyAllCaches');

        $featureSet = $this->createStub(FeatureSet::class);
        $featureSet->method('run')->willThrowException(new Exception('Hook failed'));

        $service = new ProjectCompletionService($redis, $repo);
        $service->tryCloseProject(1, 'p', 'q', $featureSet);
    }

    // ── getProjectSegmentsTranslationSummary delegation ────────────────

    #[Test]
    public function getProjectSegmentsTranslationSummary_delegates_to_repository(): void
    {
        $redis = $this->createStub(AnalysisRedisServiceInterface::class);
        $expected = [['id_job' => 1, 'eq_wc' => 500]];

        $repo = $this->createStub(ProjectCompletionRepositoryInterface::class);
        $repo->method('getProjectSegmentsTranslationSummary')->willReturn($expected);

        $service = new ProjectCompletionService($redis, $repo);
        $result = $service->getProjectSegmentsTranslationSummary(42);

        $this->assertSame($expected, $result);
    }
}
