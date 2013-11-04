<?php


include '/var/www/cattool/inc/config.inc.php';
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
$pStruct[ 'job_to_split' ]                = 5115;
$pStruct[ 'job_to_split_pass' ]           = 'UneJcrBI4CBglTVW';


$pm->getSplitData( $pStruct, 5 );

$pm->applySplit($pStruct);

var_export( $pStruct );

