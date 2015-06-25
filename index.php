<?php
if( !@include_once 'inc/Bootstrap.php')
	header("Location: configMissing");

Bootstrap::start();
$db = Database::obtain ( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
$db->connect ();

Log::$uniqID = ( isset( $_COOKIE['PHPSESSID'] ) ? substr( $_COOKIE['PHPSESSID'], 0 , 13 ) : uniqid() );

$controller = controller::getInstance ();
$controller->doAction ();
$controller->finalize ();
$db->close();