<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 04/05/15
 * Time: 13.37
 *
 */

namespace Utils\TaskRunner;

use Exception;
use Model\DataAccess\Database;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use PDOException;
use Predis\Connection\ConnectionException;
use Predis\Response\ServerException;
use Psr\Log\InvalidArgumentException;
use ReflectionException;
use RuntimeException;
use SplObserver;
use SplSubject;
use Stomp\Transport\Frame;
use Throwable;
use Utils\ActiveMQ\AMQHandler;
use Utils\Logger\LoggerFactory;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Commons\AbstractWorker;
use Utils\TaskRunner\Commons\Context;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Commons\SignalHandlerTrait;
use Utils\TaskRunner\Exceptions\EmptyElementException;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\FrameException;
use Utils\TaskRunner\Exceptions\ReQueueException;
use Utils\TaskRunner\Exceptions\WorkerClassException;

/**
 * Class Executor
 * Process class spawned from the Task Manager
 * Every Executor is bind to its context (queue name, process Redis set, ecc.)
 *
 * @package TaskRunner
 */
class Executor implements SplObserver
{

    use SignalHandlerTrait;

    /**
     * Allowed namespace prefixes for worker classes.
     * Only classes under these namespaces can be instantiated by the Executor.
     */
    private const array ALLOWED_WORKER_NAMESPACES = [
        'Utils\\AsyncTasks\\Workers\\',
        'Features\\',
    ];

    /**
     * Handler of AMQ connector
     *
     * @var AMQHandler
     */
    protected AMQHandler $_queueHandler;

    /**
     * Context of execution
     *
     * @var Context
     */
    protected Context $_executionContext;

    /**
     * AMQ frames read
     *
     * @var int
     */
    protected int $_frameID = 0;

    /**
     * Flag for control the instance running status. Setting to false causes the Executor process to stop.
     *
     * @var bool
     */
    public bool $RUNNING = true;

    /**
     * The process id of the Executor
     *
     * @var int
     */
    public int $_executorPID;

    /**
     * @var string
     */
    public string $_executor_instance_id;

    /**
     * Logger instance
     *
     * @var MatecatLogger
     */
    protected MatecatLogger $logger;

    /**
     * Concrete worker
     *
     * @var ?AbstractWorker
     */
    protected ?AbstractWorker $_worker = null;

    /** @internal Test-only: override for _createPublisher() */
    protected ?AMQHandler $_testPublisherOverride = null;

    /**
     * Executor constructor.
     *
     * @param Context $_context
     * @param AMQHandler|null $_queueHandler
     * @throws Exception
     */
    protected function __construct(Context $_context, ?AMQHandler $_queueHandler = null)
    {
        try {
            $this->installHandler();
            $this->init($_context, $_queueHandler);
        } catch (Throwable) {
            exit(1);
        }
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    protected function init(Context $_context, ?AMQHandler $_queueHandler = null): void
    {
        $this->_executorPID = posix_getpid();
        $this->_executor_instance_id = $this->_executorPID . ":" . gethostname() . ":" . AppConfig::$INSTANCE_ID;

        // Initialize the 'executor' logger using a specific filename from the context
        $this->logger = LoggerFactory::getLogger('executor', $_context->loggerName);
        if (AppConfig::$DEBUG) {
            $this->logger->pushHandler((new StreamHandler('php://stdout'))->setFormatter(new LineFormatter("[%datetime%] %channel%.%level_name%: %message% %context%\n")));
        }

        // Map multiple component aliases to this logger instance for consistent logging across modules
        LoggerFactory::setAliases(['engines', 'project_manager', 'feature_set', 'files'], $this->logger);

        $this->_executionContext = $_context;

        try {
            $this->_queueHandler = $_queueHandler ?? AMQHandler::getNewInstanceForDaemons();

            if (!$this->_queueHandler->getRedisClient()->sadd($this->_executionContext->pid_set_name, [$this->_executor_instance_id])) {
                throw new Exception("(Executor " . $this->_executor_instance_id . ") : FATAL !! cannot create my resource ID. Exiting!");
            } else {
                $this->logger->debug("(Executor " . $this->_executor_instance_id . ") : spawned !!!");
            }

            $this->_queueHandler->subscribe($this->_executionContext->queue_name);
        } catch (Exception $ex) {
            $msg = "****** No REDIS/AMQ instances found. Exiting. ******";
            $this->logger->debug($msg);
            $this->logger->debug($ex->getMessage());
            // Clean up PID from Redis if registration succeeded before the failure
            try {
                $this->_queueHandler->getRedisClient()->srem(
                    $this->_executionContext->pid_set_name,
                    $this->_executor_instance_id
                );
            } catch (Exception) {
                // Redis already unreachable — nothing we can do
            }
            throw $ex;
        }
    }

    /**
     * Instance loader
     *
     * @param Context $queueContext
     *
     * @return self
     * @throws Exception
     * @throws RuntimeException
     */
    public static function getInstance(Context $queueContext): self
    {
        return new self($queueContext);
    }

    /**
     * Main method
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public function main(int $sleepOnError = 2): void
    {
        $this->_frameID = 1;
        do {
            try {
                // PROCESS CONTROL FUNCTIONS
                if (!self::_myProcessExists($this->_executor_instance_id)) {
                    $this->logger->debug("(Executor " . $this->_executor_instance_id . ") :  EXITING! my pid does not exists anymore, my parent told me to die.");
                    $this->RUNNING = false;
                    break;
                }
                // PROCESS CONTROL FUNCTIONS

                //read Message frame from the queue
                $frameReadResult = $this->_readAMQFrame();
                /** @var Frame $msgFrame */
                $msgFrame = $frameReadResult[0];
                /** @var QueueElement $queueElement */
                $queueElement = $frameReadResult[1];

                if (!($msgFrame instanceof Frame) || !($queueElement instanceof QueueElement)) {
                    continue;
                }
            } catch (Exception) {
//                $this->logger->debug( "--- (Executor " . $this->_executorPID . ") : Failed to read frame from AMQ. Doing nothing, wait and re-try in the next cycle." );
//                $this->logger->debug( $e->getMessage() );
                continue;
            }

            $this->logger->debug("--- (Worker " . $this->_executor_instance_id . ") - QueueElement found", $queueElement->toArray());

            try {
                /**
                 * Don't re-instantiate an already existent object
                 */
                if ($this->_worker === null || ltrim($queueElement->classLoad, "\\") != ltrim(get_class($this->_worker), "\\")) {
                    if (!$this->isAllowedWorkerClass($queueElement->classLoad)) {
                        throw new WorkerClassException("--- (Executor " . $this->_executor_instance_id . ") : class " . $queueElement->classLoad . " is not in an allowed namespace");
                    }
                    $workerInstance = new $queueElement->classLoad($this->_queueHandler);
                    if (!$workerInstance instanceof AbstractWorker) {
                        throw new WorkerClassException("--- (Executor " . $this->_executor_instance_id . ") : class " . $queueElement->classLoad . " is not an AbstractWorker");
                    }
                    $this->_worker = $workerInstance;
                    $workerInstance->attach($this);
                    $workerInstance->setPid($this->_executor_instance_id);
                    $workerInstance->setContext($this->_executionContext);
                }

                $worker = $this->_worker;
                if ($worker === null) {
                    continue;
                }
                $worker->process($queueElement);
            } catch (EndQueueException $e) {
                $this->logger->debug("--- (Executor " . $this->_executor_instance_id . ") : End queue limit reached. Acknowledged. - " . $e->getMessage()); // ERROR End Queue

            } catch (ReQueueException $e) {
                $this->logger->debug("--- (Executor " . $this->_executor_instance_id . ") : Error executing task. Re-Queue - " . $e->getMessage()); // ERROR Re-queue

                // Ack original first to prevent duplication if crash occurs after requeue
                $this->_ackAndRequeue($msgFrame, $queueElement);
                continue;
            } catch (EmptyElementException) {
//                $this->logger->debug( $e->getMessage() );

            } catch (PDOException $e) {
                $this->logger->debug(
                    "************* (Executor " . $this->_executor_instance_id . ") Caught a Database exception. Wait 2 seconds and try next cycle *************\n************* " . $e->getMessage()
                );
                $this->logger->debug("************* (Executor " . $this->_executor_instance_id . ") " . $e->getTraceAsString());

                // Ack original first to prevent duplication if crash occurs after requeue
                $this->_ackAndRequeue($msgFrame, $queueElement);
                if ($sleepOnError > 0) {
                    sleep($sleepOnError);
                }
                continue;
            } catch (ConnectionException|ServerException $e) {
                $this->logger->debug(
                    "************* (Executor " . $this->_executor_instance_id . ") Redis connection error. Re-Queue - " . $e->getMessage()
                );

                // Ack original first to prevent duplication if crash occurs after requeue
                $this->_ackAndRequeue($msgFrame, $queueElement);
                if ($sleepOnError > 0) {
                    sleep($sleepOnError);
                }
                continue;
            } catch (Throwable $e) {
                $this->logger->debug("************* (Executor " . $this->_executor_instance_id . ") Caught a generic exception. SKIP Frame *************");
                $this->logger->debug("Exception details: " . $e->getMessage() . " " . $e->getFile() . " line " . $e->getLine() . " " . $e->getTraceAsString());
                if ($sleepOnError > 0) {
                    sleep($sleepOnError);
                }
            }

            //unlock frame
            $this->_queueHandler->ack($msgFrame);

            $this->logger->debug("--- (Executor " . $this->_executor_instance_id . ") - QueueElement acknowledged.");
        } while ($this->RUNNING);

        try {
            $this->cleanShutDown();
        } catch (Throwable) {
        }
    }

    /**
     * Read frame msg from the queue
     *
     * @return array{0: Frame, 1: QueueElement}
     * @throws FrameException
     * @throws InvalidArgumentException
     */
    protected function _readAMQFrame(): array
    {
        try {
            /** @var Frame $msgFrame */
            $msgFrame = $this->_queueHandler->read();

            if ($msgFrame instanceof Frame && ($msgFrame->getCommand() == "MESSAGE" || array_key_exists('MESSAGE', $msgFrame->getHeaders()))) {
                $this->_frameID++;
                $this->logger->debug("--- (Executor " . $this->_executor_instance_id . ") : processing frame $this->_frameID");

                $queueElement = json_decode($msgFrame->body, true);

                if (empty($queueElement)) {
                    $this->_queueHandler->ack($msgFrame);
                    $this->logger->debug(['ERROR' => "*** Failed to decode the json frame payload, reason: " . json_last_error_msg(), 'FRAME' => $msgFrame->body]);
                    throw new FrameException("*** Failed to decode the json, reason: " . json_last_error_msg(), -1);
                }

                $queueElement = new QueueElement($queueElement);

                //empty message what to do?? it should not be there, acknowledge and process the next one
                if (empty($queueElement->classLoad) || !class_exists($queueElement->classLoad)) {
                    $this->_queueHandler->ack($msgFrame);
                    throw new WorkerClassException("--- (Executor " . $this->_executor_instance_id . ") : found frame but no valid Worker Class found: wait 2 seconds");
                }
            } else {
                throw new FrameException("--- (Executor " . $this->_executor_instance_id . ") : no frame found. Starting next cycle.");
            }
        } catch (FrameException $e) {
            throw new FrameException($e->getMessage());
            /* jump the ack */
        } catch (Exception $e) {
            $this->logger->debug($e->getMessage());
            throw new FrameException("*** \$this->amqHandler->read() Failed. Continue Execution. ***", -1, $e);
        }

        return [$msgFrame, $queueElement];
    }

    /**
     * Close all opened resources
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public function cleanShutDown(): void
    {
        Database::obtain()->close();

        $this->_queueHandler->getRedisClient()->srem(
            $this->_executionContext->pid_set_name,
            $this->_executor_instance_id
        );

        $this->_queueHandler->getRedisClient()->disconnect();
        $this->_queueHandler->getClient()->disconnect();

        //SHUTDOWN
        $msg = str_pad(" Executor " . getmypid() . ":" . gethostname() . ":" . AppConfig::$INSTANCE_ID . " HALTED ", 50, "-", STR_PAD_BOTH);
        $this->logger->debug($msg);
    }

    /**
     * Check on redis Set for this process ID
     *
     * @param string $pid
     *
     * @return int
     * @throws ReflectionException
     */
    protected function _myProcessExists(string $pid): int
    {
        return $this->_queueHandler->getRedisClient()->sismember($this->_executionContext->pid_set_name, $pid);
    }

    /**
     * Update method, called by the subject when the application tells him to notify the Observer
     *
     * @param SplSubject $subject
     *
     * @throws Exception
     */
    public function update(SplSubject $subject): void
    {
        if ($subject instanceof AbstractWorker) {
            $this->logger->debug($subject->getLogMsg());
        }
    }

    /**
     * Acknowledge the original frame and re-publish the element with incremented requeue counter.
     * Acks first to prevent message duplication if a crash occurs after requeue.
     *
     * @param Frame $msgFrame The original frame to acknowledge
     * @param QueueElement $queueElement The element to re-publish
     *
     * @throws Exception
     */
    private function _ackAndRequeue(Frame $msgFrame, QueueElement $queueElement): void
    {
        $this->_queueHandler->ack($msgFrame);
        $queueElement->reQueueNum++;
        $amqHandlerPublisher = $this->_createPublisher();
        try {
            $amqHandlerPublisher->reQueue($queueElement, $this->_executionContext, $this->logger);
        } finally {
            $amqHandlerPublisher->getClient()->disconnect();
        }
        $this->logger->debug("--- (Executor " . $this->_executor_instance_id . ") - QueueElement re-queued and acknowledged.");
    }

    protected function _createPublisher(): AMQHandler
    {
        return $this->_testPublisherOverride ?? AMQHandler::getNewInstanceForDaemons();
    }

    private function isAllowedWorkerClass(string $className): bool
    {
        $normalized = ltrim($className, '\\');
        foreach (self::ALLOWED_WORKER_NAMESPACES as $namespace) {
            if (str_starts_with($normalized, $namespace)) {
                return true;
            }
        }

        return false;
    }

}

