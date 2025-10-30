<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 16/05/14
 * Time: 17.01
 *
 */

namespace Utils\TaskRunner;

use Exception;
use ReflectionException;
use Utils\ActiveMQ\AMQHandler;
use Utils\Logger\LoggerFactory;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Commons\AbstractDaemon;
use Utils\TaskRunner\Commons\Context;

/**
 * Class Analysis_Manager
 *
 * Generic Asynchronous Task Runner
 *
 */
class TaskManager extends AbstractDaemon {

    /**
     * Key Set that holds the main process of Task Manager
     *
     * Every Task Manager must have its pid registered to distribute the across multiple servers
     *
     */
    const string TASK_RUNNER_PID = 'task_manager_pid_set';

    /**
     * Number of running processes
     *
     * @var int
     */
    protected int $_runningPids = 0;

    /**
     * Deleted context (removed from config file at runtime) will be putted here to be removed
     * @var Context[]
     */
    protected array $_destroyContext = [];

    /**
     * Exception code, error to fork the process
     */
    const int ERR_NOT_FORK = 1;

    /**
     * TaskManager constructor.
     *
     * @param ?string $configFile
     * @param ?string $contextIndex
     *
     * @throws Exception
     */
    protected function __construct( string $configFile = null, string $contextIndex = null ) {

        $this->_configFile   = $configFile;
        $this->_contextIndex = $contextIndex;

        parent::__construct();

        try {

            $this->queueHandler = AMQHandler::getNewInstanceForDaemons();
            $this->_updateConfiguration();

        } catch ( Exception $ex ) {

            $this->_logTimeStampedMsg( str_pad( " " . $ex->getMessage() . " ", 60, "*", STR_PAD_BOTH ) );
            $this->_logTimeStampedMsg( str_pad( "EXIT", 60, " ", STR_PAD_BOTH ) );
            die();
        }

    }

    /**
     * Start the execution method
     *
     * @param array|null $args
     *
     * @return void
     * @throws Exception
     *
     */
    public function main( array $args = null ) {

        /*
         * Kill all managers. "There can be only one."
         * Register My Host address (and also overwrite the old one)
         */
        if ( !$this->queueHandler->getRedisClient()->sadd( self::TASK_RUNNER_PID, [ gethostname() . ":" . AppConfig::$INSTANCE_ID ] ) ) {
            //kill all it's children
            $this->_killPids();
        }

        // BEGIN
        do {

            try {

                if ( !$this->queueHandler->getRedisClient()->sismember( self::TASK_RUNNER_PID, gethostname() . ":" . AppConfig::$INSTANCE_ID ) ) {
                    $this->_logTimeStampedMsg( "(parent " . $this->myProcessPid . " }) : ERROR OCCURRED, MY PID DISAPPEARED FROM REDIS:  PARENT EXITING !!" );
                    self::cleanShutDown();
                    die();
                }

            } catch ( Exception $e ) {
                $this->_logTimeStampedMsg( "(child " . $this->myProcessPid . ") : FATAL !! Redis Server not available. Re-instantiated the connection and re-try in next cycle" );
                $this->_logTimeStampedMsg( "(child " . $this->myProcessPid . ") : FATAL !! " . $e->getMessage() );
                sleep( 1 );
                continue;
            }

            $this->_waitPid();

            $this->_updateConfiguration();

            foreach ( $this->_queueContextList->list as $context ) {

//                $this->_logTimeStampedMsg( "(parent " . $this->myProcessPid . ") : queue " . gethostname() . ":" . $context->queue_name . " contains $context->pid_list_len processes" );

                $numProcessesDiff             = $context->pid_list_len - $context->max_executors;
                $numProcessesToLaunchOrDelete = abs( $numProcessesDiff );

                if ( $this->RUNNING ) {

                    if ( $numProcessesDiff < 0 ) {

                        try {
                            $this->_logTimeStampedMsg( "(parent " . $this->myProcessPid . ") : need to create $numProcessesToLaunchOrDelete processes on " . $context->queue_name );
                            $this->_forkProcesses( $numProcessesToLaunchOrDelete, $context );

                        } catch ( Exception $e ) {
                            $this->_logTimeStampedMsg( "Exception {$e->getCode()}: " . $e->getMessage() );
                            $this->RUNNING = false;
                        }

                    } elseif ( $numProcessesDiff > 0 ) {

                        $this->_logTimeStampedMsg( "(parent " . $this->myProcessPid . ") : need to delete $numProcessesToLaunchOrDelete processes on " . $context->queue_name );
                        $this->_killPids( $context, 0, $numProcessesToLaunchOrDelete );

                    } else {

                        if ( !( ( round( microtime( true ), 3 ) * 1000 ) % 10 ) ) {
                            $this->_logTimeStampedMsg( "(parent) : PARENT MONITORING PAUSE (" . gethostname() . ":" . AppConfig::$INSTANCE_ID . ") sleeping ...." );
                        }

                        self::_balanceQueues();

                    }

                    usleep( 500000 );

                }

            }

            // clean deleted contexts from configuration file
            $this->_cleanContexts();

            //wait to free cpu
            sleep( self::$sleepTime );

        } while ( $this->RUNNING );

        $this->cleanShutDown();

    }

    /**
     * Waits on or returns the status of the forked childs
     *
     * Signal management for child processes termination
     *
     * @throws ReflectionException
     */
    protected function _waitPid() {

        //avoid zombies: parent process knows the death of one of the children
        $dead = pcntl_waitpid( -1, $status, WNOHANG | WUNTRACED );
        while ( $dead > 0 ) {
            $this->_logTimeStampedMsg( "(parent " . $this->myProcessPid . "): child $dead exited." );
            foreach ( $this->_queueContextList->list as $queue ) {
                $_was_active_but_unexpectedly_dead = $this->queueHandler->getRedisClient()->sismember( $queue->pid_set_name, $dead . ":" . gethostname() . ":" . AppConfig::$INSTANCE_ID );
                if ( $_was_active_but_unexpectedly_dead ) {
                    $this->_logTimeStampedMsg( "(parent " . $this->myProcessPid . "): unexpectedly dead, deleting file ...." );
                    $this->_killPids( null, $dead );
                    $this->_logTimeStampedMsg( "(parent " . $this->myProcessPid . "): DONE" );
                } else {
                    // Executor exited by user kill or was a normal exit (clean)
                    $queue->pid_list_len = $this->queueHandler->getRedisClient()->scard( $queue->pid_set_name ) ?? 0;
                }
            }

            //avoid zombies: parent process knows the death of one of the children
            $dead = pcntl_waitpid( -1, $status, WNOHANG | WUNTRACED );

        }

    }

    /**
     * Doing nothing for now
     */
    protected function _balanceQueues() {
//        $this->_TimeStampMsg( "TODO. Now i do nothing." );
//        $this->RUNNING = false;
    }

    /**
     * Launch a single process over a queue and register it's pid in the right processes queue
     *
     * @param int     $numProcesses
     *
     * @param Context $context
     *
     * @throws Exception
     */
    protected function _forkProcesses( int $numProcesses, Context $context ) {

        $processLaunched = 0;

        while ( $processLaunched < $numProcesses ) {

            $pid = pcntl_fork();

            if ( $pid == -1 ) {

                throw new Exception( "(parent " . gethostname() . ":" . AppConfig::$INSTANCE_ID . ") : ERROR OCCURRED : cannot fork. PARENT EXITING !!", static::ERR_NOT_FORK );

            } elseif ( $pid ) {

                // parent process continues running
                $processLaunched    += 1;
                $this->_runningPids += 1;
                $context->pid_list_len++;
                $msg = str_pad( "(parent " . gethostname() . ":" . AppConfig::$INSTANCE_ID . " spawned 1 new child in " . $context->pid_set_name, 50, "-", STR_PAD_BOTH );
                $this->_logTimeStampedMsg( $msg );

            } else {

                // child process runs from here
                pcntl_exec( "/usr/bin/php", [ __DIR__ . DIRECTORY_SEPARATOR . "Executor.php", json_encode( $context ) ] );
                posix_kill( posix_getpid(), SIGTERM ); //this line of code will never be executed
                exit;

            }

        }

        //NO Error. Parent returning after $numProcesses process spawned

    }

    /**
     * Clean shutdown process for the Manager
     *
     * @throws ReflectionException
     */
    public function cleanShutDown() {

        //SHUTDOWN
        $msg = str_pad( " SHUTDOWN slow children." . gethostname() . ":" . AppConfig::$INSTANCE_ID, 50, "-", STR_PAD_BOTH );
        $this->_logTimeStampedMsg( $msg );
        $this->_killPids();
        $this->queueHandler->getRedisClient()->srem( self::TASK_RUNNER_PID, gethostname() . ":" . AppConfig::$INSTANCE_ID );
        $msg = str_pad( " TASK RUNNER " . gethostname() . ":" . AppConfig::$INSTANCE_ID . " HALTED ", 50, "-", STR_PAD_BOTH );
        $this->_logTimeStampedMsg( $msg );

        $this->queueHandler->getRedisClient()->disconnect();

    }

    /**
     * Process deletion/killing
     *
     * <ul>
     *     <li>Kill a specific Process ID from a specific Queue when $pid and $queueInfo are passed.</li>
     *     <li>Kill a specific Process ID un unknown Queue when only $pid is passed.</li>
     *     <li>Kill a certain number of processes from a queue when $num and $queueInfo are passed.</li>
     *     <li>Kill all processes from a queue when only the $queueInfo is passed.</li>
     *     <li>Kill a number of elements equally from all queues when only $num is passed.</li>
     *     <li>Kill ALL processes when no parameters are sent.</li>
     * </ul>
     *
     * @param ?Context $queueInfo
     * @param int      $pid
     * @param int      $num
     *
     * @throws ReflectionException
     */
    protected function _killPids( Context $queueInfo = null, int $pid = 0, int $num = 0 ) {

        $this->_logTimeStampedMsg( "Get to kill some processes." );
        $this->_logTimeStampedMsg( "Pid List: " . @var_export( $queueInfo->pid_set_name, true ) );
        $this->_logTimeStampedMsg( "Pid:      " . @var_export( $pid, true ) );
        $this->_logTimeStampedMsg( "Num:      " . @var_export( $num, true ) );

        $numDeleted = 0;

        if ( !empty( $pid ) && !empty( $queueInfo ) ) {

            $this->_logTimeStampedMsg( "Killing pid $pid from " . $queueInfo->pid_set_name );
            $numDeleted += $this->queueHandler->getRedisClient()->srem( $queueInfo->pid_set_name, $pid . ":" . gethostname() . ":" . AppConfig::$INSTANCE_ID );
            posix_kill( $pid, SIGTERM );
            $queueInfo->pid_list_len = $this->queueHandler->getRedisClient()->scard( $queueInfo->pid_set_name );

        } elseif ( !empty( $pid ) && empty( $queueInfo ) ) {

            $this->_logTimeStampedMsg( "Killing pid $pid from a not defined queue. Seek and destroy." );

            foreach ( $this->_queueContextList->list as $queue ) {

                $deleted = $this->queueHandler->getRedisClient()->srem( $queue->pid_set_name, $pid . ":" . gethostname() . ":" . AppConfig::$INSTANCE_ID );
                if ( $deleted ) {
                    posix_kill( $pid, SIGTERM );
                    $queue->pid_list_len = $this->queueHandler->getRedisClient()->scard( $queue->pid_set_name );
                    $this->_logTimeStampedMsg( "Found. Killed pid $pid from queue " . gethostname() . ":$queue->queue_name." );
                    $numDeleted += $deleted;
                }

            }

        } elseif ( !empty( $num ) && !empty( $queueInfo ) ) {

            $this->_logTimeStampedMsg( "Killing $num pid from " . $queueInfo->pid_set_name );
            $queueBefore = $this->queueHandler->getRedisClient()->scard( $queueInfo->pid_set_name );
            $pNameList   = $this->queueHandler->getRedisClient()->smembers( $queueInfo->pid_set_name );
            $i           = 0;
            foreach ( $pNameList as $pidName ) {
                [ $pid, $hostName, $instanceID ] = explode( ":", $pidName );
                if ( $hostName == gethostname() ) {
                    posix_kill( $pid, SIGTERM );
                    $this->queueHandler->getRedisClient()->srem( $queueInfo->pid_set_name, $pidName );
                    $queueInfo->pid_list_len = $this->queueHandler->getRedisClient()->scard( $queueInfo->pid_set_name );
                    $i++;
                }

                if ( $i == $num ) {
                    break;
                }

            }
            $queueAfter = $this->queueHandler->getRedisClient()->scard( $queueInfo->pid_set_name );
            $numDeleted = $queueBefore - $queueAfter;

        } elseif ( !empty( $queueInfo ) ) {

            $this->_logTimeStampedMsg( "Killing all processes from " . $queueInfo->pid_set_name );
            $numDeleted = $this->queueHandler->getRedisClient()->scard( $queueInfo->pid_set_name );
            $pNameList  = $this->queueHandler->getRedisClient()->smembers( $queueInfo->pid_set_name );
            foreach ( $pNameList as $pidName ) {
                [ $pid, $hostName, $instanceId ] = explode( ":", $pidName );
                if ( $hostName == gethostname() ) {
                    posix_kill( $pid, SIGTERM );
                    $this->queueHandler->getRedisClient()->srem( $queueInfo->pid_set_name, $pidName );
                }
            }

            $queueInfo->pid_list_len = 0;

        } elseif ( !empty( $num ) ) {

            $this->_logTimeStampedMsg( "Killing $num processes balancing all queues." );

            while ( true ) {

                // if all queues are empty or they have less elements than requested $num
                //we do not want an infinite loop
                //so, at least one deletion per cycle
                $deleted = false;
                foreach ( $this->_queueContextList->list as $queue ) {

                    //Exit, we reached the right number, exit the while loop
                    if ( $numDeleted >= $num ) {
                        break 2; //exit the while loop
                    }

                    $pidName = false;
                    if ( $queue->max_executors < $queue->pid_list_len ) {
                        //ok, queue can be reduced because it's upper limit exceed the max queue consumers
                        $pidName = $this->queueHandler->getRedisClient()->spop( $queue->pid_set_name );
                        if ( $pidName ) {
                            [ $pid, $hostName, $instanceId ] = explode( ":", $pidName );
                            if ( $hostName == gethostname() ) {
                                $queue->pid_list_len = $this->queueHandler->getRedisClient()->scard( $queue->pid_set_name );
                                posix_kill( $pid, SIGTERM );
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

        } elseif ( empty( $pid ) ) {

            $this->_logTimeStampedMsg( "Killing ALL processes." );
            foreach ( $this->_queueContextList->list as $queue ) {
                $pNameList = $this->queueHandler->getRedisClient()->smembers( $queue->pid_set_name );
                foreach ( $pNameList as $pName ) {
                    [ $pid, $hostName, $instanceId ] = explode( ":", $pName );
                    if ( $hostName == gethostname() ) {
                        posix_kill( $pid, SIGTERM );
                        $this->queueHandler->getRedisClient()->srem( $queue->pid_set_name, $pName );
                        $numDeleted++;
                    }
                }
                $queue->pid_list_len = 0;
            }

        } else {
            $this->_logTimeStampedMsg( "Parameters not valid. Killing *** NONE ***" );
        }

        $this->_runningPids -= $numDeleted;

        $this->_logTimeStampedMsg( "Deleted $numDeleted processes." );

    }

    /**
     * Reload Configuration every cycle
     *
     * @throws Exception
     */
    protected function _updateConfiguration(): void {

        $configuration = $this->getConfiguration();
        $this->logger  = LoggerFactory::getLogger( 'task_manager', $configuration->getLoggerName() );

        if ( empty( $this->_queueContextList->list ) ) {

            //First Execution, load build object
            $this->_queueContextList = $configuration->getContextList();

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
        if ( $diff = array_diff_key( $this->_queueContextList->list, $configuration->getContextList()->list ) ) {

            //remove no more present contexts
            foreach ( $diff as $_key => $_cont ) {
                unset( $this->_queueContextList->list[ $_key ] );
                $this->_destroyContext[] = $_cont;
            }

        }

        foreach ( $configuration->getContextList()->list as $contextName => $context ) {

            if ( isset( $this->_queueContextList->list[ $contextName ] ) ) {

                //update the max executor number for this element
                $this->_queueContextList->list[ $contextName ]->max_executors = $context->max_executors;

            } else {

                //create a new Object execution context
                $this->_queueContextList->list[ $contextName ] = $context;

            }

        }

    }

    /**
     *
     * Remove no more present contexts
     * @throws ReflectionException
     */
    protected function _cleanContexts() {

        //remove no more present contexts
        foreach ( $this->_destroyContext as $_context ) {

            $this->_logTimeStampedMsg( "(parent " . gethostname() . AppConfig::$INSTANCE_ID . ") : need to delete a context" );
            $this->_killPids( $_context );

        }
        $this->_destroyContext = [];

    }

}
