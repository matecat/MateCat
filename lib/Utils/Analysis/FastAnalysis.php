<?php

include_once realpath( dirname( __FILE__ ) . '/../../../' ) . "/inc/Bootstrap.php";
Bootstrap::start();

if ( INIT::$FAST_ANALYSIS_MEMORY_LIMIT != null ) {
    ini_set( "memory_limit", INIT::$FAST_ANALYSIS_MEMORY_LIMIT );
}

use Utils\AsyncTasks\Workers\Analysis\FastAnalysis;

FastAnalysis::getInstance( @$argv[ 1 ] )->main();
