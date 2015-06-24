<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 24/06/15
 * Time: 14.26
 * 
 */
$root = realpath(dirname(__FILE__) . '/../../../../../');
require_once "$root/inc/Bootstrap.php";

Bootstrap::start();

//---------------------------


define( 'DEFAULT_NUM_WORKERS', include( '../../DefaultNumTMWorkers.php' ) );
define( 'NUM_WORKERS', INIT::$UTILS_ROOT . "/Analysis/.num_processes" );


$queueHandler = new Analysis_QueueHandler();
$consumers = $queueHandler->getConsumerCount();

$desiredCount = DEFAULT_NUM_WORKERS;
if ( file_exists( NUM_WORKERS ) ) {
    $desiredCount = intval( file_get_contents( NUM_WORKERS ) );
}

if( $desiredCount > $consumers ){
    exit(127);
} elseif( empty( $desiredCount ) ){
    exit(1);
}
exit(0);