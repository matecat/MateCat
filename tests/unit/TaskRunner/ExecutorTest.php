<?php

namespace Tests\Unit\TaskRunner;

use Exception;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionMethod;
use SplSubject;
use Stomp\Client;
use Stomp\Transport\Frame;
use TestHelpers\AbstractTest;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\TestFixtureWorker;
use Utils\Logger\LoggerFactory;
use Utils\TaskRunner\Commons\AbstractElement;
use Utils\TaskRunner\Commons\AbstractWorker;
use Utils\TaskRunner\Commons\Context;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\FrameException;
use Utils\TaskRunner\Executor;

require_once __DIR__ . '/TestFixtureWorker.php';

#[AllowMockObjectsWithoutExpectations]
class ExecutorTest extends AbstractTest
{
    private MockObject&AMQHandler $mockAmqHandler;
    private Context $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockAmqHandler = $this->createMock(AMQHandler::class);

        $this->context = Context::buildFromArray([
            'queue_name'    => 'test_queue',
            'max_executors' => 1,
        ]);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function createTestableExecutor(): Executor
    {
        $ref = new ReflectionClass(Executor::class);
        /** @var Executor $executor */
        $executor = $ref->newInstanceWithoutConstructor();

        $props = [
            '_queueHandler'         => $this->mockAmqHandler,
            '_executionContext'      => $this->context,
            '_executorPID'          => 12345,
            '_executor_instance_id' => '12345:localhost:test',
            '_frameID'              => 0,
            'RUNNING'               => true,
            '_worker'               => null,
        ];

        foreach ($props as $name => $value) {
            $ref->getProperty($name)->setValue($executor, $value);
        }

        $ref->getProperty('logger')->setValue($executor, LoggerFactory::getLogger('executor', 'test_executor.log'));

        return $executor;
    }

    /**
     * Creates a mock Redis that returns 1 for the first N sismember calls, then 0.
     */
    private function createCountdownRedis(int $aliveForCycles = 1): \Predis\Client
    {
        return new class($aliveForCycles) extends \Predis\Client {
            public int $callCount = 0;

            public function __construct(private readonly int $limit)
            {
            }

            public function sismember($key, $member): int
            {
                return ++$this->callCount <= $this->limit ? 1 : 0;
            }

            public function srem($key, $members): int
            {
                return 1;
            }

            public function disconnect(): void
            {
            }
        };
    }

    /**
     * Creates a mock Redis that records sadd/srem/subscribe calls for init() testing.
     */
    private function createInitRedis(bool $saddSucceeds = true): object
    {
        return new class($saddSucceeds) extends \Predis\Client {
            public bool $saddCalled = false;
            public bool $sremCalled = false;
            /** @var array<int, array{0: string, 1: array<string>}> */
            public array $saddCalls = [];

            public function __construct(private readonly bool $saddResult)
            {
            }

            public function sadd($key, array $members): int
            {
                $this->saddCalled = true;
                $this->saddCalls[] = [$key, $members];
                return $this->saddResult ? 1 : 0;
            }

            public function srem($key, $members): int
            {
                $this->sremCalled = true;
                return 1;
            }

            public function disconnect(): void
            {
            }
        };
    }

    /**
     * Creates a no-op Stomp\Client for disconnect testing.
     */
    private function createMockStompClient(): Client
    {
        return new class extends Client {
            public bool $disconnected = false;

            public function __construct()
            {
            }

            public function disconnect($sync = false): void
            {
                $this->disconnected = true;
            }
        };
    }

    /**
     * Calls protected init() via reflection on a bare (no-constructor) Executor instance.
     */
    private function callInit(Executor $executor, Context $context, ?AMQHandler $handler = null): void
    {
        $method = new ReflectionMethod(Executor::class, 'init');
        $method->invoke($executor, $context, $handler);
    }

    // ─── init() tests ───────────────────────────────────────────────────────────

    #[Test]
    public function test_init_happy_path_registers_pid_and_subscribes(): void
    {
        $ref = new ReflectionClass(Executor::class);
        /** @var Executor $executor */
        $executor = $ref->newInstanceWithoutConstructor();

        $mockRedis = $this->createInitRedis(saddSucceeds: true);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($mockRedis);
        $this->mockAmqHandler->expects($this->once())
            ->method('subscribe')
            ->with('test_queue');

        $this->callInit($executor, $this->context, $this->mockAmqHandler);

        // Properties set correctly
        $this->assertSame(posix_getpid(), $executor->_executorPID);
        $this->assertStringContainsString((string)posix_getpid(), $executor->_executor_instance_id);
        $this->assertTrue($mockRedis->saddCalled);
        $this->assertSame('test_queue_pid_set', $mockRedis->saddCalls[0][0]);
        $this->assertSame([$executor->_executor_instance_id], $mockRedis->saddCalls[0][1]);
    }

    #[Test]
    public function test_init_sets_execution_context(): void
    {
        $ref = new ReflectionClass(Executor::class);
        /** @var Executor $executor */
        $executor = $ref->newInstanceWithoutConstructor();

        $mockRedis = $this->createInitRedis(saddSucceeds: true);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($mockRedis);
        $this->mockAmqHandler->method('subscribe');

        $this->callInit($executor, $this->context, $this->mockAmqHandler);

        $contextProp = $ref->getProperty('_executionContext');
        $this->assertSame($this->context, $contextProp->getValue($executor));
    }

    #[Test]
    public function test_init_initializes_logger(): void
    {
        $ref = new ReflectionClass(Executor::class);
        /** @var Executor $executor */
        $executor = $ref->newInstanceWithoutConstructor();

        $mockRedis = $this->createInitRedis(saddSucceeds: true);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($mockRedis);
        $this->mockAmqHandler->method('subscribe');

        $this->callInit($executor, $this->context, $this->mockAmqHandler);

        $loggerProp = $ref->getProperty('logger');
        $this->assertNotNull($loggerProp->getValue($executor));
    }

    #[Test]
    public function test_init_throws_when_sadd_fails(): void
    {
        $ref = new ReflectionClass(Executor::class);
        /** @var Executor $executor */
        $executor = $ref->newInstanceWithoutConstructor();

        $mockRedis = $this->createInitRedis(saddSucceeds: false);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($mockRedis);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('FATAL !! cannot create my resource ID');

        $this->callInit($executor, $this->context, $this->mockAmqHandler);
    }

    #[Test]
    public function test_init_throws_when_subscribe_fails(): void
    {
        $ref = new ReflectionClass(Executor::class);
        /** @var Executor $executor */
        $executor = $ref->newInstanceWithoutConstructor();

        $mockRedis = $this->createInitRedis(saddSucceeds: true);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($mockRedis);
        $this->mockAmqHandler->method('subscribe')
            ->willThrowException(new Exception('AMQ connection refused'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('AMQ connection refused');

        $this->callInit($executor, $this->context, $this->mockAmqHandler);
    }

    #[Test]
    public function test_init_cleans_up_pid_on_subscribe_failure(): void
    {
        $ref = new ReflectionClass(Executor::class);
        /** @var Executor $executor */
        $executor = $ref->newInstanceWithoutConstructor();

        $mockRedis = $this->createInitRedis(saddSucceeds: true);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($mockRedis);
        $this->mockAmqHandler->method('subscribe')
            ->willThrowException(new Exception('AMQ down'));

        try {
            $this->callInit($executor, $this->context, $this->mockAmqHandler);
        } catch (Exception) {
            // expected
        }

        $this->assertTrue($mockRedis->sremCalled, 'Expected srem to be called for PID cleanup');
    }

    #[Test]
    public function test_init_uses_injected_queue_handler(): void
    {
        $ref = new ReflectionClass(Executor::class);
        /** @var Executor $executor */
        $executor = $ref->newInstanceWithoutConstructor();

        $mockRedis = $this->createInitRedis(saddSucceeds: true);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($mockRedis);
        $this->mockAmqHandler->method('subscribe');

        $this->callInit($executor, $this->context, $this->mockAmqHandler);

        $handlerProp = $ref->getProperty('_queueHandler');
        $this->assertSame($this->mockAmqHandler, $handlerProp->getValue($executor));
    }

    // ─── cleanShutDown() tests ──────────────────────────────────────────────────

    #[Test]
    public function test_cleanShutDown_removes_pid_from_redis(): void
    {
        $executor = $this->createTestableExecutor();

        $mockRedis = new class extends \Predis\Client {
            public bool $sremCalled = false;
            public ?string $sremKey = null;
            /** @var string|null */
            public ?string $sremMember = null;

            public function __construct()
            {
            }

            public function srem($key, $members): int
            {
                $this->sremCalled = true;
                $this->sremKey = $key;
                $this->sremMember = $members;
                return 1;
            }

            public function disconnect(): void
            {
            }
        };

        $stompClient = $this->createMockStompClient();
        $this->mockAmqHandler->method('getRedisClient')->willReturn($mockRedis);
        $this->mockAmqHandler->method('getClient')->willReturn($stompClient);

        $executor->cleanShutDown();

        $this->assertTrue($mockRedis->sremCalled);
        $this->assertSame('test_queue_pid_set', $mockRedis->sremKey);
        $this->assertSame('12345:localhost:test', $mockRedis->sremMember);
    }

    #[Test]
    public function test_cleanShutDown_disconnects_redis_and_stomp(): void
    {
        $executor = $this->createTestableExecutor();

        $mockRedis = new class extends \Predis\Client {
            public bool $disconnected = false;

            public function __construct()
            {
            }

            public function srem($key, $members): int
            {
                return 1;
            }

            public function disconnect(): void
            {
                $this->disconnected = true;
            }
        };

        $stompClient = $this->createMockStompClient();
        $this->mockAmqHandler->method('getRedisClient')->willReturn($mockRedis);
        $this->mockAmqHandler->method('getClient')->willReturn($stompClient);

        $executor->cleanShutDown();

        $this->assertTrue($mockRedis->disconnected, 'Redis should be disconnected');
        $this->assertTrue($stompClient->disconnected, 'Stomp should be disconnected');
    }

    // ─── _myProcessExists() tests ───────────────────────────────────────────────

    #[Test]
    public function test_myProcessExists_delegates_to_redis_sismember(): void
    {
        $executor = $this->createTestableExecutor();

        $mockRedis = new class extends \Predis\Client {
            public int $sismemberCallCount = 0;
            public ?string $lastKey = null;
            public ?string $lastMember = null;

            public function __construct()
            {
            }

            public function sismember($key, $member): int
            {
                $this->sismemberCallCount++;
                $this->lastKey = $key;
                $this->lastMember = $member;
                return 1;
            }
        };

        $this->mockAmqHandler->method('getRedisClient')->willReturn($mockRedis);

        $method = new ReflectionMethod(Executor::class, '_myProcessExists');
        $result = $method->invoke($executor, '12345:localhost:test');

        $this->assertSame(1, $result);
        $this->assertSame(1, $mockRedis->sismemberCallCount);
        $this->assertSame('test_queue_pid_set', $mockRedis->lastKey);
        $this->assertSame('12345:localhost:test', $mockRedis->lastMember);
    }

    #[Test]
    public function test_myProcessExists_returns_zero_when_not_member(): void
    {
        $executor = $this->createTestableExecutor();

        $mockRedis = new class extends \Predis\Client {
            public function __construct()
            {
            }

            public function sismember($key, $member): int
            {
                return 0;
            }
        };

        $this->mockAmqHandler->method('getRedisClient')->willReturn($mockRedis);

        $method = new ReflectionMethod(Executor::class, '_myProcessExists');
        $result = $method->invoke($executor, '12345:localhost:test');

        $this->assertSame(0, $result);
    }

    // ─── _readAMQFrame() tests ──────────────────────────────────────────────────

    #[Test]
    public function test_readAMQFrame_throws_FrameException_when_no_frame(): void
    {
        $executor = $this->createTestableExecutor();

        $this->mockAmqHandler->expects($this->once())
            ->method('read')
            ->willReturn(false);

        $method = new ReflectionMethod(Executor::class, '_readAMQFrame');

        $this->expectException(FrameException::class);
        $method->invoke($executor);
    }

    #[Test]
    public function test_readAMQFrame_throws_FrameException_on_empty_json(): void
    {
        $executor = $this->createTestableExecutor();

        $frame = new Frame('MESSAGE', ['MESSAGE' => ''], '');
        $this->mockAmqHandler->expects($this->once())->method('read')->willReturn($frame);
        $this->mockAmqHandler->expects($this->once())->method('ack')->with($frame);

        $method = new ReflectionMethod(Executor::class, '_readAMQFrame');

        $this->expectException(FrameException::class);
        $this->expectExceptionMessage('Failed to decode the json');
        $method->invoke($executor);
    }

    #[Test]
    public function test_readAMQFrame_throws_FrameException_on_invalid_worker_class(): void
    {
        $executor = $this->createTestableExecutor();

        $payload = json_encode(['classLoad' => 'NonExistent\\Class', 'params' => []]);
        $frame = new Frame('MESSAGE', ['MESSAGE' => ''], $payload);
        $this->mockAmqHandler->expects($this->once())->method('read')->willReturn($frame);
        $this->mockAmqHandler->expects($this->once())->method('ack')->with($frame);

        $method = new ReflectionMethod(Executor::class, '_readAMQFrame');

        $this->expectException(FrameException::class);
        $method->invoke($executor);
    }

    #[Test]
    public function test_readAMQFrame_returns_frame_and_queue_element_on_valid_message(): void
    {
        $executor = $this->createTestableExecutor();

        $payload = json_encode(['classLoad' => TestDummyWorker::class, 'params' => ['key' => 'value']]);
        $frame = new Frame('MESSAGE', ['MESSAGE' => ''], $payload);
        $this->mockAmqHandler->expects($this->once())->method('read')->willReturn($frame);

        $method = new ReflectionMethod(Executor::class, '_readAMQFrame');
        $result = $method->invoke($executor);

        $this->assertInstanceOf(Frame::class, $result[0]);
        $this->assertInstanceOf(QueueElement::class, $result[1]);
        $this->assertSame(TestDummyWorker::class, $result[1]->classLoad);
    }

    #[Test]
    public function test_readAMQFrame_throws_FrameException_on_amq_exception(): void
    {
        $executor = $this->createTestableExecutor();

        $this->mockAmqHandler->expects($this->once())
            ->method('read')
            ->willThrowException(new Exception('AMQ connection lost'));

        $method = new ReflectionMethod(Executor::class, '_readAMQFrame');

        $this->expectException(FrameException::class);
        $this->expectExceptionMessage('$this->amqHandler->read() Failed');
        $method->invoke($executor);
    }

    #[Test]
    public function test_readAMQFrame_increments_frameID(): void
    {
        $executor = $this->createTestableExecutor();

        $payload = json_encode(['classLoad' => TestDummyWorker::class, 'params' => []]);
        $frame = new Frame('MESSAGE', ['MESSAGE' => ''], $payload);
        $this->mockAmqHandler->method('read')->willReturn($frame);

        $ref = new ReflectionClass($executor);
        $frameIdProp = $ref->getProperty('_frameID');
        $initial = $frameIdProp->getValue($executor);

        $method = new ReflectionMethod(Executor::class, '_readAMQFrame');
        $method->invoke($executor);

        $this->assertSame($initial + 1, $frameIdProp->getValue($executor));
    }

    // ─── update() tests ─────────────────────────────────────────────────────────

    #[Test]
    public function test_update_logs_message_from_abstract_worker(): void
    {
        $executor = $this->createTestableExecutor();

        $mockWorker = $this->createMock(AbstractWorker::class);
        $mockWorker->expects($this->once())->method('getLogMsg')->willReturn('Worker completed task');

        $executor->update($mockWorker);
    }

    #[Test]
    public function test_update_ignores_non_abstract_worker_subjects(): void
    {
        $executor = $this->createTestableExecutor();

        $mockSubject = $this->createMock(SplSubject::class);

        // Should not throw — just does nothing
        $executor->update($mockSubject);
        $this->assertTrue(true);
    }

    // ─── isAllowedWorkerClass() tests ───────────────────────────────────────────

    #[Test]
    public function test_isAllowedWorkerClass_accepts_core_workers(): void
    {
        $executor = $this->createTestableExecutor();
        $method = new ReflectionMethod(Executor::class, 'isAllowedWorkerClass');

        $this->assertTrue($method->invoke($executor, 'Utils\\AsyncTasks\\Workers\\ErrMailWorker'));
        $this->assertTrue($method->invoke($executor, 'Utils\\AsyncTasks\\Workers\\Analysis\\TMAnalysisWorker'));
        $this->assertTrue($method->invoke($executor, 'Utils\\AsyncTasks\\Workers\\SetContributionWorker'));
    }

    #[Test]
    public function test_isAllowedWorkerClass_accepts_plugin_workers(): void
    {
        $executor = $this->createTestableExecutor();
        $method = new ReflectionMethod(Executor::class, 'isAllowedWorkerClass');

        $this->assertTrue($method->invoke($executor, 'Features\\Aligner\\Utils\\AsyncTasks\\Workers\\AlignJobWorker'));
        $this->assertTrue($method->invoke($executor, 'Features\\Aligner\\Utils\\AsyncTasks\\Workers\\TMXImportWorker'));
    }

    #[Test]
    public function test_isAllowedWorkerClass_accepts_leading_backslash(): void
    {
        $executor = $this->createTestableExecutor();
        $method = new ReflectionMethod(Executor::class, 'isAllowedWorkerClass');

        $this->assertTrue($method->invoke($executor, '\\Utils\\AsyncTasks\\Workers\\MailWorker'));
        $this->assertTrue($method->invoke($executor, '\\Features\\Aligner\\Utils\\AsyncTasks\\Workers\\AlignJobWorker'));
    }

    #[Test]
    public function test_isAllowedWorkerClass_rejects_arbitrary_classes(): void
    {
        $executor = $this->createTestableExecutor();
        $method = new ReflectionMethod(Executor::class, 'isAllowedWorkerClass');

        $this->assertFalse($method->invoke($executor, 'stdClass'));
        $this->assertFalse($method->invoke($executor, 'App\\Malicious\\Worker'));
        $this->assertFalse($method->invoke($executor, 'Utils\\TaskRunner\\Executor'));
        $this->assertFalse($method->invoke($executor, ''));
    }

    // ─── _ackAndRequeue() tests ─────────────────────────────────────────────────

    #[Test]
    public function test_ackAndRequeue_acks_increments_and_publishes(): void
    {
        $executor = $this->createTestableExecutor();

        $mockPublisher = $this->createMock(AMQHandler::class);
        $stompClient = $this->createMockStompClient();
        $mockPublisher->method('getClient')->willReturn($stompClient);
        $mockPublisher->expects($this->once())->method('reQueue');

        $ref = new ReflectionClass($executor);
        $ref->getProperty('_testPublisherOverride')->setValue($executor, $mockPublisher);

        $this->mockAmqHandler->expects($this->once())->method('ack');

        $frame = new Frame('MESSAGE', ['MESSAGE' => ''], '{}');
        $queueElement = new QueueElement(['classLoad' => TestDummyWorker::class, 'params' => []]);
        $queueElement->reQueueNum = 2;

        $method = new ReflectionMethod(Executor::class, '_ackAndRequeue');
        $method->invoke($executor, $frame, $queueElement);

        $this->assertSame(3, $queueElement->reQueueNum);
        $this->assertTrue($stompClient->disconnected);
    }

    // ─── main() tests ───────────────────────────────────────────────────────────

    #[Test]
    public function test_main_happy_path_processes_worker_and_acks(): void
    {
        $executor = $this->createTestableExecutor();
        $mockRedis = $this->createCountdownRedis(1);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($mockRedis);

        $payload = json_encode(['classLoad' => TestDummyWorker::class, 'params' => []]);
        $frame = new Frame('MESSAGE', ['MESSAGE' => ''], $payload);
        $this->mockAmqHandler->method('read')->willReturn($frame);
        $this->mockAmqHandler->expects($this->once())->method('ack')->with($frame);

        // Pre-set worker to skip namespace allowlist check
        $ref = new ReflectionClass($executor);
        $ref->getProperty('_worker')->setValue($executor, new TestDummyWorker($this->mockAmqHandler));

        $executor->main(0);

        $this->assertFalse($executor->RUNNING);
    }

    #[Test]
    public function test_main_requeue_exception_triggers_ack_and_requeue(): void
    {
        $executor = $this->createTestableExecutor();
        $mockRedis = $this->createCountdownRedis(1);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($mockRedis);

        $payload = json_encode(['classLoad' => TestReQueueWorker::class, 'params' => []]);
        $frame = new Frame('MESSAGE', ['MESSAGE' => ''], $payload);
        $this->mockAmqHandler->method('read')->willReturn($frame);
        $this->mockAmqHandler->expects($this->once())->method('ack');

        $mockPublisher = $this->createMock(AMQHandler::class);
        $mockPublisher->method('getClient')->willReturn($this->createMockStompClient());
        $mockPublisher->expects($this->once())->method('reQueue');

        $ref = new ReflectionClass($executor);
        $ref->getProperty('_worker')->setValue($executor, new TestReQueueWorker($this->mockAmqHandler));
        $ref->getProperty('_testPublisherOverride')->setValue($executor, $mockPublisher);

        $executor->main(0);

        $this->assertFalse($executor->RUNNING);
    }

    #[Test]
    public function test_main_pdo_exception_triggers_requeue_and_sleep(): void
    {
        $executor = $this->createTestableExecutor();
        $mockRedis = $this->createCountdownRedis(1);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($mockRedis);

        $payload = json_encode(['classLoad' => TestPDOExceptionWorker::class, 'params' => []]);
        $frame = new Frame('MESSAGE', ['MESSAGE' => ''], $payload);
        $this->mockAmqHandler->method('read')->willReturn($frame);
        $this->mockAmqHandler->expects($this->once())->method('ack');

        $mockPublisher = $this->createMock(AMQHandler::class);
        $mockPublisher->method('getClient')->willReturn($this->createMockStompClient());
        $mockPublisher->expects($this->once())->method('reQueue');

        $ref = new ReflectionClass($executor);
        $ref->getProperty('_worker')->setValue($executor, new TestPDOExceptionWorker($this->mockAmqHandler));
        $ref->getProperty('_testPublisherOverride')->setValue($executor, $mockPublisher);

        $executor->main(0);

        $this->assertFalse($executor->RUNNING);
    }

    #[Test]
    public function test_main_throwable_acks_and_continues(): void
    {
        $executor = $this->createTestableExecutor();
        $mockRedis = $this->createCountdownRedis(1);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($mockRedis);

        $payload = json_encode(['classLoad' => TestThrowableWorker::class, 'params' => []]);
        $frame = new Frame('MESSAGE', ['MESSAGE' => ''], $payload);
        $this->mockAmqHandler->method('read')->willReturn($frame);
        // Throwable path falls through to bottom ack
        $this->mockAmqHandler->expects($this->once())->method('ack');

        $ref = new ReflectionClass($executor);
        $ref->getProperty('_worker')->setValue($executor, new TestThrowableWorker($this->mockAmqHandler));

        $executor->main(0);

        $this->assertFalse($executor->RUNNING);
    }

    #[Test]
    public function test_main_calls_cleanShutDown_at_end(): void
    {
        $executor = $this->createTestableExecutor();
        $mockRedis = $this->createCountdownRedis(0); // immediately returns 0 → stops loop
        $this->mockAmqHandler->method('getRedisClient')->willReturn($mockRedis);

        $stompClient = $this->createMockStompClient();
        $this->mockAmqHandler->method('getClient')->willReturn($stompClient);

        $executor->main(0);

        $this->assertTrue($stompClient->disconnected, 'cleanShutDown should disconnect Stomp');
    }

    #[Test]
    public function test_main_instantiates_worker_when_null(): void
    {
        $executor = $this->createTestableExecutor();
        $mockRedis = $this->createCountdownRedis(1);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($mockRedis);

        $fixtureClass = TestFixtureWorker::class;
        $payload = json_encode(['classLoad' => $fixtureClass, 'params' => ['foo' => 'bar']]);
        $frame = new Frame('MESSAGE', ['MESSAGE' => ''], $payload);
        $this->mockAmqHandler->method('read')->willReturn($frame);
        $this->mockAmqHandler->expects($this->once())->method('ack')->with($frame);

        // _worker is null (default from createTestableExecutor) → enters instantiation block
        $executor->main(0);

        $ref = new ReflectionClass($executor);
        $worker = $ref->getProperty('_worker')->getValue($executor);

        $this->assertInstanceOf(TestFixtureWorker::class, $worker);
        $this->assertTrue($worker->processCalled);
    }

    #[Test]
    public function test_main_reuses_worker_when_same_class(): void
    {
        $executor = $this->createTestableExecutor();
        // alive for 2 cycles, then stops
        $mockRedis = $this->createCountdownRedis(2);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($mockRedis);

        $fixtureClass = TestFixtureWorker::class;
        $payload = json_encode(['classLoad' => $fixtureClass, 'params' => []]);
        $frame = new Frame('MESSAGE', ['MESSAGE' => ''], $payload);
        $this->mockAmqHandler->method('read')->willReturn($frame);
        $this->mockAmqHandler->expects($this->exactly(2))->method('ack');

        $executor->main(0);

        $ref = new ReflectionClass($executor);
        $worker = $ref->getProperty('_worker')->getValue($executor);
        $this->assertInstanceOf(TestFixtureWorker::class, $worker);
        // Worker was reused (same instance for both frames) — process called twice
        $this->assertTrue($worker->processCalled);
    }

    #[Test]
    public function test_main_reinstantiates_worker_when_class_changes(): void
    {
        $executor = $this->createTestableExecutor();
        $mockRedis = $this->createCountdownRedis(1);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($mockRedis);

        $fixtureClass = TestFixtureWorker::class;
        $payload = json_encode(['classLoad' => $fixtureClass, 'params' => []]);
        $frame = new Frame('MESSAGE', ['MESSAGE' => ''], $payload);
        $this->mockAmqHandler->method('read')->willReturn($frame);
        $this->mockAmqHandler->expects($this->once())->method('ack');

        // Pre-set a DIFFERENT worker — forces re-instantiation
        $ref = new ReflectionClass($executor);
        $ref->getProperty('_worker')->setValue($executor, new TestDummyWorker($this->mockAmqHandler));

        $executor->main(0);

        $worker = $ref->getProperty('_worker')->getValue($executor);
        $this->assertInstanceOf(TestFixtureWorker::class, $worker);
        $this->assertTrue($worker->processCalled);
    }

    #[Test]
    public function test_main_rejects_disallowed_worker_namespace(): void
    {
        $executor = $this->createTestableExecutor();
        $mockRedis = $this->createCountdownRedis(1);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($mockRedis);

        // TestDummyWorker is in Tests\Unit\TaskRunner namespace — NOT allowed
        $payload = json_encode(['classLoad' => TestDummyWorker::class, 'params' => []]);
        $frame = new Frame('MESSAGE', ['MESSAGE' => ''], $payload);
        $this->mockAmqHandler->method('read')->willReturn($frame);
        // Throwable catch acks at bottom of loop
        $this->mockAmqHandler->expects($this->once())->method('ack');

        // _worker = null → enters instantiation block → isAllowedWorkerClass fails → WorkerClassException → caught by Throwable
        $executor->main(0);

        // Worker should still be null (instantiation was rejected)
        $ref = new ReflectionClass($executor);
        $this->assertNull($ref->getProperty('_worker')->getValue($executor));
    }

    #[Test]
    public function test_main_worker_attach_sets_pid_and_context(): void
    {
        $executor = $this->createTestableExecutor();
        $mockRedis = $this->createCountdownRedis(1);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($mockRedis);

        $fixtureClass = TestFixtureWorker::class;
        $payload = json_encode(['classLoad' => $fixtureClass, 'params' => []]);
        $frame = new Frame('MESSAGE', ['MESSAGE' => ''], $payload);
        $this->mockAmqHandler->method('read')->willReturn($frame);

        $executor->main(0);

        $ref = new ReflectionClass($executor);
        /** @var TestFixtureWorker $worker */
        $worker = $ref->getProperty('_worker')->getValue($executor);

        // Verify setPid and setContext were called (via AbstractWorker properties)
        $workerRef = new ReflectionClass($worker);
        $pidProp = $workerRef->getProperty('_workerPid');
        $contextProp = $workerRef->getProperty('_myContext');

        $this->assertSame('12345:localhost:test', $pidProp->getValue($worker));
        $this->assertSame($this->context, $contextProp->getValue($worker));
    }
}

// ─── Test worker stubs ──────────────────────────────────────────────────────────

class TestDummyWorker extends AbstractWorker
{
    public function __construct(AMQHandler $queueHandler)
    {
        parent::__construct($queueHandler);
    }

    public function process(AbstractElement $queueElement): void
    {
    }

    public function getLogMsg(): array|string
    {
        return 'dummy log';
    }
}

class TestReQueueWorker extends AbstractWorker
{
    public function __construct(AMQHandler $queueHandler)
    {
        parent::__construct($queueHandler);
    }

    public function process(AbstractElement $queueElement): void
    {
        throw new \Utils\TaskRunner\Exceptions\ReQueueException('test requeue');
    }

    public function getLogMsg(): array|string
    {
        return 'requeue log';
    }
}

class TestPDOExceptionWorker extends AbstractWorker
{
    public function __construct(AMQHandler $queueHandler)
    {
        parent::__construct($queueHandler);
    }

    public function process(AbstractElement $queueElement): void
    {
        throw new \PDOException('test pdo error');
    }

    public function getLogMsg(): array|string
    {
        return 'pdo log';
    }
}

class TestThrowableWorker extends AbstractWorker
{
    public function __construct(AMQHandler $queueHandler)
    {
        parent::__construct($queueHandler);
    }

    public function process(AbstractElement $queueElement): void
    {
        throw new \Error('test throwable');
    }

    public function getLogMsg(): array|string
    {
        return 'throwable log';
    }
}
