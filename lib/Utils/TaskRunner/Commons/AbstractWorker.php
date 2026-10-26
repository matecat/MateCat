<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 23/12/15
 * Time: 17.36
 *
 */

namespace Utils\TaskRunner\Commons;

use Exception;
use Model\DataAccess\IDatabase;
use PDOException;
use Stomp\Transport\Message;
use Utils\ActiveMQ\AMQHandler;
use Utils\Logger\LoggerFactory;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Exceptions\EmptyElementException;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;

/**
 * Class AbstractWorker
 * @package TaskRunner\Commons
 */
abstract class AbstractWorker
{

    const int ERR_REQUEUE_END = 1;
    const int ERR_REQUEUE = 2;
    const int ERR_EMPTY_ELEMENT = 3;

    /**
     * Where the worker logs. The Executor passes its queue logger, so the worker's lines land in
     * the queue's log file. A worker built without one (a test) logs to general_log.txt.
     */
    protected MatecatLogger $logger;

    /**
     * This process ID
     *
     * @var string
     */
    protected string $_workerPid = '0';

    /**
     * The context object.
     * It stores the configuration for the worker
     *
     * @var Context
     */
    protected Context $_myContext;

    /**
     * @var AMQHandler
     */
    protected AMQHandler $_queueHandler;

    /**
     * The per-process database handle. One instance per worker process.
     * Defaults to the process-local Database singleton; injectable for tests.
     *
     * @var IDatabase
     */
    protected IDatabase $database;

    /**
     * Number of times the worker must be retried in case of error
     *
     * @var int
     */
    protected int $maxRequeueNum = 100;

    /**
     * AbstractWorker constructor.
     *
     * @param AMQHandler         $queueHandler
     * @param IDatabase          $database Per-process DB handle, supplied by the composition root (Executor/daemon).
     * @param MatecatLogger|null $logger   The queue logger, supplied by the Executor.
     *
     * A subclass that overrides the constructor must accept `?MatecatLogger $logger = null` and pass it on:
     * the Executor builds every worker with the named argument `logger:`, and a constructor without it
     * fails with "Unknown named parameter $logger".
     */
    public function __construct(AMQHandler $queueHandler, IDatabase $database, ?MatecatLogger $logger = null)
    {
        $this->_queueHandler = $queueHandler;
        $this->database = $database;
        $this->logger = $logger ?? LoggerFactory::getLogger();
    }

    /**
     * @return IDatabase the per-process database handle
     */
    public function getDatabaseHandler(): IDatabase
    {
        return $this->database;
    }

    /**
     * Set the caller pid. Needed to log the process ID.
     *
     * @param string $_myPid
     */
    public function setPid(string $_myPid): void
    {
        $this->_workerPid = $_myPid;
    }

    /**
     * @param Context $context
     */
    public function setContext(Context $context): void
    {
        $this->_myContext = $context;
    }

    /**
     * @return Context
     */
    public function getContext(): Context
    {
        return $this->_myContext;
    }

    /**
     * Execution method
     *
     * @param $queueElement AbstractElement
     *
     * @return void
     * @throws EndQueueException
     * @throws ReQueueException
     * @throws EmptyElementException
     */
    abstract public function process(AbstractElement $queueElement): void;

    /**
     * Kept only for the aligner plugin's workers, which still call it. New code calls the logger
     * directly, at the level the line needs: $this->logger->error('segment update failed').
     *
     * @deprecated use $this->logger
     *
     * @param array<mixed>|string $msg
     */
    protected function _doLog(array|string $msg): void
    {
        $this->logger->debug($msg);
    }

    /**
     * Check how many times the element was re-queued and raise an Exception when the limit is reached (100 times)
     *
     * @param QueueElement $queueElement
     *
     * @return void
     * @throws EndQueueException
     */
    protected function _checkForReQueueEnd(QueueElement $queueElement): void
    {
        if ($queueElement->reQueueNum >= $this->maxRequeueNum) {
            $this->logger->debug("--- (Worker " . $this->_workerPid . ") : Frame Re-queue max value reached, acknowledge and skip.");
            $this->_endQueueCallback($queueElement);
        }
    }

    /**
     * @param QueueElement $queueElement
     *
     * @throws EndQueueException
     */
    protected function _endQueueCallback(QueueElement $queueElement): void
    {
        throw new EndQueueException("--- (Worker " . $this->_workerPid . ") :  Frame Re-queue max value reached, acknowledge and skip.", self::ERR_REQUEUE_END);
    }

    /**
     * Check the connection.
     * MySql timeout closes the socket and throws Exception in the nex read/write access
     *
     * <code>
     * By default, the server closes the connection after eight hours if nothing has happened.
     * You can change the time limit by setting the wait_timeout variable when you start mysql.
     * @see http://dev.mysql.com/doc/refman/5.0/en/gone-away.html
     * </code>
     *
     * @throws PDOException
     */
    protected function _checkDatabaseConnection(): void
    {
        $db = $this->database;
        try {
            $db->ping();
        } catch (PDOException $e) {
            $this->logger->debug("--- (Worker " . $this->_workerPid . ") : {$e->getMessage()} ");
            $this->logger->debug("--- (Worker " . $this->_workerPid . ") : Database connection reloaded. ");
            $db->close();
            //reconnect
            $db->getConnection();
        }
    }

    /**
     * @param mixed $_object
     *
     * @throws Exception
     */
    protected function publishToNodeJsClients(mixed $_object): void
    {
        $message = json_encode($_object);
        if ($message === false) {
            $message = '';
        }
        $this->_getAmqHandlerForPublish()->publishToNodeJsClients(AppConfig::$SOCKET_NOTIFICATIONS_QUEUE_NAME, new Message($message, ['persistent' => 'false']));
        $this->logger->debug($message);
    }

    /**
     * Returns an AMQHandler instance for publishing to NodeJs clients.
     * Override in tests to inject a stub.
     *
     * @throws Exception
     */
    protected function _getAmqHandlerForPublish(): AMQHandler
    {
        return AMQHandler::getNewInstanceForDaemons();
    }

}