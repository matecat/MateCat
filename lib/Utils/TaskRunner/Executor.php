<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 04/05/15
 * Time: 13.37
 *
 */

namespace Utils\TaskRunner;

use Bootstrap;
use Exception;
use Model\DataAccess\Database;
use PDOException;
use ReflectionException;
use SplObserver;
use SplSubject;
use Stomp\Transport\Frame;
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
use Utils\Tools\Utils;

include_once realpath( dirname( __FILE__ ) . '/../../../' ) . "/lib/Bootstrap.php";
Bootstrap::start();

/**
 * Class Executor
 * Process class spawned from the Task Manager
 * Every Executor is bind to its context (queue name, process Redis set, ecc.)
 *
 * @package TaskRunner
 */
class Executor implements SplObserver {

    use SignalHandlerTrait;

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
     * Logging method
     *
     * @param      $_msg
     */
    protected function _logMsg( $_msg ) {
        if ( AppConfig::$DEBUG ) {
            echo "[" . date( DATE_RFC822 ) . "] " . json_encode( $_msg ) . "\n";
            $this->logger->log( $_msg );
        }
    }

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

    /**
     * Executor constructor.
     *
     * @param Context $_context
     *
     * @throws Exception
     */
    protected function __construct( Context $_context ) {

        $this->_executorPID          = posix_getpid();
        $this->_executor_instance_id = $this->_executorPID . ":" . gethostname() . ":" . AppConfig::$INSTANCE_ID;

        $this->logger = LoggerFactory::getLogger( 'executor', $_context->loggerName );
        LoggerFactory::setAliases( [ 'engines' ], $this->logger );

        $this->_executionContext = $_context;

        try {

            $this->_queueHandler = AMQHandler::getNewInstanceForDaemons();

            if ( !$this->_queueHandler->getRedisClient()->sadd( $this->_executionContext->pid_set_name, [ $this->_executor_instance_id ] ) ) {
                throw new Exception( "(Executor " . $this->_executor_instance_id . ") : FATAL !! cannot create my resource ID. Exiting!" );
            } else {
                $this->_logMsg( "(Executor " . $this->_executor_instance_id . ") : spawned !!!" );
            }

            $this->_queueHandler->subscribe( $this->_executionContext->queue_name );

        } catch ( Exception $ex ) {

            $msg = "****** No REDIS/AMQ instances found. Exiting. ******";
            $this->_logMsg( $msg );
            $this->_logMsg( $ex->getMessage() );
            die();

        }

    }

    /**
     * Instance loader
     *
     * @param Context $queueContext
     *
     * @return static
     * @throws Exception
     */
    public static function getInstance( Context $queueContext ): Executor {

        $__INSTANCE = new static( $queueContext );
        $__INSTANCE->installHandler();

        return $__INSTANCE;

    }

    /**
     * Main method
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public function main() {

        $this->_frameID = 1;
        do {

            try {

                // PROCESS CONTROL FUNCTIONS
                if ( !self::_myProcessExists( $this->_executor_instance_id ) ) {
                    $this->_logMsg( "(Executor " . $this->_executor_instance_id . ") :  EXITING! my pid does not exists anymore, my parent told me to die." );
                    $this->RUNNING = false;
                    break;
                }
                // PROCESS CONTROL FUNCTIONS

                //read Message frame from the queue
                /**
                 * @var $msgFrame     Frame
                 * @var $queueElement QueueElement
                 */
                [ $msgFrame, $queueElement ] = $this->_readAMQFrame();

            } catch ( Exception $e ) {

//                $this->_logMsg( "--- (Executor " . $this->_executorPID . ") : Failed to read frame from AMQ. Doing nothing, wait and re-try in the next cycle." );
//                $this->_logMsg( $e->getMessage() );
                continue;

            }

//            $this->_logMsg( "--- (Worker " . $this->_executor_instance_id . ") - QueueElement found: " . var_export( $queueElement, true ) );
            $this->_logMsg( $queueElement );

            try {

                /**
                 * Don't re-instantiate an already existent object
                 */
                if ( $this->_worker == null || ltrim( $queueElement->classLoad, "\\" ) != ltrim( get_class( $this->_worker ), "\\" ) ) {
                    $this->_worker = new $queueElement->classLoad( $this->_queueHandler );
                    $this->_worker->attach( $this );
                    $this->_worker->setPid( $this->_executor_instance_id );
                    $this->_worker->setContext( $this->_executionContext );
                }

                $this->_worker->process( $queueElement );

            } catch ( EndQueueException $e ) {

                $this->_logMsg( "--- (Executor " . $this->_executor_instance_id . ") : End queue limit reached. Acknowledged. - " . $e->getMessage() ); // ERROR End Queue

            } catch ( ReQueueException $e ) {

                $this->_logMsg( "--- (Executor " . $this->_executor_instance_id . ") : Error executing task. Re-Queue - " . $e->getMessage() ); // ERROR Re-queue

                //set/increment the reQueue number
                $queueElement->reQueueNum = ++$queueElement->reQueueNum;
                $amqHandlerPublisher      = AMQHandler::getNewInstanceForDaemons();
                $amqHandlerPublisher->reQueue( $queueElement, $this->_executionContext );
                $amqHandlerPublisher->getClient()->disconnect();

            } catch ( EmptyElementException $e ) {

//                $this->_logMsg( $e->getMessage() );

            } catch ( PDOException $e ) {

                $this->_logMsg( "************* (Executor " . $this->_executor_instance_id . ") Caught a Database exception. Wait 2 seconds and try next cycle *************\n************* " . $e->getMessage() );
                $this->_logMsg( "************* (Executor " . $this->_executor_instance_id . ") " . $e->getTraceAsString() );

                $queueElement->reQueueNum = ++$queueElement->reQueueNum;
                $amqHandlerPublisher      = AMQHandler::getNewInstanceForDaemons();
                $amqHandlerPublisher->reQueue( $queueElement, $this->_executionContext );
                $amqHandlerPublisher->getClient()->disconnect();
                sleep( 2 );

            } catch ( Exception $e ) {

                $this->_logMsg( "************* (Executor " . $this->_executor_instance_id . ") Caught a generic exception. SKIP Frame *************" );
                $this->_logMsg( "Exception details: " . $e->getMessage() . " " . $e->getFile() . " line " . $e->getLine() );
                $this->_logMsg( $e->getTraceAsString() );

            }

            //unlock frame
            $this->_queueHandler->ack( $msgFrame );

            $this->_logMsg( "--- (Executor " . $this->_executor_instance_id . ") - QueueElement acknowledged." );

        } while ( $this->RUNNING );

        $this->cleanShutDown();

    }

    /**
     * Read frame msg from the queue
     *
     * @return array [ Frame , QueueElement ]
     * @throws FrameException
     */
    protected function _readAMQFrame(): array {

        /**
         * @var $msgFrame Frame
         */
        try {

            $msgFrame = $this->_queueHandler->read();

            if ( $msgFrame instanceof Frame && ( $msgFrame->getCommand() == "MESSAGE" || array_key_exists( 'MESSAGE', $msgFrame->getHeaders() ) ) ) {

                $this->_frameID++;
                $this->_logMsg( "--- (Executor " . $this->_executor_instance_id . ") : processing frame $this->_frameID" );

                $queueElement = json_decode( $msgFrame->body, true );

                if ( empty( $queueElement ) ) {

                    $this->_queueHandler->ack( $msgFrame );
                    $msg = Utils::raiseJsonExceptionError( false );
                    $this->_logMsg( [ 'ERROR' => "*** Failed to decode the json frame payload, reason: " . $msg, 'FRAME' => $msgFrame->body ] );
                    throw new FrameException( "*** Failed to decode the json, reason: " . $msg, -1 );

                }

                $queueElement = new QueueElement( $queueElement );

                //empty message what to do?? it should not be there, acknowledge and process the next one
                if ( empty( $queueElement->classLoad ) || !class_exists( $queueElement->classLoad ) ) {

                    $this->_queueHandler->ack( $msgFrame );
                    throw new WorkerClassException( "--- (Executor " . $this->_executor_instance_id . ") : found frame but no valid Worker Class found: wait 2 seconds" );

                }

            } else {
                throw new FrameException( "--- (Executor " . $this->_executor_instance_id . ") : no frame found. Starting next cycle." );
            }

        } catch ( FrameException $e ) {
            throw new FrameException( $e->getMessage() );
            /* jump the ack */
        } catch ( Exception $e ) {
            $this->_logMsg( $e->getMessage() );
            throw new FrameException( "*** \$this->amqHandler->read() Failed. Continue Execution. ***", -1, $e );
        }

        return [ $msgFrame, $queueElement ];

    }

    /**
     * Close all opened resources
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public function cleanShutDown() {

        Database::obtain()->close();

        $this->_queueHandler->getRedisClient()->srem(
                $this->_executionContext->pid_set_name,
                $this->_executor_instance_id
        );

        $this->_queueHandler->getRedisClient()->disconnect();
        $this->_queueHandler->getClient()->disconnect();

        //SHUTDOWN
        $msg = str_pad( " Executor " . getmypid() . ":" . gethostname() . ":" . AppConfig::$INSTANCE_ID . " HALTED ", 50, "-", STR_PAD_BOTH );
        $this->_logMsg( $msg );

        die();

    }

    /**
     * Check on redis Set for this process ID
     *
     * @param $pid
     *
     * @return int
     * @throws ReflectionException
     */
    protected function _myProcessExists( $pid ): int {

        return $this->_queueHandler->getRedisClient()->sismember( $this->_executionContext->pid_set_name, $pid );

    }

    /**
     * Update method, called by the subject when the application tells him to notify the Observer
     *
     * @param SplSubject $subject
     *
     * @throws Exception
     */
    public function update( SplSubject $subject ) {
        /**
         * @var $subject AbstractWorker
         */
        $this->_logMsg( $subject->getLogMsg() );
    }

    public function forceAck( SplSubject $subject ) {
        //TODO
    }

}

//$argv = array();
//$argv[ 1 ] = '{"queue_length":1,"queue_name":"mail_queue","pid_set_name":"ch_pid_set_mail","pid_list":[],"pid_list_len":0,"max_executors":"1","loggerName":"mail_queue.log"}';
//$argv[ 1 ] = '{"queue_length":0,"queue_name":"set_contribution","pid_set_name":"ch_pid_set_contribution","pid_list":[],"pid_list_len":0,"max_executors":"1","loggerName":"set_contribution.log"}';
//$argv[ 1 ] = '{"queue_name":"analysis_queue_P3","pid_set_name":"ch_pid_set_p3","max_executors":"1","redis_key":"p3_list","loggerName":"tm_analysis_P3.log"}';
//$argv[ 1 ] = '{"queue_name":"analysis_queue_P1","pid_set_name":"ch_pid_set_p1","max_executors":"1","redis_key":"p1_list","loggerName":"tm_analysis_P1.log"}';
//$argv[ 1 ] = '{"queue_name":"activity_log","pid_set_name":"ch_pid_activity_log","max_executors":"1","redis_key":"activity_log_list","loggerName":"activity_log.log"}';
//$argv[ 1 ] = '{"queue_name":"project_queue","pid_set_name":"ch_pid_project_queue","max_executors":"1","redis_key":"project_queue_list","loggerName":"project_queue.log"}';
//$argv[ 1 ] = '{"queue_name":"ai_assistant_explain_meaning","pid_set_name":"ch_pid_ai_assistant_explain_meaning","max_executors":"1","redis_key":"ai_assistant_explain_meaning","loggerName":"ai_assistant_explain_meaning.log"}';
//$argv[ 1 ] = '{"queue_length":0,"queue_name":"set_contribution_mt","pid_set_name":"ch_pid_set_contribution_mt","pid_list":[],"pid_list_len":0,"max_executors":"1","loggerName":"set_contribution_mt.log"}';
//$argv[ 1 ] = '{"queue_name":"jobs","pid_set_name":"ch_pid_jobs","max_executors":"1","redis_key":"jobs_list","loggerName":"jobs.log"}';
//$argv[ 1 ] = '{"queue_name":"qa_checks","pid_set_name":"qa_checks_set","max_executors":"1","redis_key":"qa_checks_key","loggerName":"qa_checks.log"}';
//$argv[ 1 ] = '{"queue_name":"get_contribution","pid_set_name":"ch_pid_get_contribution","max_executors":"1","redis_key":"get_contribution_list","loggerName":"get_contribution.log"}';
//$argv[ 1 ] = '{"queue_name":"aligner_align_job","pid_set_name":"ch_pid_align_job","max_executors":"1","redis_key":"align_job_list","loggerName":"align_job.log"}';
//$argv[ 1 ] = '{"queue_name":"aligner_tmx_import","pid_set_name":"ch_pid_tmx_import","max_executors":"1","redis_key":"tmx_import_list","loggerName":"tmx_import.log"}';
//$argv[ 1 ] = '{"queue_name":"aligner_segment_create","pid_set_name":"ch_pid_segment_create","max_executors":"1","redis_key":"segment_create_list","loggerName":"segment_create.log"}';


/** @var array $argv */
Executor::getInstance( Context::buildFromArray( json_decode( $argv[ 1 ], true ) ) )->main();
