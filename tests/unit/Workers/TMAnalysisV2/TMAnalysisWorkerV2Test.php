<?php

namespace unit\Workers\TMAnalysisV2;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionProperty;
use TestHelpers\AbstractTest;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\AnalysisRedisServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\EngineServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\MatchProcessorServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\ProjectCompletionServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\SegmentUpdaterServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\TMAnalysisWorkerV2;
use Utils\TaskRunner\Commons\AbstractWorker;
use Utils\TaskRunner\Commons\Context;
use Utils\TaskRunner\Commons\Params;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EmptyElementException;

/**
 * Test double: overrides buildEngineConfig() (now protected) to return a fixed array,
 * eliminating static dependencies on EnginesFactory and TmKeyManager that require a DB.
 */
class TestableTMAnalysisWorkerV2 extends TMAnalysisWorkerV2
{
    public array $fixedConfig = [];

    protected function buildEngineConfig(QueueElement $queueElement): array
    {
        return $this->fixedConfig;
    }
}

class TMAnalysisWorkerV2Test extends AbstractTest
{
    private AMQHandler&MockObject $amqHandler;
    private AnalysisRedisServiceInterface&MockObject $redisService;
    private SegmentUpdaterServiceInterface&MockObject $segmentUpdater;
    private ProjectCompletionServiceInterface&MockObject $projectCompletion;
    private EngineServiceInterface&MockObject $engineService;
    private MatchProcessorServiceInterface&MockObject $matchProcessor;
    private TestableTMAnalysisWorkerV2 $worker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->amqHandler        = $this->createMock(AMQHandler::class);
        $this->redisService      = $this->createMock(AnalysisRedisServiceInterface::class);
        $this->segmentUpdater    = $this->createMock(SegmentUpdaterServiceInterface::class);
        $this->projectCompletion = $this->createMock(ProjectCompletionServiceInterface::class);
        $this->engineService     = $this->createMock(EngineServiceInterface::class);
        $this->matchProcessor    = $this->createMock(MatchProcessorServiceInterface::class);

        $this->worker = new TestableTMAnalysisWorkerV2(
            $this->amqHandler,
            $this->redisService,
            $this->segmentUpdater,
            $this->projectCompletion,
            $this->engineService,
            $this->matchProcessor,
        );

        // AbstractWorker::$_observer is a typed, uninitialized array property.
        // Without this, the first _doLog() → notify() call throws on property access.
        (new ReflectionProperty(AbstractWorker::class, '_observer'))->setValue($this->worker, []);

        $this->worker->setContext(Context::buildFromArray([
            'queue_name'    => 'test_queue',
            'max_executors' => 1,
        ]));

        $this->worker->fixedConfig = ['pid' => 100, 'segment' => 'Hello world'];
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

    private function stubLockLoserInit(): void
    {
        $this->redisService->method('acquireInitLock')->willReturn(false);
        $this->redisService->method('waitForInitialization');
        $this->redisService->method('getProjectTotalSegments')->willReturn(50);
        $this->redisService->method('getProjectAnalyzedCount')->willReturn(10);
    }

    private function stubMatchPipeline(): void
    {
        $match = ['match' => '75', 'created_by' => 'TM', 'suggestion' => 'Ciao mondo'];

        $this->engineService->method('getTMMatches')->willReturn([]);
        $this->engineService->method('getMTTranslation')->willReturn([]);
        $this->matchProcessor->method('sortMatches')->willReturn([$match]);
        $this->matchProcessor->method('isMtMatch')->willReturn(false);
        $this->matchProcessor->method('calculateWordDiscount')->willReturn(['TM_75_84', 1.5, 2.0]);
        $this->matchProcessor->method('postProcessMatch')->willReturn([
            'suggestion'             => 'Ciao mondo',
            'warning'                => 0,
            'serialized_errors_list' => '',
        ]);
        $this->matchProcessor->method('determinePreTranslateStatus')
            ->willReturnCallback(static fn(array $tmData): array => $tmData);
        $this->redisService->method('getWorkingProjects')->willReturn([]);
    }

    #[Test]
    public function constructor_has_exactly_one_required_parameter_amqhandler(): void
    {
        $ref      = new ReflectionClass(TMAnalysisWorkerV2::class);
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
        $this->redisService->method('acquireInitLock')->willReturn(false);
        $this->redisService->method('waitForInitialization');
        $this->redisService->method('getProjectTotalSegments')->willReturn(50);
        $this->redisService->method('getProjectAnalyzedCount')->willReturn(10);
        $this->redisService->method('getWorkingProjects')->willReturn([]);

        $match = ['match' => '75', 'created_by' => 'TM', 'suggestion' => 'Ciao mondo'];

        $this->engineService->expects($this->once())->method('getTMMatches')->willReturn([]);
        $this->engineService->expects($this->once())->method('getMTTranslation')->willReturn([]);
        $this->matchProcessor->expects($this->once())->method('sortMatches')->willReturn([$match]);
        $this->matchProcessor->method('isMtMatch')->willReturn(false);
        $this->matchProcessor->expects($this->once())->method('calculateWordDiscount')->willReturn(['TM_75_84', 1.5, 2.0]);
        $this->matchProcessor->method('postProcessMatch')->willReturn([
            'suggestion'             => 'Ciao mondo',
            'warning'                => 0,
            'serialized_errors_list' => '',
        ]);
        $this->matchProcessor->method('determinePreTranslateStatus')
            ->willReturnCallback(static fn(array $tmData): array => $tmData);

        $this->segmentUpdater->expects($this->once())->method('setAnalysisValue')->willReturn(1);
        $this->redisService->expects($this->once())->method('incrementAnalyzedCount');
        $this->projectCompletion->expects($this->once())->method('tryCloseProject');

        $this->worker->process($this->makeQueueElement());
    }

    #[Test]
    public function process_when_setAnalysisValue_returns_zero_skips_increment_and_close(): void
    {
        $this->stubLockLoserInit();
        $this->stubMatchPipeline();

        $this->segmentUpdater->method('setAnalysisValue')->willReturn(0);

        $this->redisService->expects($this->never())->method('incrementAnalyzedCount');
        $this->projectCompletion->expects($this->never())->method('tryCloseProject');

        $this->worker->process($this->makeQueueElement());
    }

    #[Test]
    public function process_when_setAnalysisValue_returns_positive_calls_increment_and_close(): void
    {
        $this->stubLockLoserInit();
        $this->stubMatchPipeline();

        $this->segmentUpdater->method('setAnalysisValue')->willReturn(1);

        $this->redisService->expects($this->once())->method('incrementAnalyzedCount');
        $this->projectCompletion->expects($this->once())->method('tryCloseProject');

        $this->worker->process($this->makeQueueElement());
    }

    #[Test]
    public function process_with_empty_raw_word_count_throws_EmptyElementException(): void
    {
        $this->stubLockLoserInit();
        $this->segmentUpdater->method('forceSetSegmentAnalyzed')->willReturn(false);

        $this->expectException(EmptyElementException::class);

        $this->worker->process($this->makeQueueElement(['raw_word_count' => 0]));
    }

    #[Test]
    public function process_when_init_lock_winner_calls_setProjectTotalSegments(): void
    {
        $this->redisService->method('acquireInitLock')->willReturn(true);
        $this->matchProcessor->method('getProjectSegmentsTranslationSummary')
            ->willReturn([['project_segments' => 10, 'num_analyzed' => 0]]);
        $this->redisService->method('incrementAnalyzedCount');

        $this->redisService->expects($this->once())
            ->method('setProjectTotalSegments')
            ->with(100, 10);

        $this->segmentUpdater->method('forceSetSegmentAnalyzed')->willReturn(false);
        $this->expectException(EmptyElementException::class);

        $this->worker->process($this->makeQueueElement(['raw_word_count' => 0]));
    }

    #[Test]
    public function process_when_init_lock_loser_calls_waitForInitialization(): void
    {
        $this->redisService->method('acquireInitLock')->willReturn(false);
        $this->redisService->method('getProjectTotalSegments')->willReturn(50);
        $this->redisService->method('getProjectAnalyzedCount')->willReturn(10);

        $this->redisService->expects($this->once())
            ->method('waitForInitialization')
            ->with(100);

        $this->redisService->expects($this->never())
            ->method('setProjectTotalSegments');

        $this->segmentUpdater->method('forceSetSegmentAnalyzed')->willReturn(false);
        $this->expectException(EmptyElementException::class);

        $this->worker->process($this->makeQueueElement(['raw_word_count' => 0]));
    }
}
