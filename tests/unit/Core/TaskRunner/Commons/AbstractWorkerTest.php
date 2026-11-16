<?php

namespace Matecat\Core\TaskRunner\Commons;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Utils\ActiveMQ\AMQHandler;
use Utils\Logger\MatecatLogger;
use Utils\TaskRunner\Commons\AbstractElement;
use Utils\TaskRunner\Commons\AbstractWorker;
use Utils\TaskRunner\Commons\Context;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EndQueueException;

#[AllowMockObjectsWithoutExpectations]
class AbstractWorkerTest extends AbstractTest
{
    private AMQHandler $stubAmqHandler;

    /** Collects every record the worker writes through the logger injected by makeWorker(). */
    private TestHandler $logHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubAmqHandler = $this->createStub(AMQHandler::class);
        $this->logHandler = new TestHandler();
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function makeWorker(?IDatabase $database = null): AbstractWorker
    {
        $handler = $this->stubAmqHandler;
        $monolog = new Logger('test');
        $monolog->pushHandler($this->logHandler);

        return new class($handler, $database ?? obtainTestDatabase(), new MatecatLogger($monolog)) extends AbstractWorker {
            public function process(AbstractElement $queueElement): void {}

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
    }

    /**
     * @return string[] the messages of the debug records written so far
     */
    private function debugMessages(): array
    {
        $messages = [];
        foreach ($this->logHandler->getRecords() as $record) {
            if ($record->level === Level::Debug) {
                $messages[] = $record->message;
            }
        }

        return $messages;
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

    // ─── _doLog() ───────────────────────────────────────────────────────────────

    #[Test]
    public function doLog_writes_string_at_debug_to_injected_logger(): void
    {
        $worker = $this->makeWorker();
        $worker->doLog('hello world');

        $this->assertTrue($this->logHandler->hasDebug('hello world'));
        $this->assertCount(1, $this->logHandler->getRecords());
    }

    #[Test]
    public function doLog_writes_array_as_debug_context(): void
    {
        $worker = $this->makeWorker();
        $worker->doLog(['key' => 'value', 'num' => 42]);

        $records = $this->logHandler->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame(Level::Debug, $records[0]->level);
        $this->assertSame(['key' => 'value', 'num' => 42], $records[0]->context);
    }

    #[Test]
    public function worker_built_without_logger_falls_back_to_default_logger(): void
    {
        $worker = new class($this->stubAmqHandler, obtainTestDatabase()) extends AbstractWorker {
            public function process(AbstractElement $queueElement): void {}

            public function doLog(array|string $msg): void
            {
                $this->_doLog($msg);
            }
        };

        $worker->doLog('logged without an injected logger');

        $ref = new ReflectionClass(AbstractWorker::class);
        $this->assertInstanceOf(MatecatLogger::class, $ref->getProperty('logger')->getValue($worker));
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
        $pdoStub = $this->createStub(PDO::class);

        $dbStub = $this->createStub(Database::class);
        $dbStub->method('ping')->willThrowException(new PDOException('MySQL server has gone away'));
        $dbStub->method('getConnection')->willReturn($pdoStub);

        $worker = $this->makeWorker($dbStub);

        $this->setDatabaseInstance($dbStub);

        $worker->checkDatabaseConnection();

        $messages = $this->debugMessages();
        $this->assertCount(2, $messages);
        $this->assertStringContainsString('MySQL server has gone away', $messages[0]);
        $this->assertStringContainsString('Database connection reloaded', $messages[1]);
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

        $this->assertSame(['{"foo":"bar"}'], $this->debugMessages());
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

        $this->assertSame([''], $this->debugMessages());
    }

    /**
     * The Executor builds every worker with the named argument `logger:`. A worker whose own
     * constructor has no `$logger` parameter would only fail at runtime, with
     * "Unknown named parameter $logger", so this test checks every concrete worker class.
     */
    #[Test]
    public function every_worker_constructor_accepts_the_logger_named_argument(): void
    {
        $roots = array_merge(
            [AbstractTest::projectRoot() . '/lib/Utils/AsyncTasks/Workers'],
            glob(AbstractTest::projectRoot() . '/plugins/*/lib', GLOB_ONLYDIR) ?: []
        );

        $workers = [];
        foreach ($roots as $root) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $source = (string)file_get_contents($file->getPathname());
                if (!preg_match('/^namespace\s+([^;]+);/m', $source, $ns)
                    || !preg_match('/^(?:final\s+|abstract\s+)*class\s+(\w+)/m', $source, $cls)) {
                    continue;
                }
                $className = $ns[1] . '\\' . $cls[1];
                if (!class_exists($className)) {
                    continue;
                }
                $reflection = new ReflectionClass($className);
                if ($reflection->isAbstract() || !$reflection->isSubclassOf(AbstractWorker::class)) {
                    continue;
                }
                $workers[] = $className;

                $parameters = array_map(
                    fn($parameter) => $parameter->getName(),
                    $reflection->getConstructor()?->getParameters() ?? []
                );
                $this->assertContains('logger', $parameters, "$className::__construct() must accept ?MatecatLogger \$logger.");
            }
        }

        $this->assertContains('Utils\\AsyncTasks\\Workers\\Analysis\\TMAnalysisWorker', $workers);
    }
}
