<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 04/05/15
 * Time: 13.37
 *
 */

namespace TaskRunner;

use AMQHandler;
use Bootstrap;
use Database;
use Exception;
use INIT;
use Log;
use PDOException;
use SplObserver;
use SplSubject;
use StompFrame;
use TaskRunner\Commons\AbstractWorker;
use TaskRunner\Commons\Context;
use TaskRunner\Commons\QueueElement;
use TaskRunner\Exceptions\EmptyElementException;
use TaskRunner\Exceptions\EndQueueException;
use TaskRunner\Exceptions\FrameException;
use TaskRunner\Exceptions\ReQueueException;
use TaskRunner\Exceptions\WorkerClassException;

include_once realpath( dirname( __FILE__ ) . '/../../../' ) . "/inc/Bootstrap.php";
Bootstrap::start();

/**
 * Class Executor
 * Process class spawned from the Task Manager
 * Every Executor is bind to it's context ( queue name, process Redis set, ecc. )
 *
 * @package TaskRunner
 */
class Executor implements SplObserver {

    /**
     * Handler of AMQ connector
     *
     * @var \AMQHandler
     */
    protected $_queueHandler;

    /**
     * Context of execution
     *
     * @var Context
     */
    protected $_executionContext;

    /**
     * AMQ frames read
     *
     * @var int
     */
    protected $_frameID = 0;

    /**
     * Static singleton instance reference
     *
     * @var static
     */
    public static $__INSTANCE;

    /**
     * Flag for control the instance running status. Setting to false cause the Executor process to stop.
     *
     * @var bool
     */
    public $RUNNING = true;

    /**
     * The process id of the Executor
     *
     * @var int
     */
    public $_executorPID;

    /**
     * @var string
     */
    public $_executor_instance_id;

    /**
     * Logging method
     *
     * @param      $_msg
     */
    protected function _logMsg( $_msg ) {
        if ( INIT::$DEBUG ) {
            echo "[" . date( DATE_RFC822 ) . "] " . json_encode( $_msg ) . "\n";
            Log::doJsonLog( $_msg );
        }
    }

    /**
     * Concrete worker
     *
     * @var AbstractWorker
     */
    protected $_worker;

    /**
     * Executor constructor.
     *
     * @param Context $_context
     */
    protected function __construct( Context $_context ) {

        $this->_executorPID          = posix_getpid();
        $this->_executor_instance_id = $this->_executorPID . ":" . gethostname() . ":" . (int)INIT::$INSTANCE_ID;

        Log::$fileName = $_context->loggerName;

        $this->_executionContext = $_context;

        try {

            $this->_queueHandler = new AMQHandler();

            if ( !$this->_queueHandler->getRedisClient()->sadd( $this->_executionContext->pid_set_name, $this->_executor_instance_id ) ) {
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
     */
    public static function getInstance( Context $queueContext ) {

        if ( PHP_SAPI != 'cli' || isset ( $_SERVER [ 'HTTP_HOST' ] ) ) {
            die ( "This script can be run only in CLI Mode.\n\n" );
        }

        declare( ticks=10 );
        set_time_limit( 0 );

        if ( !extension_loaded( "pcntl" ) && (bool)ini_get( "enable_dl" ) ) {
            dl( "pcntl.so" );
        }
        if ( !function_exists( 'pcntl_signal' ) ) {
            $msg = "****** PCNTL EXTENSION NOT LOADED. KILLING THIS PROCESS COULD CAUSE UNPREDICTABLE ERRORS ******";
        } else {

            pcntl_signal( SIGTERM, [ get_called_class(), 'sigSwitch' ] );
            pcntl_signal( SIGINT, [ get_called_class(), 'sigSwitch' ] );
            pcntl_signal( SIGHUP, [ get_called_class(), 'sigSwitch' ] );

            $msg = str_pad( " Signal Handler Installed ", 50, "-", STR_PAD_BOTH );

        }

        static::$__INSTANCE = new static( $queueContext );

//        static::$__INSTANCE->_logMsg( $msg );

        return static::$__INSTANCE;

    }

    /**
     * Posix signal handler
     *
     * @param $sig_no
     */
    public static function sigSwitch( $sig_no ) {

//        static::$__INSTANCE->_logMsg( "Trapped Signal : $sig_no" );

        switch ( $sig_no ) {
            case SIGTERM :
            case SIGINT :
            case SIGHUP :
                static::$__INSTANCE->RUNNING = false;
                break;
            default :
                break;
        }
    }

    /**
     * Main method
     *
     * @param null $args
     *
     * @throws \StompException
     */
    public function main( $args = null ) {

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
                 * @var $msgFrame     \StompFrame
                 * @var $queueElement QueueElement
                 */
                list( $msgFrame, $queueElement ) = $this->_readAMQFrame();

            } catch ( Exception $e ) {

//                $this->_logMsg( "--- (Executor " . $this->_executorPID . ") : Failed to read frame from AMQ. Doing nothing, wait and re-try in next cycle." );
//                $this->_logMsg( $e->getMessage() );
                usleep( 250000 );
                continue;

            }

//            $this->_logMsg( "--- (Worker " . $this->_executor_instance_id . ") - QueueElement found: " . var_export( $queueElement, true ) );
            $this->_logMsg( $queueElement );

            try {

                /**
                 * Do not re-instantiate an already existent object
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
                $amqHandlerPublisher      = new AMQHandler();
                $amqHandlerPublisher->reQueue( $queueElement, $this->_executionContext );
                $amqHandlerPublisher->disconnect();

            } catch ( EmptyElementException $e ) {

//                $this->_logMsg( $e->getMessage() );

            } catch ( PDOException $e ) {

                $this->_logMsg( "************* (Executor " . $this->_executor_instance_id . ") Caught a Database exception. Wait 2 seconds and try next cycle *************\n************* " . $e->getMessage() );
                $this->_logMsg( "************* (Executor " . $this->_executor_instance_id . ") " . $e->getTraceAsString() );

                $queueElement->reQueueNum = ++$queueElement->reQueueNum;
                $amqHandlerPublisher      = new AMQHandler();
                $amqHandlerPublisher->reQueue( $queueElement, $this->_executionContext );
                $amqHandlerPublisher->disconnect();
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

        self::cleanShutDown();

    }

    /**
     * Read frame msg from the queue
     *
     * @return array[ \StompFrame, QueueElement ]
     * @throws FrameException
     */
    protected function _readAMQFrame() {

        /**
         * @var $msgFrame \StompFrame
         */
        $msgFrame = null;
        try {

            $msgFrame = $this->_queueHandler->readFrame();

            if ( $msgFrame instanceof StompFrame && ( $msgFrame->command == "MESSAGE" || array_key_exists( 'MESSAGE', $msgFrame->headers /* Stomp Client bug... hack */ ) ) ) {

                $this->_frameID++;
                $this->_logMsg( "--- (Executor " . $this->_executor_instance_id . ") : processing frame {$this->_frameID}" );

                $queueElement = json_decode( $msgFrame->body, true );

                if ( empty( $queueElement ) ) {

                    $this->_queueHandler->ack( $msgFrame );
                    $msg = \Utils::raiseJsonExceptionError( false );
                    $this->_logMsg( [ 'ERROR' => "*** Failed to decode the json frame payload, reason: " . $msg, 'FRAME' => $msgFrame->body ] );
                    throw new FrameException( "*** Failed to decode the json, reason: " . $msg, -1 );

                }

                $queueElement = new QueueElement( $queueElement );

                //empty message what to do?? it should not be there, acknowledge and process the next one
                if ( empty( $queueElement->classLoad ) || !class_exists( $queueElement->classLoad, true ) ) {

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
            throw new FrameException( "*** \$this->amqHandler->readFrame() Failed. Continue Execution. ***", -1, $e );
        }

        return [ $msgFrame, $queueElement ];

    }

    /**
     * Close all opened resources
     *
     */
    public static function cleanShutDown() {

        Database::obtain()->close();
        static::$__INSTANCE->_queueHandler->getRedisClient()->disconnect();
        static::$__INSTANCE->_queueHandler->disconnect();

        //SHUTDOWN
        $msg = str_pad( " Executor " . getmypid() . ":" . gethostname() . ":" . INIT::$INSTANCE_ID . " HALTED ", 50, "-", STR_PAD_BOTH );
        static::$__INSTANCE->_logMsg( $msg );

        die();

    }

    /**
     * Check on redis Set for this process ID
     *
     * @param $pid
     *
     * @return int
     * @throws \Predis\Connection\ConnectionException
     */
    protected function _myProcessExists( $pid ) {

        return $this->_queueHandler->getRedisClient()->sismember( $this->_executionContext->pid_set_name, $pid );

    }

    /**
     * Update method, called by the subject when the application tell him to notify the Observer
     *
     * @param SplSubject $subject
     */
    public function update( SplSubject $subject ) {

        /**
         * @var $subject AbstractWorker
         */
        Log::$fileName = $subject->getLoggerName();
        $this->_logMsg( $subject->getLogMsg() );
        Log::$fileName = $this->_executionContext->loggerName;

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
//$argv[ 1 ] = '{"queue_name":"dqf","pid_set_name":"ch_pid_dqf","max_executors":"1","redis_key":"dqf_list","loggerName":"dqf.log"}';
//$argv[ 1 ] = '{"queue_length":0,"queue_name":"set_contribution_mt","pid_set_name":"ch_pid_set_contribution_mt","pid_list":[],"pid_list_len":0,"max_executors":"1","loggerName":"set_contribution_mt.log"}';
//$argv[ 1 ] = '{"queue_name":"jobs","pid_set_name":"ch_pid_jobs","max_executors":"1","redis_key":"jobs_list","loggerName":"jobs.log"}';
//$argv[ 1 ] = '{"queue_name":"qa_checks","pid_set_name":"qa_checks_set","max_executors":"1","redis_key":"qa_checks_key","loggerName":"qa_checks.log"}';
//$argv[ 1 ] = '{"queue_name":"get_contribution","pid_set_name":"ch_pid_get_contribution","max_executors":"1","redis_key":"get_contribution_list","loggerName":"get_contribution.log"}';
//$argv[ 1 ] = '{"queue_name":"aligner_align_job","pid_set_name":"ch_pid_align_job","max_executors":"1","redis_key":"align_job_list","loggerName":"align_job.log"}';
//$argv[ 1 ] = '{"queue_name":"aligner_tmx_import","pid_set_name":"ch_pid_tmx_import","max_executors":"1","redis_key":"tmx_import_list","loggerName":"tmx_import.log"}';
//$argv[ 1 ] = '{"queue_name":"aligner_segment_create","pid_set_name":"ch_pid_segment_create","max_executors":"1","redis_key":"segment_create_list","loggerName":"segment_create.log"}';


/** @var array $argv */
Executor::getInstance( Context::buildFromArray( json_decode( $argv[ 1 ], true ) ) )->main();
