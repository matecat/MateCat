<?php
set_time_limit(0);
$root = realpath( dirname(__FILE__) . '/../../../' );
define( 'NUM_WORKERS', $root . "/lib/Utils/Analysis/.num_processes" );
define( 'DEFAULT_NUM_WORKERS', require( 'DefaultNumTMWorkers.php' ) );
define( 'LOG_FILENAME', 'tm_analysis.log' );
$my_pid = getmypid();
require "main.php";


try {
    $queueHandler = new Analysis_QueueHandler();

    if ( $queueHandler->getRedisClient()->get( Constants_AnalysisRedisKeys::VOLUME_ANALYSIS_PID ) ){
        deletePid();
    }

    $queueHandler->getRedisClient()->set( Constants_AnalysisRedisKeys::VOLUME_ANALYSIS_PID, getmypid() );
} catch ( Exception $ex ){

    $msg = "****** No REDIS/AMQ instances found. Exiting. ******";
    _TimeStampMsg( $msg, true );
    _TimeStampMsg( $ex->getMessage(), true );
    die();
}

$RUNNING = true;

// PROCESS CONTROL FUNCTIONS

function cleanShutDown( ){

    global $queueHandler;

    //SHUTDOWN
    deletePid();
    $queueHandler->getRedisClient()->del( Constants_AnalysisRedisKeys::VOLUME_ANALYSIS_PID );
    $msg = str_pad( " TM ANALYSIS " . getmypid() . " HALTED ", 50, "-", STR_PAD_BOTH );
    _TimeStampMsg( $msg, true );

    $queueHandler->getRedisClient()->disconnect();

}

function sigSwitch( $signo ) {

    global $RUNNING;

    switch ($signo) {
        case SIGTERM :
        case SIGINT :
            $RUNNING = false;
            break;
        case SIGHUP :
            $RUNNING = false;
            cleanShutDown();
            break;
        default :
            break;
    }

    $msg = str_pad( " TM ANALYSIS " . getmypid() . " Caught Signal $signo ", 50, "-", STR_PAD_BOTH );
    _TimeStampMsg( $msg );

}

function isRunningChild($pid) {

    /**
     * @var $redisHandler Predis\Client
     */
    global $queueHandler;

    $pidList = $queueHandler->getRedisClient()->lrange( Constants_AnalysisRedisKeys::VA_CHILD_PID_LIST, 0 , -1 );

    if( array_search( $pid, $pidList ) !== false ){
        return true;
    }

    return false;

}

_TimeStampMsg( "(parent $my_pid) : ------------------- cleaning old resources -------------------", false );
// delete other processes of old execution
deletePid();
// wait all old children exits

//START EVENTS

do {

//    _TimeStampMsg( "(parent $my_pid) : PARENT MONITORING START" );

    try {

        if( !$queueHandler->getRedisClient()->get( Constants_AnalysisRedisKeys::VOLUME_ANALYSIS_PID ) ) {
            cleanShutDown();
            _TimeStampMsg( "(parent $my_pid) : ERROR OCCURRED, MY PID DISAPPEARED FROM REDIS:  PARENT EXITING !!", true );
            die();
        }

    } catch ( Exception $e ){
        _TimeStampMsg( "(child $my_pid) : FATAL !! Redis Server not available. Re-instantiated the connection and re-try in next cycle", true );
        _TimeStampMsg( "(child $my_pid) : FATAL !! " . $e->getMessage(), true );
        sleep(1);
        continue;
    }

    //avoid zombies : the parent is aware of the death of one of the children
    $dead = pcntl_waitpid( -1, $status, WNOHANG | WUNTRACED );
    if ( $dead > 0 ) {
        _TimeStampMsg( "(parent $my_pid) : child $dead exited: deleting file ....", false );
        deletePid( $dead );
        _TimeStampMsg( "DONE", false );
    }
    $numProcesses = setNumProcesses();

    try {
        $childrenRunningList = $queueHandler->getRedisClient()->lrange( Constants_AnalysisRedisKeys::VA_CHILD_PID_LIST, 0, -1 );
    } catch ( Exception $e ){
        _TimeStampMsg( "(child $pid) : FATAL !! Redis Server not available. Re-instantiated the connection and re-try in next cycle", true );
        _TimeStampMsg( "(child $pid) : FATAL !! " . $e->getMessage(), true );
        sleep(1);
        continue;
    }

    $numProcessesNow  = count( $childrenRunningList );
    $numProcessesDiff = $numProcessesNow - $numProcesses;

//    _TimeStampMsg( "(parent $my_pid) :  $numProcesses to launch - $numProcessesNow already launched , diff is $numProcessesDiff\n " );
    $numProcessesToLaunchOrDelete = abs( $numProcessesDiff );

    switch ( $numProcessesDiff ) {

        case $numProcessesDiff < 0:
            //launch abs($numProcessesDiff) processes
//            _TimeStampMsg( "(parent $my_pid) : need to launch additional $numProcessesToLaunchOrDelete processes", false );
            $res = launchProcesses( $numProcessesToLaunchOrDelete );
            if ( $res < 0 ) {
                cleanShutDown();
                _TimeStampMsg( "(parent $my_pid) : ERROR OCCURRED :  PARENT EXITING !!", true );
                die();
            }
            break;
        case $numProcessesDiff > 0:
            _TimeStampMsg( "(parent $my_pid) : need to delete $numProcessesToLaunchOrDelete processes" );
            deletePid( "", $numProcessesToLaunchOrDelete );
            sleep(1);
            break;
        default:
            _TimeStampMsg( "(parent $my_pid) : no pid to delete everithing  works well", false );
            _TimeStampMsg( "(parent) : PARENT MONITORING PAUSE ($my_pid) sleeping ....", false );
            sleep( 5 );
    }

} while( $RUNNING );

cleanShutDown();

die();


function launchProcesses( $numProcesses = 1 ) {


    /**
     * @var $redisHandler Analysis_QueueHandler
     */
    global $queueHandler;

    $processLaunched = 0;
//    _TimeStampMsg( __FUNCTION__ . " : parent launching $numProcesses processes - $processLaunched  already launched ", false );

    while ( $processLaunched < $numProcesses ) {
        _TimeStampMsg( "launching .....", false );
        $pid = pcntl_fork();

        if ( $pid == -1 ) {
            _TimeStampMsg( "PARENT FATAL !! cannot fork. Exiting!", true );

            return -1;
        } elseif ( $pid ) {
            _TimeStampMsg( "DONE pid is $pid", false );
            // parent process runs what is here
            $processLaunched += 1;
        } else {
            // child process runs what is here
            $pid = getmypid();

            try {

                if ( !$queueHandler->getRedisClient()->rpush( Constants_AnalysisRedisKeys::VA_CHILD_PID_LIST, $pid ) ) {
                    _TimeStampMsg( "(child $pid) : FATAL !! cannot create child file. Exiting!", true );
                    return -2;

                } else {
                    _TimeStampMsg( "(child $pid) : created !!!", false );
                }

            } catch ( Exception $e ){
                $queueHandler->getRedisClient()->lrem( Constants_AnalysisRedisKeys::VA_CHILD_PID_LIST, 0, $pid );
                _TimeStampMsg( "(child $pid) : FATAL !! Redis Server not available. Re-instantiated the connection and removed last pid from list.", true );
                _TimeStampMsg( "(child $pid) : FATAL !! " . $e->getMessage(), true );
                return 0;
            }

            pcntl_exec( "/usr/bin/php", array( "tmAnalysisThreadChild.php" ) );

            exit; //exit process
        }

        // this sleep is for tempt to avoid two fork select the same segment: backward compatibility with MySql Child
        usleep(200000);
    }

    return 0;
}

function deletePid( $pid = "", $num = -1 ) {

    /**
     * @var $redisHandler Analysis_QueueHandler
     */
    global $queueHandler;

    _TimeStampMsg( "Request to delete pid = " . var_export( $pid, true ) . ", num = " . var_export( $num, true ), true );

    $numDeleted = 0;
    $files      = array();

    if ( empty( $pid ) ) {

        if ( $num > 0 ) {
            _TimeStampMsg( "Deleting $num pid in the list.", false );
        }

        $files = $queueHandler->getRedisClient()->lrange( Constants_AnalysisRedisKeys::VA_CHILD_PID_LIST, 0, -1 );

    } else {

        _TimeStampMsg( "Single PID to delete: $pid", false );
        $files[ ] = $pid;

    }

    if( empty( $pid ) && $num == -1 ){

        //default params
        _TimeStampMsg( "Deleting all pid process id", false );
        $queueHandler->getRedisClient()->del( Constants_AnalysisRedisKeys::VA_CHILD_PID_LIST );
        $numDeleted = count( $files );
        foreach( $files as $_pid ){
            posix_kill( $_pid, SIGTERM );
        }

    } else {

        foreach( $files as $file ) { // iterate ids

            $queueHandler->getRedisClient()->lrem( Constants_AnalysisRedisKeys::VA_CHILD_PID_LIST, 0, $file  );

            if ( $num > 0 ) {

                $numDeleted += 1;
                if ( $numDeleted == $num ) {
                    break;
                }

            } else {

                $numDeleted = count( $files );

            }

        }

    }

    _TimeStampMsg( "Deleted $numDeleted files", true );

}

function setNumProcesses() {

    // legge quanti processi lanciare
    $num_processes = DEFAULT_NUM_WORKERS;
    if ( file_exists( NUM_WORKERS ) ) {
        $num_processes = intval( file_get_contents( NUM_WORKERS ) );
    }

    if ( !is_int( $num_processes ) ) {
        _TimeStampMsg( "WARNING : num processes from file is not numeric. Back to default value NUM_PROCESSES = 1", false );
        $num_processes = 1;
    }

    return $num_processes;
}
