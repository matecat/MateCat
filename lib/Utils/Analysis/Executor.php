<?php
namespace Analysis;
use Analysis\Commons\AbstractContext;
use Analysis\Commons\AbstractWorker;
use Analysis\Commons\QueueElement;
use Analysis\Exceptions\EmptyElementException;
use Analysis\Exceptions\EndQueueException;
use Analysis\Exceptions\FrameException;
use Analysis\Exceptions\ReQueueException;
use Analysis\Exceptions\WorkerClassException;
use Analysis\Queue\QueueInfo;

use Analysis\Workers\TMAnalysisWorker;
use \Exception, \Bootstrap;

$root = realpath( dirname( __FILE__ ) . '/../../../' );
include_once $root . "/inc/Bootstrap.php";
Bootstrap::start();
include \INIT::$MODEL_ROOT . "/queries.php";

class Executor {

    /**
     * @var \Analysis\AMQHandler
     */
    protected $_queueHandler;

    /**
     * @var QueueInfo
     */
    protected $_executionContext;

    /**
     * AMQ frames read
     *
     * @var int
     */
    protected $_frameID = 0;

    /**
     * Matches vector
     *
     * @var array|null
     */
    protected $_matches = null;

    /**
     * @var static
     */
    public static $__INSTANCE;
    public $RUNNING = true;
    public $_tHandlerPID;
    protected static function _TimeStampMsg( $msg ) {
//        \INIT::$DEBUG = false;
        if ( \INIT::$DEBUG ) echo "[" . date( DATE_RFC822 ) . "] " . $msg . "\n";
        \Log::doLog( $msg );
    }

    /**
     * @var AbstractWorker
     */
    protected $_worker;

    /**
     * Executor constructor.
     *
     * @param AbstractContext $_context
     */
    protected function __construct( AbstractContext $_context ) {

        $this->_tHandlerPID = posix_getpid();
        \Log::$fileName = 'Executor.log';

        $this->_executionContext = $_context;

        try {

            $this->_queueHandler = new AMQHandler();

            if ( !$this->_queueHandler->getRedisClient()->sadd( $this->_executionContext->pid_set_name, $this->_tHandlerPID ) ) {
                throw new \Exception( "(Executor {$this->_tHandlerPID}) : FATAL !! cannot create my resource ID. Exiting!" );
            } else {
                self::_TimeStampMsg( "(Executor {$this->_tHandlerPID}) : spawned !!!" );
            }

            $this->_queueHandler->subscribe( $this->_executionContext->queue_name );

        } catch ( \Exception $ex ){

            $msg = "****** No REDIS/AMQ instances found. Exiting. ******";
            static::_TimeStampMsg( $msg );
            static::_TimeStampMsg( $ex->getMessage() );
            die();

        }

    }

    /**
     * @param AbstractContext $queueContext
     *
     * @return static
     */
    public static function getInstance( AbstractContext $queueContext ) {

        if ( PHP_SAPI != 'cli' || isset ( $_SERVER [ 'HTTP_HOST' ] ) ) {
            die ( "This script can be run only in CLI Mode.\n\n" );
        }

        declare( ticks = 10 );
        set_time_limit( 0 );

        if ( !extension_loaded( "pcntl" ) && (bool)ini_get( "enable_dl" ) ) {
            dl( "pcntl.so" );
        }
        if ( !function_exists( 'pcntl_signal' ) ) {
            $msg = "****** PCNTL EXTENSION NOT LOADED. KILLING THIS PROCESS COULD CAUSE UNPREDICTABLE ERRORS ******";
            static::_TimeStampMsg( $msg );
            sleep( 3 );
        } else {
//            static::_TimeStampMsg( 'registering signal handlers' );

            pcntl_signal( SIGTERM, array( get_called_class(), 'sigSwitch' ) );
            pcntl_signal( SIGINT,  array( get_called_class(), 'sigSwitch' ) );
            pcntl_signal( SIGHUP,  array( get_called_class(), 'sigSwitch' ) );

//            $msg = str_pad( " Signal Handler Installed ", 50, "-", STR_PAD_BOTH );
//            static::_TimeStampMsg( "$msg\n" );
        }

        static::$__INSTANCE = new static( $queueContext );
        return static::$__INSTANCE;

    }

    public static function sigSwitch( $sig_no ) {

        static::_TimeStampMsg( "Trapped Signal : $sig_no" );

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

    public function main( $args = null ) {

        $this->_frameID = 1;
        do {

            //reset matches vector
            $this->_matches = null;

            try {

                // PROCESS CONTROL FUNCTIONS
                if ( !self::_myProcessExists( $this->_tHandlerPID ) ) {
                    self::_TimeStampMsg( "(Executor " . $this->_tHandlerPID . ") :  EXITING! my pid does not exists anymore, my parent told me to die." );
                    $this->RUNNING = false;
                    break;
                }
                // PROCESS CONTROL FUNCTIONS

                //read Message frame from the queue
                /**
                 * @var $msgFrame \StompFrame
                 * @var $queueElement QueueElement
                 */
                list( $msgFrame, $queueElement ) = $this->_readAMQFrame();

            } catch ( \Exception $e ) {
//                self::_TimeStampMsg( "--- (Executor " . $this->_tHandlerPID . ") : Failed to read frame from AMQ. Doing nothing, wait $secs seconds and re-try in next cycle." );
//                self::_TimeStampMsg( $e->getMessage() );
                usleep( 500000 );
                continue;

            }

            self::_TimeStampMsg( "--- (Executor " . $this->_tHandlerPID . ") - QueueElement found: " . var_export( $queueElement, true ) );

            try {
                /**
                 * Do not re-instantiate an already existent object
                 */
                if( ltrim( $queueElement->classLoad, "\\" ) != ltrim( get_class( $this->_worker ), "\\" ) ){
                    $this->_worker = new $queueElement->classLoad( $this->_queueHandler );
                }
                $this->_worker->process( $queueElement, $this->_executionContext );
            } catch ( EndQueueException $e ){
                $this->_queueHandler->ack( $msgFrame );
                continue;
            } catch ( ReQueueException $e ){
//                self::_TimeStampMsg( "--- (Executor " . $this->_tHandlerPID . ") : error retrieving Matches. Try again in the next cycle. - " . $e->getMessage() ); // ERROR FROM Memory Server
                $this->_queueHandler->ack( $msgFrame ); //ack the message try again next time. Re-queue
//
                //set/increment the reQueue number
                $queueElement->reQueueNum = ++$queueElement->reQueueNum;
                $amqHandlerPublisher          = new AMQHandler();
                $amqHandlerPublisher->reQueue( $queueElement, $this->_executionContext );
                $amqHandlerPublisher->disconnect();
                continue;
            } catch( EmptyElementException $e ){
                self::_TimeStampMsg( $e->getMessage() );
                $this->_queueHandler->ack( $msgFrame );
                continue;
            }

            //unlock segment
            $this->_queueHandler->ack( $msgFrame );

            self::_TimeStampMsg( "--- (Executor " . $this->_tHandlerPID . ") - QueueElement acknowledged." );

        } while( $this->RUNNING );

        self::cleanShutDown();

    }

    /**
     * Read frame msg from the queue
     *
     * @return array[ \StompFrame, QueueElement ]
     * @throws FrameException
     * @throws WorkerClassException
     */
    protected function _readAMQFrame() {

        /**
         * @var $msgFrame \StompFrame
         */
        $msgFrame = null;
        try {

            $msgFrame = $this->_queueHandler->readFrame();

            if ( $msgFrame instanceof \StompFrame && ( $msgFrame->command == "MESSAGE" || array_key_exists( 'MESSAGE', $msgFrame->headers /* Stomp Client bug... hack */ ) ) ) {

                $this->_frameID++;
                self::_TimeStampMsg( "--- (Executor " . $this->_tHandlerPID . ") : processing frame {$this->_frameID}" );

                $queueElement = json_decode( $msgFrame->body, true );
                $queueElement = new QueueElement( $queueElement );
                //empty message what to do?? it should not be there, acknowledge and process the next one
                if ( empty( $queueElement->classLoad ) || !class_exists( $queueElement->classLoad, true ) ) {

                    \Utils::raiseJsonExceptionError();
                    $this->_queueHandler->ack( $msgFrame );
                    sleep( 2 );
                    throw new WorkerClassException( "--- (Executor " . $this->_tHandlerPID . ") : found frame but no valid Worker Class found: wait 2 seconds" );

                }

            } else {
                throw new FrameException( "--- (Executor " . $this->_tHandlerPID . ") : no frame found. Starting next cycle." );
            }

        } catch ( \Exception $e ) {
//            self::_TimeStampMsg( $e->getMessage() );
//            self::_TimeStampMsg( $e->getTraceAsString() );
            throw new FrameException( "*** \$this->amqHandler->readFrame() Failed. Continue Execution. ***" );
            /* jump the ack */
        }

        return array( $msgFrame, $queueElement );

    }

    /**
     * Close all opened resources
     *
     */
    public static function cleanShutDown() {

        \Database::obtain()->close();
        static::$__INSTANCE->_queueHandler->getRedisClient()->disconnect();
        static::$__INSTANCE->_queueHandler->disconnect();

        //SHUTDOWN
        $msg = str_pad( " Executor " . getmypid() . " HALTED ", 50, "-", STR_PAD_BOTH );
        self::_TimeStampMsg( $msg );

        die();

    }
    
    /**
     * @param $pid
     *
     * @return int
     */
    protected function _myProcessExists( $pid ) {

        return $this->_queueHandler->getRedisClient()->sismember( $this->_executionContext->pid_set_name, $pid );

    }

}

//$argv = array();
//$argv[ 1 ] = '{"redis_key":"p1_list","queue_name":"analysis_queue_P1","pid_set_name":"ch_pid_set_p1","pid_list":[],"pid_list_len":1,"queue_length":0,"pid_set_perc_break":10}';

Executor::getInstance( QueueInfo::buildFromArray( json_decode( $argv[ 1 ], true ) ) )->main();