<?php

namespace unit\Workers\TMAnalysisV2;

use Model\Analysis\Constants\InternalMatchesConstants;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use ReflectionClass;
use ReflectionProperty;
use TestHelpers\AbstractTest;
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
        $redis->method('waitForInitialization');
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
    public function constructor_has_exactly_one_required_parameter_amqhandler(): void
    {
        $ref      = new ReflectionClass(TMAnalysisWorker::class);
        $required = array_values(array_filter(
            $ref->getConstructor()->getParameters(),
            static fn(\ReflectionParameter $p): bool => !$p->isOptional()
        ));

        $this->assertCount(1, $required);
        $this->assertSame('queueHandler', $required[0]->getName());
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
        $redis->method('waitForInitialization');
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
    public function process_with_empty_raw_word_count_throws_EmptyElementException(): void
    {
        $redis   = $this->createStub(AnalysisRedisServiceInterface::class);
        $updater = $this->createStub(SegmentUpdaterServiceInterface::class);

        $this->stubLockLoserInit($redis);
        $updater->method('forceSetSegmentAnalyzed')->willReturn(false);

        $worker = $this->buildWorker(redis: $redis, updater: $updater);

        $this->expectException(EmptyElementException::class);
        $worker->process($this->makeQueueElement(['raw_word_count' => 0]));
    }

    #[Test]
    public function process_when_init_lock_winner_calls_setProjectTotalSegments(): void
    {
        $redis      = $this->createMock(AnalysisRedisServiceInterface::class);
        $completion = $this->createStub(ProjectCompletionServiceInterface::class);
        $updater    = $this->createStub(SegmentUpdaterServiceInterface::class);

        $redis->method('acquireInitLock')->willReturn(true);
        $redis->method('incrementAnalyzedCount');
        $completion->method('getProjectSegmentsTranslationSummary')
            ->willReturn([['project_segments' => 10, 'num_analyzed' => 0]]);

        $redis->expects($this->once())
            ->method('setProjectTotalSegments')
            ->with(100, 10);

        $updater->method('forceSetSegmentAnalyzed')->willReturn(false);

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
        $redis->method('getProjectTotalSegments')->willReturn(50);
        $redis->method('getProjectAnalyzedCount')->willReturn(10);

        $redis->expects($this->once())
            ->method('waitForInitialization')
            ->with(100);

        $redis->expects($this->never())
            ->method('setProjectTotalSegments');

        $updater->method('forceSetSegmentAnalyzed')->willReturn(false);

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

        $updater->method('setAnalysisValue')->willThrowException(new \Exception('DB error'));

        $worker = $this->buildWorker(redis: $redis, updater: $updater, engine: $engine, processor: $processor);

        $this->expectException(\Utils\TaskRunner\Exceptions\ReQueueException::class);
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
            ->willThrowException(new \Utils\TaskRunner\Exceptions\NotSupportedMTException('not supported'));
        $engine->method('getMTTranslation')->willReturn([]);

        // No matches at all → EmptyElementException
        $processor->method('sortMatches')->willReturn([]);
        $updater->method('forceSetSegmentAnalyzed')->willReturn(false);

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
        $updater->method('forceSetSegmentAnalyzed')->willReturn(true);

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
        $updater->method('forceSetSegmentAnalyzed')->willReturn(false);

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
        $updater->method('forceSetSegmentAnalyzed')->willReturn(true);

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
}
