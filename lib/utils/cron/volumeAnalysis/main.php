<?php
$root = realpath(dirname(__FILE__) . '/../../../../');
include_once "$root/inc/config.inc.php";
INIT::obtain();
//INIT::$DEBUG = false;
require_once INIT::$MODEL_ROOT.'/queries.php';

$db=Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
$db->debug=false;
$db->connect();
