<?php
$root = realpath(dirname(__FILE__) . '/../../../../');
include_once "$root/inc/config.inc.php";
INIT::obtain();

require_once INIT::$MODEL_ROOT.'/queries.php';

$db=Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
$db->debug=INIT::$DEBUG;
$db->connect();
