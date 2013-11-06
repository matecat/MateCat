<?php
$r=dirname( dirname(__FILE__) );
include "$r/inc/config.inc.php";
if (isset($argv[1]) && $argv[1]!="--apply"){
    die ("Usage $argv[0] [--apply]");
}
$apply_split=isset($argv[1]) && $argv[1]=="--apply";

@INIT::obtain();
include_once INIT::$UTILS_ROOT . '/cat.class.php';
include_once INIT::$UTILS_ROOT . '/utils.class.php';
include_once INIT::$UTILS_ROOT . '/log.class.php';
include_once INIT::$MODEL_ROOT . '/queries.php';
ini_set('error_reporting', E_ALL & ~E_DEPRECATED );
$db = Database::obtain ( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
$db->debug = INIT::$DEBUG;
$db->connect ();

include INIT::$UTILS_ROOT . '/RecursiveArrayObject.php';
include INIT::$UTILS_ROOT . '/ProjectManager.php';

$pm = new ProjectManager();


/*
 http://matecat.local/translate/EN_2_segments.txt/en-US-it-IT/5163-4d960596548e
 */

$pStruct                                  = $pm->getProjectStructure();
$pStruct[ 'job_to_split' ]                = 5163;
$pStruct[ 'job_to_split_pass' ]           = '4d960596548e';


$pm->getSplitData( $pStruct, 3 );

if ($apply_split){
    $pm->applySplit($pStruct);
}


var_export( $pStruct );