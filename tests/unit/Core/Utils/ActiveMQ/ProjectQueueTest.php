<?php

namespace Matecat\Core\Utils\ActiveMQ;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\ProjectCreation\ProjectStructure;
use PHPUnit\Framework\Attributes\Test;
use ReflectionException;
use RuntimeException;
use Utils\ActiveMQ\AMQHandler;
use Utils\ActiveMQ\ClientHelpers\ProjectQueue;
use Utils\ActiveMQ\WorkerClient;
use Utils\Constants\ProjectStatus;
use Utils\Redis\RedisHandler;
use Utils\TaskRunner\Commons\Context;

/**
 * ID block base 9073000 (reserved): base+1 = project id 9073001, used only as a Redis key.
 */
class ProjectQueueTest extends AbstractTest
{
    private const int ID_PROJECT = 9073001;
    private const string PASSWORD = 'pass9073001xxxx';

    private ?AMQHandler $handlerMock = null;
    /** @var array<string, Context> */
    private array $originalQueues = [];

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->originalQueues = WorkerClient::$_QUEUES;
        $this->clearRedisKey();

        $this->handlerMock = $this->createStub(AMQHandler::class);
        $this->handlerMock->persistent = true;

        WorkerClient::$_HANDLER = $this->handlerMock;

        $context = Context::buildFromArray(['queue_name' => '/queue/project', 'max_executors' => 1]);
        WorkerClient::$_QUEUES = ['PROJECT_QUEUE' => $context];
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    protected function tearDown(): void
    {
        $this->clearRedisKey();
        WorkerClient::$_QUEUES = $this->originalQueues;
        // Restore WorkerClient static handler to avoid polluting subsequent tests
        // with stale mock or stub that may have uninitialised typed properties.
        disableAmqWorkerClientHelper();
        parent::tearDown();
    }

    private function projectStructure(): ProjectStructure
    {
        $projectStructure             = new ProjectStructure([]);
        $projectStructure->id_project = self::ID_PROJECT;
        $projectStructure->ppassword  = self::PASSWORD;

        return $projectStructure;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    private function clearRedisKey(): void
    {
        $redis = (new RedisHandler())->getConnection();
        $redis->del(sprintf(ProjectStatus::PROJECT_QUEUE_HASH, self::ID_PROJECT));
        $redis->disconnect();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    private function redisTtl(): int
    {
        $redis = (new RedisHandler())->getConnection();
        $ttl   = $redis->ttl(sprintf(ProjectStatus::PROJECT_QUEUE_HASH, self::ID_PROJECT));
        $redis->disconnect();

        return $ttl;
    }

    #[Test]
    public function sendProjectEnqueuesViaWorkerClient(): void
    {
        $mock = $this->createMock(AMQHandler::class);
        $mock->persistent = true;
        $mock->expects($this->once())->method('publishToQueues');
        $mock->expects($this->once())->method('close');
        WorkerClient::$_HANDLER = $mock;

        ProjectQueue::sendProject($this->projectStructure());
    }

    /**
     * The first status poll can arrive before the worker runs: the password must already be published.
     *
     * @throws Exception
     */
    #[Test]
    public function sendProjectPublishesThePendingPayloadBeforeTheEnqueue(): void
    {
        $publishedAtEnqueue = null;

        $mock = $this->createMock(AMQHandler::class);
        $mock->persistent = true;
        $mock->expects($this->once())->method('publishToQueues')->willReturnCallback(
            function () use (&$publishedAtEnqueue): bool {
                $publishedAtEnqueue = ProjectQueue::getPublishedResults(self::ID_PROJECT);

                return true;
            }
        );
        WorkerClient::$_HANDLER = $mock;

        ProjectQueue::sendProject($this->projectStructure());

        self::assertSame(['ppassword' => self::PASSWORD, 'pending' => true], $publishedAtEnqueue);
        self::assertGreaterThan(ProjectQueue::RESULTS_TTL - 60, $this->redisTtl());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function sendProjectKeepsThePendingPayloadWhenTheEnqueueFails(): void
    {
        $stub = $this->createStub(AMQHandler::class);
        $stub->persistent = true;
        $stub->method('publishToQueues')->willThrowException(new RuntimeException('Broker down'));
        WorkerClient::$_HANDLER = $stub;

        try {
            ProjectQueue::sendProject($this->projectStructure());
            self::fail('Expected the enqueue failure to propagate');
        } catch (RuntimeException $e) {
            self::assertSame('Broker down', $e->getMessage());
        }

        // Harmless: only the password holder can read it, and it expires with the TTL.
        self::assertSame(['ppassword' => self::PASSWORD, 'pending' => true], ProjectQueue::getPublishedResults(self::ID_PROJECT));
    }

    /**
     * A failed creation never reaches populateResult(), so the result has no password of its own.
     *
     * @throws Exception
     */
    #[Test]
    public function publishResultsStampsThePasswordAndReplacesThePendingPayload(): void
    {
        WorkerClient::$_HANDLER->persistent = true;
        $projectStructure = $this->projectStructure();
        ProjectQueue::sendProject($projectStructure);

        $projectStructure->result['errors'][] = ['code' => 17, 'message' => 'Boom failure'];
        ProjectQueue::publishResults($projectStructure);

        self::assertSame(
            ['errors' => [['code' => 17, 'message' => 'Boom failure']], 'data' => [], 'ppassword' => self::PASSWORD],
            ProjectQueue::getPublishedResults(self::ID_PROJECT)
        );
        self::assertGreaterThan(ProjectQueue::RESULTS_TTL - 60, $this->redisTtl());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function publishResultsKeepsThePasswordOfTheStructureOverTheResult(): void
    {
        $projectStructure                      = $this->projectStructure();
        $projectStructure->result['ppassword'] = 'stale-password';

        ProjectQueue::publishResults($projectStructure);

        self::assertSame(self::PASSWORD, ProjectQueue::getPublishedResults(self::ID_PROJECT)['ppassword']);
    }
}
