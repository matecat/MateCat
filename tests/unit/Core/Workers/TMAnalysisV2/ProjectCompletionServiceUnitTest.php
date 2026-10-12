<?php

namespace Matecat\Core\Workers\TMAnalysisV2;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\AnalysisRedisServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\ProjectCompletionRepositoryInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service\ProjectCompletionService;

class ProjectCompletionServiceUnitTest extends AbstractTest
{
    /**
     * A repository double whose transaction() runs its callback inline.
     *
     * Unconfigured it would return null without ever calling the closure, so the finalization body
     * would not run and the assertions below would pass against a method that did nothing.
     *
     * @return ProjectCompletionRepositoryInterface&MockObject
     */
    private function makeRepositoryMock(): ProjectCompletionRepositoryInterface
    {
        $repo = $this->createMock(ProjectCompletionRepositoryInterface::class);
        $repo->method('transaction')->willReturnCallback(static fn(callable $work) => $work());

        return $repo;
    }

    private function makeRepositoryStub(): ProjectCompletionRepositoryInterface
    {
        $repo = $this->createStub(ProjectCompletionRepositoryInterface::class);
        $repo->method('transaction')->willReturnCallback(static fn(callable $work) => $work());

        return $repo;
    }

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
        $repo = $this->makeRepositoryMock();
        $repo->expects($this->never())->method('transaction');

        $service = new ProjectCompletionService($redis, $repo);
        $service->tryCloseProject(1, 'pass', 'queue', $this->makeFeatureSet());
    }

    #[Test]
    public function tryCloseProject_returns_early_when_not_all_segments_analyzed(): void
    {
        $redis = $this->makeRedisStub(['project_segments' => 10, 'num_analyzed' => 5]);
        $repo = $this->makeRepositoryMock();
        $repo->expects($this->never())->method('transaction');

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

        $repo = $this->makeRepositoryMock();
        $repo->method('getProjectSegmentsTranslationSummary')->willReturn([
            ['id_job' => 1, 'password' => 'abc', 'eq_wc' => 500, 'st_wc' => 600],
            ['id_job' => null, 'password' => null, 'eq_wc' => 500, 'st_wc' => 600, 'project_segments' => 10, 'num_analyzed' => 10],
        ]);
        $repo->expects($this->once())->method('updateProjectAnalysisStatus')
            ->with(99, 'DONE', 500.0, 600.0);
        $repo->method('getProjectJobIds')->willReturn([
            ['id' => 1, 'password' => 'abc'],
        ]);
        $repo->expects($this->once())->method('destroyAllCaches')->with(99, 'secret');

        $service = new ProjectCompletionService($redis, $repo);
        $service->tryCloseProject(99, 'secret', 'queue', $this->makeFeatureSet());
    }

    #[Test]
    public function tryCloseProject_returns_early_when_lock_not_acquired(): void
    {
        $redis = $this->makeRedisStub(['project_segments' => 10, 'num_analyzed' => 10], false);
        $repo = $this->makeRepositoryMock();
        $repo->expects($this->never())->method('transaction');

        $service = new ProjectCompletionService($redis, $repo);
        $service->tryCloseProject(1, 'pass', 'queue', $this->makeFeatureSet());
    }

    #[Test]
    public function tryCloseProject_routes_logs_through_injected_logger(): void
    {
        // The worker injects _doLog here so completion logs land in the analysis-queue
        // log rather than the global general_log.txt. Verify the logger is actually used.
        $captured = [];
        $logger = function (string $message) use (&$captured): void {
            $captured[] = $message;
        };

        $redis = $this->createMock(AnalysisRedisServiceInterface::class);
        $redis->method('getProjectWordCounts')->willReturn(['project_segments' => 10, 'num_analyzed' => 10]);
        $redis->method('acquireCompletionLock')->willReturn(true);
        $redis->expects($this->once())->method('releaseCompletionLock')->with(7);

        $repo = $this->makeRepositoryStub();
        // Empty summary → array_pop() yields null → "empty rollup" branch logs + releases.
        $repo->method('getProjectSegmentsTranslationSummary')->willReturn([]);

        $service = new ProjectCompletionService($redis, $repo, $logger);
        $service->tryCloseProject(7, 'pass', 'queue', $this->makeFeatureSet());

        $this->assertNotEmpty($captured, 'injected logger must receive completion log output');
        $this->assertStringContainsString('empty rollup', implode("\n", $captured));
    }

    // ── Happy path ─────────────────────────────────────────────────────

    #[Test]
    public function tryCloseProject_happy_path_commits_and_destroys_caches(): void
    {
        $redis = $this->createMock(AnalysisRedisServiceInterface::class);
        $redis->method('getProjectWordCounts')->willReturn(['project_segments' => 10, 'num_analyzed' => 10]);
        $redis->method('acquireCompletionLock')->willReturn(true);
        $redis->expects($this->once())->method('removeProjectFromQueue')->with('queue', 100);

        $repo = $this->makeRepositoryMock();
        $repo->method('getProjectSegmentsTranslationSummary')->willReturn([
            ['id_job' => 1, 'password' => 'abc', 'eq_wc' => 500, 'st_wc' => 600],
            ['id_job' => null, 'password' => null, 'eq_wc' => 500, 'st_wc' => 600, 'project_segments' => 10, 'num_analyzed' => 10], // rollup row
        ]);
        $repo->expects($this->once())->method('updateProjectAnalysisStatus')
            ->with(100, 'DONE', 500.0, 600.0);
        $repo->method('getProjectJobIds')->willReturn([
            ['id' => 1, 'password' => 'abc'],
        ]);
        $repo->expects($this->once())->method('updateJobStandardWordCount')->with(1, 600.0);
        $repo->expects($this->once())->method('initializeJobWordCount')->with(1, 'abc');
        $repo->expects($this->once())->method('destroyAllCaches')->with(100, 'secret');

        $service = new ProjectCompletionService($redis, $repo);
        $service->tryCloseProject(100, 'secret', 'queue', $this->makeFeatureSet());
    }

    #[Test]
    public function tryCloseProject_distributes_st_wc_evenly_across_multiple_jobs(): void
    {
        $redis = $this->makeRedisStub(['project_segments' => 5, 'num_analyzed' => 5]);

        $repo = $this->makeRepositoryStub();
        $repo->method('getProjectSegmentsTranslationSummary')->willReturn([
            ['id_job' => 1, 'password' => 'a', 'eq_wc' => 100, 'st_wc' => 200],
            ['id_job' => 2, 'password' => 'b', 'eq_wc' => 100, 'st_wc' => 200],
            ['id_job' => null, 'password' => null, 'eq_wc' => 200, 'st_wc' => 400, 'project_segments' => 5, 'num_analyzed' => 5], // rollup
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

    // ── DB-authoritative gate ───────────────────────────────────────────

    #[Test]
    public function tryCloseProject_releases_lock_when_mysql_says_segments_remain(): void
    {
        // Redis says all done (drift from lost INCRBY), but MySQL disagrees.
        $redis = $this->createMock(AnalysisRedisServiceInterface::class);
        $redis->method('getProjectWordCounts')->willReturn(['project_segments' => 10, 'num_analyzed' => 10]);
        $redis->method('acquireCompletionLock')->willReturn(true);
        $redis->expects($this->once())->method('releaseCompletionLock')->with(42);
        $redis->expects($this->never())->method('removeProjectFromQueue');

        $repo = $this->makeRepositoryMock();
        // MySQL rollup: 10 segments but only 8 analyzed — 2 still pending
        $repo->method('getProjectSegmentsTranslationSummary')->willReturn([
            ['id_job' => 1, 'password' => 'a', 'eq_wc' => 100, 'st_wc' => 200],
            ['id_job' => null, 'password' => null, 'eq_wc' => 100, 'st_wc' => 200, 'project_segments' => 10, 'num_analyzed' => 8],
        ]);
        // The master-read wrapper opens its own scope, but the completion
        // transaction must NOT start — updateProjectAnalysisStatus is the real guard.
        $repo->expects($this->never())->method('updateProjectAnalysisStatus');

        // gateRetrySleepMicros:0 → the retry loop exhausts all attempts with no real sleep.
        $service = new ProjectCompletionService($redis, $repo, null, gateRetrySleepMicros: 0);
        $service->tryCloseProject(42, 'pass', 'queue', $this->makeFeatureSet());
    }

    #[Test]
    public function tryCloseProject_finalizes_when_gate_confirms_completion_on_a_later_retry(): void
    {
        // Core-fix proof (last-segment race): the first gate read still sees segments
        // pending (inter-worker commit skew), but a later read confirms completion. The
        // project must FINALIZE — not release the lock and hang at FAST_OK.
        $redis = $this->createMock(AnalysisRedisServiceInterface::class);
        $redis->method('getProjectWordCounts')->willReturn(['project_segments' => 10, 'num_analyzed' => 10]);
        $redis->method('acquireCompletionLock')->willReturn(true);
        // It finalized rather than gave up, so the lock is NEVER released here.
        $redis->expects($this->never())->method('releaseCompletionLock');
        $redis->expects($this->once())->method('removeProjectFromQueue')->with('queue', 77);

        $repo = $this->makeRepositoryMock();
        // Same rollup + per-job-row structure as the happy-path finalize test. First call:
        // 2 segments still pending. Second call: all analyzed → finalize on this snapshot.
        $repo->method('getProjectSegmentsTranslationSummary')->willReturnOnConsecutiveCalls(
            [
                ['id_job' => 1, 'password' => 'a', 'eq_wc' => 100, 'st_wc' => 200],
                ['id_job' => null, 'password' => null, 'eq_wc' => 100, 'st_wc' => 200, 'project_segments' => 10, 'num_analyzed' => 8],
            ],
            [
                ['id_job' => 1, 'password' => 'a', 'eq_wc' => 100, 'st_wc' => 200],
                ['id_job' => null, 'password' => null, 'eq_wc' => 100, 'st_wc' => 200, 'project_segments' => 10, 'num_analyzed' => 10],
            ],
        );
        $repo->method('getProjectJobIds')->willReturn([['id' => 1, 'password' => 'a']]);
        $repo->expects($this->once())->method('updateProjectAnalysisStatus');
        // A scope is opened once per gate query (master-read wrapper) plus the
        // completion transaction, so assert the finalize scope ran at all.
        $repo->expects($this->atLeastOnce())->method('transaction');

        // gateRetrySleepMicros:0 → the one retry needed happens with no real sleep.
        $service = new ProjectCompletionService($redis, $repo, null, gateRetrySleepMicros: 0);
        $service->tryCloseProject(77, 'pass', 'queue', $this->makeFeatureSet());
    }

    #[Test]
    public function tryCloseProject_releases_lock_when_gate_retries_misconfigured_below_one(): void
    {
        // maxGateRetries < 1 skips the gate loop entirely, so $rollup stays null. The
        // defensive post-loop guard must release the lock and bail without finalizing.
        $redis = $this->createMock(AnalysisRedisServiceInterface::class);
        $redis->method('getProjectWordCounts')->willReturn(['project_segments' => 10, 'num_analyzed' => 10]);
        $redis->method('acquireCompletionLock')->willReturn(true);
        $redis->expects($this->once())->method('releaseCompletionLock')->with(88);
        $redis->expects($this->never())->method('removeProjectFromQueue');

        $repo = $this->makeRepositoryMock();
        // Loop never runs → the gate query is never issued and finalize never starts.
        $repo->expects($this->never())->method('getProjectSegmentsTranslationSummary');
        $repo->expects($this->never())->method('updateProjectAnalysisStatus');

        $service = new ProjectCompletionService($redis, $repo, null, maxGateRetries: 0);
        $service->tryCloseProject(88, 'pass', 'queue', $this->makeFeatureSet());
    }

    #[Test]
    public function tryCloseProject_releases_lock_when_rollup_is_empty(): void
    {
        // Redis says complete → enters the locked block and acquires the completion lock.
        // The gate query returns [] (no rows at all), so array_pop yields null → the
        // "empty rollup" branch must release the lock and bail without finalizing.
        $redis = $this->createMock(AnalysisRedisServiceInterface::class);
        $redis->method('getProjectWordCounts')->willReturn(['project_segments' => 10, 'num_analyzed' => 10]);
        $redis->method('acquireCompletionLock')->willReturn(true);
        $redis->expects($this->once())->method('releaseCompletionLock')->with(55);
        $redis->expects($this->never())->method('removeProjectFromQueue');

        $repo = $this->makeRepositoryMock();
        $repo->method('getProjectSegmentsTranslationSummary')->willReturn([]);
        // No finalize: the DONE status update must never run on an empty rollup.
        $repo->expects($this->never())->method('updateProjectAnalysisStatus');

        $service = new ProjectCompletionService($redis, $repo);
        $service->tryCloseProject(55, 'pass', 'queue', $this->makeFeatureSet());
    }

    // ── Error/rollback path ────────────────────────────────────────────

    #[Test]
    public function tryCloseProject_releases_the_lock_when_the_scope_fails(): void
    {
        $redis = $this->createMock(AnalysisRedisServiceInterface::class);
        $redis->method('getProjectWordCounts')->willReturn(['project_segments' => 10, 'num_analyzed' => 10]);
        $redis->method('acquireCompletionLock')->willReturn(true);
        $redis->expects($this->once())->method('releaseCompletionLock')->with(100);
        // The project stays queued so another worker can retry it: the writes never committed.
        $redis->expects($this->never())->method('removeProjectFromQueue');

        // Undoing the writes is the scope's job, not this service's: the repository no longer
        // exposes a commit or a rollback for the service to reach for, so the assertions all sit
        // on the Redis side.
        $repo = $this->makeRepositoryStub();
        $repo->method('getProjectSegmentsTranslationSummary')
            ->willThrowException(new RuntimeException('DB gone'));

        $service = new ProjectCompletionService($redis, $repo);
        $service->tryCloseProject(100, 'pass', 'queue', $this->makeFeatureSet());
    }

    #[Test]
    public function tryCloseProject_handles_a_failed_rollback_gracefully(): void
    {
        $redis = $this->createMock(AnalysisRedisServiceInterface::class);
        $redis->method('getProjectWordCounts')->willReturn(['project_segments' => 10, 'num_analyzed' => 10]);
        $redis->method('acquireCompletionLock')->willReturn(true);
        $redis->expects($this->once())->method('releaseCompletionLock');

        // A rollback that fails surfaces out of the scope instead of the original failure. The
        // service used to guard its own rollback call against exactly this; now the double fault
        // arrives as an ordinary throw and the outer catch has to be the one that absorbs it.
        $repo = $this->createStub(ProjectCompletionRepositoryInterface::class);
        $repo->method('transaction')
            ->willThrowException(new RuntimeException('No active transaction'));

        $service = new ProjectCompletionService($redis, $repo);
        // Should not throw — the lock is released and the project stays queued for a retry.
        $service->tryCloseProject(100, 'pass', 'queue', $this->makeFeatureSet());
    }

    #[Test]
    public function tryCloseProject_feature_hook_exception_does_not_prevent_cache_destruction(): void
    {
        $redis = $this->makeRedisStub(['project_segments' => 5, 'num_analyzed' => 5]);

        $repo = $this->makeRepositoryMock();
        $repo->method('getProjectSegmentsTranslationSummary')->willReturn([
            ['id_job' => 1, 'password' => 'a', 'eq_wc' => 100, 'st_wc' => 100],
            ['id_job' => null, 'password' => null, 'eq_wc' => 100, 'st_wc' => 100, 'project_segments' => 5, 'num_analyzed' => 5],
        ]);
        $repo->method('getProjectJobIds')->willReturn([['id' => 1, 'password' => 'a']]);
        $repo->expects($this->once())->method('destroyAllCaches');

        $featureSet = $this->createStub(FeatureSet::class);
        $featureSet->method('dispatch')->willThrowException(new Exception('Hook failed'));

        $service = new ProjectCompletionService($redis, $repo);
        $service->tryCloseProject(1, 'p', 'q', $featureSet);
    }

    // ── getProjectSegmentsTranslationSummary delegation ────────────────

    #[Test]
    public function getProjectSegmentsTranslationSummary_delegates_to_repository(): void
    {
        $redis = $this->createStub(AnalysisRedisServiceInterface::class);
        $expected = [['id_job' => 1, 'eq_wc' => 500]];

        $repo = $this->makeRepositoryStub();
        $repo->method('getProjectSegmentsTranslationSummary')->willReturn($expected);

        $service = new ProjectCompletionService($redis, $repo);
        $result = $service->getProjectSegmentsTranslationSummary(42);

        $this->assertSame($expected, $result);
    }
}
