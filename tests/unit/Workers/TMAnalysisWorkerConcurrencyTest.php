<?php

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class TMAnalysisWorkerConcurrencyTest extends AbstractTest
{
    private function tmAnalysisWorkerPath(): string
    {
        $path = realpath(__DIR__ . '/../../../lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysisWorker.php');
        $this->assertNotFalse($path);

        return $path;
    }

    private function redisKeysPath(): string
    {
        $path = realpath(__DIR__ . '/../../../lib/Utils/AsyncTasks/Workers/Analysis/RedisKeys.php');
        $this->assertNotFalse($path);

        return $path;
    }

    private function executorPath(): string
    {
        $path = realpath(__DIR__ . '/../../../lib/Utils/TaskRunner/Executor.php');
        $this->assertNotFalse($path);

        return $path;
    }

    private function segmentTranslationDaoPath(): string
    {
        $path = realpath(__DIR__ . '/../../../lib/Model/Translations/SegmentTranslationDao.php');
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
    public function test_update_record_returns_early_on_zero_updated_rows_before_increment_side_effects(): void
    {
        $source = $this->readSource($this->tmAnalysisWorkerPath());

        $zeroGuardPos = strpos($source, 'if ($updateRes === 0)');
        $this->assertNotFalse($zeroGuardPos, 'Expected $updateRes === 0 guard in _updateRecord().');

        $returnPos = strpos($source, 'return;', $zeroGuardPos);
        $this->assertNotFalse($returnPos, 'Expected early return after $updateRes === 0 guard in _updateRecord().');

        $incrementPos = strpos($source, '_incrementAnalyzedCount');
        $this->assertNotFalse($incrementPos, 'Expected _incrementAnalyzedCount call in _updateRecord().');

        $this->assertLessThan($incrementPos, $zeroGuardPos, 'Guard must appear before _incrementAnalyzedCount.');
        $this->assertLessThan($incrementPos, $returnPos, 'Return after zero-guard must appear before _incrementAnalyzedCount.');
    }

    #[Test]
    public function test_force_set_segment_analyzed_returns_on_db_failure_and_guards_zero_affected_rows(): void
    {
        $source = $this->readSource($this->tmAnalysisWorkerPath());

        $this->assertStringContainsString(
            '$affectedRows = $db->update(',
            $source,
            'Expected $affectedRows to capture update result in _forceSetSegmentAnalyzed().'
        );

        $catchPos = strpos($source, 'catch (PDOException $e)');
        $this->assertNotFalse($catchPos, 'Expected PDOException catch in _forceSetSegmentAnalyzed().');

        $returnInCatchPos = strpos($source, 'return;', $catchPos);
        $this->assertNotFalse($returnInCatchPos, 'Expected early return inside PDOException catch in _forceSetSegmentAnalyzed().');

        $affectedRowsGuardPos = strpos($source, 'if ($affectedRows === 0)');
        $this->assertNotFalse($affectedRowsGuardPos, 'Expected $affectedRows === 0 guard in _forceSetSegmentAnalyzed().');

        $incrementPos = strpos($source, '_incrementAnalyzedCount($elementQueue->params->pid', $affectedRowsGuardPos);
        $this->assertNotFalse($incrementPos, 'Expected _incrementAnalyzedCount call after $affectedRows guard in _forceSetSegmentAnalyzed().');

        $this->assertLessThan($incrementPos, $affectedRowsGuardPos, 'Guard must appear before _incrementAnalyzedCount in _forceSetSegmentAnalyzed().');
    }

    #[Test]
    public function test_try_to_close_project_releases_finalization_lock_and_recovers_on_transaction_failure(): void
    {
        $source = $this->readSource($this->tmAnalysisWorkerPath());

        $methodPos = strpos($source, 'protected function _tryToCloseProject');
        $this->assertNotFalse($methodPos, 'Expected _tryToCloseProject() definition.');

        $catchPos = strpos($source, 'catch (\Throwable $e)', $methodPos);
        $this->assertNotFalse($catchPos, 'Expected catch (\\Throwable $e) in _tryToCloseProject().');

        $this->assertStringContainsString('->rollback()', $source, 'Expected DB rollback in _tryToCloseProject() failure path.');

        $delPos = strpos($source, '->del(RedisKeys::PROJECT_ENDING_SEMAPHORE', $catchPos);
        $this->assertNotFalse($delPos, 'Expected semaphore release in _tryToCloseProject() catch block.');

        $rpushPos = strpos($source, '->rpush(', $catchPos);
        $this->assertNotFalse($rpushPos, 'Expected project requeue in _tryToCloseProject() catch block.');
    }

    #[Test]
    public function test_get_matches_requeue_path_has_no_force_set_segment_analyzed_side_effects(): void
    {
        $source = $this->readSource($this->tmAnalysisWorkerPath());

        $methodPos = strpos($source, 'protected function _getMatches');
        $this->assertNotFalse($methodPos, 'Expected _getMatches() definition.');

        $requeueCatchPos = strpos($source, 'catch (ReQueueException $rEx)', $methodPos);
        $this->assertNotFalse($requeueCatchPos, 'Expected ReQueueException catch in _getMatches().');

        $rethrowPos = strpos($source, 'throw $rEx', $requeueCatchPos);
        $this->assertNotFalse($rethrowPos, 'Expected throw $rEx in _getMatches() requeue path.');

        $between = substr($source, $requeueCatchPos, $rethrowPos - $requeueCatchPos);
        $this->assertStringNotContainsString(
            '_forceSetSegmentAnalyzed',
            $between,
            'Requeue path in _getMatches() must not force DONE state side-effects.'
        );
    }

    #[Test]
    public function test_tm_analysis_worker_uses_atomic_redis_locks_without_setnx_expire_pattern(): void
    {
        $source = $this->readSource($this->tmAnalysisWorkerPath());

        $this->assertStringNotContainsString('setnx(', $source, 'setnx() should not be used for semaphores.');

        $this->assertStringContainsString(
            'RedisKeys::PROJECT_INIT_SEMAPHORE',
            $source,
            'Expected PROJECT_INIT_SEMAPHORE acquisition in _initializeTMAnalysis().'
        );
        $this->assertStringContainsString(
            'RedisKeys::PROJECT_ENDING_SEMAPHORE',
            $source,
            'Expected PROJECT_ENDING_SEMAPHORE acquisition in _tryToCloseProject().'
        );
        $this->assertStringContainsString(
            "'NX'",
            $source,
            'Expected Redis set(..., ..., ..., ..., \"NX\") semaphore acquisition pattern.'
        );
    }

    #[Test]
    public function test_initialize_tm_analysis_loser_workers_wait_for_totals_and_warn_on_timeout(): void
    {
        $source = $this->readSource($this->tmAnalysisWorkerPath());

        $methodPos = strpos($source, 'protected function _initializeTMAnalysis');
        $this->assertNotFalse($methodPos, 'Expected _initializeTMAnalysis() definition.');

        $elsePos = strpos($source, '} else {', $methodPos);
        $this->assertNotFalse($elsePos, 'Expected loser-worker else branch in _initializeTMAnalysis().');

        $this->assertNotFalse(
            strpos($source, 'usleep(', $elsePos),
            'Expected usleep() wait loop in _initializeTMAnalysis loser-worker branch.'
        );
        $this->assertStringContainsString(
            'WARNING — timed out waiting for PROJECT_TOT_SEGMENTS',
            $source,
            'Expected timeout warning log in _initializeTMAnalysis().'
        );
    }

    #[Test]
    public function test_try_to_close_project_uses_db_rollup_totals_for_project_word_counts(): void
    {
        $source = $this->readSource($this->tmAnalysisWorkerPath());

        $updateFieldsPos = strpos($source, 'ProjectDao::updateFields(');
        $this->assertNotFalse($updateFieldsPos, 'Expected ProjectDao::updateFields() in _tryToCloseProject().');

        $updateFieldsSlice = substr($source, $updateFieldsPos, 600);
        $this->assertStringContainsString(
            "'tm_analysis_wc' => \$rollup['eq_wc']",
            $updateFieldsSlice,
            'Expected tm_analysis_wc to use DB rollup eq_wc in finalizer.'
        );
        $this->assertStringContainsString(
            "'standard_analysis_wc' => \$rollup['st_wc']",
            $updateFieldsSlice,
            'Expected standard_analysis_wc to use DB rollup st_wc in finalizer.'
        );
    }

    #[Test]
    public function test_set_analysis_value_is_idempotent_for_done_and_skipped_statuses(): void
    {
        $source = $this->readSource($this->segmentTranslationDaoPath());

        $this->assertStringContainsString(
            "tm_analysis_status NOT IN ('SKIPPED', 'DONE')",
            $source,
            'Expected idempotency WHERE guard in SegmentTranslationDao::setAnalysisValue().'
        );
    }

    #[Test]
    public function test_executor_catches_predis_connection_exception_before_throwable(): void
    {
        $source = $this->readSource($this->executorPath());

        $predisCatchPos = strpos($source, 'catch (\\Predis\\Connection\\ConnectionException|\\Predis\\Response\\ServerException $e)');
        $this->assertNotFalse($predisCatchPos, 'Expected Predis catch block in Executor::main().');

        $throwableCatchPos = strpos($source, 'catch (Throwable $e)');
        $this->assertNotFalse($throwableCatchPos, 'Expected Throwable catch block in Executor::main().');

        $this->assertLessThan(
            $throwableCatchPos,
            $predisCatchPos,
            'Predis catch block must appear before Throwable catch block.'
        );
    }

    #[Test]
    public function test_tm_analysis_worker_uses_word_count_scale_constant_and_redis_keys_defines_it(): void
    {
        $workerSource = $this->readSource($this->tmAnalysisWorkerPath());
        $redisKeysSource = $this->readSource($this->redisKeysPath());

        $this->assertStringContainsString(
            'RedisKeys::WORD_COUNT_SCALE',
            $workerSource,
            'Expected RedisKeys::WORD_COUNT_SCALE usage in TMAnalysisWorker incrby calls.'
        );

        $this->assertStringNotContainsString(
            'incrby(RedisKeys::PROJ_EQ_WORD_COUNT . $pid, (int)($eq_words * 1000))',
            $workerSource,
            'Expected no magic 1000 in PROJ_EQ_WORD_COUNT incrby call.'
        );
        $this->assertStringNotContainsString(
            'incrby(RedisKeys::PROJ_ST_WORD_COUNT . $pid, (int)($standard_words * 1000))',
            $workerSource,
            'Expected no magic 1000 in PROJ_ST_WORD_COUNT incrby call.'
        );

        $this->assertStringContainsString(
            'const int WORD_COUNT_SCALE = 1000;',
            $redisKeysSource,
            'Expected WORD_COUNT_SCALE constant definition in RedisKeys.php.'
        );
    }
}
