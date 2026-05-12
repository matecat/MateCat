<?php

namespace unit\Workers\TMAnalysisV2;

use Model\Analysis\Constants\InternalMatchesConstants;
use Model\MTQE\Templates\DTO\MTQEWorkflowParams;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Predis\Client;
use ReflectionClass;
use ReflectionProperty;
use TestHelpers\AbstractTest;
use Tests\Unit\Workers\TMAnalysisV2\FakeEngines\FakeMTEngine;
use Tests\Unit\Workers\TMAnalysisV2\FakeEngines\FakeTMEngine;
use Model\DataAccess\Database;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\AnalysisRedisServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\EngineServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\MatchProcessorServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\ProjectCompletionServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\SegmentUpdaterServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service\AnalysisRedisService;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service\DefaultEngineResolver;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service\EngineService;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service\MatchProcessorService;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service\ProjectCompletionService;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysisWorker;
use Utils\AsyncTasks\Workers\Service\MatchSorter;
use Utils\TaskRunner\Commons\AbstractWorker;
use Utils\TaskRunner\Commons\Context;
use Utils\TaskRunner\Commons\Params;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EmptyElementException;

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
    protected function tearDown(): void
    {
        FakeTMEngine::$cannedMatches = [];
        FakeMTEngine::$cannedTranslation = [];
        parent::tearDown();
    }

    /**
     * Ensure FakeEngine rows exist in the test DB so EnginesFactory::getInstance() can load them.
     * Uses INSERT IGNORE to be idempotent — safe to call every test.
     */
    private function ensureFakeEnginesExist(): void
    {
        $conn = Database::obtain()->getConnection();
        $conn->exec("
            INSERT IGNORE INTO engines (id, name, type, description, base_url, translate_relative_url, others, class_load, extra_parameters, penalty, active)
            VALUES
                (900, 'FakeTMEngine', 'TM', 'Test', 'http://fake-tm', 'get', '{}', 'Tests\\\\Unit\\\\Workers\\\\TMAnalysisV2\\\\FakeEngines\\\\FakeTMEngine', '{}', 0, 1),
                (901, 'FakeMTEngine', 'MT', 'Test', 'http://fake-mt', 'get', '{}', 'Tests\\\\Unit\\\\Workers\\\\TMAnalysisV2\\\\FakeEngines\\\\FakeMTEngine', '{}', 0, 1)
        ");
    }

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
            'match_type' => InternalMatchesConstants::NO_MATCH,
            'fast_exact_match_type' => null,
            'icu_enabled' => false,
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
        // Database::obtain() is allowed ONLY in the constructor for DI wiring
        // It must NOT appear in process() or any other method
        $this->assertStringNotContainsString('SegmentTranslationDao::', $source);

        // Extract everything after __construct to verify no DB calls in operational code
        $constructorEnd = strpos($source, 'public function process(');
        $this->assertNotFalse($constructorEnd);
        $operationalCode = substr($source, $constructorEnd);
        $this->assertStringNotContainsString('Database::obtain()', $operationalCode);
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
        $redisService->expects($this->once())->method('waitForInitialization')->with(100)->willReturn(true);
        $redisService->method('getProjectTotalSegments')->willReturn(50);
        $redisService->method('getProjectAnalyzedCount')->willReturn(10);
        $redisService->method('getWorkingProjects')->willReturn([]);

        $engineService->expects($this->once())->method('getTMMatches')->willReturn($tmMatches);
        $engineService->expects($this->once())->method('getMTTranslation')->with(
            ['pid' => 100, 'segment' => 'Hello world'],
            $this->anything(),
            null,
            $this->isInstanceOf(QueueElement::class)
        )->willReturn($mtResult);

        $matchProcessor->expects($this->once())->method('sortMatches')->with($mtResult, $tmMatches)->willReturn($sortedMatches);
        $matchProcessor->method('isMtMatch')->willReturn(false);
        $matchProcessor->expects($this->once())->method('postProcessMatch')->willReturn([
            'suggestion' => 'Ciao mondo',
            'warning' => 0,
            'serialized_errors_list' => '',
        ]);
        $matchProcessor->expects($this->once())->method('determinePreTranslateStatus')
            ->willReturnCallback(static fn(array $tmData): array => $tmData);

        $segmentUpdater->expects($this->once())->method('setAnalysisValue')->willReturn(1);
        // With empty payable_rates, discountRate=0 → eqWords=0.0, standardWords=0.0
        $redisService->expects($this->once())->method('incrementAnalyzedCount')->with(100, 1, 0.0, 0.0);
        $projectCompletion->expects($this->once())->method('tryCloseProject')->with(
            100,
            'secret',
            'test_queue_redis_key',
            $this->anything()
        );

        $worker->process($this->makeQueueElement());
    }

    // ── Helpers for real-service integration tests ──────────────────────

    /**
     * Canned TM match in MyMemory API response format (pre-GetMemoryResponse).
     * Match score is a fraction (0.85 = 85%).
     */
    private function cannedTmMatch(
        float $matchScore = 0.85,
        bool $ice = false,
        string $translation = 'Ciao mondo',
    ): array {
        return [
            'id'               => '12345',
            'segment'          => 'Hello world',
            'translation'      => $translation,
            'match'            => $matchScore,
            'quality'          => 74,
            'reference'        => '',
            'usage-count'      => 1,
            'subject'          => 'All',
            'created-by'       => 'TM-User',
            'last-updated-by'  => 'TM-User',
            'create-date'      => '2024-01-01 12:00:00',
            'last-update-date' => '2024-01-01 12:00:00',
            'key'              => 'tm-key-1',
            'ICE'              => $ice,
            'tm_properties'    => null,
        ];
    }

    /**
     * Canned MT result in plain array format (NOT GetMemoryResponse).
     */
    private function cannedMtResult(
        string $translation = 'Ciao mondo (MT)',
        float $score = 0.6,
    ): array {
        return [
            'segment'         => 'Hello world',
            'translation'     => $translation,
            'raw_translation' => $translation,
            'match'           => '75',
            'created_by'      => 'MT!',
            'memory_key'      => '',
            'ICE'             => false,
            'score'           => $score,
        ];
    }

    private function standardPayableRates(): string
    {
        return json_encode([
            'NO_MATCH'        => 100,
            '50%-74%'         => 100,
            '75%-84%'         => 60,
            '85%-94%'         => 35,
            '95%-99%'         => 20,
            '100%'            => 10,
            '100%_PUBLIC'     => 10,
            'ICE'             => 0,
            'MT'              => 80,
            'ICE_MT'          => 5,
        ]);
    }

    /**
     * Build a worker with real EngineService + MatchProcessorService,
     * FakeEngines loaded from DB (IDs 900/901), and stubbed infra services.
     */
    private function createWorkerWithRealServices(
        ?AnalysisRedisServiceInterface $redisService = null,
        ?SegmentUpdaterServiceInterface $segmentUpdater = null,
        ?ProjectCompletionServiceInterface $projectCompletion = null,
    ): IntegrationTestableTMAnalysisWorker {
        $this->ensureFakeEnginesExist();

        $amqHandler = $this->createStub(AMQHandler::class);

        $worker = new IntegrationTestableTMAnalysisWorker(
            $amqHandler,
            $redisService ?? $this->createStub(AnalysisRedisServiceInterface::class),
            $segmentUpdater ?? $this->createStub(SegmentUpdaterServiceInterface::class),
            $projectCompletion ?? $this->createStub(ProjectCompletionServiceInterface::class),
            new EngineService(new DefaultEngineResolver()),
            new MatchProcessorService(new MatchSorter()),
        );

        (new ReflectionProperty(AbstractWorker::class, '_observer'))->setValue($worker, []);
        $worker->setContext(Context::buildFromArray([
            'queue_name'    => 'test_queue',
            'max_executors' => 1,
        ]));

        return $worker;
    }

    /**
     * Configure redis stub for non-init-winner path (most common test scenario).
     */
    private function configureRedisStubAsLoser(AnalysisRedisServiceInterface $redisService): void
    {
        $redisService->method('acquireInitLock')->willReturn(false);
        $redisService->method('waitForInitialization')->willReturn(true);
        $redisService->method('getProjectTotalSegments')->willReturn(50);
        $redisService->method('getProjectAnalyzedCount')->willReturn(10);
        $redisService->method('getWorkingProjects')->willReturn([]);
    }

    // ── Real-service integration tests (FakeEngines from DB) ───────────

    #[Test]
    public function process_with_real_services_tm_85_percent_wins_over_mt(): void
    {
        FakeTMEngine::$cannedMatches = [$this->cannedTmMatch(0.85)];
        FakeMTEngine::$cannedTranslation = $this->cannedMtResult();

        $redisService = $this->createStub(AnalysisRedisServiceInterface::class);
        $this->configureRedisStubAsLoser($redisService);

        $capturedTmData = null;
        $segmentUpdater = $this->createMock(SegmentUpdaterServiceInterface::class);
        $segmentUpdater->expects($this->once())
            ->method('setAnalysisValue')
            ->willReturnCallback(function (array $tmData) use (&$capturedTmData) {
                $capturedTmData = $tmData;

                return 1;
            });

        $worker = $this->createWorkerWithRealServices($redisService, $segmentUpdater);
        $worker->fixedConfig = [
            'id_tms'                 => 900,
            'id_mt_engine'           => 901,
            'segment'                => 'Hello world',
            'source'                 => 'en-US',
            'target'                 => 'it-IT',
            'pid'                    => 100,
            'get_mt'                 => true,
            'num_result'             => 3,
            'mt_qe_workflow_enabled' => false,
        ];

        $worker->process($this->makeQueueElement([
            'id_tms'                 => 900,
            'id_mt_engine'           => 901,
            'payable_rates'          => $this->standardPayableRates(),
            'mt_qe_workflow_enabled' => false,
        ]));

        $this->assertNotNull($capturedTmData, 'setAnalysisValue should have been called');
        $this->assertEquals('DONE', $capturedTmData['tm_analysis_status']);
        $this->assertEquals('TM', $capturedTmData['suggestion_source']);
        $this->assertEquals('85%-94%', $capturedTmData['match_type']);
        $this->assertEquals(1, $capturedTmData['id_segment']);
        $this->assertEquals(2, $capturedTmData['id_job']);
    }

    #[Test]
    public function process_with_real_services_ice_match_sets_approved_and_locked(): void
    {
        FakeTMEngine::$cannedMatches = [$this->cannedTmMatch(1.0, true)];
        FakeMTEngine::$cannedTranslation = $this->cannedMtResult();

        $redisService = $this->createStub(AnalysisRedisServiceInterface::class);
        $this->configureRedisStubAsLoser($redisService);

        $capturedTmData = null;
        $segmentUpdater = $this->createMock(SegmentUpdaterServiceInterface::class);
        $segmentUpdater->expects($this->once())
            ->method('setAnalysisValue')
            ->willReturnCallback(function (array $tmData) use (&$capturedTmData) {
                $capturedTmData = $tmData;

                return 1;
            });

        $worker = $this->createWorkerWithRealServices($redisService, $segmentUpdater);
        $worker->fixedConfig = [
            'id_tms'                 => 900,
            'id_mt_engine'           => 901,
            'segment'                => 'Hello world',
            'source'                 => 'en-US',
            'target'                 => 'it-IT',
            'pid'                    => 100,
            'get_mt'                 => true,
            'num_result'             => 3,
            'mt_qe_workflow_enabled' => false,
        ];

        $worker->process($this->makeQueueElement([
            'id_tms'                 => 900,
            'id_mt_engine'           => 901,
            'payable_rates'          => $this->standardPayableRates(),
            'mt_qe_workflow_enabled' => false,
        ]));

        $this->assertNotNull($capturedTmData);
        $this->assertEquals('ICE', $capturedTmData['match_type']);
        $this->assertEquals('APPROVED', $capturedTmData['status'] ?? null);
        $this->assertTrue($capturedTmData['locked'] ?? false);
        $this->assertEquals('TM', $capturedTmData['suggestion_source']);
    }

    #[Test]
    public function process_with_real_services_mt_only_when_no_tm_matches(): void
    {
        FakeTMEngine::$cannedMatches = [];
        FakeMTEngine::$cannedTranslation = $this->cannedMtResult('Ciao mondo (MT)', 0.6);

        $redisService = $this->createStub(AnalysisRedisServiceInterface::class);
        $this->configureRedisStubAsLoser($redisService);

        $capturedTmData = null;
        $segmentUpdater = $this->createMock(SegmentUpdaterServiceInterface::class);
        $segmentUpdater->expects($this->once())
            ->method('setAnalysisValue')
            ->willReturnCallback(function (array $tmData) use (&$capturedTmData) {
                $capturedTmData = $tmData;

                return 1;
            });

        $worker = $this->createWorkerWithRealServices($redisService, $segmentUpdater);
        $worker->fixedConfig = [
            'id_tms'                 => 900,
            'id_mt_engine'           => 901,
            'segment'                => 'Hello world',
            'source'                 => 'en-US',
            'target'                 => 'it-IT',
            'pid'                    => 100,
            'get_mt'                 => true,
            'num_result'             => 3,
            'mt_qe_workflow_enabled' => false,
        ];

        $worker->process($this->makeQueueElement([
            'id_tms'                 => 900,
            'id_mt_engine'           => 901,
            'payable_rates'          => $this->standardPayableRates(),
            'mt_qe_workflow_enabled' => false,
        ]));

        $this->assertNotNull($capturedTmData);
        $this->assertEquals('MT', $capturedTmData['suggestion_source']);
        $this->assertStringContainsString('MT', $capturedTmData['match_type']);
    }

    #[Test]
    public function process_with_real_services_empty_matches_throws_empty_element(): void
    {
        FakeTMEngine::$cannedMatches = [];
        FakeMTEngine::$cannedTranslation = [];

        $redisService = $this->createStub(AnalysisRedisServiceInterface::class);
        $this->configureRedisStubAsLoser($redisService);

        $worker = $this->createWorkerWithRealServices($redisService);
        $worker->fixedConfig = [
            'id_tms'                 => 900,
            'id_mt_engine'           => 901,
            'segment'                => 'Hello world',
            'source'                 => 'en-US',
            'target'                 => 'it-IT',
            'pid'                    => 100,
            'get_mt'                 => true,
            'num_result'             => 3,
            'mt_qe_workflow_enabled' => false,
        ];

        $this->expectException(EmptyElementException::class);

        $worker->process($this->makeQueueElement([
            'id_tms'                 => 900,
            'id_mt_engine'           => 901,
            'payable_rates'          => $this->standardPayableRates(),
            'mt_qe_workflow_enabled' => false,
        ]));
    }

    // ── MTQE filter integration tests ──────────────────────────────────

    #[Test]
    public function process_with_real_services_mtqe_default_drops_matches_below_100(): void
    {
        // TM 85% match should be filtered by default MTQE (keeps only >=100)
        FakeTMEngine::$cannedMatches = [$this->cannedTmMatch(0.85)];
        FakeMTEngine::$cannedTranslation = $this->cannedMtResult('Ciao mondo (MT)', 0.6);

        $mtqeConfig = new MTQEWorkflowParams();
        $mtqeConfig->analysis_ignore_100 = false;
        $mtqeConfig->analysis_ignore_101 = false;

        $redisService = $this->createStub(AnalysisRedisServiceInterface::class);
        $this->configureRedisStubAsLoser($redisService);

        $capturedTmData = null;
        $segmentUpdater = $this->createMock(SegmentUpdaterServiceInterface::class);
        $segmentUpdater->expects($this->once())
            ->method('setAnalysisValue')
            ->willReturnCallback(function (array $tmData) use (&$capturedTmData) {
                $capturedTmData = $tmData;

                return 1;
            });

        $worker = $this->createWorkerWithRealServices($redisService, $segmentUpdater);
        $worker->fixedConfig = [
            'id_tms'                 => 900,
            'id_mt_engine'           => 901,
            'segment'                => 'Hello world',
            'source'                 => 'en-US',
            'target'                 => 'it-IT',
            'pid'                    => 100,
            'get_mt'                 => true,
            'num_result'             => 3,
            'mt_qe_workflow_enabled' => true,
            'mt_qe_config'           => $mtqeConfig,
        ];

        $worker->process($this->makeQueueElement([
            'id_tms'                 => 900,
            'id_mt_engine'           => 901,
            'payable_rates'          => $this->standardPayableRates(),
            'mt_qe_workflow_enabled' => true,
        ]));

        // TM 85% was filtered (< 100), MT survives as best match
        $this->assertNotNull($capturedTmData);
        $this->assertEquals('MT', $capturedTmData['suggestion_source']);
    }

    #[Test]
    public function process_with_real_services_mtqe_ignore_101_drops_all_tm_matches(): void
    {
        // 100% ICE match should be dropped when ignore_101 is set
        FakeTMEngine::$cannedMatches = [$this->cannedTmMatch(1.0, true)];
        FakeMTEngine::$cannedTranslation = $this->cannedMtResult('Ciao mondo (MT)', 0.6);

        $mtqeConfig = new MTQEWorkflowParams();
        $mtqeConfig->analysis_ignore_101 = true;

        $redisService = $this->createStub(AnalysisRedisServiceInterface::class);
        $this->configureRedisStubAsLoser($redisService);

        $capturedTmData = null;
        $segmentUpdater = $this->createMock(SegmentUpdaterServiceInterface::class);
        $segmentUpdater->expects($this->once())
            ->method('setAnalysisValue')
            ->willReturnCallback(function (array $tmData) use (&$capturedTmData) {
                $capturedTmData = $tmData;

                return 1;
            });

        $worker = $this->createWorkerWithRealServices($redisService, $segmentUpdater);
        $worker->fixedConfig = [
            'id_tms'                 => 900,
            'id_mt_engine'           => 901,
            'segment'                => 'Hello world',
            'source'                 => 'en-US',
            'target'                 => 'it-IT',
            'pid'                    => 100,
            'get_mt'                 => true,
            'num_result'             => 3,
            'mt_qe_workflow_enabled' => true,
            'mt_qe_config'           => $mtqeConfig,
        ];

        $worker->process($this->makeQueueElement([
            'id_tms'                 => 900,
            'id_mt_engine'           => 901,
            'payable_rates'          => $this->standardPayableRates(),
            'mt_qe_workflow_enabled' => true,
        ]));

        // ALL TM matches dropped by ignore_101, MT survives
        $this->assertNotNull($capturedTmData);
        $this->assertEquals('MT', $capturedTmData['suggestion_source']);
    }

    #[Test]
    public function process_with_real_services_mtqe_ignore_100_keeps_ice_only(): void
    {
        // ICE 100% match survives ignore_100 filter, non-ICE 100% gets dropped
        FakeTMEngine::$cannedMatches = [
            $this->cannedTmMatch(1.0, true, 'ICE translation'),   // ICE — survives
            $this->cannedTmMatch(1.0, false, 'Non-ICE 100% translation'), // non-ICE 100% — dropped
        ];
        FakeMTEngine::$cannedTranslation = $this->cannedMtResult('MT translation', 0.5);

        $mtqeConfig = new MTQEWorkflowParams();
        $mtqeConfig->analysis_ignore_100 = true;
        $mtqeConfig->analysis_ignore_101 = false;

        $redisService = $this->createStub(AnalysisRedisServiceInterface::class);
        $this->configureRedisStubAsLoser($redisService);

        $capturedTmData = null;
        $segmentUpdater = $this->createMock(SegmentUpdaterServiceInterface::class);
        $segmentUpdater->expects($this->once())
            ->method('setAnalysisValue')
            ->willReturnCallback(function (array $tmData) use (&$capturedTmData) {
                $capturedTmData = $tmData;

                return 1;
            });

        $worker = $this->createWorkerWithRealServices($redisService, $segmentUpdater);
        $worker->fixedConfig = [
            'id_tms'                 => 900,
            'id_mt_engine'           => 901,
            'segment'                => 'Hello world',
            'source'                 => 'en-US',
            'target'                 => 'it-IT',
            'pid'                    => 100,
            'get_mt'                 => true,
            'num_result'             => 3,
            'mt_qe_workflow_enabled' => true,
            'mt_qe_config'           => $mtqeConfig,
        ];

        $worker->process($this->makeQueueElement([
            'id_tms'                 => 900,
            'id_mt_engine'           => 901,
            'payable_rates'          => $this->standardPayableRates(),
            'mt_qe_workflow_enabled' => true,
        ]));

        // ICE match survives the ignore_100 filter
        $this->assertNotNull($capturedTmData);
        $this->assertEquals('ICE', $capturedTmData['match_type']);
        $this->assertEquals('TM', $capturedTmData['suggestion_source']);
    }

    // ── buildEngineConfig test ─────────────────────────────────────────

    #[Test]
    public function buildEngineConfig_maps_queue_params_to_engine_config_array(): void
    {
        $this->ensureFakeEnginesExist();

        $worker = new class($this->createStub(AMQHandler::class)) extends TMAnalysisWorker {
            public function exposedBuildEngineConfig(QueueElement $queueElement): array
            {
                return $this->buildEngineConfig($queueElement);
            }
        };

        $element = $this->makeQueueElement([
            'id_tms'        => 900,
            'id_mt_engine'  => 901,
            'segment'       => 'Test segment',
            'source'        => 'en-US',
            'target'        => 'fr-FR',
            'pid'           => 200,
            'context_before' => 'before ctx',
            'context_after'  => 'after ctx',
            'tm_keys'       => '[]',
        ]);

        $config = $worker->exposedBuildEngineConfig($element);

        $this->assertEquals(200, $config['pid']);
        $this->assertEquals('Test segment', $config['segment']);
        $this->assertEquals('en-US', $config['source']);
        $this->assertEquals('fr-FR', $config['target']);
        $this->assertEquals(900, $config['id_tms']);
        $this->assertEquals(901, $config['id_mt_engine']);
        $this->assertEquals('before ctx', $config['context_before']);
        $this->assertEquals('after ctx', $config['context_after']);
        $this->assertEquals(3, $config['num_result']);
        // FakeMTEngine is NOT MyMemory, so get_mt = false
        $this->assertFalse($config['get_mt']);
    }

    #[Test]
    public function buildEngineConfig_includes_dialect_strict_when_set(): void
    {
        $this->ensureFakeEnginesExist();

        $worker = new class($this->createStub(AMQHandler::class)) extends TMAnalysisWorker {
            public function exposedBuildEngineConfig(QueueElement $queueElement): array
            {
                return $this->buildEngineConfig($queueElement);
            }
        };

        $element = $this->makeQueueElement([
            'id_tms'           => 900,
            'id_mt_engine'     => 901,
            'tm_keys'          => '[]',
            'dialect_strict'   => true,
        ]);

        $config = $worker->exposedBuildEngineConfig($element);

        $this->assertTrue($config['dialect_strict']);
    }

    #[Test]
    public function buildEngineConfig_includes_public_tm_penalty_when_set(): void
    {
        $this->ensureFakeEnginesExist();

        $worker = new class($this->createStub(AMQHandler::class)) extends TMAnalysisWorker {
            public function exposedBuildEngineConfig(QueueElement $queueElement): array
            {
                return $this->buildEngineConfig($queueElement);
            }
        };

        $element = $this->makeQueueElement([
            'id_tms'             => 900,
            'id_mt_engine'       => 901,
            'tm_keys'            => '[]',
            'public_tm_penalty'  => 5,
        ]);

        $config = $worker->exposedBuildEngineConfig($element);

        $this->assertEquals(5, $config['public_tm_penalty']);
    }

    #[Test]
    public function buildEngineConfig_parses_tm_keys_into_penalty_map(): void
    {
        $this->ensureFakeEnginesExist();

        $worker = new class($this->createStub(AMQHandler::class)) extends TMAnalysisWorker {
            public function exposedBuildEngineConfig(QueueElement $queueElement): array
            {
                return $this->buildEngineConfig($queueElement);
            }
        };

        $tmKeys = json_encode([
            ['key' => 'abc123', 'r' => true, 'w' => false, 'owner' => true, 'penalty' => 0, 'tm' => true, 'glos' => false],
            ['key' => 'def456', 'r' => true, 'w' => true, 'owner' => true, 'penalty' => 3, 'tm' => true, 'glos' => false],
        ]);

        $element = $this->makeQueueElement([
            'id_tms'       => 900,
            'id_mt_engine' => 901,
            'tm_keys'      => $tmKeys,
        ]);

        $config = $worker->exposedBuildEngineConfig($element);

        $this->assertArrayHasKey('id_user', $config);
        $this->assertContains('abc123', $config['id_user']);
        $this->assertContains('def456', $config['id_user']);
        $this->assertArrayHasKey('penalty_key', $config);
    }

    #[Test]
    public function buildEngineConfig_includes_mt_qe_config_when_workflow_enabled(): void
    {
        $this->ensureFakeEnginesExist();

        $worker = new class($this->createStub(AMQHandler::class)) extends TMAnalysisWorker {
            public function exposedBuildEngineConfig(QueueElement $queueElement): array
            {
                return $this->buildEngineConfig($queueElement);
            }
        };

        $mtqeParams = json_encode(['analysis_ignore_100' => true, 'analysis_ignore_101' => false]);

        $element = $this->makeQueueElement([
            'id_tms'                    => 900,
            'id_mt_engine'              => 901,
            'tm_keys'                   => '[]',
            'mt_qe_workflow_enabled'    => true,
            'mt_qe_workflow_parameters' => $mtqeParams,
        ]);

        $config = $worker->exposedBuildEngineConfig($element);

        $this->assertTrue($config['mt_qe_workflow_enabled']);
        $this->assertInstanceOf(MTQEWorkflowParams::class, $config['mt_qe_config']);
    }

    #[Test]
    public function buildEngineConfig_sets_only_private_and_disables_tms_when_no_keys_and_no_mt(): void
    {
        $this->ensureFakeEnginesExist();

        $worker = new class($this->createStub(AMQHandler::class)) extends TMAnalysisWorker {
            public function exposedBuildEngineConfig(QueueElement $queueElement): array
            {
                return $this->buildEngineConfig($queueElement);
            }
        };

        $element = $this->makeQueueElement([
            'id_tms'       => 900,
            'id_mt_engine' => 901,  // FakeMTEngine (not MyMemory) → get_mt = false
            'tm_keys'      => '[]',
            'only_private' => true,
        ]);

        $config = $worker->exposedBuildEngineConfig($element);

        $this->assertTrue($config['onlyprivate']);
        // only_private + no id_user + !get_mt → id_tms = 0
        $this->assertEquals(0, $config['id_tms']);
    }
}
