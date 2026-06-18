<?php

namespace Matecat\Core\TaskRunner\Commons;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use SplObserver;
use SplSubject;
use Utils\ActiveMQ\AMQHandler;
use Utils\TaskRunner\Commons\AbstractElement;
use Utils\TaskRunner\Commons\AbstractWorker;
use Utils\TaskRunner\Commons\Context;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EndQueueException;

#[AllowMockObjectsWithoutExpectations]
class AbstractWorkerTest extends AbstractTest
{
    private AMQHandler $stubAmqHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubAmqHandler = $this->createStub(AMQHandler::class);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function makeWorker(): AbstractWorker
    {
        $handler = $this->stubAmqHandler;

        $worker = new class($handler) extends AbstractWorker {
            public function process(AbstractElement $queueElement): void {}

            public function getLogMsg(): array|string
            {
                return $this->_logMsg ?? '';
            }

            /** Expose _doLog for testing */
            public function doLog(array|string $msg): void
            {
                $this->_doLog($msg);
            }

            /** Expose _checkForReQueueEnd for testing */
            public function checkForReQueueEnd(QueueElement $queueElement): void
            {
                $this->_checkForReQueueEnd($queueElement);
            }

            /** Expose _checkDatabaseConnection for testing */
            public function checkDatabaseConnection(): void
            {
                $this->_checkDatabaseConnection();
            }

            /** Expose publishToNodeJsClients for testing */
            public function callPublishToNodeJsClients(mixed $object): void
            {
                $this->publishToNodeJsClients($object);
            }

            /** AMQHandler stub injected for publishToNodeJsClients tests */
            public ?AMQHandler $amqHandlerStub = null;

            protected function _getAmqHandlerForPublish(): AMQHandler
            {
                return $this->amqHandlerStub ?? parent::_getAmqHandlerForPublish();
            }
        };

        // Initialize $_observer so notify() works without prior attach()
        $ref = new ReflectionClass($worker);
        $ref->getProperty('_observer')->setValue($worker, []);

        return $worker;
    }

    // ─── Constructor ─────────────────────────────────────────────────────────────

    #[Test]
    public function constructor_sets_queue_handler(): void
    {
        $worker = $this->makeWorker();

        $ref = new ReflectionClass($worker);
        $this->assertSame($this->stubAmqHandler, $ref->getProperty('_queueHandler')->getValue($worker));
    }

    // ─── setPid() ────────────────────────────────────────────────────────────────

    #[Test]
    public function setPid_stores_pid_string(): void
    {
        $worker = $this->makeWorker();
        $worker->setPid('42');

        $ref = new ReflectionClass($worker);
        $this->assertSame('42', $ref->getProperty('_workerPid')->getValue($worker));
    }

    #[Test]
    public function setPid_default_pid_is_zero_string(): void
    {
        $worker = $this->makeWorker();

        $ref = new ReflectionClass($worker);
        $this->assertSame('0', $ref->getProperty('_workerPid')->getValue($worker));
    }

    // ─── setContext() / getContext() ─────────────────────────────────────────────

    #[Test]
    public function setContext_stores_context(): void
    {
        $worker = $this->makeWorker();
        $context = Context::buildFromArray(['queue_name' => 'test_q', 'max_executors' => 1]);
        $worker->setContext($context);

        $this->assertSame($context, $worker->getContext());
    }

    #[Test]
    public function getContext_returns_same_instance_after_setContext(): void
    {
        $worker = $this->makeWorker();
        $context = Context::buildFromArray(['queue_name' => 'test_q', 'max_executors' => 2]);
        $worker->setContext($context);

        $this->assertSame($context, $worker->getContext());
    }

    // ─── getLoggerName() ─────────────────────────────────────────────────────────

    #[Test]
    public function getLoggerName_returns_context_logger_name(): void
    {
        $worker = $this->makeWorker();
        $context = Context::buildFromArray(['queue_name' => 'test_q', 'max_executors' => 1]);
        $worker->setContext($context);

        $this->assertSame($context->loggerName, $worker->getLoggerName());
    }

    // ─── _doLog() / getLogMsg() ──────────────────────────────────────────────────

    #[Test]
    public function doLog_sets_log_message_string(): void
    {
        $worker = $this->makeWorker();
        $worker->doLog('hello world');

        $this->assertSame('hello world', $worker->getLogMsg());
    }

    #[Test]
    public function doLog_sets_log_message_array(): void
    {
        $worker = $this->makeWorker();
        $worker->doLog(['key' => 'value', 'num' => 42]);

        $this->assertSame(['key' => 'value', 'num' => 42], $worker->getLogMsg());
    }

    // ─── attach() / detach() / notify() ─────────────────────────────────────────

    #[Test]
    public function attach_adds_observer_and_notify_calls_it(): void
    {
        $worker = $this->makeWorker();

        $observer = new class implements SplObserver {
            public bool $updated = false;
            public function update(SplSubject $subject): void { $this->updated = true; }
        };

        $worker->attach($observer);
        $worker->notify();

        $this->assertTrue($observer->updated);
    }

    #[Test]
    public function doLog_triggers_observer_update(): void
    {
        $worker = $this->makeWorker();

        $observer = new class implements SplObserver {
            public bool $updated = false;
            public function update(SplSubject $subject): void { $this->updated = true; }
        };

        $worker->attach($observer);
        $worker->doLog('logged');

        $this->assertTrue($observer->updated);
    }

    #[Test]
    public function detach_removes_observer(): void
    {
        $worker = $this->makeWorker();

        $observer = new class implements SplObserver {
            public int $callCount = 0;
            public function update(SplSubject $subject): void { $this->callCount++; }
        };

        $worker->attach($observer);
        $worker->detach($observer);
        $worker->notify();

        $this->assertSame(0, $observer->callCount);
    }

    #[Test]
    public function notify_calls_all_attached_observers(): void
    {
        $worker = $this->makeWorker();

        $observer1 = new class implements SplObserver {
            public bool $updated = false;
            public function update(SplSubject $subject): void { $this->updated = true; }
        };
        $observer2 = new class implements SplObserver {
            public bool $updated = false;
            public function update(SplSubject $subject): void { $this->updated = true; }
        };

        $worker->attach($observer1);
        $worker->attach($observer2);
        $worker->notify();

        $this->assertTrue($observer1->updated);
        $this->assertTrue($observer2->updated);
    }

    #[Test]
    public function notify_passes_worker_as_subject(): void
    {
        $worker = $this->makeWorker();

        $observer = new class implements SplObserver {
            public ?SplSubject $received = null;
            public function update(SplSubject $subject): void { $this->received = $subject; }
        };

        $worker->attach($observer);
        $worker->notify();

        $this->assertSame($worker, $observer->received);
    }

    #[Test]
    public function attach_same_observer_twice_is_idempotent(): void
    {
        $worker = $this->makeWorker();

        $observer = new class implements SplObserver {
            public int $callCount = 0;
            public function update(SplSubject $subject): void { $this->callCount++; }
        };

        $worker->attach($observer);
        $worker->attach($observer); // same hash → same slot
        $worker->notify();

        $this->assertSame(1, $observer->callCount);
    }

    // ─── _checkForReQueueEnd() ────────────────────────────────────────────────────

    #[Test]
    public function checkForReQueueEnd_does_not_throw_below_limit(): void
    {
        $worker = $this->makeWorker();

        $element = new QueueElement(['classLoad' => 'SomeClass', 'params' => []]);
        $element->reQueueNum = 50;

        $worker->checkForReQueueEnd($element);
        $this->assertTrue(true);
    }

    #[Test]
    public function checkForReQueueEnd_throws_EndQueueException_at_limit(): void
    {
        $worker = $this->makeWorker();

        $ref = new ReflectionClass($worker);
        $ref->getProperty('maxRequeueNum')->setValue($worker, 100);

        $element = new QueueElement(['classLoad' => 'SomeClass', 'params' => []]);
        $element->reQueueNum = 100;

        $this->expectException(EndQueueException::class);
        $worker->checkForReQueueEnd($element);
    }

    #[Test]
    public function checkForReQueueEnd_throws_EndQueueException_above_limit(): void
    {
        $worker = $this->makeWorker();

        $ref = new ReflectionClass($worker);
        $ref->getProperty('maxRequeueNum')->setValue($worker, 100);

        $element = new QueueElement(['classLoad' => 'SomeClass', 'params' => []]);
        $element->reQueueNum = 150;

        $this->expectException(EndQueueException::class);
        $worker->checkForReQueueEnd($element);
    }

    #[Test]
    public function checkForReQueueEnd_zero_requeue_does_not_throw(): void
    {
        $worker = $this->makeWorker();

        $element = new QueueElement(['classLoad' => 'SomeClass', 'params' => []]);
        $element->reQueueNum = 0;

        $worker->checkForReQueueEnd($element);
        $this->assertTrue(true);
    }

    // ─── _checkDatabaseConnection() ──────────────────────────────────────────────

    #[Test]
    public function checkDatabaseConnection_succeeds_when_ping_ok(): void
    {
        $worker = $this->makeWorker();

        $dbStub = $this->createStub(Database::class);
        $dbStub->method('ping')->willReturn(true);

        $this->setDatabaseInstance($dbStub);

        $worker->checkDatabaseConnection();
        $this->assertTrue(true);
    }

    #[Test]
    public function checkDatabaseConnection_reconnects_on_pdo_exception(): void
    {
        $worker = $this->makeWorker();

        $pdoStub = $this->createStub(PDO::class);

        $dbStub = $this->createStub(Database::class);
        $dbStub->method('ping')->willThrowException(new PDOException('MySQL server has gone away'));
        $dbStub->method('getConnection')->willReturn($pdoStub);

        $log = new class implements SplObserver {
            /** @var string[] */
            public array $messages = [];

            public function update(SplSubject $subject): void
            {
                /** @var AbstractWorker $subject */
                $msg = $subject->getLogMsg();
                $this->messages[] = is_array($msg) ? implode(' ', $msg) : (string)$msg;
            }
        };
        $worker->attach($log);

        $this->setDatabaseInstance($dbStub);

        $worker->checkDatabaseConnection();

        $this->assertCount(2, $log->messages);
        $this->assertStringContainsString('MySQL server has gone away', $log->messages[0]);
        $this->assertStringContainsString('Database connection reloaded', $log->messages[1]);
    }

    // ─── publishToNodeJsClients() ─────────────────────────────────────────────────

    #[Test]
    public function publishToNodeJsClients_sends_json_encoded_message(): void
    {
        $worker = $this->makeWorker();

        $amqStub = $this->createStub(AMQHandler::class);
        $amqStub->method('publishToNodeJsClients')->willReturn(true);
        $worker->amqHandlerStub = $amqStub;

        $worker->callPublishToNodeJsClients(['foo' => 'bar']);

        $this->assertSame('{"foo":"bar"}', $worker->getLogMsg());
    }

    #[Test]
    public function publishToNodeJsClients_handles_non_encodable_object(): void
    {
        $worker = $this->makeWorker();

        $amqStub = $this->createStub(AMQHandler::class);
        $amqStub->method('publishToNodeJsClients')->willReturn(true);
        $worker->amqHandlerStub = $amqStub;

        // json_encode on a resource returns false → fallback to empty string
        $resource = fopen('php://memory', 'r');
        $worker->callPublishToNodeJsClients($resource);
        if (is_resource($resource)) {
            fclose($resource);
        }

        $this->assertSame('', $worker->getLogMsg());
    }
}
