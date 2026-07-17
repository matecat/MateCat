<?php

namespace Matecat\Core\Utils\ActiveMQ;

use Exception;
use InvalidArgumentException;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Utils\ActiveMQ\AMQHandler;
use Utils\ActiveMQ\WorkerClient;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Commons\Context;

class WorkerClientTest extends AbstractTest
{
    private ?AMQHandler $handlerMock = null;
    /** @var array<string, mixed> */
    private array $originalConfig = [];
    /** @var array<string, Context> */
    private array $originalQueues = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConfig = AppConfig::$TASK_RUNNER_CONFIG;
        $this->originalQueues = WorkerClient::$_QUEUES;

        $this->handlerMock = $this->createStub(AMQHandler::class);
        $this->handlerMock->persistent = true;
    }

    protected function tearDown(): void
    {
        AppConfig::$TASK_RUNNER_CONFIG = $this->originalConfig;
        WorkerClient::$_QUEUES = $this->originalQueues;
        // Restore WorkerClient static handler to avoid polluting subsequent tests
        // with stale mock or stub that may have uninitialised typed properties.
        disableAmqWorkerClientHelper();
        parent::tearDown();
    }

    #[Test]
    public function initThrowsWhenConfigMissing(): void
    {
        AppConfig::$TASK_RUNNER_CONFIG = [];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing task runner config');

        WorkerClient::init();
    }

    #[Test]
    public function initWithHandlerSetsHandler(): void
    {
        AppConfig::$TASK_RUNNER_CONFIG = [
            'context_definitions' => $this->getMinimalContextDefinitions(),
        ];

        WorkerClient::init($this->handlerMock);

        $this->assertSame($this->handlerMock, WorkerClient::$_HANDLER);
        $this->assertNotEmpty(WorkerClient::$_QUEUES);
    }

    #[Test]
    public function enqueueWithClientSetsDefaultPersistence(): void
    {
        $context = Context::buildFromArray(['queue_name' => '/queue/test', 'max_executors' => 1]);
        WorkerClient::$_QUEUES = ['TEST_QUEUE' => $context];

        $mock = $this->createMock(AMQHandler::class);
        $mock->persistent = true;
        $mock->expects($this->once())->method('publishToQueues');
        $mock->expects($this->once())->method('close');

        WorkerClient::enqueueWithClient($mock, 'TEST_QUEUE', 'SomeWorker', ['key' => 'value']);
    }

    #[Test]
    public function enqueueWithClientUsesProvidedPersistence(): void
    {
        $context = Context::buildFromArray(['queue_name' => '/queue/test', 'max_executors' => 1]);
        WorkerClient::$_QUEUES = ['TEST_QUEUE' => $context];

        $mock = $this->createMock(AMQHandler::class);
        $mock->persistent = true;
        $mock->expects($this->once())->method('publishToQueues');

        WorkerClient::enqueueWithClient($mock, 'TEST_QUEUE', 'SomeWorker', ['key' => 'value'], ['persistent' => false]);
    }

    #[Test]
    public function enqueueWithClientThrowsOnEmptyQueueName(): void
    {
        $context = Context::buildFromArray(['queue_name' => '', 'max_executors' => 1]);
        WorkerClient::$_QUEUES = ['EMPTY_QUEUE' => $context];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Empty queue_name');

        WorkerClient::enqueueWithClient(
            $this->handlerMock,
            'EMPTY_QUEUE',
            'SomeWorker',
            []
        );
    }

    #[Test]
    public function enqueueDelegatesToEnqueueWithClient(): void
    {
        $context = Context::buildFromArray(['queue_name' => '/queue/test', 'max_executors' => 1]);
        WorkerClient::$_QUEUES = ['TEST_QUEUE' => $context];

        $mock = $this->createMock(AMQHandler::class);
        $mock->persistent = true;
        $mock->expects($this->once())->method('publishToQueues');
        $mock->expects($this->once())->method('close');

        WorkerClient::$_HANDLER = $mock;

        WorkerClient::enqueue('TEST_QUEUE', 'SomeWorker', ['data' => 1]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getMinimalContextDefinitions(): array
    {
        return [
            'PROJECT_QUEUE' => [
                'queue_name' => '/queue/project',
                'max_executors' => 1,
                'max_queue_length' => 100,
                'max_requeue_num' => 3,
            ],
        ];
    }
}
