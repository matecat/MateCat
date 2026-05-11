<?php

namespace unit\Workers\TMAnalysisV2;

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

class TMAnalysisWorkerV2Test extends AbstractTest
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
        $processor->method('calculateWordDiscount')->willReturn(['TM_75_84', 1.5, 2.0]);
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
        $processor->expects($this->once())->method('calculateWordDiscount')->willReturn(['TM_75_84', 1.5, 2.0]);
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
        $redis     = $this->createMock(AnalysisRedisServiceInterface::class);
        $processor = $this->createStub(MatchProcessorServiceInterface::class);
        $updater   = $this->createStub(SegmentUpdaterServiceInterface::class);

        $redis->method('acquireInitLock')->willReturn(true);
        $redis->method('incrementAnalyzedCount');
        $processor->method('getProjectSegmentsTranslationSummary')
            ->willReturn([['project_segments' => 10, 'num_analyzed' => 0]]);

        $redis->expects($this->once())
            ->method('setProjectTotalSegments')
            ->with(100, 10);

        $updater->method('forceSetSegmentAnalyzed')->willReturn(false);

        $worker = $this->buildWorker(redis: $redis, processor: $processor, updater: $updater);

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
}
