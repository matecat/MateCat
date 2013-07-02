<?php
$root = realpath(dirname(__FILE__) . '/../../../../');
include_once "$root/inc/config.inc.php";
INIT::obtain();


require_once INIT::$UTILS_ROOT.'/log.class.php';
require_once INIT::$UTILS_ROOT.'/utils.class.php';
require_once INIT::$CONTROLLER_ROOT.'/frontController.php';
require_once INIT::$MODEL_ROOT.'/Database.class.php';
require_once INIT::$MODEL_ROOT.'/queries.php';


$db=Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
$db->debug=INIT::$DEBUG;
$db->connect();


$equivalentWordMapping = array();
$equivalentWordMapping["NO_MATCH"] = 100;
$equivalentWordMapping['50%-74%'] = 100;
$equivalentWordMapping['75%-99%'] = 60;
$equivalentWordMapping['100%'] = 30;
$equivalentWordMapping['REPETITIONS'] = 30;
$equivalentWordMapping['INTERNAL'] = 60;
$equivalentWordMapping['MT'] = 85;


/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
?>