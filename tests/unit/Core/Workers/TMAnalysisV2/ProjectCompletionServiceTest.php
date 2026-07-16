<?php


namespace Matecat\Core\Workers\TMAnalysisV2;

use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use PHPUnit\Framework\Attributes\Test;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\AnalysisRedisServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\ProjectCompletionRepositoryInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\ProjectCompletionServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service\ProjectCompletionService;

class ProjectCompletionServiceTest extends AbstractTest
{

    private function sourcePath(): string
    {
        $path = realpath(self::projectRoot() . '/lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Service/ProjectCompletionService.php');
        $this->assertNotFalse($path, 'ProjectCompletionService.php must exist at expected path.');

        return $path;
    }

    private function repositorySourcePath(): string
    {
        $path = realpath(self::projectRoot() . '/lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Service/ProjectCompletionRepository.php');
        $this->assertNotFalse($path, 'ProjectCompletionRepository.php must exist at expected path.');

        return $path;
    }

    private function readSource(): string
    {
        $source = file_get_contents($this->sourcePath());
        $this->assertNotFalse($source, 'Could not read ProjectCompletionService.php source.');

        return $source;
    }

    private function readRepositorySource(): string
    {
        $source = file_get_contents($this->repositorySourcePath());
        $this->assertNotFalse($source, 'Could not read ProjectCompletionRepository.php source.');

        return $source;
    }

    private function makeRedisService(): AnalysisRedisServiceInterface
    {
        return $this->createMock(AnalysisRedisServiceInterface::class);
    }

    private function makeService(?AnalysisRedisServiceInterface $redisService = null): ProjectCompletionService
    {
        $redisService ??= $this->createStub(AnalysisRedisServiceInterface::class);

        return new ProjectCompletionService($redisService, $this->createStub(ProjectCompletionRepositoryInterface::class));
    }

    #[Test]
    public function test_service_implements_project_completion_service_interface(): void
    {
        $source = $this->readSource();
        $this->assertStringContainsString(
            'implements ProjectCompletionServiceInterface',
            $source,
            'ProjectCompletionService must declare implements ProjectCompletionServiceInterface.'
        );
    }

    #[Test]
    public function test_try_close_project_catches_throwable(): void
    {
        $source = $this->readSource();
        $this->assertStringContainsString(
            'catch (Throwable $e)',
            $source,
            'tryCloseProject must contain a catch (\Throwable $e) block to handle finalization failures.'
        );
    }

    #[Test]
    public function test_rollback_is_called_inside_throwable_catch(): void
    {
        $source = $this->readSource();

        $catchPos = strpos($source, 'catch (Throwable $e)');
        $this->assertNotFalse($catchPos, 'Expected catch (\Throwable $e) block in tryCloseProject.');

        $rollbackPos = strpos($source, '->rollback()', $catchPos);
        $this->assertNotFalse(
            $rollbackPos,
            'Expected ->rollback() call inside the \Throwable catch block.'
        );
    }

    #[Test]
    public function test_release_completion_lock_is_called_inside_throwable_catch(): void
    {
        $source = $this->readSource();

        $catchPos = strpos($source, 'catch (Throwable $e)');
        $this->assertNotFalse($catchPos, 'Expected catch (\Throwable $e) block in tryCloseProject.');

        $releasePos = strpos($source, 'releaseCompletionLock(', $catchPos);
        $this->assertNotFalse(
            $releasePos,
            'Expected releaseCompletionLock() call inside the \Throwable catch block.'
        );
    }

    #[Test]
    public function test_remove_project_from_queue_appears_after_commit(): void
    {
        $source = $this->readSource();

        $methodPos = strpos($source, 'public function tryCloseProject');
        $this->assertNotFalse($methodPos);

        $commitPos = strpos($source, '->commit()', $methodPos);
        $this->assertNotFalse($commitPos, 'Expected ->commit() call in tryCloseProject.');

        $removePos = strpos($source, 'removeProjectFromQueue(', $methodPos);
        $this->assertNotFalse($removePos, 'Expected removeProjectFromQueue() call in tryCloseProject.');

        $this->assertGreaterThan(
            $commitPos,
            $removePos,
            'removeProjectFromQueue() must appear AFTER commit() — if the worker crashes before commit, the project must remain in the queue for retry.'
        );
    }

    #[Test]
    public function test_rollback_appears_before_release_in_catch(): void
    {
        $source = $this->readSource();

        $catchPos = strpos($source, 'catch (Throwable $e)');
        $this->assertNotFalse($catchPos);

        $rollbackPos  = strpos($source, '->rollback()', $catchPos);
        $releasePos   = strpos($source, 'releaseCompletionLock(', $catchPos);

        $this->assertNotFalse($rollbackPos, 'Expected ->rollback() in catch block.');
        $this->assertNotFalse($releasePos, 'Expected releaseCompletionLock() in catch block.');

        $this->assertLessThan($releasePos, $rollbackPos, 'rollback() must appear before releaseCompletionLock().');
    }

    #[Test]
    public function test_get_project_segments_summary_uses_group_by_rollup_sql(): void
    {
        $source = $this->readRepositorySource();
        $this->assertStringContainsString(
            'GROUP BY id_job WITH ROLLUP',
            $source,
            'ProjectCompletionRepository::getProjectSegmentsTranslationSummary() must use GROUP BY id_job WITH ROLLUP to produce totals row.'
        );
    }

    #[Test]
    public function test_array_pop_used_to_extract_rollup_totals_row(): void
    {
        $source = $this->readSource();
        $this->assertStringContainsString(
            'array_pop(',
            $source,
            'array_pop() must be used to extract the ROLLUP totals row from the query result set.'
        );
    }

    #[Test]
    public function test_public_method_get_project_segments_translation_summary_declared(): void
    {
        $source = $this->readSource();
        $this->assertStringContainsString(
            'public function getProjectSegmentsTranslationSummary(',
            $source,
            'Expected public method getProjectSegmentsTranslationSummary() to be declared in ProjectCompletionService.'
        );
    }

    #[Test]
    public function test_db_derived_rollup_fields_eq_wc_and_st_wc_used_for_project_update(): void
    {
        $source = $this->readSource();

        $rollupArrayPop = strpos($source, 'array_pop(');
        $this->assertNotFalse($rollupArrayPop);

        $eqWcUsage = strpos($source, "['eq_wc']", $rollupArrayPop);
        $stWcUsage = strpos($source, "['st_wc']", $rollupArrayPop);

        $this->assertNotFalse($eqWcUsage, "Expected rollup['eq_wc'] usage after array_pop.");
        $this->assertNotFalse($stWcUsage, "Expected rollup['st_wc'] usage after array_pop.");
    }

    #[Test]
    public function test_empty_project_segments_causes_early_return_before_lock(): void
    {
        $redisService = $this->makeRedisService();
        $redisService->method('getProjectWordCounts')
            ->willReturn(['project_segments' => '', 'num_analyzed' => 0]);

        $redisService->expects($this->never())
            ->method('acquireCompletionLock');

        $featureSet = $this->createStub(FeatureSet::class);
        $service    = $this->makeService($redisService);

        $service->tryCloseProject(1, 'secret', 'queue:key', $featureSet);
    }

    #[Test]
    public function test_absent_project_segments_key_causes_early_return_before_lock(): void
    {
        $redisService = $this->makeRedisService();
        $redisService->method('getProjectWordCounts')
            ->willReturn(['num_analyzed' => 3]);

        $redisService->expects($this->never())
            ->method('acquireCompletionLock');

        $featureSet = $this->createStub(FeatureSet::class);
        $service    = $this->makeService($redisService);

        $service->tryCloseProject(42, 'secret', 'queue:key', $featureSet);
    }

    #[Test]
    public function test_segments_not_fully_analyzed_does_not_acquire_completion_lock(): void
    {
        $redisService = $this->makeRedisService();
        $redisService->method('getProjectWordCounts')
            ->willReturn(['project_segments' => 10, 'num_analyzed' => 7]);

        $redisService->expects($this->never())
            ->method('acquireCompletionLock');

        $featureSet = $this->createStub(FeatureSet::class);
        $service    = $this->makeService($redisService);

        $service->tryCloseProject(7, 'pass', 'queue:k', $featureSet);
    }

    #[Test]
    public function test_fully_analyzed_but_lock_not_acquired_does_not_start_db_transaction(): void
    {
        $redisService = $this->makeRedisService();
        $redisService->method('getProjectWordCounts')
            ->willReturn(['project_segments' => 5, 'num_analyzed' => 5]);

        $redisService->method('acquireCompletionLock')
            ->willReturn(false);

        $redisService->expects($this->never())
            ->method('removeProjectFromQueue');

        $featureSet = $this->createStub(FeatureSet::class);
        $service    = $this->makeService($redisService);

        $service->tryCloseProject(99, 'pwd', 'q', $featureSet);
    }

    #[Test]
    public function test_service_can_be_instantiated_and_implements_interface(): void
    {
        $service = $this->makeService();
        $this->assertInstanceOf(ProjectCompletionServiceInterface::class, $service);
    }

    #[Test]
    public function test_successful_finalization_clears_project_counters_exactly_once(): void
    {
        // After a project finalizes (DB rollup confirms all segments DONE/SKIPPED and the
        // transaction commits), the 5 Redis counter keys MUST be cleared so a later
        // re-analysis of the same PID starts clean. Without this, a stale PROJECT_TOT_SEGMENTS
        // survives its 24h TTL and both suppresses re-init (doInit's idempotency guard) and
        // can block completion — the counter-lifecycle other half of the mid-run-reset fix.
        $pid = 42;

        $redisService = $this->createMock(AnalysisRedisServiceInterface::class);
        $redisService->method('getProjectWordCounts')->willReturn([
            'project_segments' => '3039',
            'num_analyzed'     => '3039',
            'eq_wc'            => 10.0,
            'st_wc'            => 8.0,
        ]);
        $redisService->method('acquireCompletionLock')->willReturn(true);

        $repository = $this->createStub(ProjectCompletionRepositoryInterface::class);
        // Single ROLLUP totals row → array_pop() yields it; dbRemaining = 3039 - 3039 = 0 → finalize.
        $repository->method('getProjectSegmentsTranslationSummary')->willReturn([
            ['project_segments' => 3039, 'num_analyzed' => 3039, 'eq_wc' => 10.0, 'st_wc' => 8.0],
        ]);
        $repository->method('getProjectJobIds')->willReturn([]);

        $redisService->expects($this->once())
            ->method('clearProjectCounters')
            ->with($pid);

        // gateRetrySleepMicros = 0: the first DB-gate check already confirms completion, so no
        // sleep is needed, but pass 0 defensively to keep the test fast.
        $service = new ProjectCompletionService($redisService, $repository, null, 6, 0);

        $service->tryCloseProject($pid, 'ppwd', 'queue_key', $this->createStub(FeatureSet::class));
    }
}
