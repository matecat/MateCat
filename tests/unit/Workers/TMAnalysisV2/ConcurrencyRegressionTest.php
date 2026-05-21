<?php

namespace unit\Workers\TMAnalysisV2;

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class ConcurrencyRegressionTest extends AbstractTest
{
    private function workerPath(): string
    {
        $path = realpath(self::projectRoot() . '/lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysisWorker.php');
        $this->assertNotFalse($path);

        return $path;
    }

    private function analysisRedisServicePath(): string
    {
        $path = realpath(self::projectRoot() . '/lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Service/AnalysisRedisService.php');
        $this->assertNotFalse($path);

        return $path;
    }

    private function segmentUpdaterServicePath(): string
    {
        $path = realpath(self::projectRoot() . '/lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Service/SegmentUpdaterService.php');
        $this->assertNotFalse($path);

        return $path;
    }

    private function projectCompletionServicePath(): string
    {
        $path = realpath(self::projectRoot() . '/lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Service/ProjectCompletionService.php');
        $this->assertNotFalse($path);

        return $path;
    }

    private function projectCompletionRepositoryPath(): string
    {
        $path = realpath(self::projectRoot() . '/lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Service/ProjectCompletionRepository.php');
        $this->assertNotFalse($path);

        return $path;
    }

    private function matchProcessorServicePath(): string
    {
        $path = realpath(self::projectRoot() . '/lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Service/MatchProcessorService.php');
        $this->assertNotFalse($path);

        return $path;
    }

    private function segmentTranslationDaoPath(): string
    {
        $path = realpath(self::projectRoot() . '/lib/Model/Translations/SegmentTranslationDao.php');
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
    public function test_worker_returns_early_on_zero_updated_rows_before_redis_side_effects(): void
    {
        $source = $this->readSource($this->workerPath());

        $zeroGuardPos = strpos($source, 'if ($updateRes === 0)');
        $this->assertNotFalse($zeroGuardPos, 'Expected $updateRes === 0 guard in TMAnalysisWorker::process().');

        $returnPos = strpos($source, 'return;', $zeroGuardPos);
        $this->assertNotFalse($returnPos, 'Expected early return after $updateRes === 0 guard.');

        $incrementPos = strpos($source, 'incrementAnalyzedCount');
        $this->assertNotFalse($incrementPos, 'Expected incrementAnalyzedCount call in TMAnalysisWorker::process().');

        $this->assertLessThan($incrementPos, $zeroGuardPos, 'Zero-update guard must appear before incrementAnalyzedCount.');
        $this->assertLessThan($incrementPos, $returnPos, 'Early return after zero-update guard must appear before incrementAnalyzedCount.');
    }

    #[Test]
    public function test_worker_requeue_exception_catch_has_no_force_set_segment_analyzed(): void
    {
        $source = $this->readSource($this->workerPath());

        $requeueCatchPos = strpos($source, 'catch (ReQueueException $e)');
        $this->assertNotFalse($requeueCatchPos, 'Expected ReQueueException catch in TMAnalysisWorker::process().');

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
            "Completion lock must have TTL 86400 to prevent permanent deadlocks on crash."
        );
    }

    #[Test]
    public function test_project_completion_service_removes_from_queue_after_commit_not_before(): void
    {
        $source = $this->readSource($this->projectCompletionServicePath());

        $methodPos = strpos($source, 'public function tryCloseProject');
        $this->assertNotFalse($methodPos);

        $commitPos = strpos($source, '->commit()', $methodPos);
        $this->assertNotFalse($commitPos, 'Expected ->commit() in tryCloseProject().');

        $removePos = strpos($source, 'removeProjectFromQueue(', $methodPos);
        $this->assertNotFalse($removePos, 'Expected removeProjectFromQueue() in tryCloseProject().');

        $this->assertGreaterThan(
            $commitPos,
            $removePos,
            'removeProjectFromQueue() must appear AFTER commit() — crash-safety requires the project to stay in the queue until DB work is committed.'
        );
    }

    #[Test]
    public function test_project_completion_service_recovers_on_transaction_failure_with_rollback(): void
    {
        $source = $this->readSource($this->projectCompletionServicePath());

        $methodPos = strpos($source, 'public function tryCloseProject');
        $this->assertNotFalse($methodPos, 'Expected tryCloseProject() definition in ProjectCompletionService.');

        $catchPos = strpos($source, 'catch (Throwable $e)', $methodPos);
        $this->assertNotFalse($catchPos, 'Expected catch (Throwable $e) in ProjectCompletionService::tryCloseProject().');

        $this->assertStringContainsString(
            '->rollback()',
            $source,
            'Expected DB rollback() in ProjectCompletionService failure path.'
        );

        $releasePos = strpos($source, 'releaseCompletionLock(', $catchPos);
        $this->assertNotFalse($releasePos, 'Expected releaseCompletionLock() in catch block — lock must be released on failure.');
    }

    #[Test]
    public function test_project_completion_service_has_db_authoritative_gate_before_transaction(): void
    {
        $source = $this->readSource($this->projectCompletionServicePath());

        $methodPos = strpos($source, 'public function tryCloseProject');
        $this->assertNotFalse($methodPos);

        // DB gate must query MySQL via getProjectSegmentsTranslationSummary BEFORE beginTransaction
        $summaryPos = strpos($source, 'getProjectSegmentsTranslationSummary(', $methodPos);
        $this->assertNotFalse($summaryPos, 'Expected getProjectSegmentsTranslationSummary() call in tryCloseProject().');

        $beginPos = strpos($source, '->beginTransaction()', $methodPos);
        $this->assertNotFalse($beginPos, 'Expected beginTransaction() in tryCloseProject().');

        $this->assertLessThan(
            $beginPos,
            $summaryPos,
            'DB-authoritative gate (getProjectSegmentsTranslationSummary) must appear BEFORE beginTransaction — verify segments are DONE in MySQL before starting the completion transaction.'
        );

        // Must release lock and return early if MySQL disagrees
        $dbRemainingPos = strpos($source, 'dbRemaining', $methodPos);
        $this->assertNotFalse($dbRemainingPos, 'Expected $dbRemaining check in DB-authoritative gate.');

        $releaseOnDriftPos = strpos($source, 'releaseCompletionLock(', $dbRemainingPos);
        $this->assertNotFalse($releaseOnDriftPos, 'Expected releaseCompletionLock() when MySQL says segments remain (Redis/MySQL drift).');

        $this->assertLessThan(
            $beginPos,
            $releaseOnDriftPos,
            'Lock release on drift must happen BEFORE beginTransaction — do not start a transaction if MySQL disagrees.'
        );
    }

    #[Test]
    public function test_project_completion_service_uses_defensive_lte_zero_close_condition(): void
    {
        $source = $this->readSource($this->projectCompletionServicePath());

        $this->assertStringContainsString(
            '<= 0',
            $source,
            'tryCloseProject() must use <= 0 (not === 0) to handle overcounted num_analyzed defensively.'
        );

        $this->assertStringNotContainsString(
            "=== 0\n",
            $source,
            'tryCloseProject() must NOT use === 0 for the close condition — overcounted segments would leave the project stuck.'
        );
    }

    #[Test]
    public function test_segment_updater_force_set_has_not_in_done_skipped_where_guard(): void
    {
        $source = $this->readSource($this->segmentUpdaterServicePath());

        $this->assertStringContainsString(
            "tm_analysis_status NOT IN ('DONE', 'SKIPPED')",
            $source,
            'forceSetSegmentAnalyzed() must include WHERE guard to skip already-DONE/SKIPPED rows, preventing double-counting under MYSQL_ATTR_FOUND_ROWS.'
        );
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
            'WARNING — timed out waiting for init completion',
            $source,
            'Expected timeout warning log in waitForInitialization().'
        );
    }

    #[Test]
    public function test_wait_for_initialization_checks_both_tot_segments_and_num_done(): void
    {
        $source = $this->readSource($this->analysisRedisServicePath());

        $methodPos = strpos($source, 'public function waitForInitialization');
        $this->assertNotFalse($methodPos);

        $methodEnd = strpos($source, "\n    }\n", $methodPos);
        $body = substr($source, $methodPos, $methodEnd - $methodPos);

        $this->assertStringContainsString(
            'PROJECT_TOT_SEGMENTS',
            $body,
            'waitForInitialization must check PROJECT_TOT_SEGMENTS.'
        );
        $this->assertStringContainsString(
            'PROJECT_NUM_SEGMENTS_DONE',
            $body,
            'waitForInitialization must check PROJECT_NUM_SEGMENTS_DONE — both keys must exist before losers proceed (TOCTOU fix).'
        );
    }

    #[Test]
    public function test_initialize_project_counters_uses_transaction(): void
    {
        $source = $this->readSource($this->analysisRedisServicePath());

        $methodPos = strpos($source, 'public function initializeProjectCounters');
        $this->assertNotFalse($methodPos, 'Expected initializeProjectCounters() definition in AnalysisRedisService.');

        $methodEnd = strpos($source, "\n    }\n", $methodPos);
        $body = substr($source, $methodPos, $methodEnd - $methodPos);

        $this->assertStringContainsString(
            '->transaction(',
            $body,
            'initializeProjectCounters must use a Redis transaction (MULTI/EXEC) to guarantee atomic, ordered writes.'
        );

        $totPos = strpos($body, 'PROJECT_TOT_SEGMENTS');
        $donePos = strpos($body, 'PROJECT_NUM_SEGMENTS_DONE');
        $this->assertNotFalse($totPos);
        $this->assertNotFalse($donePos);
        $this->assertLessThan(
            $donePos,
            $totPos,
            'PROJECT_TOT_SEGMENTS must be written BEFORE PROJECT_NUM_SEGMENTS_DONE — losers poll for the last key as the init-complete signal.'
        );
    }

    #[Test]
    public function test_init_lock_ttl_is_short_for_crash_safety(): void
    {
        $source = $this->readSource($this->analysisRedisServicePath());

        $methodPos = strpos($source, 'public function acquireInitLock');
        $this->assertNotFalse($methodPos);

        $methodEnd = strpos($source, "\n    }\n", $methodPos);
        $body = substr($source, $methodPos, $methodEnd - $methodPos);

        $this->assertStringContainsString(
            "'EX', 30",
            $body,
            'Init lock TTL must be 30s (not 86400s) — short enough to recover from crashed winners.'
        );
    }

    #[Test]
    public function test_match_processor_service_does_not_contain_db_reporting_query(): void
    {
        $source = $this->readSource($this->matchProcessorServicePath());

        $this->assertStringNotContainsString(
            'getProjectSegmentsTranslationSummary',
            $source,
            'getProjectSegmentsTranslationSummary() must NOT live in MatchProcessorService — it belongs in ProjectCompletionService.'
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

        $this->assertStringContainsString(
            "(float)\$rollup['eq_wc']",
            $source,
            "ProjectCompletionService must use DB rollup eq_wc (not Redis counter) to prevent word-count drift."
        );
        $this->assertStringContainsString(
            "(float)\$rollup['st_wc']",
            $source,
            "ProjectCompletionService must use DB rollup st_wc (not Redis counter) to prevent word-count drift."
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

    // ── Ported from V1 DAO tests ─────────────────────────────────────────

    #[Test]
    public function test_project_completion_distributes_job_standard_wc_from_db_rollup(): void
    {
        $source = $this->readSource($this->projectCompletionServicePath());

        $this->assertStringContainsString(
            "\$rollup['st_wc'] / \$numberOfJobs",
            $source,
            'Job standard_analysis_wc must use $rollup[st_wc] / $numberOfJobs from DB rollup.'
        );

        $this->assertStringNotContainsString(
            "\$project_totals['st_wc']",
            $source,
            'Job standard_analysis_wc must NOT use Redis-derived $project_totals — use DB $rollup only.'
        );
    }

    #[Test]
    public function test_segment_updater_force_set_captures_affected_rows_and_logs_idempotency(): void
    {
        $source = $this->readSource($this->segmentUpdaterServicePath());

        $this->assertStringContainsString(
            "tm_analysis_status NOT IN ('DONE', 'SKIPPED')",
            $source,
            'forceSetSegmentAnalyzed must use WHERE guard to skip DONE/SKIPPED rows, preventing double-counting.'
        );

        $this->assertStringContainsString(
            'already DONE, skipping force-set side-effects.',
            $source,
            'Expected idempotency log message when affected rows is zero.'
        );

        $catchPos = strpos($source, 'catch (PDOException $e)');
        $this->assertNotFalse($catchPos);

        $zeroGuardPos = strpos($source, 'if ($affectedRows === 0)');
        $this->assertNotFalse($zeroGuardPos);

        $this->assertLessThan(
            $zeroGuardPos,
            $catchPos,
            'PDOException catch must appear before $affectedRows === 0 guard in forceSetSegmentAnalyzed.'
        );
    }

    #[Test]
    public function test_worker_side_effects_gate_logs_before_early_return(): void
    {
        $source = $this->readSource($this->workerPath());

        $guardPos = strpos($source, 'if ($updateRes === 0)');
        $this->assertNotFalse($guardPos);

        $logPos = strpos($source, 'not updated (already DONE/SKIPPED or missing), skipping side-effects', $guardPos);
        $this->assertNotFalse(
            $logPos,
            'TMAnalysisWorker must log explicit idempotency message after zero-update guard.'
        );

        $returnPos = strpos($source, 'return;', $logPos);
        $this->assertNotFalse(
            $returnPos,
            'Expected early return after idempotency log message.'
        );
    }

    #[Test]
    public function test_analysis_redis_service_wait_uses_correct_constants(): void
    {
        $source = $this->readSource($this->analysisRedisServicePath());

        $this->assertStringContainsString(
            'int $maxWaitMs = 5000',
            $source,
            'waitForInitialization default timeout must be 5000ms.'
        );

        $this->assertSame(
            1,
            preg_match('/\$sleepMs\s*=\s*min\(\$sleepMs\s*\*\s*2,\s*500\)/', $source),
            'Exponential backoff must cap at 500ms per iteration.'
        );
    }

    #[Test]
    public function test_analysis_redis_service_has_exactly_two_atomic_nx_lock_acquisitions(): void
    {
        $source = $this->readSource($this->analysisRedisServicePath());

        $this->assertSame(
            2,
            preg_match_all(
                '/->set\s*\(\s*RedisKeys::PROJECT_(?:INIT|ENDING)_SEMAPHORE/s',
                $source
            ),
            'AnalysisRedisService must have exactly 2 atomic NX lock acquisitions (init + completion).'
        );
    }

    #[Test]
    public function test_project_completion_service_sql_counts_done_and_skipped_as_analyzed(): void
    {
        $source = $this->readSource($this->projectCompletionRepositoryPath());

        $this->assertStringContainsString(
            "SUM(IF(st.tm_analysis_status IN ('DONE', 'SKIPPED'), 1, 0)) AS num_analyzed",
            $source,
            "ProjectCompletionRepository::getProjectSegmentsTranslationSummary() must count both DONE and SKIPPED segments."
        );
    }

    #[Test]
    public function test_redis_keys_defines_word_count_scale_constant(): void
    {
        $path = realpath(self::projectRoot() . '/lib/Utils/AsyncTasks/Workers/Analysis/RedisKeys.php');
        $this->assertNotFalse($path);

        $source = $this->readSource($path);

        $this->assertStringContainsString(
            'const int WORD_COUNT_SCALE = 1000;',
            $source,
            'RedisKeys must define WORD_COUNT_SCALE constant for integer-scaled Redis word counts.'
        );
    }

    // ── Layer 1: post-commit Redis retry (KNOWN_CONCURRENCY_ISSUES.md #1) ──

    #[Test]
    public function test_worker_has_apply_post_commit_side_effects_method(): void
    {
        $source = $this->readSource($this->workerPath());

        $this->assertStringContainsString(
            'private function applyPostCommitSideEffects(',
            $source,
            'TMAnalysisWorker must have applyPostCommitSideEffects() to isolate post-commit Redis ops from the Executor requeue path.'
        );
    }

    #[Test]
    public function test_process_calls_apply_post_commit_side_effects_not_increment_directly(): void
    {
        $source = $this->readSource($this->workerPath());

        // Bound process() body tightly: from "public function process(" to the
        // next method declaration. process() is followed by _endQueueCallback().
        $processStart = strpos($source, 'public function process(');
        $this->assertNotFalse($processStart);
        $nextMethod = strpos($source, 'protected function _endQueueCallback(', $processStart + 1);
        $this->assertNotFalse($nextMethod, 'Expected _endQueueCallback() after process().');
        $processBody = substr($source, $processStart, $nextMethod - $processStart);

        $this->assertStringContainsString(
            'applyPostCommitSideEffects(',
            $processBody,
            'process() must delegate to applyPostCommitSideEffects() after DB commit.'
        );

        $this->assertStringNotContainsString(
            'incrementAnalyzedCount(',
            $processBody,
            'process() must NOT call incrementAnalyzedCount() directly — must go through applyPostCommitSideEffects() to prevent requeue after DB commit.'
        );
    }

    #[Test]
    public function test_force_set_segment_analyzed_calls_apply_post_commit_side_effects(): void
    {
        $source = $this->readSource($this->workerPath());

        $methodPos = strpos($source, 'protected function _forceSetSegmentAnalyzed(');
        $this->assertNotFalse($methodPos);

        $nextMethod = strpos($source, 'private function ', $methodPos);
        $methodBody = substr($source, $methodPos, $nextMethod - $methodPos);

        $this->assertStringContainsString(
            'applyPostCommitSideEffects(',
            $methodBody,
            '_forceSetSegmentAnalyzed() must delegate to applyPostCommitSideEffects() after DB commit.'
        );

        $this->assertStringNotContainsString(
            'incrementAnalyzedCount(',
            $methodBody,
            '_forceSetSegmentAnalyzed() must NOT call incrementAnalyzedCount() directly.'
        );
    }

    #[Test]
    public function test_apply_post_commit_side_effects_catches_predis_exceptions_and_does_not_rethrow(): void
    {
        $source = $this->readSource($this->workerPath());

        $methodPos = strpos($source, 'private function applyPostCommitSideEffects(');
        $this->assertNotFalse($methodPos, 'applyPostCommitSideEffects() must exist in TMAnalysisWorker.');

        $nextMethod = strpos($source, "\n    private function ", $methodPos + 1);
        if ($nextMethod === false) {
            $nextMethod = strpos($source, "\n    public function ", $methodPos + 1);
        }
        $methodBody = substr($source, $methodPos, $nextMethod - $methodPos);

        $this->assertStringContainsString(
            'PredisConnectionException|PredisServerException',
            $methodBody,
            'applyPostCommitSideEffects() must catch both PredisConnectionException and PredisServerException.'
        );

        // Must NOT rethrow — swallowing is intentional to prevent Executor requeue
        $this->assertStringNotContainsString(
            'throw $e',
            $methodBody,
            'applyPostCommitSideEffects() must NOT rethrow Predis exceptions — rethrowing causes Executor requeue which permanently loses the counter.'
        );

        $this->assertStringNotContainsString(
            'throw new',
            $methodBody,
            'applyPostCommitSideEffects() must NOT throw any exception — it is a fire-and-forget method after DB commit.'
        );
    }

    #[Test]
    public function test_apply_post_commit_side_effects_uses_exponential_backoff(): void
    {
        $source = $this->readSource($this->workerPath());

        $methodPos = strpos($source, 'private function applyPostCommitSideEffects(');
        $this->assertNotFalse($methodPos);

        $nextMethod = strpos($source, "\n    private function ", $methodPos + 1);
        if ($nextMethod === false) {
            $nextMethod = strpos($source, "\n    public function ", $methodPos + 1);
        }
        $methodBody = substr($source, $methodPos, $nextMethod - $methodPos);

        $this->assertStringContainsString(
            'usleep(',
            $methodBody,
            'applyPostCommitSideEffects() must use usleep() for backoff between retries.'
        );

        $this->assertStringContainsString(
            '$delayMs *= 2',
            $methodBody,
            'applyPostCommitSideEffects() must double the delay on each retry (exponential backoff).'
        );

        $this->assertStringContainsString(
            '$delayMs    = 500',
            $methodBody,
            'applyPostCommitSideEffects() initial delay must be 500ms (not lower — allows Redis to recover).'
        );
    }

    #[Test]
    public function test_apply_post_commit_side_effects_reconnects_redis_between_retries(): void
    {
        $source = $this->readSource($this->workerPath());

        $methodPos = strpos($source, 'private function applyPostCommitSideEffects(');
        $this->assertNotFalse($methodPos);

        $nextMethod = strpos($source, "\n    private function ", $methodPos + 1);
        if ($nextMethod === false) {
            $nextMethod = strpos($source, "\n    public function ", $methodPos + 1);
        }
        $methodBody = substr($source, $methodPos, $nextMethod - $methodPos);

        $this->assertStringContainsString(
            '->reconnect()',
            $methodBody,
            'applyPostCommitSideEffects() must call reconnect() between retries to destroy and recreate the Redis TCP connection.'
        );

        // reconnect() must appear BEFORE usleep() — disconnect first, then wait
        $reconnectPos = strpos($methodBody, '->reconnect()');
        $usleepPos = strpos($methodBody, 'usleep(', $reconnectPos);
        $this->assertNotFalse($usleepPos, 'usleep() must appear after reconnect() — disconnect before sleeping.');
    }

    #[Test]
    public function test_apply_post_commit_side_effects_logs_critical_on_exhaustion(): void
    {
        $source = $this->readSource($this->workerPath());

        $methodPos = strpos($source, 'private function applyPostCommitSideEffects(');
        $this->assertNotFalse($methodPos);

        $nextMethod = strpos($source, "\n    private function ", $methodPos + 1);
        if ($nextMethod === false) {
            $nextMethod = strpos($source, "\n    public function ", $methodPos + 1);
        }
        $methodBody = substr($source, $methodPos, $nextMethod - $methodPos);

        $this->assertStringContainsString(
            'CRITICAL:',
            $methodBody,
            'applyPostCommitSideEffects() must log a CRITICAL message when all retries are exhausted.'
        );
    }

    #[Test]
    public function test_worker_imports_predis_exception_classes(): void
    {
        $source = $this->readSource($this->workerPath());

        $this->assertStringContainsString(
            'use Predis\Connection\ConnectionException as PredisConnectionException;',
            $source,
            'TMAnalysisWorker must import Predis\Connection\ConnectionException.'
        );

        $this->assertStringContainsString(
            'use Predis\Response\ServerException as PredisServerException;',
            $source,
            'TMAnalysisWorker must import Predis\Response\ServerException.'
        );
    }

    #[Test]
    public function test_analysis_redis_service_interface_declares_reconnect(): void
    {
        $path = realpath(self::projectRoot() . '/lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Interface/AnalysisRedisServiceInterface.php');
        $this->assertNotFalse($path);

        $source = $this->readSource($path);

        $this->assertStringContainsString(
            'public function reconnect(): void;',
            $source,
            'AnalysisRedisServiceInterface must declare reconnect() for connection reset between retries.'
        );
    }

    #[Test]
    public function test_analysis_redis_service_reconnect_calls_disconnect(): void
    {
        $source = $this->readSource($this->analysisRedisServicePath());

        $methodPos = strpos($source, 'public function reconnect()');
        $this->assertNotFalse($methodPos, 'AnalysisRedisService must implement reconnect().');

        $nextMethod = strpos($source, 'public function ', $methodPos + 1);
        $methodBody = substr($source, $methodPos, $nextMethod - $methodPos);

        $this->assertStringContainsString(
            '->disconnect()',
            $methodBody,
            'reconnect() must call disconnect() on the Predis client to force a fresh TCP connection on next command.'
        );
    }
}
