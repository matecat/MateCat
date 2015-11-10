<?php
if( !@include_once 'inc/Bootstrap.php')
	header("Location: configMissing");

Bootstrap::start();

$controller = controller::getInstance ();
$controller->doAction ();
$controller->finalize ();
