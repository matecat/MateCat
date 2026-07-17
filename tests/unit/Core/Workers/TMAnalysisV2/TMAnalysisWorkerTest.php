<?php

namespace Matecat\Core\Workers\TMAnalysisV2;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\Analysis\Constants\InternalMatchesConstants;
use Model\DataAccess\Database;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use ReflectionClass;
use ReflectionParameter;
use ReflectionProperty;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\AnalysisRedisServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\EngineServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\MatchProcessorServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\ProjectCompletionServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\SegmentUpdaterServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysisWorker;
use Utils\TaskRunner\Commons\AbstractWorker;
use Utils\TaskRunner\Commons\Context;
use Utils\TaskRunner\Commons\Params;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EmptyElementException;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\NotSupportedMTException;
use Utils\TaskRunner\Exceptions\ReQueueException;

/**
 * Test double: overrides buildEngineConfig() (now protected) to return a fixed array,
 * eliminating static dependencies on EnginesFactory and TmKeyManager that require a DB.
 */
class TestableTMAnalysisWorker extends TMAnalysisWorker
{
    public array $fixedConfig = [];

    protected function buildEngineConfig(QueueElement $queueElement): array
    {
        return $this->fixedConfig;
    }
}

/**
 * In-memory, stateful fake of AnalysisRedisServiceInterface for behavioral tests that must
 * observe the LIVE analyzed counter surviving (or being reset by) a mid-run re-init. The
 * RedisClientSpy used by AnalysisRedisServiceTest only records calls (it holds no state), so
 * it cannot demonstrate the counter being reset downward. This fake tracks the total, the
 * analyzed counter and a per-(segment,job) dedup set, mirroring the real Redis semantics.
 */
class StatefulFakeRedisService implements AnalysisRedisServiceInterface
{
    /** @var array<int, int> */
    public array $tot = [];
    /** @var array<int, int> */
    public array $done = [];
    /** @var array<int, array<string, true>> */
    private array $dedup = [];
    public int $initializeCalls = 0;

    public function reconnect(): void {}

    public function acquireInitLock(int $pid): bool
    {
        // Always grant the lock: models the 30s INIT_LOCK TTL having expired mid-run, so a
        // second worker re-acquires it and drives doInit() again.
        return true;
    }

    public function releaseInitLock(int $pid): void {}

    public function initializeProjectCounters(int $pid, int $projectSegments, int $numAnalyzed): void
    {
        $this->initializeCalls++;
        $this->tot[$pid]   = $projectSegments;
        $this->done[$pid]  = $numAnalyzed;
        $this->dedup[$pid] = [];
    }

    public function clearProjectCounters(int $pid): void
    {
        unset($this->tot[$pid], $this->done[$pid], $this->dedup[$pid]);
    }

    public function setProjectTotalSegments(int $pid, int $total): void
    {
        $this->tot[$pid] = $total;
    }

    public function getProjectTotalSegments(int $pid): ?int
    {
        return $this->tot[$pid] ?? null;
    }

    public function getProjectAnalyzedCount(int $pid): ?int
    {
        return $this->done[$pid] ?? null;
    }

    public function waitForInitialization(int $pid, int $maxWaitMs = 5000): bool
    {
        return true;
    }

    public function incrementAnalyzedCount(int $pid, int $idSegment, int $idJob, float $eqWc, float $stWc): void
    {
        $member = $idSegment . ':' . $idJob;
        if (isset($this->dedup[$pid][$member])) {
            return; // idempotent per (segment, job)
        }
        $this->dedup[$pid][$member] = true;
        $this->done[$pid]           = ($this->done[$pid] ?? 0) + 1;
    }

    public function setProjectAnalyzedCountTTL(int $pid, int $ttlSeconds = 86400): void {}

    /**
     * @return string[]
     */
    public function getWorkingProjects(string $queueKey): array
    {
        return [];
    }

    public function decrementWaitingSegments(string $qid): int
    {
        return 0;
    }

    public function removeProjectFromQueue(string $queueKey, int $pid): void {}

    public function acquireCompletionLock(int $pid): bool
    {
        return false;
    }

    public function releaseCompletionLock(int $pid): void {}

    /**
     * @return array{project_segments: mixed, num_analyzed: mixed, eq_wc: float, st_wc: float}
     */
    public function getProjectWordCounts(int $pid): array
    {
        return [
            'project_segments' => $this->tot[$pid] ?? null,
            'num_analyzed'     => $this->done[$pid] ?? null,
            'eq_wc'            => 0.0,
            'st_wc'            => 0.0,
        ];
    }
}

class TMAnalysisWorkerTest extends AbstractTest
{
    private function buildWorker(
        ?AnalysisRedisServiceInterface $redis = null,
        ?SegmentUpdaterServiceInterface $updater = null,
        ?ProjectCompletionServiceInterface $completion = null,
        ?EngineServiceInterface $engine = null,
        ?MatchProcessorServiceInterface $processor = null,
    ): TestableTMAnalysisWorker {
        $worker = new TestableTMAnalysisWorker(
            $this->createStub(AMQHandler::class),
            obtainTestDatabase(),
            $redis ?? $this->createStub(AnalysisRedisServiceInterface::class),
            $updater ?? $this->createStub(SegmentUpdaterServiceInterface::class),
            $completion ?? $this->createStub(ProjectCompletionServiceInterface::class),
            $engine ?? $this->createStub(EngineServiceInterface::class),
            $processor ?? $this->createStub(MatchProcessorServiceInterface::class),
        );

        (new ReflectionProperty(AbstractWorker::class, '_observer'))->setValue($worker, []);

        $worker->setContext(Context::buildFromArray([
            'queue_name'    => 'test_queue',
            'max_executors' => 1,
        ]));

        $worker->fixedConfig = ['pid' => 100, 'segment' => 'Hello world'];

        return $worker;
    }

    private function makeQueueElement(array $overrides = []): QueueElement
    {
        $element = new QueueElement();
        $params  = new Params();

        $defaults = [
            'id_segment'                 => 1,
            'id_job'                     => 2,
            'pid'                        => 100,
            'ppassword'                  => 'secret',
            'source'                     => 'en-US',
            'target'                     => 'it-IT',
            'segment'                    => 'Hello world',
            'raw_word_count'             => 2.0,
            'payable_rates'              => '{}',
            'id_tms'                     => 1,
            'id_mt_engine'               => 0,
            'features'                   => '',
            'tm_keys'                    => '[]',
            'context_before'             => '',
            'context_after'              => '',
            'mt_quality_value_in_editor' => null,
            'enable_mt_analysis'         => false,
            'mt_qe_workflow_enabled'     => null,
            'match_type'                 => InternalMatchesConstants::NO_MATCH,
            'fast_exact_match_type'      => null,
            'icu_enabled'                => false,
        ];

        foreach (array_merge($defaults, $overrides) as $key => $value) {
            $params->$key = $value;
        }

        $element->params     = $params;
        $element->reQueueNum = 0;

        return $element;
    }

    private function stubLockLoserInit(AnalysisRedisServiceInterface&Stub $redis): void
    {
        $redis->method('acquireInitLock')->willReturn(false);
        $redis->method('waitForInitialization')->willReturn(true);
        $redis->method('getProjectTotalSegments')->willReturn(50);
        $redis->method('getProjectAnalyzedCount')->willReturn(10);
    }

    private function stubMatchPipeline(
        EngineServiceInterface&Stub $engine,
        MatchProcessorServiceInterface&Stub $processor,
        AnalysisRedisServiceInterface&Stub $redis,
    ): void {
        $match = ['match' => '75', 'created_by' => 'TM', 'suggestion' => 'Ciao mondo'];

        $engine->method('getTMMatches')->willReturn([]);
        $engine->method('getMTTranslation')->willReturn([]);
        $processor->method('sortMatches')->willReturn([$match]);
        $processor->method('isMtMatch')->willReturn(false);
        $processor->method('postProcessMatch')->willReturn([
            'suggestion'             => 'Ciao mondo',
            'warning'                => 0,
            'serialized_errors_list' => '',
        ]);
        $processor->method('determinePreTranslateStatus')
            ->willReturnCallback(static fn(array $tmData): array => $tmData);
        $redis->method('getWorkingProjects')->willReturn([]);
    }

    #[Test]
    public function constructor_has_two_required_parameters_queueHandler_and_database(): void
    {
        $ref      = new ReflectionClass(TMAnalysisWorker::class);
        $required = array_values(array_filter(
            $ref->getConstructor()->getParameters(),
            static fn(ReflectionParameter $p): bool => !$p->isOptional()
        ));

        $this->assertCount(2, $required);
        $this->assertSame('queueHandler', $required[0]->getName());
        $this->assertSame('database', $required[1]->getName());
    }

    #[Test]
    public function process_happy_path_calls_all_key_services(): void
    {
        $redis      = $this->createMock(AnalysisRedisServiceInterface::class);
        $engine     = $this->createMock(EngineServiceInterface::class);
        $processor  = $this->createMock(MatchProcessorServiceInterface::class);
        $updater    = $this->createMock(SegmentUpdaterServiceInterface::class);
        $completion = $this->createMock(ProjectCompletionServiceInterface::class);

        $redis->method('acquireInitLock')->willReturn(false);
        $redis->method('waitForInitialization')->willReturn(true);
        $redis->method('getProjectTotalSegments')->willReturn(50);
        $redis->method('getProjectAnalyzedCount')->willReturn(10);
        $redis->method('getWorkingProjects')->willReturn([]);

        $match = ['match' => '75', 'created_by' => 'TM', 'suggestion' => 'Ciao mondo'];

        $engine->expects($this->once())->method('getTMMatches')->willReturn([]);
        $engine->expects($this->once())->method('getMTTranslation')->willReturn([]);
        $processor->expects($this->once())->method('sortMatches')->willReturn([$match]);
        $processor->method('isMtMatch')->willReturn(false);
        $processor->method('postProcessMatch')->willReturn([
            'suggestion'             => 'Ciao mondo',
            'warning'                => 0,
            'serialized_errors_list' => '',
        ]);
        $processor->method('determinePreTranslateStatus')
            ->willReturnCallback(static fn(array $tmData): array => $tmData);

        $updater->expects($this->once())->method('setAnalysisValue')->willReturn(1);
        $redis->expects($this->once())->method('incrementAnalyzedCount');
        $completion->expects($this->once())->method('tryCloseProject');

        $worker = $this->buildWorker(
            redis: $redis,
            updater: $updater,
            completion: $completion,
            engine: $engine,
            processor: $processor,
        );
        $worker->process($this->makeQueueElement());
    }

    #[Test]
    public function process_when_setAnalysisValue_returns_zero_skips_increment_and_close(): void
    {
        $redis      = $this->createMock(AnalysisRedisServiceInterface::class);
        $completion = $this->createMock(ProjectCompletionServiceInterface::class);
        $engine     = $this->createStub(EngineServiceInterface::class);
        $processor  = $this->createStub(MatchProcessorServiceInterface::class);
        $updater    = $this->createStub(SegmentUpdaterServiceInterface::class);

        $this->stubLockLoserInit($redis);
        $this->stubMatchPipeline($engine, $processor, $redis);

        $updater->method('setAnalysisValue')->willReturn(0);

        $redis->expects($this->never())->method('incrementAnalyzedCount');
        $completion->expects($this->never())->method('tryCloseProject');

        $worker = $this->buildWorker(
            redis: $redis,
            updater: $updater,
            completion: $completion,
            engine: $engine,
            processor: $processor,
        );
        $worker->process($this->makeQueueElement());
    }

    #[Test]
    public function process_when_setAnalysisValue_returns_minus_one_still_calls_increment_and_close(): void
    {
        $redis      = $this->createMock(AnalysisRedisServiceInterface::class);
        $completion = $this->createMock(ProjectCompletionServiceInterface::class);
        $engine     = $this->createStub(EngineServiceInterface::class);
        $processor  = $this->createStub(MatchProcessorServiceInterface::class);
        $updater    = $this->createStub(SegmentUpdaterServiceInterface::class);

        $this->stubLockLoserInit($redis);
        $this->stubMatchPipeline($engine, $processor, $redis);

        $updater->method('setAnalysisValue')->willReturn(-1);

        $redis->expects($this->once())->method('incrementAnalyzedCount');
        $completion->expects($this->once())->method('tryCloseProject');

        $worker = $this->buildWorker(
            redis: $redis,
            updater: $updater,
            completion: $completion,
            engine: $engine,
            processor: $processor,
        );
        $worker->process($this->makeQueueElement());
    }

    #[Test]
    public function process_when_setAnalysisValue_returns_positive_calls_increment_and_close(): void
    {
        $redis      = $this->createMock(AnalysisRedisServiceInterface::class);
        $completion = $this->createMock(ProjectCompletionServiceInterface::class);
        $engine     = $this->createStub(EngineServiceInterface::class);
        $processor  = $this->createStub(MatchProcessorServiceInterface::class);
        $updater    = $this->createStub(SegmentUpdaterServiceInterface::class);

        $this->stubLockLoserInit($redis);
        $this->stubMatchPipeline($engine, $processor, $redis);

        $updater->method('setAnalysisValue')->willReturn(1);

        $redis->expects($this->once())->method('incrementAnalyzedCount');
        $completion->expects($this->once())->method('tryCloseProject');

        $worker = $this->buildWorker(
            redis: $redis,
            updater: $updater,
            completion: $completion,
            engine: $engine,
            processor: $processor,
        );
        $worker->process($this->makeQueueElement());
    }

    #[Test]
    public function process_when_doInit_gets_zero_segment_count_releases_lock_and_skips_counter_init(): void
    {
        // Winner branch: we acquire the init lock and run doInit(). The summary query
        // returns a rollup with project_segments <= 0 (segment rows not visible yet), so
        // doInit must release the init lock for retry and NEVER persist a zero total.
        $redis      = $this->createMock(AnalysisRedisServiceInterface::class);
        $completion = $this->createStub(ProjectCompletionServiceInterface::class);
        $engine     = $this->createStub(EngineServiceInterface::class);
        $processor  = $this->createStub(MatchProcessorServiceInterface::class);
        $updater    = $this->createStub(SegmentUpdaterServiceInterface::class);

        $redis->method('acquireInitLock')->willReturn(true);
        $completion->method('getProjectSegmentsTranslationSummary')
            ->willReturn([['project_segments' => 0, 'num_analyzed' => 0]]);
        $this->stubMatchPipeline($engine, $processor, $redis);
        $updater->method('setAnalysisValue')->willReturn(1);

        $redis->expects($this->once())->method('releaseInitLock')->with(100);
        $redis->expects($this->never())->method('initializeProjectCounters');

        $worker = $this->buildWorker(
            redis: $redis,
            updater: $updater,
            completion: $completion,
            engine: $engine,
            processor: $processor,
        );
        $worker->process($this->makeQueueElement());
    }

    #[Test]
    public function process_when_doInit_summary_throws_releases_lock_and_swallows_exception(): void
    {
        // Winner branch: doInit() acquires the lock then the summary query throws. The
        // failure must be swallowed (segment still analyzed on its own) and the lock
        // released so init can be retried — the exception must NOT escape process().
        $redis      = $this->createMock(AnalysisRedisServiceInterface::class);
        $completion = $this->createStub(ProjectCompletionServiceInterface::class);
        $engine     = $this->createStub(EngineServiceInterface::class);
        $processor  = $this->createStub(MatchProcessorServiceInterface::class);
        $updater    = $this->createStub(SegmentUpdaterServiceInterface::class);

        $redis->method('acquireInitLock')->willReturn(true);
        $completion->method('getProjectSegmentsTranslationSummary')
            ->willThrowException(new \RuntimeException('summary query failed'));
        $this->stubMatchPipeline($engine, $processor, $redis);
        $updater->method('setAnalysisValue')->willReturn(1);

        $redis->expects($this->once())->method('releaseInitLock')->with(100);
        $redis->expects($this->never())->method('initializeProjectCounters');

        $worker = $this->buildWorker(
            redis: $redis,
            updater: $updater,
            completion: $completion,
            engine: $engine,
            processor: $processor,
        );
        // Must not throw.
        $worker->process($this->makeQueueElement());
    }

    #[Test]
    public function process_when_reinit_loser_never_acquires_lock_just_logs_and_waits(): void
    {
        // Loser branch: the first acquireInitLock fails, waitForInitialization returns
        // false (winner abandoned init), so we attempt re-init — but the second
        // acquireInitLock also fails (another loser won it). We log "init not ready" and
        // wait again without throwing.
        $redis      = $this->createMock(AnalysisRedisServiceInterface::class);
        $completion = $this->createStub(ProjectCompletionServiceInterface::class);
        $engine     = $this->createStub(EngineServiceInterface::class);
        $processor  = $this->createStub(MatchProcessorServiceInterface::class);
        $updater    = $this->createStub(SegmentUpdaterServiceInterface::class);

        $redis->expects($this->exactly(2))->method('acquireInitLock')->willReturn(false);
        $redis->method('waitForInitialization')->willReturn(false);
        $this->stubMatchPipeline($engine, $processor, $redis);
        $updater->method('setAnalysisValue')->willReturn(1);

        $worker = $this->buildWorker(
            redis: $redis,
            updater: $updater,
            completion: $completion,
            engine: $engine,
            processor: $processor,
        );
        // Must not throw.
        $worker->process($this->makeQueueElement());
    }

    #[Test]
    public function process_with_empty_raw_word_count_throws_EmptyElementException(): void
    {
        $redis   = $this->createStub(AnalysisRedisServiceInterface::class);
        $updater = $this->createStub(SegmentUpdaterServiceInterface::class);

        $this->stubLockLoserInit($redis);
        $updater->method('forceSetSegmentAnalyzed')->willReturn(0);

        $worker = $this->buildWorker(redis: $redis, updater: $updater);

        $this->expectException(EmptyElementException::class);
        $worker->process($this->makeQueueElement(['raw_word_count' => 0]));
    }

    #[Test]
    public function process_when_init_lock_winner_calls_initializeProjectCounters(): void
    {
        $redis      = $this->createMock(AnalysisRedisServiceInterface::class);
        $completion = $this->createStub(ProjectCompletionServiceInterface::class);
        $updater    = $this->createStub(SegmentUpdaterServiceInterface::class);

        $redis->method('acquireInitLock')->willReturn(true);
        $completion->method('getProjectSegmentsTranslationSummary')
            ->willReturn([['project_segments' => 10, 'num_analyzed' => 0]]);

        $redis->expects($this->once())
            ->method('initializeProjectCounters')
            ->with(100, 10, 0);

        $updater->method('forceSetSegmentAnalyzed')->willReturn(0);

        $worker = $this->buildWorker(redis: $redis, completion: $completion, updater: $updater);

        $this->expectException(EmptyElementException::class);
        $worker->process($this->makeQueueElement(['raw_word_count' => 0]));
    }

    #[Test]
    public function process_when_init_lock_loser_calls_waitForInitialization(): void
    {
        $redis   = $this->createMock(AnalysisRedisServiceInterface::class);
        $updater = $this->createStub(SegmentUpdaterServiceInterface::class);

        $redis->method('acquireInitLock')->willReturn(false);
        $redis->method('waitForInitialization')->willReturn(true);
        $redis->method('getProjectTotalSegments')->willReturn(50);
        $redis->method('getProjectAnalyzedCount')->willReturn(10);

        $redis->expects($this->once())
            ->method('waitForInitialization')
            ->with(100);

        $redis->expects($this->never())
            ->method('initializeProjectCounters');

        $updater->method('forceSetSegmentAnalyzed')->willReturn(0);

        $worker = $this->buildWorker(redis: $redis, updater: $updater);

        $this->expectException(EmptyElementException::class);
        $worker->process($this->makeQueueElement(['raw_word_count' => 0]));
    }

    // ── MT match band tests ─────────────────────────────────────────────

    #[Test]
    public function process_mt_match_with_score_gte_09_gets_ice_mt_band(): void
    {
        $redis     = $this->createStub(AnalysisRedisServiceInterface::class);
        $engine    = $this->createStub(EngineServiceInterface::class);
        $processor = $this->createStub(MatchProcessorServiceInterface::class);
        $updater   = $this->createStub(SegmentUpdaterServiceInterface::class);

        $this->stubLockLoserInit($redis);
        $redis->method('getWorkingProjects')->willReturn([]);

        $match = ['match' => '75', 'created_by' => 'MT!', 'memory_key' => '', 'score' => 0.92];

        $engine->method('getTMMatches')->willReturn([]);
        $engine->method('getMTTranslation')->willReturn([]);
        $processor->method('sortMatches')->willReturn([$match]);
        $processor->method('isMtMatch')->willReturn(true);
        $processor->method('calculateWordDiscount')->willReturn([InternalMatchesConstants::ICE_MT, 0.5, 2.0]);
        $processor->method('postProcessMatch')->willReturn([
            'suggestion' => 'translated', 'warning' => 0, 'serialized_errors_list' => '',
        ]);
        $processor->method('determinePreTranslateStatus')
            ->willReturnCallback(static fn(array $d): array => $d);

        $captured = null;
        $updater->method('setAnalysisValue')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return 1;
        });

        $worker = $this->buildWorker(redis: $redis, updater: $updater, engine: $engine, processor: $processor);
        $worker->process($this->makeQueueElement(['mt_qe_workflow_enabled' => true]));

        $this->assertSame(InternalMatchesConstants::ICE_MT, $captured['match_type']);
    }

    #[Test]
    public function process_mt_match_without_mtqe_gets_plain_mt_band(): void
    {
        $redis     = $this->createStub(AnalysisRedisServiceInterface::class);
        $engine    = $this->createStub(EngineServiceInterface::class);
        $processor = $this->createStub(MatchProcessorServiceInterface::class);
        $updater   = $this->createStub(SegmentUpdaterServiceInterface::class);

        $this->stubLockLoserInit($redis);
        $redis->method('getWorkingProjects')->willReturn([]);

        $match = ['match' => '75', 'created_by' => 'MT!', 'memory_key' => '', 'score' => 0.7];

        $engine->method('getTMMatches')->willReturn([]);
        $engine->method('getMTTranslation')->willReturn([]);
        $processor->method('sortMatches')->willReturn([$match]);
        $processor->method('isMtMatch')->willReturn(true);
        $processor->method('calculateWordDiscount')->willReturn([InternalMatchesConstants::MT, 1.5, 2.0]);
        $processor->method('postProcessMatch')->willReturn([
            'suggestion' => 'translated', 'warning' => 0, 'serialized_errors_list' => '',
        ]);
        $processor->method('determinePreTranslateStatus')
            ->willReturnCallback(static fn(array $d): array => $d);

        $captured = null;
        $updater->method('setAnalysisValue')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return 1;
        });

        $worker = $this->buildWorker(redis: $redis, updater: $updater, engine: $engine, processor: $processor);
        $worker->process($this->makeQueueElement(['mt_qe_workflow_enabled' => false]));

        $this->assertSame(InternalMatchesConstants::MT, $captured['match_type']);
    }

    #[Test]
    public function process_mt_match_score_08_with_mtqe_gets_top_quality_band(): void
    {
        $redis     = $this->createStub(AnalysisRedisServiceInterface::class);
        $engine    = $this->createStub(EngineServiceInterface::class);
        $processor = $this->createStub(MatchProcessorServiceInterface::class);
        $updater   = $this->createStub(SegmentUpdaterServiceInterface::class);

        $this->stubLockLoserInit($redis);
        $redis->method('getWorkingProjects')->willReturn([]);

        $match = ['match' => '75', 'created_by' => 'MT!', 'memory_key' => '', 'score' => 0.85];

        $engine->method('getTMMatches')->willReturn([]);
        $engine->method('getMTTranslation')->willReturn([]);
        $processor->method('sortMatches')->willReturn([$match]);
        $processor->method('isMtMatch')->willReturn(true);
        $processor->method('calculateWordDiscount')->willReturn([InternalMatchesConstants::TOP_QUALITY_MT, 1.7, 2.0]);
        $processor->method('postProcessMatch')->willReturn([
            'suggestion' => 'translated', 'warning' => 0, 'serialized_errors_list' => '',
        ]);
        $processor->method('determinePreTranslateStatus')
            ->willReturnCallback(static fn(array $d): array => $d);

        $captured = null;
        $updater->method('setAnalysisValue')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return 1;
        });

        $worker = $this->buildWorker(redis: $redis, updater: $updater, engine: $engine, processor: $processor);
        $worker->process($this->makeQueueElement(['mt_qe_workflow_enabled' => true]));

        $this->assertSame(InternalMatchesConstants::TOP_QUALITY_MT, $captured['match_type']);
    }

    #[Test]
    public function process_mt_match_score_05_with_mtqe_gets_higher_quality_band(): void
    {
        $redis     = $this->createStub(AnalysisRedisServiceInterface::class);
        $engine    = $this->createStub(EngineServiceInterface::class);
        $processor = $this->createStub(MatchProcessorServiceInterface::class);
        $updater   = $this->createStub(SegmentUpdaterServiceInterface::class);

        $this->stubLockLoserInit($redis);
        $redis->method('getWorkingProjects')->willReturn([]);

        $match = ['match' => '75', 'created_by' => 'MT!', 'memory_key' => '', 'score' => 0.65];

        $engine->method('getTMMatches')->willReturn([]);
        $engine->method('getMTTranslation')->willReturn([]);
        $processor->method('sortMatches')->willReturn([$match]);
        $processor->method('isMtMatch')->willReturn(true);
        $processor->method('calculateWordDiscount')->willReturn([InternalMatchesConstants::HIGHER_QUALITY_MT, 1.3, 2.0]);
        $processor->method('postProcessMatch')->willReturn([
            'suggestion' => 'translated', 'warning' => 0, 'serialized_errors_list' => '',
        ]);
        $processor->method('determinePreTranslateStatus')
            ->willReturnCallback(static fn(array $d): array => $d);

        $captured = null;
        $updater->method('setAnalysisValue')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return 1;
        });

        $worker = $this->buildWorker(redis: $redis, updater: $updater, engine: $engine, processor: $processor);
        $worker->process($this->makeQueueElement(['mt_qe_workflow_enabled' => true]));

        $this->assertSame(InternalMatchesConstants::HIGHER_QUALITY_MT, $captured['match_type']);
    }

    #[Test]
    public function process_mt_match_score_below_05_with_mtqe_gets_standard_quality_band(): void
    {
        $redis     = $this->createStub(AnalysisRedisServiceInterface::class);
        $engine    = $this->createStub(EngineServiceInterface::class);
        $processor = $this->createStub(MatchProcessorServiceInterface::class);
        $updater   = $this->createStub(SegmentUpdaterServiceInterface::class);

        $this->stubLockLoserInit($redis);
        $redis->method('getWorkingProjects')->willReturn([]);

        $match = ['match' => '75', 'created_by' => 'MT!', 'memory_key' => '', 'score' => 0.3];

        $engine->method('getTMMatches')->willReturn([]);
        $engine->method('getMTTranslation')->willReturn([]);
        $processor->method('sortMatches')->willReturn([$match]);
        $processor->method('isMtMatch')->willReturn(true);
        $processor->method('calculateWordDiscount')->willReturn([InternalMatchesConstants::STANDARD_QUALITY_MT, 1.0, 2.0]);
        $processor->method('postProcessMatch')->willReturn([
            'suggestion' => 'translated', 'warning' => 0, 'serialized_errors_list' => '',
        ]);
        $processor->method('determinePreTranslateStatus')
            ->willReturnCallback(static fn(array $d): array => $d);

        $captured = null;
        $updater->method('setAnalysisValue')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return 1;
        });

        $worker = $this->buildWorker(redis: $redis, updater: $updater, engine: $engine, processor: $processor);
        $worker->process($this->makeQueueElement(['mt_qe_workflow_enabled' => true]));

        $this->assertSame(InternalMatchesConstants::STANDARD_QUALITY_MT, $captured['match_type']);
    }

    // ── TM match band tests ─────────────────────────────────────────────

    #[Test]
    public function process_tm_100_non_ice_public_gets_tm_100_public_band(): void
    {
        $redis     = $this->createStub(AnalysisRedisServiceInterface::class);
        $engine    = $this->createStub(EngineServiceInterface::class);
        $processor = $this->createStub(MatchProcessorServiceInterface::class);
        $updater   = $this->createStub(SegmentUpdaterServiceInterface::class);

        $this->stubLockLoserInit($redis);
        $redis->method('getWorkingProjects')->willReturn([]);

        // memory_key empty → public TM
        $match = ['match' => '100', 'created_by' => 'TM-User', 'memory_key' => '', 'ICE' => false];

        $engine->method('getTMMatches')->willReturn([]);
        $engine->method('getMTTranslation')->willReturn([]);
        $processor->method('sortMatches')->willReturn([$match]);
        $processor->method('isMtMatch')->willReturn(false);
        $processor->method('calculateWordDiscount')->willReturn([InternalMatchesConstants::TM_100_PUBLIC, 0.2, 0.2]);
        $processor->method('postProcessMatch')->willReturn([
            'suggestion' => 'translated', 'warning' => 0, 'serialized_errors_list' => '',
        ]);
        $processor->method('determinePreTranslateStatus')
            ->willReturnCallback(static fn(array $d): array => $d);

        $captured = null;
        $updater->method('setAnalysisValue')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return 1;
        });

        $worker = $this->buildWorker(redis: $redis, updater: $updater, engine: $engine, processor: $processor);
        $worker->process($this->makeQueueElement(['mt_qe_workflow_enabled' => false]));

        $this->assertSame(InternalMatchesConstants::TM_100_PUBLIC, $captured['match_type']);
    }

    #[Test]
    public function process_tm_100_private_with_mtqe_gets_tm_100_mt_qe_band(): void
    {
        $redis     = $this->createStub(AnalysisRedisServiceInterface::class);
        $engine    = $this->createStub(EngineServiceInterface::class);
        $processor = $this->createStub(MatchProcessorServiceInterface::class);
        $updater   = $this->createStub(SegmentUpdaterServiceInterface::class);

        $this->stubLockLoserInit($redis);
        $redis->method('getWorkingProjects')->willReturn([]);

        $match = ['match' => '100', 'created_by' => 'TM-User', 'memory_key' => 'my-key', 'ICE' => false];

        $engine->method('getTMMatches')->willReturn([]);
        $engine->method('getMTTranslation')->willReturn([]);
        $processor->method('sortMatches')->willReturn([$match]);
        $processor->method('isMtMatch')->willReturn(false);
        $processor->method('calculateWordDiscount')->willReturn([InternalMatchesConstants::TM_100_MT_QE, 0.2, 0.2]);
        $processor->method('postProcessMatch')->willReturn([
            'suggestion' => 'translated', 'warning' => 0, 'serialized_errors_list' => '',
        ]);
        $processor->method('determinePreTranslateStatus')
            ->willReturnCallback(static fn(array $d): array => $d);

        $captured = null;
        $updater->method('setAnalysisValue')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return 1;
        });

        $worker = $this->buildWorker(redis: $redis, updater: $updater, engine: $engine, processor: $processor);
        $worker->process($this->makeQueueElement(['mt_qe_workflow_enabled' => true]));

        $this->assertSame(InternalMatchesConstants::TM_100, $captured['match_type']);
    }

    #[Test]
    public function process_tm_100_public_with_mtqe_gets_tm_100_public_mt_qe_band(): void
    {
        $redis     = $this->createStub(AnalysisRedisServiceInterface::class);
        $engine    = $this->createStub(EngineServiceInterface::class);
        $processor = $this->createStub(MatchProcessorServiceInterface::class);
        $updater   = $this->createStub(SegmentUpdaterServiceInterface::class);

        $this->stubLockLoserInit($redis);
        $redis->method('getWorkingProjects')->willReturn([]);

        $match = ['match' => '100', 'created_by' => 'TM-User', 'memory_key' => '', 'ICE' => false];

        $engine->method('getTMMatches')->willReturn([]);
        $engine->method('getMTTranslation')->willReturn([]);
        $processor->method('sortMatches')->willReturn([$match]);
        $processor->method('isMtMatch')->willReturn(false);
        $processor->method('calculateWordDiscount')->willReturn([InternalMatchesConstants::TM_100_PUBLIC_MT_QE, 0.2, 0.2]);
        $processor->method('postProcessMatch')->willReturn([
            'suggestion' => 'translated', 'warning' => 0, 'serialized_errors_list' => '',
        ]);
        $processor->method('determinePreTranslateStatus')
            ->willReturnCallback(static fn(array $d): array => $d);

        $captured = null;
        $updater->method('setAnalysisValue')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return 1;
        });

        $worker = $this->buildWorker(redis: $redis, updater: $updater, engine: $engine, processor: $processor);
        $worker->process($this->makeQueueElement(['mt_qe_workflow_enabled' => true]));

        $this->assertSame(InternalMatchesConstants::TM_100_PUBLIC, $captured['match_type']);
    }

    #[Test]
    public function process_tm_below_50_gets_no_match_band(): void
    {
        $redis     = $this->createStub(AnalysisRedisServiceInterface::class);
        $engine    = $this->createStub(EngineServiceInterface::class);
        $processor = $this->createStub(MatchProcessorServiceInterface::class);
        $updater   = $this->createStub(SegmentUpdaterServiceInterface::class);

        $this->stubLockLoserInit($redis);
        $redis->method('getWorkingProjects')->willReturn([]);

        $match = ['match' => '40', 'created_by' => 'TM-User', 'memory_key' => 'k1'];

        $engine->method('getTMMatches')->willReturn([]);
        $engine->method('getMTTranslation')->willReturn([]);
        $processor->method('sortMatches')->willReturn([$match]);
        $processor->method('isMtMatch')->willReturn(false);
        $processor->method('calculateWordDiscount')->willReturn([InternalMatchesConstants::NO_MATCH, 2.0, 2.0]);
        $processor->method('postProcessMatch')->willReturn([
            'suggestion' => 'translated', 'warning' => 0, 'serialized_errors_list' => '',
        ]);
        $processor->method('determinePreTranslateStatus')
            ->willReturnCallback(static fn(array $d): array => $d);

        $captured = null;
        $updater->method('setAnalysisValue')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return 1;
        });

        $worker = $this->buildWorker(redis: $redis, updater: $updater, engine: $engine, processor: $processor);
        $worker->process($this->makeQueueElement());

        $this->assertSame(InternalMatchesConstants::NO_MATCH, $captured['match_type']);
    }

    #[Test]
    public function process_tm_95_gets_tm_95_99_band(): void
    {
        $redis     = $this->createStub(AnalysisRedisServiceInterface::class);
        $engine    = $this->createStub(EngineServiceInterface::class);
        $processor = $this->createStub(MatchProcessorServiceInterface::class);
        $updater   = $this->createStub(SegmentUpdaterServiceInterface::class);

        $this->stubLockLoserInit($redis);
        $redis->method('getWorkingProjects')->willReturn([]);

        $match = ['match' => '97', 'created_by' => 'TM-User', 'memory_key' => 'k1'];

        $engine->method('getTMMatches')->willReturn([]);
        $engine->method('getMTTranslation')->willReturn([]);
        $processor->method('sortMatches')->willReturn([$match]);
        $processor->method('isMtMatch')->willReturn(false);
        $processor->method('calculateWordDiscount')->willReturn([InternalMatchesConstants::TM_95_99, 0.4, 0.4]);
        $processor->method('postProcessMatch')->willReturn([
            'suggestion' => 'translated', 'warning' => 0, 'serialized_errors_list' => '',
        ]);
        $processor->method('determinePreTranslateStatus')
            ->willReturnCallback(static fn(array $d): array => $d);

        $captured = null;
        $updater->method('setAnalysisValue')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return 1;
        });

        $worker = $this->buildWorker(redis: $redis, updater: $updater, engine: $engine, processor: $processor);
        $worker->process($this->makeQueueElement());

        $this->assertSame(InternalMatchesConstants::TM_95_99, $captured['match_type']);
    }

    #[Test]
    public function process_tm_60_gets_tm_50_74_band(): void
    {
        $redis     = $this->createStub(AnalysisRedisServiceInterface::class);
        $engine    = $this->createStub(EngineServiceInterface::class);
        $processor = $this->createStub(MatchProcessorServiceInterface::class);
        $updater   = $this->createStub(SegmentUpdaterServiceInterface::class);

        $this->stubLockLoserInit($redis);
        $redis->method('getWorkingProjects')->willReturn([]);

        $match = ['match' => '60', 'created_by' => 'TM-User', 'memory_key' => 'k1'];

        $engine->method('getTMMatches')->willReturn([]);
        $engine->method('getMTTranslation')->willReturn([]);
        $processor->method('sortMatches')->willReturn([$match]);
        $processor->method('isMtMatch')->willReturn(false);
        $processor->method('calculateWordDiscount')->willReturn([InternalMatchesConstants::TM_50_74, 2.0, 2.0]);
        $processor->method('postProcessMatch')->willReturn([
            'suggestion' => 'translated', 'warning' => 0, 'serialized_errors_list' => '',
        ]);
        $processor->method('determinePreTranslateStatus')
            ->willReturnCallback(static fn(array $d): array => $d);

        $captured = null;
        $updater->method('setAnalysisValue')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return 1;
        });

        $worker = $this->buildWorker(redis: $redis, updater: $updater, engine: $engine, processor: $processor);
        $worker->process($this->makeQueueElement());

        $this->assertSame(InternalMatchesConstants::TM_50_74, $captured['match_type']);
    }

    // ── Misc process branch tests ───────────────────────────────────────

    #[Test]
    public function process_picks_first_match_when_all_are_mt_or_below_75(): void
    {
        $redis     = $this->createStub(AnalysisRedisServiceInterface::class);
        $engine    = $this->createStub(EngineServiceInterface::class);
        $processor = $this->createStub(MatchProcessorServiceInterface::class);
        $updater   = $this->createStub(SegmentUpdaterServiceInterface::class);

        $this->stubLockLoserInit($redis);
        $redis->method('getWorkingProjects')->willReturn([]);

        $mtMatch = ['match' => '75', 'created_by' => 'MT!', 'memory_key' => '', 'score' => 0.6];
        $lowTm   = ['match' => '50', 'created_by' => 'TM-User', 'memory_key' => 'k1'];

        $engine->method('getTMMatches')->willReturn([]);
        $engine->method('getMTTranslation')->willReturn([]);
        $processor->method('sortMatches')->willReturn([$mtMatch, $lowTm]);

        // isMtMatch returns true for first, false for second (but second is <75)
        $processor->method('isMtMatch')->willReturnCallback(
            static fn(array $m): bool => str_contains($m['created_by'] ?? '', 'MT')
        );
        $processor->method('calculateWordDiscount')->willReturn([InternalMatchesConstants::MT, 1.5, 2.0]);
        $processor->method('postProcessMatch')->willReturn([
            'suggestion' => 'MT translation', 'warning' => 0, 'serialized_errors_list' => '',
        ]);
        $processor->method('determinePreTranslateStatus')
            ->willReturnCallback(static fn(array $d): array => $d);

        $captured = null;
        $updater->method('setAnalysisValue')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return 1;
        });

        $worker = $this->buildWorker(redis: $redis, updater: $updater, engine: $engine, processor: $processor);
        $worker->process($this->makeQueueElement(['mt_qe_workflow_enabled' => false]));

        // Falls through to first match (MT) since no non-MT match >= 75%
        $this->assertSame('MT', $captured['suggestion_source']);
    }

    #[Test]
    public function process_requeue_exception_when_setAnalysisValue_throws(): void
    {
        $redis     = $this->createStub(AnalysisRedisServiceInterface::class);
        $engine    = $this->createStub(EngineServiceInterface::class);
        $processor = $this->createStub(MatchProcessorServiceInterface::class);
        $updater   = $this->createStub(SegmentUpdaterServiceInterface::class);

        $this->stubLockLoserInit($redis);
        $this->stubMatchPipeline($engine, $processor, $redis);

        $updater->method('setAnalysisValue')->willThrowException(new Exception('DB error'));

        $worker = $this->buildWorker(redis: $redis, updater: $updater, engine: $engine, processor: $processor);

        $this->expectException(ReQueueException::class);
        $worker->process($this->makeQueueElement());
    }

    #[Test]
    public function process_NotSupportedMTException_returns_empty_tm_matches(): void
    {
        $redis     = $this->createStub(AnalysisRedisServiceInterface::class);
        $engine    = $this->createStub(EngineServiceInterface::class);
        $processor = $this->createStub(MatchProcessorServiceInterface::class);
        $updater   = $this->createStub(SegmentUpdaterServiceInterface::class);

        $this->stubLockLoserInit($redis);
        $redis->method('getWorkingProjects')->willReturn([]);

        // getTMMatches throws NotSupportedMTException → caught, tmMatches = []
        $engine->method('getTMMatches')
            ->willThrowException(new NotSupportedMTException('not supported'));
        $engine->method('getMTTranslation')->willReturn([]);

        // No matches at all → EmptyElementException
        $processor->method('sortMatches')->willReturn([]);
        $updater->method('forceSetSegmentAnalyzed')->willReturn(0);

        $worker = $this->buildWorker(redis: $redis, updater: $updater, engine: $engine, processor: $processor);

        $this->expectException(EmptyElementException::class);
        $worker->process($this->makeQueueElement());
    }

    #[Test]
    public function process_suggestion_source_set_to_mt_when_created_by_contains_mt(): void
    {
        $redis     = $this->createStub(AnalysisRedisServiceInterface::class);
        $engine    = $this->createStub(EngineServiceInterface::class);
        $processor = $this->createStub(MatchProcessorServiceInterface::class);
        $updater   = $this->createStub(SegmentUpdaterServiceInterface::class);

        $this->stubLockLoserInit($redis);
        $redis->method('getWorkingProjects')->willReturn([]);

        $match = ['match' => '85', 'created_by' => 'GoogleMT', 'memory_key' => 'k1'];

        $engine->method('getTMMatches')->willReturn([]);
        $engine->method('getMTTranslation')->willReturn([]);
        $processor->method('sortMatches')->willReturn([$match]);
        $processor->method('isMtMatch')->willReturn(true);
        $processor->method('calculateWordDiscount')->willReturn([InternalMatchesConstants::MT, 1.5, 2.0]);
        $processor->method('postProcessMatch')->willReturn([
            'suggestion' => 'translated', 'warning' => 0, 'serialized_errors_list' => '',
        ]);
        $processor->method('determinePreTranslateStatus')
            ->willReturnCallback(static fn(array $d): array => $d);

        $captured = null;
        $updater->method('setAnalysisValue')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return 1;
        });

        $worker = $this->buildWorker(redis: $redis, updater: $updater, engine: $engine, processor: $processor);
        $worker->process($this->makeQueueElement(['mt_qe_workflow_enabled' => false]));

        $this->assertSame(InternalMatchesConstants::MT, $captured['suggestion_source']);
    }

    #[Test]
    public function process_suggestion_source_set_to_tm_when_created_by_has_no_mt(): void
    {
        $redis     = $this->createStub(AnalysisRedisServiceInterface::class);
        $engine    = $this->createStub(EngineServiceInterface::class);
        $processor = $this->createStub(MatchProcessorServiceInterface::class);
        $updater   = $this->createStub(SegmentUpdaterServiceInterface::class);

        $this->stubLockLoserInit($redis);
        $redis->method('getWorkingProjects')->willReturn([]);

        $match = ['match' => '85', 'created_by' => 'translator@example.com', 'memory_key' => 'k1'];

        $engine->method('getTMMatches')->willReturn([]);
        $engine->method('getMTTranslation')->willReturn([]);
        $processor->method('sortMatches')->willReturn([$match]);
        $processor->method('isMtMatch')->willReturn(false);
        $processor->method('calculateWordDiscount')->willReturn([InternalMatchesConstants::TM_85_94, 0.7, 0.7]);
        $processor->method('postProcessMatch')->willReturn([
            'suggestion' => 'translated', 'warning' => 0, 'serialized_errors_list' => '',
        ]);
        $processor->method('determinePreTranslateStatus')
            ->willReturnCallback(static fn(array $d): array => $d);

        $captured = null;
        $updater->method('setAnalysisValue')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return 1;
        });

        $worker = $this->buildWorker(redis: $redis, updater: $updater, engine: $engine, processor: $processor);
        $worker->process($this->makeQueueElement());

        $this->assertSame(InternalMatchesConstants::TM, $captured['suggestion_source']);
    }

    #[Test]
    public function process_forceSetSegmentAnalyzed_true_increments_and_closes(): void
    {
        $redis      = $this->createMock(AnalysisRedisServiceInterface::class);
        $updater    = $this->createStub(SegmentUpdaterServiceInterface::class);
        $completion = $this->createMock(ProjectCompletionServiceInterface::class);

        $this->stubLockLoserInit($redis);
        $redis->method('getWorkingProjects')->willReturn([]);

        // forceSetSegmentAnalyzed returns true → triggers increment + close
        $updater->method('forceSetSegmentAnalyzed')->willReturn(1);

        $redis->expects($this->atLeastOnce())->method('incrementAnalyzedCount');
        $completion->expects($this->atLeastOnce())->method('tryCloseProject');

        $worker = $this->buildWorker(redis: $redis, updater: $updater, completion: $completion);

        $this->expectException(EmptyElementException::class);
        $worker->process($this->makeQueueElement(['raw_word_count' => 0]));
    }

    #[Test]
    public function process_forceSetSegmentAnalyzed_Skipped_increments_and_closes(): void
    {
        $redis = $this->createMock(AnalysisRedisServiceInterface::class);
        $updater = $this->createStub(SegmentUpdaterServiceInterface::class);
        $completion = $this->createMock(ProjectCompletionServiceInterface::class);

        $this->stubLockLoserInit($redis);
        $redis->method('getWorkingProjects')->willReturn([]);

        // forceSetSegmentAnalyzed returns -1 (SKIPPED pre-translation) → triggers increment + close
        $updater->method('forceSetSegmentAnalyzed')->willReturn(-1);

        $redis->expects($this->atLeastOnce())->method('incrementAnalyzedCount');
        $completion->expects($this->atLeastOnce())->method('tryCloseProject');

        $worker = $this->buildWorker(redis: $redis, updater: $updater, completion: $completion);

        $this->expectException(EmptyElementException::class);
        $worker->process($this->makeQueueElement(['raw_word_count' => 0]));
    }

    #[Test]
    public function process_decrements_waiting_projects_from_found_pid_onwards(): void
    {
        $redis     = $this->createStub(AnalysisRedisServiceInterface::class);
        $engine    = $this->createStub(EngineServiceInterface::class);
        $processor = $this->createStub(MatchProcessorServiceInterface::class);
        $updater   = $this->createStub(SegmentUpdaterServiceInterface::class);

        $this->stubLockLoserInit($redis);

        // Working projects: [50, 100, 200] — current PID is 100
        // Should decrement 100 and 200 (from found onwards)
        $redis->method('getWorkingProjects')->willReturn(['50', '100', '200']);

        $decremented = [];
        $redis->method('decrementWaitingSegments')
            ->willReturnCallback(function (string $value) use (&$decremented): int {
                $decremented[] = $value;
                return 1;
            });

        $match = ['match' => '75', 'created_by' => 'TM', 'memory_key' => 'k1'];
        $engine->method('getTMMatches')->willReturn([]);
        $engine->method('getMTTranslation')->willReturn([]);
        $processor->method('sortMatches')->willReturn([$match]);
        $processor->method('isMtMatch')->willReturn(false);
        $processor->method('calculateWordDiscount')->willReturn(['75%-84%', 1.2, 2.0]);
        $processor->method('postProcessMatch')->willReturn([
            'suggestion' => 'translated', 'warning' => 0, 'serialized_errors_list' => '',
        ]);
        $processor->method('determinePreTranslateStatus')
            ->willReturnCallback(static fn(array $d): array => $d);
        $updater->method('setAnalysisValue')->willReturn(1);

        $worker = $this->buildWorker(redis: $redis, updater: $updater, engine: $engine, processor: $processor);
        $worker->process($this->makeQueueElement(['pid' => 100]));

        $this->assertSame(['100', '200'], $decremented);
    }

    #[Test]
    public function process_eq_word_count_capped_at_raw_word_count(): void
    {
        $redis     = $this->createStub(AnalysisRedisServiceInterface::class);
        $engine    = $this->createStub(EngineServiceInterface::class);
        $processor = $this->createStub(MatchProcessorServiceInterface::class);
        $updater   = $this->createStub(SegmentUpdaterServiceInterface::class);

        $this->stubLockLoserInit($redis);
        $redis->method('getWorkingProjects')->willReturn([]);

        $match = ['match' => '75', 'created_by' => 'TM', 'memory_key' => 'k1'];

        $engine->method('getTMMatches')->willReturn([]);
        $engine->method('getMTTranslation')->willReturn([]);
        $processor->method('sortMatches')->willReturn([$match]);
        $processor->method('isMtMatch')->willReturn(false);
        // Return eqWords (999) > raw_word_count (2.0) → should be capped
        $processor->method('postProcessMatch')->willReturn([
            'suggestion' => 'translated', 'warning' => 0, 'serialized_errors_list' => '',
        ]);
        $processor->method('determinePreTranslateStatus')
            ->willReturnCallback(static fn(array $d): array => $d);

        $captured = null;
        $updater->method('setAnalysisValue')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return 1;
        });

        $worker = $this->buildWorker(redis: $redis, updater: $updater, engine: $engine, processor: $processor);
        // payable_rates with rate > 100 so eq_word_count exceeds raw_word_count and gets capped
        $worker->process($this->makeQueueElement(['raw_word_count' => 2.0, 'payable_rates' => '{"75%-84%": 9000}']));

        $this->assertEquals(2.0, $captured['eq_word_count']);
        $this->assertEquals(2.0, $captured['standard_word_count']);
    }

    #[Test]
    public function process_empty_matches_after_sort_throws_EmptyElementException(): void
    {
        $redis     = $this->createStub(AnalysisRedisServiceInterface::class);
        $engine    = $this->createStub(EngineServiceInterface::class);
        $processor = $this->createStub(MatchProcessorServiceInterface::class);
        $updater   = $this->createStub(SegmentUpdaterServiceInterface::class);

        $this->stubLockLoserInit($redis);
        $redis->method('getWorkingProjects')->willReturn([]);

        $engine->method('getTMMatches')->willReturn([]);
        $engine->method('getMTTranslation')->willReturn([]);
        $processor->method('sortMatches')->willReturn([]);
        $updater->method('forceSetSegmentAnalyzed')->willReturn(0);

        $worker = $this->buildWorker(redis: $redis, updater: $updater, engine: $engine, processor: $processor);

        $this->expectException(EmptyElementException::class);
        $worker->process($this->makeQueueElement());
    }

    #[Test]
    public function process_endQueueCallback_when_requeue_exceeds_max(): void
    {
        $redis   = $this->createStub(AnalysisRedisServiceInterface::class);
        $updater = $this->createStub(SegmentUpdaterServiceInterface::class);

        $this->stubLockLoserInit($redis);
        $redis->method('getWorkingProjects')->willReturn([]);
        $updater->method('forceSetSegmentAnalyzed')->willReturn(1);

        $worker = $this->buildWorker(redis: $redis, updater: $updater);

        // Set maxRequeueNum to a low value so reQueueNum >= max triggers _endQueueCallback
        $ref = new ReflectionProperty(AbstractWorker::class, 'maxRequeueNum');
        $ref->setValue($worker, 1);

        $element = $this->makeQueueElement();
        $element->reQueueNum = 5; // exceeds maxRequeueNum

        $this->expectException(EndQueueException::class);
        $worker->process($element);
    }

    #[Test]
    public function process_malformed_payable_rates_json_falls_back_to_empty_array(): void
    {
        $redis     = $this->createStub(AnalysisRedisServiceInterface::class);
        $engine    = $this->createStub(EngineServiceInterface::class);
        $processor = $this->createStub(MatchProcessorServiceInterface::class);
        $updater   = $this->createStub(SegmentUpdaterServiceInterface::class);

        $this->stubLockLoserInit($redis);
        $redis->method('getWorkingProjects')->willReturn([]);

        $match = ['match' => '75', 'created_by' => 'TM', 'memory_key' => 'k1'];

        $engine->method('getTMMatches')->willReturn([]);
        $engine->method('getMTTranslation')->willReturn([]);
        $processor->method('sortMatches')->willReturn([$match]);
        $processor->method('isMtMatch')->willReturn(false);
        $processor->method('calculateWordDiscount')->willReturn([InternalMatchesConstants::NO_MATCH, 2.0, 2.0]);
        $processor->method('postProcessMatch')->willReturn([
            'suggestion' => 'translated', 'warning' => 0, 'serialized_errors_list' => '',
        ]);
        $processor->method('determinePreTranslateStatus')
            ->willReturnCallback(static fn(array $d): array => $d);

        $captured = null;
        $updater->method('setAnalysisValue')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return 1;
        });

        $worker = $this->buildWorker(redis: $redis, updater: $updater, engine: $engine, processor: $processor);
        // payable_rates = "not json" → json_decode returns null → fallback to []
        $worker->process($this->makeQueueElement(['payable_rates' => 'invalid json{{{']));

        // Should still process without error, using default rate=100
        $this->assertNotNull($captured);
        // Match is '75' → band 75%-84% (from getNewMatchTypeAndEquivalentWordDiscount)
        $this->assertSame(InternalMatchesConstants::TM_75_84, $captured['match_type']);
    }

    // ── doInit idempotency guard (mid-run re-init) regression ───────────

    #[Test]
    public function process_doInit_when_already_initialized_skips_reseed_and_summary_query(): void
    {
        // Regression: mid-run re-init MUST be idempotent. The 30s INIT_LOCK TTL
        // (AnalysisRedisService::INIT_LOCK_TTL_SECONDS) can expire during an analysis longer
        // than 30s; a second worker then re-acquires the lock and re-runs doInit(). If doInit
        // re-ran initializeProjectCounters() it would setex-OVERWRITE the live
        // PROJECT_NUM_SEGMENTS_DONE with a lagging DB snapshot and del the dedup set, dropping
        // in-flight increments and stranding the project below PROJECT_TOT_SEGMENTS (real
        // incident: counter 3036 vs total 3039). The guard skips the re-seed when the total is
        // already present in Redis — and must not even run the expensive summary query.
        $redis      = $this->createMock(AnalysisRedisServiceInterface::class);
        $completion = $this->createMock(ProjectCompletionServiceInterface::class);
        $updater    = $this->createStub(SegmentUpdaterServiceInterface::class);

        // Winner re-acquires the init lock mid-run …
        $redis->method('acquireInitLock')->willReturn(true);
        // … but the project counters already exist (this run initialized them earlier).
        $redis->method('getProjectTotalSegments')->willReturn(3039);

        // Guard must short-circuit: no re-seed, and not even the heavy DB summary query.
        $redis->expects($this->never())->method('initializeProjectCounters');
        $completion->expects($this->never())->method('getProjectSegmentsTranslationSummary');

        $updater->method('forceSetSegmentAnalyzed')->willReturn(0);

        $worker = $this->buildWorker(redis: $redis, completion: $completion, updater: $updater);

        // raw_word_count 0 short-circuits with EmptyElementException right after init,
        // isolating doInit() (mirrors process_when_init_lock_winner_calls_initializeProjectCounters).
        $this->expectException(EmptyElementException::class);
        $worker->process($this->makeQueueElement(['raw_word_count' => 0]));
    }

    #[Test]
    public function process_doInit_on_first_init_still_seeds_counters_from_db_summary(): void
    {
        // The idempotency guard must NOT break legitimate first-time init (or crashed-winner
        // recovery): when Redis has no total yet, doInit seeds the counters from the
        // DB-derived summary. Proves the guard's early-return does not swallow real inits.
        $redis      = $this->createMock(AnalysisRedisServiceInterface::class);
        $completion = $this->createStub(ProjectCompletionServiceInterface::class);
        $updater    = $this->createStub(SegmentUpdaterServiceInterface::class);

        $redis->method('acquireInitLock')->willReturn(true);
        // No total present in Redis → this is a genuine first init.
        $redis->method('getProjectTotalSegments')->willReturn(null);
        $completion->method('getProjectSegmentsTranslationSummary')
            ->willReturn([['project_segments' => 3039, 'num_analyzed' => 1200]]);

        $redis->expects($this->once())
            ->method('initializeProjectCounters')
            ->with(100, 3039, 1200);

        $updater->method('forceSetSegmentAnalyzed')->willReturn(0);

        $worker = $this->buildWorker(redis: $redis, completion: $completion, updater: $updater);

        $this->expectException(EmptyElementException::class);
        $worker->process($this->makeQueueElement(['raw_word_count' => 0]));
    }

    #[Test]
    public function doInit_reinit_midrun_does_not_reset_live_analyzed_counter(): void
    {
        // Behavioral reproduction of the real 1840→reset incident, driven through the same
        // public entry point the other worker tests use (process()). First init seeds
        // tot=3039, done=0. Progress then advances the LIVE counter to 1840 via distinct
        // (segment,job) increments. The 30s INIT_LOCK TTL expires mid-run and a worker
        // re-acquires it, driving doInit() a SECOND time. The idempotency guard must skip the
        // re-seed so the live 1840 survives — before the fix, doInit re-ran
        // initializeProjectCounters() and reset done back to 0, so the completion gate
        // (done >= tot) could never open and the project stranded at FAST_OK.
        $pid  = 100;
        $fake = new StatefulFakeRedisService();

        $completion = $this->createStub(ProjectCompletionServiceInterface::class);
        $completion->method('getProjectSegmentsTranslationSummary')
            ->willReturn([['project_segments' => 3039, 'num_analyzed' => 0]]);

        $updater = $this->createStub(SegmentUpdaterServiceInterface::class);
        $updater->method('forceSetSegmentAnalyzed')->willReturn(0);

        // ── Run A: first init (raw_word_count 0 isolates doInit via EmptyElementException) ──
        $workerA = $this->buildWorker(redis: $fake, completion: $completion, updater: $updater);
        try {
            $workerA->process($this->makeQueueElement(['raw_word_count' => 0]));
        } catch (EmptyElementException) {
            // expected — empty word count segment
        }

        $this->assertSame(3039, $fake->getProjectTotalSegments($pid));
        $this->assertSame(0, $fake->getProjectAnalyzedCount($pid));

        // ── Simulate live progress: 1840 distinct (segment, job) pairs analyzed ──
        for ($i = 1; $i <= 1840; $i++) {
            $fake->incrementAnalyzedCount($pid, $i, 2, 1.0, 1.0);
        }
        $this->assertSame(1840, $fake->getProjectAnalyzedCount($pid));

        // ── Run B: mid-run re-init after the 30s lock TTL expired (lock re-acquired) ──
        $workerB = $this->buildWorker(redis: $fake, completion: $completion, updater: $updater);
        try {
            $workerB->process($this->makeQueueElement(['raw_word_count' => 0]));
        } catch (EmptyElementException) {
            // expected
        }

        // Live counter preserved and NOT reset downward; the dedup set was not wiped.
        $this->assertSame(
            1840,
            $fake->getProjectAnalyzedCount($pid),
            'mid-run re-init must not reset the live analyzed counter (the 1840→0 bug)'
        );
        $this->assertSame(3039, $fake->getProjectTotalSegments($pid));
        $this->assertSame(
            1,
            $fake->initializeCalls,
            'initializeProjectCounters must run only on first init, never on the mid-run re-init'
        );
    }
}
