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
use Model\DataAccess\Database;
use PDOException;
use SplObserver;
use SplSubject;
use Stomp\Transport\Message;
use Utils\ActiveMQ\AMQHandler;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Exceptions\EmptyElementException;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;

/**
 * Class AbstractWorker
 * @package TaskRunner\Commons
 */
abstract class AbstractWorker implements SplSubject
{

    const int ERR_REQUEUE_END   = 1;
    const int ERR_REQUEUE       = 2;
    const int ERR_EMPTY_ELEMENT = 3;

    /**
     * Observer container
     *
     * @var SplObserver[]
     */
    protected array $_observer;

    /**
     * The last log message
     *
     * @var string|array
     */
    protected string|array $_logMsg;

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
     * Number of times the worker must be retried in case of error
     *
     * @var int
     */
    protected int $maxRequeueNum = 100;

    /**
     * TMAnalysisWorker constructor.
     *
     * @param AMQHandler $queueHandler
     */
    public function __construct(AMQHandler $queueHandler)
    {
        $this->_queueHandler = $queueHandler;
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
     * Override this method in the concrete worker if you need the worker to log in a file
     * different from the one in context object
     *
     * @return string
     */
    public function getLoggerName(): string
    {
        return $this->_myContext->loggerName;
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
     * Attach an SplObserver
     * @link  http://php.net/manual/en/splsubject.attach.php
     *
     * @param SplObserver $observer <p>
     *                              The <b>SplObserver</b> to attach.
     *                              </p>
     *
     * @return void
     * @since 5.1.0
     */
    public function attach(SplObserver $observer): void
    {
        $this->_observer[ spl_object_hash($observer) ] = $observer;
    }

    /**
     * Detach an observer
     * @link  http://php.net/manual/en/splsubject.detach.php
     *
     * @param SplObserver $observer <p>
     *                              The <b>SplObserver</b> to detach.
     *                              </p>
     *
     * @return void
     * @since 5.1.0
     */
    public function detach(SplObserver $observer): void
    {
        unset($this->_observer[ spl_object_hash($observer) ]);
    }

    /**
     * Notify an observer
     * @link  http://php.net/manual/en/splsubject.notify.php
     * @return void
     * @since 5.1.0
     */
    public function notify(): void
    {
        foreach ($this->_observer as $observer) {
            $observer->update($this);
        }
    }

    /**
     * Method used by the Observer to get the logging message
     * @return string|array
     */
    public function getLogMsg(): array|string
    {
        return $this->_logMsg;
    }

    /**
     * Method to be called when a concrete worker must log
     *
     * @param $msg array|string
     */
    protected function _doLog(array|string $msg): void
    {
        $this->_logMsg = $msg;
        $this->notify();
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
        if (isset($queueElement->reQueueNum) && $queueElement->reQueueNum >= $this->maxRequeueNum) {
            $this->_doLog("--- (Worker " . $this->_workerPid . ") : Frame Re-queue max value reached, acknowledge and skip.");
            $this->_endQueueCallback($queueElement);
        } elseif (isset($queueElement->reQueueNum)) {
//            $this->_doLog( "--- (Worker " . $this->_workerPid . ") :  Frame re-queued {$queueElement->reQueueNum} times." );
        }
    }

    /**
     * @param QueueElement $queueElement
     *
     * @throws EndQueueException
     */
    protected function _endQueueCallback(QueueElement $queueElement)
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
     */
    protected function _checkDatabaseConnection(): void
    {
        $db = Database::obtain();
        try {
            $db->ping();
        } catch (PDOException $e) {
            $this->_doLog("--- (Worker " . $this->_workerPid . ") : {$e->getMessage()} ");
            $this->_doLog("--- (Worker " . $this->_workerPid . ") : Database connection reloaded. ");
            $db->close();
            //reconnect
            $db->getConnection();
        }
    }

    /**
     * @param $_object
     *
     * @throws Exception
     */
    protected function publishToNodeJsClients($_object): void
    {
        $message = json_encode($_object);
        AMQHandler::getNewInstanceForDaemons()->publishToNodeJsClients(AppConfig::$SOCKET_NOTIFICATIONS_QUEUE_NAME, new Message($message, ['persistent' => 'false']));
        $this->_doLog($message);
    }

}