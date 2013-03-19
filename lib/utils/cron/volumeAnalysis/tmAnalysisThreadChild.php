<?php
set_time_limit(0);
include "main.php";
include INIT::$UTILS_ROOT . "/engines/mt.class.php";
include INIT::$UTILS_ROOT . "/engines/tms.class.php";

define("PID_FOLDER", ".pidlist");
define("NUM_PROCESSES", 1);
define("NUM_PROCESSES_FILE", ".num_processes");

// PROCESS CONTROL FUNCTIONS
function isRunningProcess($pid) {
    if (file_exists("/proc/$pid")) {
        return true;
    }
    return false;
}

function processFileExists($pid) {
    $folder = PID_FOLDER;
    echo __FUNCTION__ . " : $folder/$pid ....";
    if (file_exists("$folder/$pid")) {
        echo "true\n\n";
        return true;
    }
    echo "false\n\n";
    return false;
}

//doChild();


//function isRunningProcess($pid) {
//    if (file_exists("/proc/$pid")) {
//        return true;
//    }
//    return false;
//}
//function doChild() {


$my_pid = getmypid();
$parent_pid = posix_getppid();
echo "--- (child $my_pid) : parent pid is $parent_pid\n";
$tms = new TMS(1); //1 is the id related to mymemor
$mt = null;

while (1) {
    if (!processFileExists($my_pid)) {
        die("(child $my_pid) :  EXITING!  my file does not exists anymore\n");
    }
    
    // control if parent is still running
    if (!isRunningProcess($parent_pid)) {
        echo "--- (child $my_pid) : EXITING : parent seems to be died.\n";
        exit (-1);
    }

    //lockUnlockTable("segment_translations","lock","write");
    //$segment = getNextSegmentForTMVolumeAnalysys();
    $res = getNextSegmentAndLock();
    
    if (empty($res)) {
        echo "--- (child $my_pid) : _-_getNextSegmentAndLock_-_ no segment ready for tm volume analisys: wait 3 seconds\n";
        sleep(3);
        continue;
    }
    $sid = $res['id_segment'];
    $jid = $res['id_job'];
    echo "--- (child $my_pid) : segment $sid-$jid found \n";
    $segment = getSegmentForTMVolumeAnalysys($sid, $jid);
    //print_r ($segment);
    if (empty($segment)) {
        deleteLockSegment($sid, $jid,"unlock");
        echo "--- (child $my_pid) : no segment ready for tm volume analisys: wait 3 seconds\n";
        continue;
    }

    if (is_numeric($segment) and $segment < 0) {
        deleteLockSegment($sid, $jid);
        setSegmentTranslationError($sid, $jid); // devo seetarli come done e lasciare il vecchio livello di match
        echo "--- (child $my_pid) : FATAL !!  error occurred during fetching segment : exiting\n";
        continue;
    }

    //  print_r($segment);

    $pid = $segment['pid'];

    echo "--- (child $my_pid) : fetched data for segment $sid-$jid. PID is $pid\n";

    //lock segment
    //$res = lockUnlockSegment($sid, $jid, 1);
    // lockUnlockTable("segment_translations","unlock");
    echo "--- (child $my_pid) :  segment $sid-$jid locked\n";

    $source = $segment['source'];
    $target = $segment['target'];
    $id_mt_engine = $segment['id_engine_mt'];
    $id_translator = $segment['id_translator'];
    $raw_wc = $segment['raw_word_count'];
    $fast_match_type = $segment['match_type'];
    
    //echo "text before : ". $segment['segment']. "\n";
    $text =$segment['segment']; // CatUtils::view2rawxliff($segment['segment']); // da verificare
    //echo "text after : $text\n\n";
    
    
    if ($raw_wc == 0) {
        echo "--- (child $my_pid) : empty segment. deleting lock and continue\n";
        deleteLockSegment($sid, $jid);
        continue;
    }
    $mt_engine = null;
    $mt_from_tms = 1;
    if (!empty($id_mt_engine)) {
        $mt_engine = new MT($id_mt_engine);
        $mt_from_tms = 0;
    }

    $tms_match = $tms->get($text, $source, $target, "demo@matecat.com", $mt_from_tms, $id_translator);
    //echo "TMS MATCH\n";print_r($tms_match);
    

    if (!$tms_match || !is_array($tms_match)) {
        echo "--- (child $my_pid) : error from mymemory : set error and continue\n"; // ERROR FROM MYMEMORY
        setSegmentTranslationError($sid, $jid); // devo seetarli come done e lasciare il vecchio livello di match
        deleteLockSegment($sid, $jid);
        //lockUnlockSegment($sid, $jid, 0);
        continue;
    }

    $first_match = $tms_match[0];

    $suggestion = CatUtils::view2rawxliff($first_match['raw_translation']);
    $suggestion_match = $first_match['match'];
    $suggestion_json = json_encode($tms_match);
    $suggestion_source = $first_match['created_by'];

    $tm_match_type = $first_match['match'];
    if (stripos($suggestion_source, "MT") !== false) {
        $tm_match_type = "MT";
    }

    $mt_res = array();
    $mt_match = "";


    //CODICE DUPLICATO da getContributionController::doAction . Da fattorizzare
    if (!empty($id_mt_engine)) {

        $mt = new MT($id_mt_engine);
        $mt_result = $mt->get($text, $this->source, $this->target);

        if ($mt_result[0] < 0) {
            $mt_match = '';
        } else {
            $mt_match = $mt_result[1];
            $penalty = $mt->getPenalty();
            $mt_score = 100 - $penalty;
            $mt_score.="%";

            $mt_match_res = new TMS_GET_MATCHES($text, $mt_match, $mt_score, "MT-" . $mt->getName(), date("Y-m-d"));
            $mt_res = $mt_match_res->get_as_array();
        }
    }

    $matches = array();

    if (!empty($first_match)) {
        $matches = $first_match;
    }

    if (!empty($mt_match)) {
        $matches[] = $mt_res;
        usort($matches, "compareScore");
    }
    
    $new_match_type = getNewMatchType($tm_match_type, $fast_match_type, $equivalentWordMapping);
    //echo "sid is $sid ";
    $eq_words = $equivalentWordMapping[$new_match_type] * $raw_wc / 100;
    $standard_words = $eq_words;

    if ($new_match_type == 'MT') {
        $standard_words = $equivalentWordMapping["NO_MATCH"] * $raw_wc / 100;
        //  echo "fff $raw_wc --- \$equivalentWordMapping[\"NO_MATCH\"]=".$equivalentWordMapping["100%"]." --- ". $equivalentWordMapping["100%"] * $raw_wc / 100;
    }

    //echo "suggestion before is : ". $matches['raw_translation'] ."\n";
   // $suggestion = CatUtils::view2rawxliff($matches['raw_translation']);
    //echo "suggestion after is :  $suggestion\n\n";
    
    //$suggestion_match = $matches['match'];
    //$suggestion_json = json_encode($matches);
    //$suggestion_source = $matches['created_by'];
    //if (stripos($suggestion_source, "MT") !== false) {
    //    $tm_match_type = "MT";
   // }
    
    
    echo "--- (child $my_pid) : sid=$sid --- \$tm_match_type=$tm_match_type, \$fast_match_type=$fast_match_type, \$new_match_type=$new_match_type, \$equivalentWordMapping[\$new_match_type]=" . $equivalentWordMapping[$new_match_type] . ", \$raw_wc=$raw_wc,\$standard_words=$standard_words,\$eq_words=$eq_words\n";

    $ret = CatUtils::addTranslationSuggestion($sid, $jid, $suggestion_json, $suggestion, $suggestion_match, $suggestion_source, $new_match_type, $eq_words, $standard_words, $suggestion, "DONE");
    //unlock segment
    //$res = lockUnlockSegment($sid, $jid, 0);
    
    deleteLockSegment($sid, $jid);
    echo "--- (child $my_pid) : segment $sid-$jid unlocked\n";

    $segs_in_project = countSegments($pid);
    if ($segs_in_project < 0) {
        echo "--- (child $my_pid) : WARNING !!! erro while counting segments in projects $pid skipping and continure \n";
        continue;
    }
    echo "--- (child $my_pid) : count segments in project $pid = $segs_in_project\n";
    $analyzed_report = countSegmentsTranslationAnalyzed($pid);
    // print_r ($analyzed_report);
    $segs_analyzed = $analyzed_report['num_analyzed'];
    $pid_eq_words = $analyzed_report['eq_wc'];
    $pid_standard_words = $analyzed_report['st_wc'];
    if ($segs_in_project - $segs_analyzed == 0) {
        echo "--- (child $my_pid) : analysis project $pid finished : change status to DONE\n";
        $change_res = changeProjectStatus($pid, "DONE");
        $tm_wc_res = changeTmWc($pid, $pid_eq_words, $pid_standard_words);
    }
    echo "\n\n";
}

//}

function getNewMatchType($tm_match_type, $fast_match_type, $equivalentWordMapping) {

// RATIO : modifico il valore solo se il nuovo match è strettamente migliore (in termini di percentuale pagata per parola) di quello corrente
    $tm_match_cat = "";
    $tm_rate_paid = 0;

    $fast_match_type = strtoupper($fast_match_type);
    $fast_rate_paid = $equivalentWordMapping[$fast_match_type];


    if ($tm_match_type == "MT") {
        $tm_match_cat = "MT";
        $tm_rate_paid = $equivalentWordMapping[$tm_match_type];
    }


    if (empty($tm_match_cat)) {
        $ind = intval($tm_match_type);

        if ($ind == "100") {
            $tm_match_cat = "100%";
            $tm_rate_paid = $equivalentWordMapping[$tm_match_cat];
        }

        if ($ind < 50) {
            $tm_match_cat = "NO_MATCH";
            $tm_rate_paid = $equivalentWordMapping["NO_MATCH"];
        }

        if ($ind >= 50 and $ind <= 74) {
            $tm_match_cat = "50%-74%";
            $tm_rate_paid = $equivalentWordMapping["50%-74%"];
        }

        if ($ind >= 75 and $ind <= 99) {
            $tm_match_cat = "75%-99%";
            $tm_rate_paid = $equivalentWordMapping["75%-99%"];
        }
    }

    if ($tm_rate_paid < $fast_rate_paid) {
        return $tm_match_cat;
    }
    return $fast_match_type;
}

function compareScore($a, $b) {
    return floatval($a['match']) < floatval($b['match']);
}

// PROCESS CONTROL FUNCTIONS
//function isRunningProcess($pid) {
//    if (file_exists("/proc/$pid")) {
//        return true;
//    }
//    return false;
//}
//function getPidFromFiles() {
//    $cwd = getcwd();
//    chdir(PID_FOLDER);
//    $files = glob('*'); // get all file names
//    chdir($cwd);
//    //echo __FUNCTION__ . "\n";
//    //print_r($files);
//    //echo "\n";
//    return $files;
//}
//function launchProcesses($numProcesses = 1,$equivalentWordMapping=array()) {
//    $processLaunched = 0;
//    echo __FUNCTION__ . " : parent launching $numProcesses processes - $processLaunched  already launched \n";
//
//    while ($processLaunched < $numProcesses) {
//        echo "launching .....";
//        $pid = pcntl_fork();
//        echo "DONE\n";
//        echo ":::: $pid\n";
//        if ($pid == -1) {
//            echo("PARENT FATAL !! cannot fork. Exiting!\n");
//            return -1;
//        }
//        if ($pid) {
//            // parent process runs what is here
//            $processLaunched+=1;
//        } else {
//            // child process runs what is here
//            $pid = getmypid();
//            if (!touch(PID_FOLDER . "/$pid")) {
//                echo("CHILD $pid FATAL !! cannot create child file. Exiting!\n");
//                return -2;
//            } else {
//                echo "CHILD $pid : file " . PID_FOLDER . "/$pid created !!!\n";
//            }
//            doChild($equivalentWordMapping);
//            exit;
//        }
//        sleep(1); // this sleep is for tempt to avoid two fork select the same segment: it will be better found something different. Problem table is MyISAM
//    }
//    return 0;
//}
//function deletePidFile($pid = "", $num = -1) {
//    echo __FUNCTION__ . ": pid= $pid, num= $num\n";
//    $numDeleted = 0;
//    $pidDeleted = array();
//    $files = array();
//    if (empty($pid)) {
//        $files = getPidFromFiles();
//    } else {
//        $files[] = $pid;
//    }
//
//    echo "no pid indicated --> deleting all (or num if \$num > 0) pid files\n";
//    //$files = getPidFromFiles(); // get all file names
//    foreach ($files as $file) { // iterate files
//        if (is_file(PID_FOLDER . "/$file")) {
//            echo "deleting pid $file ....";
//            unlink(PID_FOLDER . "/$file"); // delete 
//            echo "done\n";
//            $pidDeleted[] = $file;
//            if ($num > 0) {
//                $numDeleted+=1;
//                if ($numDeleted == $num) {
//                    break;
//                }
//            }
//        } else {
//            echo "WARNING  2 !!!  file $file not exists\n";
//        }
//    }
//    /* } else {
//      echo "pid selected  --> deleting pid file $pid\n";
//      $file = PID_FOLDER . "/$pid";
//      if (is_file($file)) {
//      unlink($file); // delete file
//      $pidDeleted[] = $file;
//      $numDeleted+=1;
//      } else {
//      echo "WARNING !!!  file $pid not exists\n";
//      }
//      } */
//    echo __FUNCTION__ . " exiting : deleted $numDeleted files\n";
//
//    return $pidDeleted;
//}
//function processFileExists($pid) {
//    $folder = PID_FOLDER;
//    echo __FUNCTION__ . " : $folder/$pid ....";
//    if (file_exists("$folder/$pid")) {
//        echo "true\n\n";
//        return true;
//    }
//    echo "false\n\n";
//    return false;
//}
//function setNumProcesses() {
//    // legge quanti processi lanciare
//    $num_processes = NUM_PROCESSES;
//    if (file_exists(NUM_PROCESSES_FILE)) {
//        $num_processes = intval(file_get_contents(NUM_PROCESSES_FILE));
//    }
//    if (!is_int($num_processes)) {
//        echo "WARNING : num processes from file is not numeric. Back to default value NUM_PROCESSES = " . NUM_PROCESSES . "\n";
//        $num_processes = NUM_PROCESSES;
//    }
//    return $num_processes;
//}
?>
