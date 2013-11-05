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



$pStruct                                  = $pm->getProjectStructure();
$pStruct[ 'job_to_split' ]                = 5104;
$pStruct[ 'job_to_split_pass' ]           = '526a7bbdf026952043998601';


$pm->getSplitData( $pStruct, 5 );

if ($apply_script){
    $pm->applySplit($pStruct);
}


var_export( $pStruct );