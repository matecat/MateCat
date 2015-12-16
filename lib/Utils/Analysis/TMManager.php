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
    protected static $_queueHandler;

    protected $NUM_WORKERS_FILE;
    protected $DEFAULT_NUM_WORKERS;

    const LOG_FILENAME= 'tm_analysis.log';

    protected function __construct( $logger = null ) {

        Log::$fileName = self::LOG_FILENAME;
        parent::__construct();

        try {

            $this->NUM_WORKERS_FILE = INIT::$ROOT . "/lib/Utils/Analysis/.num_processes";
            $this->DEFAULT_NUM_WORKERS = require( 'Commons/DefaultNumTMWorkers.php' );

            set_time_limit(0);

            self::$_queueHandler = new QueueHandler();

        } catch ( Exception $ex ){

            $msg = "****** No REDIS/AMQ instances found. Exiting. ******";
            self::_TimeStampMsg( $msg );
            self::_TimeStampMsg( $ex->getMessage() );
            die();
        }

    }

    public function main( $args = null ) {

        /*
         * Kill all other managers. "There can be only one."
         */
        if ( self::$_queueHandler->getRedisClient()->get( RedisKeys::VOLUME_ANALYSIS_PID ) ){
            //kill all it's children
            self::_killPids();
        }

        //register My Pid ( and also overwrite the old one )
        self::$_queueHandler->getRedisClient()->set( RedisKeys::VOLUME_ANALYSIS_PID, getmypid() );

        // BEGIN
        do {

            try {

                if( !self::$_queueHandler->getRedisClient()->get( RedisKeys::VOLUME_ANALYSIS_PID ) ) {
                    self::cleanShutDown();
                    self::_TimeStampMsg( "(parent " . self::$tHandlerPID . " }) : ERROR OCCURRED, MY PID DISAPPEARED FROM REDIS:  PARENT EXITING !!" );
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
                self::_killPids( $dead );
                self::_TimeStampMsg( "DONE" );
            }

            $numProcessesMax = $this->_getNumProcesses();
            $numProcessesActual = 0;
            try {
                $queueObjectsList = self::$_queueHandler->getQueues();
                foreach( $queueObjectsList->list as $queueObject ){
                    $queueObject->pid_list = self::$_queueHandler->getRedisClient()->lrange( $queueObject->pid_list_name, 0, -1 );
                    $numProcessesActual += count( $queueObject->pid_list );
                }
            } catch ( Exception $e ){
                self::_TimeStampMsg( "(child " . self::$tHandlerPID .  ") : FATAL !! Redis Server not available. Re-instantiated the connection and re-try in next cycle" );
                self::_TimeStampMsg( "(child " . self::$tHandlerPID .  ") : FATAL !! " . $e->getMessage() );
                sleep(1);
                continue;
            }

            $numProcessesDiff = $numProcessesActual - $numProcessesMax;

            $numProcessesToLaunchOrDelete = abs( $numProcessesDiff );

            switch ( $numProcessesDiff ) {

                case $numProcessesDiff < 0 && $numProcessesActual == 0:
                    self::_warmUp( $queueObjectsList );
                    break;
                case $numProcessesDiff < 0:

                    $res = self::_launchProcesses( $numProcessesToLaunchOrDelete );
                    if ( $res < 0 ) {
                        self::cleanShutDown();
                        self::_TimeStampMsg( "(parent " . self::$tHandlerPID .  ") : ERROR OCCURRED :  PARENT EXITING !!" );
                        die();
                    }
                    break;
                case $numProcessesDiff > 0:
                    self::_TimeStampMsg( "(parent " . self::$tHandlerPID .  ") : need to delete $numProcessesToLaunchOrDelete processes" );
                    self::_killPids( "", $numProcessesToLaunchOrDelete );
                    sleep(1);
                    break;
                default:
                    self::_TimeStampMsg( "(parent " . self::$tHandlerPID .  ") : no pid to delete everithing  works well" );
                    self::_TimeStampMsg( "(parent) : PARENT MONITORING PAUSE (" . self::$tHandlerPID .  ") sleeping ...." );
                    sleep( 5 );
            }

        } while( self::$RUNNING );

    }

    protected static function _warmUp( QueuesList $queueList ){

        /*
         * 	4 se tutte le code sono vuote metto il 20% sulla coda di priorità massima e non ne spawno altri ( solo per fast warmup così non spreco risorse )
            5 se ci sono elementi nella coda di priorità più alta spwano il 100% su quella coda
            6 se la coda di priorità è vuota procedo sulla seconda
                7 se ci sono elementi spawna il 100% su questa coda
                8 se la seconda è vuota procedo sulla terza
                    9 se ci sono elementi spawna il 100% su questa coda
                    10 se la seconda è vuota procedo sulla terza
                        11 proseguo ricorsivamente se ci sono altre code
         */

        print_r( $queueList );
        die();
    }

    /**
     * Launch a single process over a queue and register it's pid in the right processes queue
     *
     * @param int  $numProcesses
     * @param Info $queueInfo
     *
     * @return int
     */
    protected function _launchProcesses( $numProcesses, Info $queueInfo ) {

        $numProcesses = ( empty( $numProcesses ) ? 1 : $numProcesses );
        $processLaunched = 0;

        while ( $processLaunched < $numProcesses ) {
           self:: _TimeStampMsg( "launching ....." );
            $pid = pcntl_fork();

            if ( $pid == -1 ) {
                self::_TimeStampMsg( "PARENT FATAL !! cannot fork. Exiting!" );

                return -1;
            } elseif ( $pid ) {
                self::_TimeStampMsg( "DONE pid is $pid" );
                // parent process runs what is here
                $processLaunched += 1;
            } else {
                // child process runs what is here
                $pid = getmypid();

                try {

                    if ( !self::$_queueHandler->getRedisClient()->rpush( $queueInfo->pid_list_name, $pid ) ) {
                        self::_TimeStampMsg( "(child $pid) : FATAL !! cannot create child file. Exiting!" );
                        return -2;

                    } else {
                        self::_TimeStampMsg( "(child $pid) : created !!!" );
                    }

                } catch ( Exception $e ){
                    self::$_queueHandler->getRedisClient()->lrem( $queueInfo->pid_list_name, 0, $pid );
                    self::_TimeStampMsg( "(child $pid) : FATAL !! Redis Server not available. Re-instantiated the connection and removed last pid from list." );
                    self::_TimeStampMsg( "(child $pid) : FATAL !! " . $e->getMessage() );
                    return 0;
                }

//                pcntl_exec( "/usr/bin/php", array( "tmAnalysisThreadChild.php" ) );
                echo "OK"; sleep(60);
                exit; //exit process
            }

        }

        return 0;
    }

    public static function cleanShutDown() {

        //SHUTDOWN
        self::_killPids();
        self::$_queueHandler->getRedisClient()->del( RedisKeys::VOLUME_ANALYSIS_PID );
        $msg = str_pad( " TM ANALYSIS " . getmypid() . " HALTED ", 50, "-", STR_PAD_BOTH );
        self::_TimeStampMsg( $msg );

        self::$_queueHandler->getRedisClient()->disconnect();

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
    protected static function _killPids( Info $queueInfo = null, $pid = 0, $num = 0 ) {

        self::_TimeStampMsg( "Request to kill some processes." );
        self::_TimeStampMsg( "Pid List: " . @var_export( $queueInfo->pid_list_name, true ) );
        self::_TimeStampMsg( "Pid:      " . @var_export( $pid, true ) );
        self::_TimeStampMsg( "Num:      " . @var_export( $num, true ) );

        $numDeleted = 0;

        if ( !empty( $pid ) && !empty( $queueInfo ) ) {

            self::_TimeStampMsg( "Killing pid $pid from " . $queueInfo->pid_list_name );
            $numDeleted += self::$_queueHandler->getRedisClient()->lrem( $queueInfo->pid_list_name, 0, $pid );

        } elseif ( !empty( $pid ) && empty( $queueInfo ) ) {

            self::_TimeStampMsg( "Killing pid $pid from a not defined queue. Seek and destroy." );
            $queuesInfo = QueuesList::get();
            /**
             * @var $queue Info
             */
            foreach ( $queuesInfo->list as $queue ) {
                $numDeleted += self::$_queueHandler->getRedisClient()->lrem( $queue->pid_list_name, 0, $pid );
            }

        } elseif ( !empty( $num ) && !empty( $queueInfo ) ) {

            self::_TimeStampMsg( "Killing $num pid from " . $queueInfo->pid_list_name );
            $queueBefore = self::$_queueHandler->getRedisClient()->llen( $queueInfo->pid_list_name );
            self::$_queueHandler->getRedisClient()->ltrim( $queueInfo->pid_list_name, 0, -$num -1 );
            $queueAfter = self::$_queueHandler->getRedisClient()->llen( $queueInfo->pid_list_name );
            $numDeleted = $queueBefore - $queueAfter;

        } elseif ( !empty( $queueInfo ) ) {

            self::_TimeStampMsg( "Killing all processes from " . $queueInfo->pid_list_name );
            $numDeleted = self::$_queueHandler->getRedisClient()->llen( $queueInfo->pid_list_name );
            self::$_queueHandler->getRedisClient()->del( $queueInfo->pid_list_name );

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

                    $_deleted = self::$_queueHandler->getRedisClient()->lpop( $queue->pid_list_name );

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
            $queuesInfo = QueuesList::get();
            foreach ( $queuesInfo->list as $queue ) {
                $numDeleted += self::$_queueHandler->getRedisClient()->llen( $queue->pid_list_name );
                self::$_queueHandler->getRedisClient()->del( $queue->pid_list_name );
            }

        } else {
            self::_TimeStampMsg( "Parameters not valid. Killing *** NONE ***" );
        }

        self::_TimeStampMsg( "Deleted $numDeleted processes." );

    }

    protected function _getNumProcesses() {

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

$_TMInstance = TMManager::getInstance()->main();

//$reflectedMethod = new \ReflectionMethod($_TMInstance, '_killPids' );
//$reflectedMethod->setAccessible( true );
//
////remove pid 2 ( seek and destroy )
//$_redisHandler =  new \RedisHandler();
//$_redisHandler->getConnection()->rpush( RedisKeys::VA_CHILD_PID_LIST_P2, 2 );
//
//$reflectedMethod->invokeArgs( $_TMInstance, array( null, 2 ) );