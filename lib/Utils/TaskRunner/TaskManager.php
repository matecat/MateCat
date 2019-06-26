<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 16/05/14
 * Time: 17.01
 * 
 */

namespace TaskRunner;

use AMQHandler;
use Exception;
use INIT;
use Log;
use TaskRunner\Commons\AbstractDaemon;
use TaskRunner\Commons\Context;
use TaskRunner\Commons\ContextList;
use TaskRunner\Commons\RedisKeys;

/**
 * Class Analysis_Manager
 *
 * Generic Asynchronous Task Runner
 *
 */
class TaskManager extends AbstractDaemon {

    /**
     * Handler of AMQ connector
     *
     * @var \AMQHandler
     */
    protected $_queueHandler;

    /**
     * Number of running processes
     *
     * @var int
     */
    protected $_runningPids     = 0;

    /**
     * List of contexts loaded from configuration file
     * @var array
     */
    protected $_context_definitions = array();

    /**
     * Optional context index on which the task runner works
     *
     * @var null
     */
    protected $_contextIndex;

    /**
     * Path to che configuration file
     *
     * @var string
     */
    protected $_configFile;

    /**
     * Context list definitions
     *
     * @var ContextList
     */
    protected $_queueContextList = array();

    /**
     * Deleted context ( removed from config file at runtime ) will be putted here to be removed
     * @var Context[]
     */
    protected $_destroyContext = array();

    /**
     * Exception code, error to fork the process
     */
    const ERR_NOT_FORK          = 1;

    /**
     * Exception code, error to increment the number of processes on the Redis key
     */
    const ERR_NOT_INCREMENT     = 2;

    /**
     * TaskManager constructor.
     *
     * @param null $configFile
     * @param null $contextIndex
     */
    protected function __construct( $configFile = null, $contextIndex = null ) {

        $this->_configFile   = $configFile;
        $this->_contextIndex = $contextIndex;

        parent::__construct();

        try {

            set_time_limit(0);

            $this->_queueHandler = new AMQHandler();
            $this->_updateConfiguration();

        } catch ( Exception $ex ){

            self::_TimeStampMsg( str_pad( " " . $ex->getMessage() . " ", 60, "*", STR_PAD_BOTH ) );
            self::_TimeStampMsg( str_pad( "EXIT", 60, " ", STR_PAD_BOTH ) );
            die();
        }

    }

    /**
     * Start execution method
     *
     * @param null $args
     *
     * @throws Exception
     *
     * @return void
     */
    public function main( $args = null ) {

        /*
         * Kill all managers. "There can be only one."
         * Register My Host address ( and also overwrite the old one )
         */
        if ( !$this->_queueHandler->getRedisClient()->sadd( RedisKeys::TASK_RUNNER_PID, gethostname() . ":" . (int) INIT::$INSTANCE_ID ) ){
            //kill all it's children
            $this->_killPids();
        }

        // BEGIN
        do {

            try {

                if( !$this->_queueHandler->getRedisClient()->sismember( RedisKeys::TASK_RUNNER_PID, gethostname() . ":" . (int) INIT::$INSTANCE_ID ) ) {
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

            $this->_waitPid();

            $this->_updateConfiguration();

            foreach ( $this->_queueContextList->list as $context_index => $context ) {

//                self::_TimeStampMsg( "(parent " . self::$tHandlerPID . ") : queue " . gethostname() . ":" . $context->queue_name . " contains $context->pid_list_len processes" );

                $numProcessesDiff = $context->pid_list_len - $context->max_executors;
                $numProcessesToLaunchOrDelete = abs( $numProcessesDiff );

                switch ( true ) {

                    case $numProcessesDiff < 0:

                        try {
                            self::_TimeStampMsg( "(parent " . self::$tHandlerPID . ") : need to create $numProcessesToLaunchOrDelete processes" );
                            $this->_forkProcesses( $numProcessesToLaunchOrDelete, $context );

                        } catch ( Exception $e ) {
                            self::_TimeStampMsg( "Exception {$e->getCode()}: " . $e->getMessage() );
                            $this->RUNNING = false;
                        }

                        break;

                    case $numProcessesDiff > 0:

                        self::_TimeStampMsg( "(parent " . self::$tHandlerPID . ") : need to delete $numProcessesToLaunchOrDelete processes" );
                        $this->_killPids( $context, 0, $numProcessesToLaunchOrDelete );
                        sleep( 1 );

                        break;

                    default:
                        if ( !( ( round( microtime(true), 3 ) * 1000 ) % 10 ) ) {
                            self::_TimeStampMsg( "(parent) : PARENT MONITORING PAUSE (" . gethostname() . ":" . INIT::$INSTANCE_ID . ") sleeping ...." );
                            usleep( 1000 );
                        }

                        self::_balanceQueues();
                        break;
                }
            }

            // clean deleted contexts from configuration file
            $this->_cleanContexts();

            //wait to free cpu
            sleep( self::$sleepTime );

        } while( $this->RUNNING );

        self::cleanShutDown();

    }

    /**
     * Waits on or returns the status of the forked childs
     *
     * Signal management for child processes termination
     *
     */
    protected function _waitPid(){

        //avoid zombies : parent process knows the death of one of the children
        $dead = pcntl_waitpid( -1, $status, WNOHANG | WUNTRACED );
        while ( $dead > 0 ) {

            self::_TimeStampMsg( "(parent " . self::$tHandlerPID . ") : child $dead exited: deleting file ...." );
            foreach ( $this->_queueContextList->list as $queue ) {
                $_was_active = $this->_queueHandler->getRedisClient()->sismember( $queue->pid_set_name, $dead . ":" . gethostname() . ":" . (int) INIT::$INSTANCE_ID );
                if ( $_was_active ) {
                    $this->_killPids( null, $dead );
                }
            }

            //avoid zombies : parent process knows the death of one of the children
            $dead = pcntl_waitpid( -1, $status, WNOHANG | WUNTRACED );

            self::_TimeStampMsg( "DONE" );

        }

    }

    /**
     * Doing nothing for now
     */
    protected function _balanceQueues(){
//        self::_TimeStampMsg( "TODO. Now i do nothing." );
//        $this->RUNNING = false;
    }

    /**
     * Launch a single process over a queue and register it's pid in the right processes queue
     *
     * @param int     $numProcesses
     *
     * @param Context $context
     *
     * @return int|null
     * @throws Exception
     */
    protected function _forkProcesses( $numProcesses, Context $context ) {

        $processLaunched = 0;

        while ( $processLaunched < $numProcesses ) {

            try {
                $context->pid_list_len = $this->_queueHandler->getRedisClient()->incr( gethostname() . ":" . $context->queue_name );
//                self::_TimeStampMsg( "(parent " . self::$tHandlerPID . ") : queue " . gethostname() . ":" . $context->queue_name . " counter incremented, now it contains $context->pid_list_len processes" );
            } catch ( Exception $e ){
                throw new Exception( "(parent " . gethostname() . ":" . INIT::$INSTANCE_ID . ") ERROR: " . $e->getMessage() . " ... EXITING.", static::ERR_NOT_INCREMENT );
            }

            $pid = pcntl_fork();

            if ( $pid == -1 ) {

                throw new Exception( "(parent " . gethostname() . ":" . INIT::$INSTANCE_ID . ") : ERROR OCCURRED : cannot fork. PARENT EXITING !!", static::ERR_NOT_FORK );

            } elseif ( $pid ) {

                // parent process continue running
                $processLaunched += 1;
                $this->_runningPids += 1;
                $msg = str_pad( "(parent " . gethostname() . ":" . INIT::$INSTANCE_ID . " spawned 1 new child in " . gethostname() . ":" . $context->queue_name, 50, "-", STR_PAD_BOTH );
                self::_TimeStampMsg( $msg );

            } else {

                // child process runs from here
                pcntl_exec( "/usr/bin/php", array( __DIR__ . DIRECTORY_SEPARATOR . "Executor.php" , json_encode( $context ) ) );
                posix_kill( posix_getpid(), SIGINT ); //this line of code will never be executed
                exit;

            }

        }

        //NO Error. Parent returning after $numProcesses process spawned
        return 0;

    }

    /**
     * Clean shutdown process for the Manager
     *
     */
    public static function cleanShutDown() {

        //SHUTDOWN
        static::$__INSTANCE->_killPids();
        static::$__INSTANCE->_queueHandler->getRedisClient()->srem( RedisKeys::TASK_RUNNER_PID, gethostname() . ":" . (int) INIT::$INSTANCE_ID );
        $msg = str_pad( " TASK RUNNER " . gethostname() . ":" . INIT::$INSTANCE_ID . " HALTED ", 50, "-", STR_PAD_BOTH );
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
     * @param Context $queueInfo
     * @param int     $pid
     * @param int     $num
     *
     * @throws \Predis\Connection\ConnectionException
     */
    protected function _killPids( Context $queueInfo = null, $pid = 0, $num = 0 ) {

        self::_TimeStampMsg( "Request to kill some processes." );
        self::_TimeStampMsg( "Pid List: " . @var_export( $queueInfo->pid_set_name, true ) );
        self::_TimeStampMsg( "Pid:      " . @var_export( $pid, true ) );
        self::_TimeStampMsg( "Num:      " . @var_export( $num, true ) );

        $numDeleted = 0;

        if ( !empty( $pid ) && !empty( $queueInfo ) ) {

            self::_TimeStampMsg( "Killing pid $pid from " . $queueInfo->pid_set_name );
            $numDeleted += $this->_queueHandler->getRedisClient()->srem( $queueInfo->pid_set_name, $pid . ":" . gethostname() . ":" . (int) INIT::$INSTANCE_ID );
            posix_kill( $pid, SIGINT );
            $queueInfo->pid_list_len = $this->_queueHandler->getRedisClient()->decr( gethostname() . ":" . $queueInfo->queue_name );

        } elseif ( !empty( $pid ) && empty( $queueInfo ) ) {

            self::_TimeStampMsg( "Killing pid $pid from a not defined queue. Seek and destroy." );
            /**
             * @var $queue Context
             */
            foreach ( $this->_queueContextList->list as $queue ) {

                $deleted = $this->_queueHandler->getRedisClient()->srem( $queue->pid_set_name, $pid . ":" . gethostname() . ":" . (int) INIT::$INSTANCE_ID );
                if( $deleted ){
                    posix_kill( $pid, SIGINT );
                    $queue->pid_list_len = $this->_queueHandler->getRedisClient()->decr( gethostname() . ":" . $queue->queue_name );
                    self::_TimeStampMsg( "Found. Killed pid $pid from queue " . gethostname() . ":$queue->queue_name." );
                    $numDeleted += $deleted;
                }

            }

        } elseif ( !empty( $num ) && !empty( $queueInfo ) ) {

            self::_TimeStampMsg( "Killing $num pid from " . $queueInfo->pid_set_name );
            $queueBefore = $this->_queueHandler->getRedisClient()->scard( $queueInfo->pid_set_name );
            $pNameList = $this->_queueHandler->getRedisClient()->smembers( $queueInfo->pid_set_name );
            $i = 0;
            foreach( $pNameList as $pidName ){
                list( $pid, $hostName, $instanceID ) = explode( ":", $pidName );
                if( $hostName == gethostname() ){
                    posix_kill( $pid, SIGINT );
                    $this->_queueHandler->getRedisClient()->srem( $queueInfo->pid_set_name, $pidName );
                    $queueInfo->pid_list_len = $this->_queueHandler->getRedisClient()->decr( $hostName . ":" . $queueInfo->queue_name );
                    $i++;
                }

                if( $i == $num ){
                    break;
                }

            }
            $queueAfter = $this->_queueHandler->getRedisClient()->scard( $queueInfo->pid_set_name );
            $numDeleted = $queueBefore - $queueAfter;

        } elseif ( !empty( $queueInfo ) ) {

            self::_TimeStampMsg( "Killing all processes from " . $queueInfo->pid_set_name );
            $numDeleted = $this->_queueHandler->getRedisClient()->scard( $queueInfo->pid_set_name );
            $pNameList = $this->_queueHandler->getRedisClient()->smembers( $queueInfo->pid_set_name );
            foreach( $pNameList as $pidName ){
                list( $pid, $hostName, $instanceId ) = explode( ":", $pidName );
                if( $hostName == gethostname() ){
                    posix_kill( $pid, SIGINT );
                    $this->_queueHandler->getRedisClient()->srem( $queueInfo->pid_set_name, $pidName );
                    $this->_queueHandler->getRedisClient()->decr( $hostName . ":" . $queueInfo->queue_name );
                }
            }

            $queueInfo->pid_list_len = 0;

        } elseif ( !empty( $num ) ) {

            self::_TimeStampMsg( "Killing $num processes balancing all queues." );

            while ( true ) {

                // if all queues are empty or they have less elements than requested $num
                //we do not want an infinite loop
                //so, at least one deletion per cycle
                $deleted = false;
                foreach ( $this->_queueContextList->list as $queue ) {

                    //Exit, we reached the right number, exit the while loop
                    if( $numDeleted >= $num ){
                        break 2; //exit the while loop
                    }

                    $pidName = false;
                    if( $queue->max_executors < $queue->pid_list_len ){
                        //ok, queue can be reduced because it's upper limit exceed the max queue consumers
                        $pidName = $this->_queueHandler->getRedisClient()->spop( $queue->pid_set_name );
                        if ( $pidName ) {
                            list( $pid, $hostName, $instanceId ) = explode( ":", $pidName );
                            if( $hostName == gethostname() ){
                                $queue->pid_list_len = $this->_queueHandler->getRedisClient()->decr( $hostName . ":" . $queue->queue_name );
                                posix_kill( $pid, SIGINT );
                            } else {
                                $pidName = false;
                            }
                        }
                    }

                    //we do not want an infinite loop
                    //so, at least one deletion per cycle
                    $deleted = $pidName || $deleted;

                    $numDeleted += (int)(bool)$pidName;

                }

                //no more processes to kill!! Avoid infinite loop
                if ( !$deleted ) {
                    break;
                }

            }

        } elseif ( empty( $queueInfo ) && empty( $pid ) && empty( $num ) ) {

            self::_TimeStampMsg( "Killing ALL processes." );
            foreach ( $this->_queueContextList->list as $queue ) {
                $pNameList = $this->_queueHandler->getRedisClient()->smembers( $queue->pid_set_name );
                foreach ( $pNameList as $pName ){
                    list( $pid, $hostName, $instanceId ) = explode( ":", $pName );
                    if( $hostName == gethostname() ){
                        posix_kill( $pid, SIGINT );
                        $this->_queueHandler->getRedisClient()->srem( $queue->pid_set_name, $pName );
                        $this->_queueHandler->getRedisClient()->decr( $hostName . ":" . $queue->queue_name );
                        $numDeleted++;
                    }
                }
                $queue->pid_list_len = 0;
            }

        } else {
            self::_TimeStampMsg( "Parameters not valid. Killing *** NONE ***" );
        }

        $this->_runningPids -= $numDeleted;

        self::_TimeStampMsg( "Deleted $numDeleted processes." );

    }

    /**
     * Reload Configuration every cycle
     *
     */
    protected function _updateConfiguration() {

        $config = @parse_ini_file( $this->_configFile, true );

        if( empty( $this->_configFile ) || !isset( $config[ 'context_definitions' ] ) || empty( $config[ 'context_definitions' ] ) ){
            throw new Exception( 'Wrong configuration file provided.' );
        }

        Log::$fileName              = $config[ 'loggerName' ];
        $this->_context_definitions = $config[ 'context_definitions' ];

        if ( empty( $this->_contextIndex ) ) {
            $contextList = $this->_context_definitions;
        } else {
            $contextList = array( $this->_context_definitions[ $this->_contextIndex ] );
        }

        if( empty( $this->_queueContextList->list ) ){

            //First Execution, load build object
            $this->_queueContextList = ContextList::get( $contextList );

            //exit method
            return;

        }

        /**
         * Compares the keys from array1 against the keys from array2 and returns the difference.
         * This function is like array_diff() except the comparison is done on the keys instead of the values.
         *
         * <pre>
         *     array_diff:
         *     Compares array1 against one or more other arrays
         *     and returns the values in array1 that are not present in any of the other arrays.
         *</pre>
         *
         */
        if( $diff = array_diff_key( $this->_queueContextList->list, ContextList::get( $contextList )->list ) ){

            //remove no more present contexts
            foreach( $diff as $_key => $_cont ){
                unset( $this->_queueContextList->list[ $_key ] );
                $this->_destroyContext[] = $_cont;
            }

        }

        foreach( $this->_context_definitions as $contextName => $context ){

            if( isset( $this->_queueContextList->list[ $contextName ] ) ){

                //update the max executor number for this element
                $this->_queueContextList->list[ $contextName ]->max_executors = $context[ 'max_executors' ];

            } else {

                //create a new Object execution context
                $this->_queueContextList->list[ $contextName ] = Context::buildFromArray( $context );

            }

        }

    }

    /**
     *
     * Remove no more present contexts
     * @throws \Predis\Connection\ConnectionException
     */
    protected function _cleanContexts(){

        //remove no more present contexts
        foreach( $this->_destroyContext as $_context ){

            self::_TimeStampMsg( "(parent " . gethostname() . INIT::$INSTANCE_ID . ") : need to delete a context" );
            $this->_killPids( $_context );

        }
        $this->_destroyContext = array();

    }

}
