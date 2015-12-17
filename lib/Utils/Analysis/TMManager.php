<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 16/05/14
 * Time: 17.01
 * 
 */

namespace Analysis;
use Analysis\Commons\AbstractDaemon, 
    Analysis\Commons\RedisKeys,
    Analysis\Queue\Info,
    Analysis\Queue\QueuesList;

use \INIT, \Log, \Exception, \Bootstrap;

$root = realpath( dirname( __FILE__ ) . '/../../../' );
include_once $root . "/inc/Bootstrap.php";
Bootstrap::start();

/**
 * Class Analysis_Manager
 *
 * Should be the final class when daemons will refactored
 *
 */
class TMManager extends AbstractDaemon {

    /**
     * @var \Analysis\QueueHandler
     */
    protected $_queueHandler;

    protected $NUM_WORKERS_FILE;
    protected $DEFAULT_NUM_WORKERS;

    protected $_numProcessesMax = 0;
    protected $_runningPids     = 0;
    protected $_queueObjectList = array();

    const LOG_FILENAME = 'tm_analysis.log';

    const ERR_NOT_FORK          = 1;
    const ERR_SET_NOT_AVAILABLE = 2;
    const ERR_PID_NOT_PUBLISHED = 3;
    const ERR_NOT_ROUTE         = 4;

    protected function __construct( $logger = null ) {

        Log::$fileName = self::LOG_FILENAME;
        parent::__construct();

        try {

            $this->NUM_WORKERS_FILE = INIT::$ROOT . "/lib/Utils/Analysis/.num_processes";
            $this->DEFAULT_NUM_WORKERS = require( 'Commons/DefaultNumTMWorkers.php' );

            set_time_limit(0);

            $this->_queueHandler = new QueueHandler();
            $this->_queueObjectList = $this->_queueHandler->getQueues();


        } catch ( Exception $ex ){

            $msg = "****** No REDIS/AMQ instances found. Exiting. ******";
            self::_TimeStampMsg( $msg );
            self::_TimeStampMsg( $ex->getMessage() );
            die();
        }

    }

    public function main( $args = null ) {

        /*
         * TODO improve, put on redis not only the pid, but the ip also to make unique
         * Kill all other managers. "There can be only one."
         */
        if ( $this->_queueHandler->getRedisClient()->get( RedisKeys::VOLUME_ANALYSIS_PID ) ){
            //kill all it's children
            $this->_killPids();
        }

        //register My Pid ( and also overwrite the old one )
        $this->_queueHandler->getRedisClient()->set( RedisKeys::VOLUME_ANALYSIS_PID, getmypid() );

        // BEGIN
        do {

            try {

                if( !$this->_queueHandler->getRedisClient()->get( RedisKeys::VOLUME_ANALYSIS_PID ) ) {
                    self::_TimeStampMsg( "(parent " . self::$tHandlerPID . " }) : ERROR OCCURRED, MY PID DISAPPEARED FROM REDIS:  PARENT EXITING !!" );
                    self::cleanShutDown();
                    die();
                }

            } catch ( Exception $e ){
                self::_TimeStampMsg( "(child " . self::$tHandlerPID .  ") : FATAL !! Redis Server not available. Re-instantiated the connection and re-try in next cycle" );
                self::_TimeStampMsg( "(child " . self::$tHandlerPID .  ") : FATAL !! " . $e->getMessage() );
                sleep(1);
                continue;
            }

            //avoid zombies : the parent is aware of the death of one of the children
            $dead = pcntl_waitpid( -1, $status, WNOHANG | WUNTRACED );
            if ( $dead > 0 ) {
                self::_TimeStampMsg( "(parent " . self::$tHandlerPID .  ") : child $dead exited: deleting file ...." );
                $this->_killPids( null, $dead );
                self::_TimeStampMsg( "DONE" );
            }

            $this->_numProcessesMax = $this->_getNumProcessesMax();

            $numProcessesDiff = $this->_runningPids - $this->_numProcessesMax;

            $numProcessesToLaunchOrDelete = abs( $numProcessesDiff );

            switch ( true ) {

                case $numProcessesDiff < 0:

                    try {

                        $this->_forkProcesses( $numProcessesToLaunchOrDelete );

                    } catch ( \Exception $e ){
                        self::_TimeStampMsg( "Exception {$e->getCode()}: " . $e->getMessage() );
                        if( $e->getCode() != self::ERR_NOT_ROUTE ) {
                            $this->RUNNING = false;
                        }
                    }

                    break;

                case $numProcessesDiff > 0:

                    self::_TimeStampMsg( "(parent " . self::$tHandlerPID .  ") : need to delete $numProcessesToLaunchOrDelete processes" );
                    $this->_killPids( "", $numProcessesToLaunchOrDelete );
                    sleep(1);

                    break;

                default:

                    self::_TimeStampMsg( "(parent " . self::$tHandlerPID .  ") : no pid to delete everithing  works well" );
                    self::_TimeStampMsg( "(parent) : PARENT MONITORING PAUSE (" . self::$tHandlerPID .  ") sleeping ...." );
                    self::_balanceQueues( $this->_queueObjectList );
                    sleep( 5 );

                    break;
            }

        } while( $this->RUNNING );

        self::cleanShutDown();

    }


    protected function _routeThisChildProcess( $myPid, QueuesList $queueList ){

//        $totalElements = 0;
        $numProcessesActual = 0;
        $queueObject = $queueList->list[ 0 ]; //DEFAULT
        try {
            foreach ( $queueList->list as $queueObject ) {

                $queueObject->pid_list = $this->_queueHandler->getRedisClient()->smembers( $queueObject->pid_set_name );

                //TODO Use this!
                $numProcessesActual += count( $queueObject->pid_list );

//                $totalElements += $queueObject->queue_length =
//                        $this->_queueHandler->getQueueLength( $queueObject->queue_name );

                $maxElementsPerQueue = $this->_numProcessesMax / 100 * $queueObject->pid_set_perc_break;
                if ( count( $queueObject->pid_list ) <= $maxElementsPerQueue ) {
                    return $queueObject; //use this queue
                }

            }
        } catch ( Exception $e ) {
            throw new \Exception( "(child " . getmypid() . ") : FATAL !! " . $e->getMessage(), static::ERR_SET_NOT_AVAILABLE );
        }

        return $queueObject;

    }

    protected function _balanceQueues( QueuesList $queueList ){
//        self::_TimeStampMsg( "TODO. Now i do nothing." );
//        $this->RUNNING = false;
    }

    /**
     * Launch a single process over a queue and register it's pid in the right processes queue
     *
     * @param int $numProcesses
     *
     * @return int|null
     *
     * @throws \Exception
     */
    protected function _forkProcesses( $numProcesses ) {

        $numProcesses    = ( empty( $numProcesses ) ? 1 : $numProcesses );
        $processLaunched = 0;

        while ( $processLaunched < $numProcesses ) {

            try {
                $queueObject = $this->_routeThisChildProcess( getmypid(), $this->_queueObjectList );
            } catch ( \Exception $e ){
                throw new \Exception( "(parent " . getmypid() . ") ERROR: " . $e->getMessage() . " ... EXITING.", static::ERR_NOT_ROUTE );
            }

            self::_TimeStampMsg( "Spawn process ....." );
            $pid = pcntl_fork();

            if ( $pid == -1 ) {

                throw new \Exception( "(parent " . self::$tHandlerPID . ") : ERROR OCCURRED : cannot fork. PARENT EXITING !!", static::ERR_NOT_FORK );

            } elseif ( $pid ) {

                // parent process continue running
                $processLaunched += 1;
                $this->_runningPids += 1;
                usleep( 200000 );

            } else {

                // child process runs from here
                pcntl_exec( "/usr/bin/php", array( "TMThread.php" , json_encode( $queueObject ) ) );
                posix_kill( posix_getpid(), SIGINT );
                exit;

            }

        }

        //NO Error. Parent returning after $numProcesses process spawned
        return 0;

    }

    public static function cleanShutDown() {

        //SHUTDOWN
        static::$__INSTANCE->_killPids();
        static::$__INSTANCE->_queueHandler->getRedisClient()->del( RedisKeys::VOLUME_ANALYSIS_PID );
        $msg = str_pad( " TM ANALYSIS " . getmypid() . " HALTED ", 50, "-", STR_PAD_BOTH );
        self::_TimeStampMsg( $msg );

        static::$__INSTANCE->_queueHandler->getRedisClient()->disconnect();

    }

    /**
     * Process deletion/killing
     *
     * <ul>
     *     <li>Kill a specific Process ID from a specific Queue when $pid and $queueInfo are passed</li>
     *     <li>Kill a specific Process ID un unknown Queue when only $pid is passed</li>
     *     <li>Kill a certain number of processes from a queue when $num and $queueInfo are passed</li>
     *     <li>Kill all processes from a queue when only the $queueInfo is passed</li>
     *     <li>Kill a number of elements equally from all queues when only $num is passed</li>
     *     <li>Kill ALL processes when no parameters are sent</li>
     * </ul>
     *
     * @param Info $queueInfo
     * @param int  $pid
     * @param int  $num
     */
    protected function _killPids( Info $queueInfo = null, $pid = 0, $num = 0 ) {

        self::_TimeStampMsg( "Request to kill some processes." );
        self::_TimeStampMsg( "Pid List: " . @var_export( $queueInfo->pid_set_name, true ) );
        self::_TimeStampMsg( "Pid:      " . @var_export( $pid, true ) );
        self::_TimeStampMsg( "Num:      " . @var_export( $num, true ) );

        $x = debug_backtrace();

        self::_TimeStampMsg( $x );

        $numDeleted = 0;

        if ( !empty( $pid ) && !empty( $queueInfo ) ) {

            self::_TimeStampMsg( "Killing pid $pid from " . $queueInfo->pid_set_name );
            $numDeleted += $this->_queueHandler->getRedisClient()->srem( $queueInfo->pid_set_name, $pid );

        } elseif ( !empty( $pid ) && empty( $queueInfo ) ) {

            self::_TimeStampMsg( "Killing pid $pid from a not defined queue. Seek and destroy." );
            $queuesInfo = QueuesList::get();
            /**
             * @var $queue Info
             */
            foreach ( $queuesInfo->list as $queue ) {
                $numDeleted += $this->_queueHandler->getRedisClient()->srem( $queue->pid_set_name, $pid );
            }

        } elseif ( !empty( $num ) && !empty( $queueInfo ) ) {

            self::_TimeStampMsg( "Killing $num pid from " . $queueInfo->pid_set_name );
            $queueBefore = $this->_queueHandler->getRedisClient()->scard( $queueInfo->pid_set_name );
            for( $i = 0; $i < $num; $i++ ){
                $this->_queueHandler->getRedisClient()->spop( $queueInfo->pid_set_name );
            }
            $queueAfter = $this->_queueHandler->getRedisClient()->scard( $queueInfo->pid_set_name );
            $numDeleted = $queueBefore - $queueAfter;

        } elseif ( !empty( $queueInfo ) ) {

            self::_TimeStampMsg( "Killing all processes from " . $queueInfo->pid_set_name );
            $numDeleted = $this->_queueHandler->getRedisClient()->scard( $queueInfo->pid_set_name );
            $this->_queueHandler->getRedisClient()->del( $queueInfo->pid_set_name );

        } elseif ( !empty( $num ) ) {

            self::_TimeStampMsg( "Killing $num processes balancing all queues." );
            $queuesInfo = QueuesList::get();

            while ( true ) {

                // if all queues are empty or they have less elements than requested $num
                //we do not want an infinite loop
                //so, at least one deletion per cycle
                $deleted = false;
                foreach ( $queuesInfo->list as $queue ) {

                    //Exit, we reached the right number, exit the while loop
                    if( $numDeleted >= $num ){
                        break 2; //exit the while loop
                    }

                    $_deleted = $this->_queueHandler->getRedisClient()->spop( $queue->pid_set_name );

                    //we do not want an infinite loop
                    //so, at least one deletion per cycle
                    $deleted = $_deleted || $deleted;

                    $numDeleted += (int)(bool)$_deleted;

                }

                //no more processes to kill!! Avoid infinite loop
                if ( !$deleted ) {
                    break;
                }

            }

        } elseif ( empty( $queueInfo ) && empty( $pid ) && empty( $num ) ) {

            self::_TimeStampMsg( "Killing ALL processes." );
            foreach ( $this->_queueObjectList->list as $queue ) {
                $numDeleted += $this->_queueHandler->getRedisClient()->scard( $queue->pid_set_name );
                $this->_queueHandler->getRedisClient()->del( $queue->pid_set_name );
            }

        } else {
            self::_TimeStampMsg( "Parameters not valid. Killing *** NONE ***" );
        }

        $this->_runningPids -= $numDeleted;

        self::_TimeStampMsg( "Deleted $numDeleted processes." );

    }

    protected function _getNumProcessesMax() {

        // legge quanti processi lanciare
        $num_processes = $this->DEFAULT_NUM_WORKERS;
        if ( file_exists( $this->NUM_WORKERS_FILE ) ) {
            $num_processes = intval( file_get_contents( $this->NUM_WORKERS_FILE ) );
        }

        if ( !is_int( $num_processes ) ) {
            self::_TimeStampMsg( "WARNING : num processes from file is not numeric. Back to default value NUM_PROCESSES = 1" );
            $num_processes = 1;
        }

        return $num_processes;
    }

}

TMManager::getInstance()->main();
