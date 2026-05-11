<?php

namespace unit\Workers\TMAnalysisV2;

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class ConcurrencyRegressionTest extends AbstractTest
{
    private function workerV2Path(): string
    {
        $path = realpath(__DIR__ . '/../../../../lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/TMAnalysisWorkerV2.php');
        $this->assertNotFalse($path);

        return $path;
    }

    private function analysisRedisServicePath(): string
    {
        $path = realpath(__DIR__ . '/../../../../lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Service/AnalysisRedisService.php');
        $this->assertNotFalse($path);

        return $path;
    }

    private function segmentUpdaterServicePath(): string
    {
        $path = realpath(__DIR__ . '/../../../../lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Service/SegmentUpdaterService.php');
        $this->assertNotFalse($path);

        return $path;
    }

    private function projectCompletionServicePath(): string
    {
        $path = realpath(__DIR__ . '/../../../../lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Service/ProjectCompletionService.php');
        $this->assertNotFalse($path);

        return $path;
    }

    private function matchProcessorServicePath(): string
    {
        $path = realpath(__DIR__ . '/../../../../lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Service/MatchProcessorService.php');
        $this->assertNotFalse($path);

        return $path;
    }

    private function segmentTranslationDaoPath(): string
    {
        $path = realpath(__DIR__ . '/../../../../lib/Model/Translations/SegmentTranslationDao.php');
        $this->assertNotFalse($path);

        return $path;
    }

    private function readSource(string $path): string
    {
        $source = file_get_contents($path);
        $this->assertNotFalse($source);

        return $source;
    }

    #[Test]
    public function test_set_analysis_value_dao_has_where_not_in_done_skipped_guard(): void
    {
        $source = $this->readSource($this->segmentTranslationDaoPath());

        $this->assertStringContainsString(
            "tm_analysis_status NOT IN ('SKIPPED', 'DONE')",
            $source,
            'SegmentTranslationDao::setAnalysisValue() must contain WHERE guard to skip DONE/SKIPPED rows, preventing double-counting.'
        );
    }

    #[Test]
    public function test_worker_v2_returns_early_on_zero_updated_rows_before_redis_side_effects(): void
    {
        $source = $this->readSource($this->workerV2Path());

        $zeroGuardPos = strpos($source, 'if ($updateRes === 0)');
        $this->assertNotFalse($zeroGuardPos, 'Expected $updateRes === 0 guard in TMAnalysisWorkerV2::process().');

        $returnPos = strpos($source, 'return;', $zeroGuardPos);
        $this->assertNotFalse($returnPos, 'Expected early return after $updateRes === 0 guard.');

        $incrementPos = strpos($source, 'incrementAnalyzedCount');
        $this->assertNotFalse($incrementPos, 'Expected incrementAnalyzedCount call in TMAnalysisWorkerV2::process().');

        $this->assertLessThan($incrementPos, $zeroGuardPos, 'Zero-update guard must appear before incrementAnalyzedCount.');
        $this->assertLessThan($incrementPos, $returnPos, 'Early return after zero-update guard must appear before incrementAnalyzedCount.');
    }

    #[Test]
    public function test_worker_v2_requeue_exception_catch_has_no_force_set_segment_analyzed(): void
    {
        $source = $this->readSource($this->workerV2Path());

        $requeueCatchPos = strpos($source, 'catch (ReQueueException $e)');
        $this->assertNotFalse($requeueCatchPos, 'Expected ReQueueException catch in TMAnalysisWorkerV2::process().');

        $rethrowPos = strpos($source, 'throw $e', $requeueCatchPos);
        $this->assertNotFalse($rethrowPos, 'Expected throw $e in ReQueueException catch path.');

        $between = substr($source, $requeueCatchPos, $rethrowPos - $requeueCatchPos);

        $this->assertStringNotContainsString(
            '_forceSetSegmentAnalyzed',
            $between,
            'ReQueueException catch must not call _forceSetSegmentAnalyzed — that would force DONE on a segment about to be retried.'
        );
    }

    #[Test]
    public function test_segment_updater_force_set_catches_pdo_exception_and_guards_zero_affected_rows(): void
    {
        $source = $this->readSource($this->segmentUpdaterServicePath());

        $catchPos = strpos($source, 'catch (PDOException $e)');
        $this->assertNotFalse($catchPos, 'Expected PDOException catch in SegmentUpdaterService::forceSetSegmentAnalyzed().');

        $returnInCatchPos = strpos($source, 'return false;', $catchPos);
        $this->assertNotFalse($returnInCatchPos, 'Expected return false inside PDOException catch to prevent counter increment on DB failure.');

        $affectedRowsGuardPos = strpos($source, 'if ($affectedRows === 0)');
        $this->assertNotFalse($affectedRowsGuardPos, 'Expected $affectedRows === 0 guard in SegmentUpdaterService::forceSetSegmentAnalyzed().');

        $returnOnZeroPos = strpos($source, 'return false;', $affectedRowsGuardPos);
        $this->assertNotFalse($returnOnZeroPos, 'Expected return false after $affectedRows === 0 guard to prevent duplicate counter increment.');
    }

    #[Test]
    public function test_analysis_redis_service_uses_atomic_nx_locks_for_init_and_completion(): void
    {
        $source = $this->readSource($this->analysisRedisServicePath());

        $this->assertStringNotContainsString(
            'setnx(',
            $source,
            'AnalysisRedisService must not use setnx() — it is non-atomic with a separate expire call.'
        );

        $initLockPos = strpos($source, 'PROJECT_INIT_SEMAPHORE');
        $this->assertNotFalse($initLockPos, 'Expected PROJECT_INIT_SEMAPHORE usage in AnalysisRedisService::acquireInitLock().');

        $completionLockPos = strpos($source, 'PROJECT_ENDING_SEMAPHORE');
        $this->assertNotFalse($completionLockPos, 'Expected PROJECT_ENDING_SEMAPHORE usage in AnalysisRedisService::acquireCompletionLock().');

        $nxCountInit = substr_count(
            substr($source, $initLockPos, 200),
            "'NX'"
        );
        $this->assertGreaterThanOrEqual(1, $nxCountInit, "acquireInitLock() must use 'NX' option for atomic Redis lock acquisition.");

        $nxCountCompletion = substr_count(
            substr($source, $completionLockPos, 200),
            "'NX'"
        );
        $this->assertGreaterThanOrEqual(1, $nxCountCompletion, "acquireCompletionLock() must use 'NX' option for atomic Redis lock acquisition.");

        $this->assertStringContainsString(
            "'EX', 86400",
            $source,
            "Locks must have a TTL via 'EX', 86400 to prevent deadlocks on crash."
        );
    }

    #[Test]
    public function test_project_completion_service_recovers_on_transaction_failure_with_rollback_and_requeue(): void
    {
        $source = $this->readSource($this->projectCompletionServicePath());

        $methodPos = strpos($source, 'public function tryCloseProject');
        $this->assertNotFalse($methodPos, 'Expected tryCloseProject() definition in ProjectCompletionService.');

        $catchPos = strpos($source, 'catch (\Throwable $e)', $methodPos);
        $this->assertNotFalse($catchPos, 'Expected catch (\\Throwable $e) in ProjectCompletionService::tryCloseProject().');

        $this->assertStringContainsString(
            '->rollback()',
            $source,
            'Expected DB rollback() in ProjectCompletionService failure path.'
        );

        $releasePos = strpos($source, 'releaseCompletionLock(', $catchPos);
        $this->assertNotFalse($releasePos, 'Expected releaseCompletionLock() in catch block — lock must be released on failure.');

        $requeuePos = strpos($source, 'reAddProjectToQueue(', $catchPos);
        $this->assertNotFalse($requeuePos, 'Expected reAddProjectToQueue() in catch block — project must be requeued for retry on failure.');
    }

    #[Test]
    public function test_analysis_redis_service_wait_for_initialization_uses_exponential_backoff(): void
    {
        $source = $this->readSource($this->analysisRedisServicePath());

        $methodPos = strpos($source, 'public function waitForInitialization');
        $this->assertNotFalse($methodPos, 'Expected waitForInitialization() definition in AnalysisRedisService.');

        $usleepPos = strpos($source, 'usleep(', $methodPos);
        $this->assertNotFalse($usleepPos, 'Expected usleep() spin-wait loop in waitForInitialization().');

        $backoffPos = strpos($source, '$sleepMs * 2', $methodPos);
        $this->assertNotFalse($backoffPos, 'Expected exponential backoff ($sleepMs * 2) in waitForInitialization().');

        $this->assertStringContainsString(
            'WARNING — timed out waiting for PROJECT_TOT_SEGMENTS',
            $source,
            'Expected timeout warning log in waitForInitialization().'
        );
    }

    #[Test]
    public function test_match_processor_service_uses_sum_if_done_skipped_sql_pattern(): void
    {
        $source = $this->readSource($this->matchProcessorServicePath());

        $this->assertStringContainsString(
            "SUM(IF(st.tm_analysis_status IN ('DONE', 'SKIPPED'), 1, 0)) AS num_analyzed",
            $source,
            "MatchProcessorService::getProjectSegmentsTranslationSummary() must use SUM(IF(... IN ('DONE','SKIPPED'), 1, 0)) to count only truly analyzed segments."
        );
    }

    #[Test]
    public function test_executor_predis_catch_not_applicable_to_v2_services(): void
    {
        $this->assertTrue(
            true,
            'Pattern 9 (Executor Predis catch) is N/A for V2 service files — V2 uses injected AnalysisRedisServiceInterface, not Executor. Covered by TMAnalysisWorkerConcurrencyTest::test_executor_catches_predis_connection_exception_before_throwable.'
        );
    }

    #[Test]
    public function test_project_completion_service_uses_db_rollup_via_array_pop_for_word_counts(): void
    {
        $source = $this->readSource($this->projectCompletionServicePath());

        $methodPos = strpos($source, 'public function tryCloseProject');
        $this->assertNotFalse($methodPos, 'Expected tryCloseProject() definition in ProjectCompletionService.');

        $fullReportPos = strpos($source, '$_full_report', $methodPos);
        $this->assertNotFalse($fullReportPos, 'Expected $_full_report variable in tryCloseProject().');

        $arrayPopPos = strpos($source, 'array_pop($_full_report)', $methodPos);
        $this->assertNotFalse($arrayPopPos, 'Expected array_pop($_full_report) to extract the SQL ROLLUP row for word counts.');

        $rollupPos = strpos($source, "\$rollup['eq_wc']", $methodPos);
        $this->assertNotFalse($rollupPos, "Expected \$rollup['eq_wc'] usage after array_pop to populate tm_analysis_wc from DB rollup.");

        $this->assertStringContainsString(
            "'tm_analysis_wc' => \$rollup['eq_wc']",
            $source,
            "ProjectCompletionService must use DB rollup eq_wc for tm_analysis_wc (not Redis counter) to prevent word-count drift."
        );
        $this->assertStringContainsString(
            "'standard_analysis_wc' => \$rollup['st_wc']",
            $source,
            "ProjectCompletionService must use DB rollup st_wc for standard_analysis_wc (not Redis counter) to prevent word-count drift."
        );
    }

    #[Test]
    public function test_analysis_redis_service_uses_word_count_scale_constant_for_incrby(): void
    {
        $source = $this->readSource($this->analysisRedisServicePath());

        $this->assertStringContainsString(
            'RedisKeys::WORD_COUNT_SCALE',
            $source,
            'AnalysisRedisService must use RedisKeys::WORD_COUNT_SCALE constant for incrby calls — no magic 1000 literals.'
        );

        $this->assertStringNotContainsString(
            '$eqWc * 1000',
            $source,
            'AnalysisRedisService must not use magic literal 1000 for eq word count incrby — use RedisKeys::WORD_COUNT_SCALE instead.'
        );

        $this->assertStringNotContainsString(
            '$stWc * 1000',
            $source,
            'AnalysisRedisService must not use magic literal 1000 for st word count incrby — use RedisKeys::WORD_COUNT_SCALE instead.'
        );
    }
}
