<?php
set_time_limit(0);
require "main.php";

define( 'ANALYSIS_ROOT', INIT::$UTILS_ROOT . "/Analysis/.num_processes" );
$my_pid = getmypid();

try {
    $redisHandler = new Predis\Client( INIT::$REDIS_SERVERS, array( 'read_write_timeout' => 30, 'timeout' => 30 ) );
    $redisHandler->set( Constants_AnalysisRedisKeys::VOLUME_ANALYSIS_PID, $my_pid );
} catch ( Exception $ex ){
    $msg = "****** No REDIS instances found. Exiting. ******";
    _TimeStampMsg( $msg, true );
    die();
}

Log::$fileName = "tm_analysis.log";
$RUNNING = true;

// PROCESS CONTROL FUNCTIONS

function cleanShutDown( ){

    global $redisHandler, $db;

    //SHUTDOWN
    deletePid();
    $redisHandler->del( Constants_AnalysisRedisKeys::VOLUME_ANALYSIS_PID );
    $redisHandler->disconnect();
    $db->close();

    $msg = str_pad( " TM ANALYSIS " . getmypid() . " HALTED ", 50, "-", STR_PAD_BOTH );
    _TimeStampMsg( $msg, true );

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
    global $redisHandler;

    $pidList = $redisHandler->lrange( Constants_AnalysisRedisKeys::VA_CHILD_PID_LIST, 0 , -1 );

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

    if( !$redisHandler->get( Constants_AnalysisRedisKeys::VOLUME_ANALYSIS_PID ) ) {
        cleanShutDown();
        _TimeStampMsg( "(parent $my_pid) : ERROR OCCURRED, MY PID DISAPPEARED FROM REDIS:  PARENT EXITING !!", true );
        die();
    }

    //avoid zombies : the parent is aware of the death of one of the children
    $dead = pcntl_waitpid( -1, $status, WNOHANG );
    if ( $dead > 0 ) {
        _TimeStampMsg( "(parent $my_pid) : child $dead exited: deleting file ....", false );
        deletePid( $dead );
        _TimeStampMsg( "DONE", false );
    }
    $numProcesses        = setNumProcesses();
    $childrenRunningList = $redisHandler->lrange( Constants_AnalysisRedisKeys::VA_CHILD_PID_LIST, 0, -1 );

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
            _TimeStampMsg( "(parent $my_pid) : need to delete $numProcessesToLaunchOrDelete processes", false );
            deletePid( "", $numProcessesToLaunchOrDelete );
            break;
        default:
            _TimeStampMsg( "(parent $my_pid) : no pid to delete everithing  works well", false );

    }

    _TimeStampMsg( "(parent) : PARENT MONITORING PAUSE ($my_pid) sleeping ....", false );
    sleep( 5 );

} while( $RUNNING );

cleanShutDown();

die();


function launchProcesses( $numProcesses = 1 ) {


    /**
     * @var $redisHandler Predis\Client
     */
    global $redisHandler;

    $processLaunched = 0;
//    _TimeStampMsg( __FUNCTION__ . " : parent launching $numProcesses processes - $processLaunched  already launched ", false );

    while ( $processLaunched < $numProcesses ) {
        _TimeStampMsg( "launching .....", false );
        $pid = pcntl_fork();

        if ( $pid == -1 ) {
            _TimeStampMsg( "PARENT FATAL !! cannot fork. Exiting!", false );

            return -1;
        }
        if ( $pid ) {
            _TimeStampMsg( "DONE pid is $pid", false );
            // parent process runs what is here
            $processLaunched += 1;
        } else {
            // child process runs what is here
            $pid = getmypid();

            if ( !$redisHandler->rpush( Constants_AnalysisRedisKeys::VA_CHILD_PID_LIST, $pid ) ) {
                _TimeStampMsg( "(child $pid) : FATAL !! cannot create child file. Exiting!", false );
                return -2;

            } else {
                _TimeStampMsg( "(child $pid) : created !!!", false );
            }

            pcntl_exec( "/usr/bin/php", array( "tmAnalysisThreadChild.php" ) );

            exit; //never executed
        }

        // this sleep is for tempt to avoid two fork select the same segment: backward compatibility with MySql Child
        usleep(200000);
    }

    return 0;
}

function deletePid( $pid = "", $num = -1 ) {

    /**
     * @var $redisHandler Predis\Client
     */
    global $redisHandler;

    _TimeStampMsg( "Request to delete pid = " . var_export( $pid, true ) . ", num = " . var_export( $num, true ), false );

    $numDeleted = 0;
    $files      = array();

    if ( empty( $pid ) ) {

        if ( $num > 0 ) {
            _TimeStampMsg( "Deleting $num pid in the list.", false );
        }

        $files = $redisHandler->lrange( Constants_AnalysisRedisKeys::VA_CHILD_PID_LIST, 0, -1 );

    } else {

        _TimeStampMsg( "Single PID to delete: $pid", false );
        $files[ ] = $pid;

    }

    if( empty( $pid ) && $num == -1 ){

        //default params
        _TimeStampMsg( "Deleting all pid process id", false );
        $redisHandler->del( Constants_AnalysisRedisKeys::VA_CHILD_PID_LIST );
        $numDeleted = count( $files );

    } else {

        foreach( $files as $file ) { // iterate ids

            $redisHandler->lrem( Constants_AnalysisRedisKeys::VA_CHILD_PID_LIST, 0, $file  );

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

    _TimeStampMsg( "Deleted $numDeleted files", false );

}

function setNumProcesses() {

    // legge quanti processi lanciare
    $num_processes = null;
    if ( file_exists( ANALYSIS_ROOT ) ) {
        $num_processes = intval( file_get_contents( ANALYSIS_ROOT ) );
    }

    if ( !is_int( $num_processes ) ) {
        _TimeStampMsg( "WARNING : num processes from file is not numeric. Back to default value NUM_PROCESSES = 1", false );
        $num_processes = 1;
    }

    return $num_processes;
}
