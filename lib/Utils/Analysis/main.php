<?php
$root = realpath(dirname(__FILE__) . '/../../../');
include_once "$root/inc/Bootstrap.php";
Bootstrap::start();
require_once INIT::$MODEL_ROOT.'/queries.php';
declare ( ticks = 10 );

if (! function_exists ( 'pcntl_signal' )) {
    $msg = "****** PCNTL EXTENSION NOT LOADED. KILLING THIS PROCESS COULD CAUSE UNPREDICTABLE ERRORS ******";
    _TimeStampMsg( $msg );
} else {

    pcntl_signal( SIGTERM, 'sigSwitch' );
    pcntl_signal( SIGINT, 'sigSwitch' );
    pcntl_signal( SIGHUP, 'sigSwitch' );

    $msg = str_pad( " " . getmypid() . " Signal Handler Installed ", 50, "-", STR_PAD_BOTH );
    _TimeStampMsg( $msg );

}

function _TimeStampMsg( $msg, $log = true ) {
    if( $log ) Log::doLog( $msg );
    echo "[" . date( DATE_RFC822 ) . "] " . $msg . "\n";
}