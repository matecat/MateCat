<?php
$r=dirname( dirname(__FILE__) );
include "$r/inc/config.inc.php";
if ( ( isset($argv[1]) && (int)$argv[1]==0 )  ){
//    die ("Usage $argv[0] [--apply]");
}

$job_num=(int)$argv[1];
$job_pass= $argv[2];
$num_split = $argv[3];
$apply_split=isset($argv[4]) && $argv[4]=="--apply";

@INIT::obtain();
include_once INIT::$UTILS_ROOT . '/cat.class.php';
include_once INIT::$UTILS_ROOT . '/utils.class.php';
include_once INIT::$UTILS_ROOT . '/log.class.php';
include_once INIT::$MODEL_ROOT . '/queries.php';
ini_set('error_reporting', E_ALL );
$db = Database::obtain ( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
$db->debug = INIT::$DEBUG;
$db->connect ();

include INIT::$UTILS_ROOT . '/RecursiveArrayObject.php';
include INIT::$UTILS_ROOT . '/ProjectManager.php';

error_reporting(E_ALL &~ E_DEPRECATED);

$pm = new ProjectManager();


/*
 http://matecat.local/translate/ESPANOL_VERY_BIG_2900_segs.sdlxliff/en-US-fr-FR/5294-e01e940bdca8
 */

$pStruct                                  = $pm->getProjectStructure();
$pStruct[ 'job_to_split' ]                = $job_num;
$pStruct[ 'job_to_split_pass' ]           = $job_pass;


$pm->getSplitData( $pStruct, $num_split );

if ($apply_split){
    $pm->applySplit($pStruct);
}


var_export( json_encode($pStruct['split_result']) );
echo "\n";
echo "\n";
echo "\n";
var_export( $pStruct['split_result']->getArrayCopy() );
