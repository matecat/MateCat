<?php

namespace Matecat\Core\Utils\ActiveMQ;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Utils\ActiveMQ\AMQHandler;
use Utils\ActiveMQ\ClientHelpers\ProjectQueue;
use Utils\ActiveMQ\WorkerClient;
use Utils\TaskRunner\Commons\Context;

class ProjectQueueTest extends AbstractTest
{
    private ?AMQHandler $handlerMock = null;
    /** @var array<string, Context> */
    private array $originalQueues = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalQueues = WorkerClient::$_QUEUES;

        $this->handlerMock = $this->createStub(AMQHandler::class);
        $this->handlerMock->persistent = true;

        WorkerClient::$_HANDLER = $this->handlerMock;

        $context = Context::buildFromArray(['queue_name' => '/queue/project', 'max_executors' => 1]);
        WorkerClient::$_QUEUES = ['PROJECT_QUEUE' => $context];
    }

    protected function tearDown(): void
    {
        WorkerClient::$_QUEUES = $this->originalQueues;
        parent::tearDown();
    }

    #[Test]
    public function sendProjectEnqueuesViaWorkerClient(): void
    {
        $mock = $this->createMock(AMQHandler::class);
        $mock->persistent = true;
        $mock->expects($this->once())->method('publishToQueues');
        $mock->expects($this->once())->method('close');
        WorkerClient::$_HANDLER = $mock;

        $projectStructure = new \Model\ProjectCreation\ProjectStructure([]);
        ProjectQueue::sendProject($projectStructure);
    }
}
