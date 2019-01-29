<?php

use Exceptions\ControllerReturnException;

if( !@include_once 'inc/Bootstrap.php')
	header("Location: configMissing");

Bootstrap::start();

$controller = controller::getInstance ();
try {
    $controller->doAction ();
} catch ( ControllerReturnException $e ) {
    // Do nothing. This mimics the behaviour of return -1 in various controllers,
    // for refactoring purpose.
}
$controller->finalize ();
