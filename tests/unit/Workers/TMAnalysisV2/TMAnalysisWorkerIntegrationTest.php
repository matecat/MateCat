<?php

namespace unit\Workers\TMAnalysisV2;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Predis\Client;
use ReflectionClass;
use ReflectionProperty;
use TestHelpers\AbstractTest;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\AnalysisRedisServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\EngineServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\MatchProcessorServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\ProjectCompletionServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\SegmentUpdaterServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service\AnalysisRedisService;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service\ProjectCompletionService;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysisWorker;
use Utils\TaskRunner\Commons\AbstractWorker;
use Utils\TaskRunner\Commons\Context;
use Utils\TaskRunner\Commons\Params;
use Utils\TaskRunner\Commons\QueueElement;

class IntegrationTestableTMAnalysisWorker extends TMAnalysisWorker
{
    public array $fixedConfig = [];

    protected function buildEngineConfig(QueueElement $queueElement): array
    {
        return $this->fixedConfig;
    }
}

#[AllowMockObjectsWithoutExpectations]
class TMAnalysisWorkerIntegrationTest extends AbstractTest
{
    private function getPrivateProperty(object $object, string $className, string $propertyName): mixed
    {
        $reader = \Closure::bind(
            static function (object $target, string $name): mixed {
                return $target->$name;
            },
            null,
            $className
        );

        return $reader($object, $propertyName);
    }

    private function makeQueueElement(array $overrides = []): QueueElement
    {
        $element = new QueueElement();
        $params = new Params();

        $defaults = [
            'id_segment' => 1,
            'id_job' => 2,
            'pid' => 100,
            'ppassword' => 'secret',
            'source' => 'en-US',
            'target' => 'it-IT',
            'segment' => 'Hello world',
            'raw_word_count' => 2.0,
            'payable_rates' => '{}',
            'id_tms' => 1,
            'id_mt_engine' => 0,
            'features' => '',
            'tm_keys' => '[]',
            'context_before' => '',
            'context_after' => '',
            'mt_quality_value_in_editor' => null,
            'enable_mt_analysis' => true,
            'mt_qe_workflow_enabled' => true,
        ];

        foreach (array_merge($defaults, $overrides) as $key => $value) {
            $params->$key = $value;
        }

        $element->params = $params;
        $element->reQueueNum = 0;

        return $element;
    }

    #[Test]
    public function constructor_with_only_amqhandler_initializes_all_services(): void
    {
        $amqHandler = $this->createMock(AMQHandler::class);
        $amqHandler->method('getRedisClient')->willReturn($this->createMock(Client::class));

        $worker = new TMAnalysisWorker($amqHandler);

        $this->assertNotNull($this->getPrivateProperty($worker, TMAnalysisWorker::class, 'redisService'));
        $this->assertNotNull($this->getPrivateProperty($worker, TMAnalysisWorker::class, 'segmentUpdater'));
        $this->assertNotNull($this->getPrivateProperty($worker, TMAnalysisWorker::class, 'projectCompletion'));
        $this->assertNotNull($this->getPrivateProperty($worker, TMAnalysisWorker::class, 'engineService'));
        $this->assertNotNull($this->getPrivateProperty($worker, TMAnalysisWorker::class, 'matchProcessor'));
    }

    #[Test]
    public function constructor_with_injected_services_keeps_same_instances(): void
    {
        $amqHandler = $this->createMock(AMQHandler::class);
        $redisService = $this->createMock(AnalysisRedisServiceInterface::class);
        $segmentUpdater = $this->createMock(SegmentUpdaterServiceInterface::class);
        $projectCompletion = $this->createMock(ProjectCompletionServiceInterface::class);
        $engineService = $this->createMock(EngineServiceInterface::class);
        $matchProcessor = $this->createMock(MatchProcessorServiceInterface::class);

        $worker = new TMAnalysisWorker(
            $amqHandler,
            $redisService,
            $segmentUpdater,
            $projectCompletion,
            $engineService,
            $matchProcessor
        );

        $this->assertSame($redisService, $this->getPrivateProperty($worker, TMAnalysisWorker::class, 'redisService'));
        $this->assertSame($segmentUpdater, $this->getPrivateProperty($worker, TMAnalysisWorker::class, 'segmentUpdater'));
        $this->assertSame($projectCompletion, $this->getPrivateProperty($worker, TMAnalysisWorker::class, 'projectCompletion'));
        $this->assertSame($engineService, $this->getPrivateProperty($worker, TMAnalysisWorker::class, 'engineService'));
        $this->assertSame($matchProcessor, $this->getPrivateProperty($worker, TMAnalysisWorker::class, 'matchProcessor'));
    }

    #[Test]
    public function default_constructor_shares_same_redis_service_with_project_completion_service(): void
    {
        $amqHandler = $this->createMock(AMQHandler::class);
        $amqHandler->method('getRedisClient')->willReturn($this->createMock(Client::class));

        $worker = new TMAnalysisWorker($amqHandler);

        $workerRedisService = $this->getPrivateProperty($worker, TMAnalysisWorker::class, 'redisService');
        $projectCompletion = $this->getPrivateProperty($worker, TMAnalysisWorker::class, 'projectCompletion');

        $this->assertInstanceOf(AnalysisRedisService::class, $workerRedisService);
        $this->assertInstanceOf(ProjectCompletionService::class, $projectCompletion);

        $projectCompletionRedisService = $this->getPrivateProperty(
            $projectCompletion,
            ProjectCompletionService::class,
            'redisService'
        );

        $this->assertSame($workerRedisService, $projectCompletionRedisService);
    }

    #[Test]
    public function class_hierarchy_is_abstract_worker(): void
    {
        $this->assertTrue(is_subclass_of(TMAnalysisWorker::class, AbstractWorker::class));
    }

    #[Test]
    public function expected_worker_methods_exist(): void
    {
        $reflection = new ReflectionClass(TMAnalysisWorker::class);

        $this->assertTrue($reflection->hasMethod('process'));
        $this->assertTrue($reflection->hasMethod('_endQueueCallback'));
        $this->assertTrue($reflection->hasMethod('_checkWordCount'));
    }

    #[Test]
    public function worker_orchestrator_has_no_direct_redis_client_access(): void
    {
        $source = file_get_contents(
            self::projectRoot() . '/lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysisWorker.php'
        );

        $this->assertIsString($source);
        $this->assertStringNotContainsString('getRedisClient()', $source);
    }

    #[Test]
    public function worker_orchestrator_has_no_direct_database_calls(): void
    {
        $source = file_get_contents(
            self::projectRoot() . '/lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysisWorker.php'
        );

        $this->assertIsString($source);
        $this->assertStringNotContainsString('Database::obtain()', $source);
        $this->assertStringNotContainsString('SegmentTranslationDao::', $source);
    }

    #[Test]
    public function process_runs_full_flow_with_injected_services_and_mocked_redis_paths(): void
    {
        $amqHandler = $this->createMock(AMQHandler::class);
        $redisService = $this->createMock(AnalysisRedisServiceInterface::class);
        $segmentUpdater = $this->createMock(SegmentUpdaterServiceInterface::class);
        $projectCompletion = $this->createMock(ProjectCompletionServiceInterface::class);
        $engineService = $this->createMock(EngineServiceInterface::class);
        $matchProcessor = $this->createMock(MatchProcessorServiceInterface::class);

        $worker = new IntegrationTestableTMAnalysisWorker(
            $amqHandler,
            $redisService,
            $segmentUpdater,
            $projectCompletion,
            $engineService,
            $matchProcessor
        );

        $worker->fixedConfig = ['pid' => 100, 'segment' => 'Hello world'];

        (new ReflectionProperty(AbstractWorker::class, '_observer'))->setValue($worker, []);
        $worker->setContext(Context::buildFromArray([
            'queue_name' => 'test_queue',
            'max_executors' => 1,
        ]));

        $tmMatches = [['match' => 75, 'created_by' => 'TM', 'suggestion' => 'Ciao mondo', 'memory_key' => 'tm-key-1']];
        $mtResult = ['translation' => 'Ciao mondo (MT)', 'score' => 0.88, 'match' => 'MT', 'created_by' => 'MT'];
        $sortedMatches = [['match' => '75', 'created_by' => 'TM', 'suggestion' => 'Ciao mondo', 'memory_key' => 'tm-key-1']];

        $redisService->method('acquireInitLock')->willReturn(false);
        $redisService->expects($this->once())->method('waitForInitialization')->with(100);
        $redisService->method('getProjectTotalSegments')->willReturn(50);
        $redisService->method('getProjectAnalyzedCount')->willReturn(10);
        $redisService->method('getWorkingProjects')->willReturn([]);

        $engineService->expects($this->once())->method('getTMMatches')->willReturn($tmMatches);
        $engineService->expects($this->once())->method('getMTTranslation')->with(
            ['pid' => 100, 'segment' => 'Hello world'],
            $this->anything(),
            null,
            false
        )->willReturn($mtResult);

        $matchProcessor->expects($this->once())->method('sortMatches')->with($mtResult, $tmMatches)->willReturn($sortedMatches);
        $matchProcessor->method('isMtMatch')->willReturn(false);
        $matchProcessor->expects($this->once())->method('calculateWordDiscount')->willReturn(['TM_75_84', 1.5, 2.0]);
        $matchProcessor->expects($this->once())->method('postProcessMatch')->willReturn([
            'suggestion' => 'Ciao mondo',
            'warning' => 0,
            'serialized_errors_list' => '',
        ]);
        $matchProcessor->expects($this->once())->method('determinePreTranslateStatus')
            ->willReturnCallback(static fn(array $tmData): array => $tmData);

        $segmentUpdater->expects($this->once())->method('setAnalysisValue')->willReturn(1);
        $redisService->expects($this->once())->method('incrementAnalyzedCount')->with(100, 1, 2, 2);
        $projectCompletion->expects($this->once())->method('tryCloseProject')->with(
            100,
            'secret',
            'test_queue_redis_key',
            $this->anything()
        );

        $worker->process($this->makeQueueElement());
    }
}
