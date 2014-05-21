<?php
set_time_limit(0);
require "main.php";

$my_pid = getmypid();

if ( !file_exists( Constants_Daemons::PID_FOLDER ) ) {
    mkdir( Constants_Daemons::PID_FOLDER );
}

/**
 * WARNING on 2 frontend web server or in an architecture where the daemons runs in a place different from the web server
 * this should be put in a shared location ( memcache/redis/ntfs/mysql ) and a service should be
 * queried for know that number
 */
file_put_contents( Constants_Daemons::PID_FOLDER . "/" . Constants_Daemons::TM_MASTER_PID_FILE, $my_pid );

// PROCESS CONTROL FUNCTIONS
function isRunningProcess($pid) {
    if (file_exists("/proc/$pid")) {
        return true;
    }
    return false;
}

function processFileExists($pid) {
    $folder = Constants_Daemons::PID_FOLDER;
    echo __FUNCTION__ . " : $folder/$pid ....";
    if (file_exists("$folder/$pid")) {
        echo "true\n\n";
        return true;
    }
    echo "false\n\n";
    return false;
}


echo "(parent $my_pid) : ------------------- cleaning old resources -------------------\n\n";
// delete other processes of old execution
$deletedPidList = deletePidFile();
// wait all old children exits
do {
    foreach ($deletedPidList as $k => $pid) {
        // check il process pid ended
        if ( !isRunningProcess($pid) ) {
            unset($deletedPidList[$k]);
            echo "(parent $my_pid) : pid $pid unset\n";
        }
    }
} while (!empty($deletedPidList));

resetLockSegment();

echo "(parent $my_pid) : -------------------cleaning old resources DONE-------------------\n\n";


while (1) {
    echo "(parent $my_pid) : PARENT MONITORING START";

    //avoid zombies : the parent is aware of the death of one of the children
    $dead = pcntl_waitpid(-1, $status, WNOHANG);
    if ($dead > 0) {
        echo "(parent $my_pid) : child $dead exited: deleting file ....";
        deletePidFile($dead);
        echo "DONE\n";
    }
    $numProcesses = setNumProcesses();
    $childrenRunningList = getPidFromFiles();

    //__________________________________________________________________________________
    // questo pezzo di codice dovrebbe essere inutile vista la presenza di pcntl_waitpid
    foreach ($childrenRunningList as $k => $v) {
        if (!isRunningProcess($v)) {
            echo "(parent $my_pid) : child $v not running. Delete file .....";
            deletePidFile($v);
            unset($childrenRunningList[$k]);
            echo "DONE\n";
        }
    }
    //__________________________________________________________________________________

    $numProcessesNow = count($childrenRunningList);
    $numProcessesDiff = $numProcessesNow - $numProcesses;
    echo "(parent $my_pid) :  $numProcesses to launch - $numProcessesNow already launched , diff is $numProcessesDiff\n ";
    $numProcessesToLaunchOrDelete = abs($numProcessesDiff);
    switch ($numProcessesDiff) {
        case $numProcessesDiff < 0:
            //launch abs($numProcessesDiff) processes
            echo "(parent $my_pid) : need to launch additional $numProcessesToLaunchOrDelete processes\n";
            $res = launchProcesses($numProcessesToLaunchOrDelete, $equivalentWordMapping);
            if ($res < 0) {
                die("(parent $my_pid) : ERROR OCCURRED :  PARENT EXITING !!");
            }
            break;
        case $numProcessesDiff > 0:
            echo "(parent $my_pid) : need to delete $numProcessesToLaunchOrDelete processes\n";
            $res = deletePidFile("", $numProcessesToLaunchOrDelete);
            break;
        default:
            echo "(parent $my_pid) : no pid to delete everithing  works well\n";
    }

    echo "(parent) : PARENT MONITORING PAUSE ($my_pid) sleeping ....\n\n";
    sleep(5);
}


function getPidFromFiles() {
    $cwd = getcwd();
    chdir( Constants_Daemons::PID_FOLDER );
    $files = glob('*'); // get all file names
    chdir($cwd);
    return $files;
}

function launchProcesses($numProcesses = 1, $equivalentWordMapping = array()) {
    $processLaunched = 0;
    echo __FUNCTION__ . " : parent launching $numProcesses processes - $processLaunched  already launched \n";

    while ($processLaunched < $numProcesses) {
        echo "launching .....";
        $pid = pcntl_fork();
        
        if ($pid == -1) {
            echo("PARENT FATAL !! cannot fork. Exiting!\n");
            return -1;
        }
        if ($pid) {
            echo "DONE pid is $pid\n";
            // parent process runs what is here
            $processLaunched+=1;
        } else {
            // child process runs what is here
            $pid = getmypid();
            if (!touch( Constants_Daemons::PID_FOLDER . "/$pid")) {
                echo "(child $pid) : FATAL !! cannot create child file. Exiting!\n";
                return -2;
            } else {
                echo "(child $pid) : file " . Constants_Daemons::PID_FOLDER . "/$pid created !!!\n";
            }

            try{
                MemcacheHandler::getInstance();
                pcntl_exec("/usr/bin/php",array("tmAnalysisThreadChild.php"));
            } catch( Exception $e ){
                echo $e->getMessage() . "\n";
                echo $e->getTraceAsString() . "\n";
                echo "(child $pid) : Fallback to Mysql Version\n";
                pcntl_exec("/usr/bin/php",array("tmAnalysisThreadChildMySQL.php"));
            }

            exit;
        }
        usleep(200000); // this sleep is for tempt to avoid two fork select the same segment: it will be better found something different. Problem table is MyISAM
    }
    return 0;
}

function deletePidFile($pid = "", $num = -1) {
    echo "\n".__FUNCTION__ . ": pid= $pid, num= $num\n";
    $numDeleted = 0;
    $pidDeleted = array();
    $files = array();
    if (empty($pid)) {
        $files = getPidFromFiles();
    } else {
        $files[] = $pid;
    }

    echo "no pid indicated --> deleting all (or num if \$num > 0) pid files\n";

    foreach ( $files as $file ) { // iterate files

        if( in_array( $file, array( Constants_Daemons::FAST_PID_FILE, Constants_Daemons::TM_MASTER_PID_FILE ) ) ) continue;

        if ( is_file( Constants_Daemons::PID_FOLDER . "/$file" ) ) {
            echo "deleting pid $file ....";
            unlink( Constants_Daemons::PID_FOLDER . "/$file" ); // delete
            echo "done\n";
            $pidDeleted[ ] = $file;
            if ( $num > 0 ) {
                $numDeleted += 1;
                if ( $numDeleted == $num ) {
                    break;
                }
            }
        } else {
            echo "WARNING  2 !!!  file $file not exists\n";
        }
    }
    echo __FUNCTION__ . " exiting : deleted $numDeleted files\n\n";

    return $pidDeleted;
}

function setNumProcesses() {
    // legge quanti processi lanciare
    $num_processes = Constants_Daemons::NUM_PROCESSES;
    if ( file_exists( Constants_Daemons::NUM_PROCESSES_FILE ) ) {
        $num_processes = intval( file_get_contents( Constants_Daemons::NUM_PROCESSES_FILE ) );
    }
    if (!is_int($num_processes)) {
        echo "WARNING : num processes from file is not numeric. Back to default value NUM_PROCESSES = " . Constants_Daemons::NUM_PROCESSES . "\n";
        $num_processes = Constants_Daemons::NUM_PROCESSES;
    }
    return $num_processes;
}

?>
