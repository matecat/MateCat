<?php

include_once realpath( dirname( __FILE__ ) . '/../../../' ) . "/inc/Bootstrap.php";
Bootstrap::start();

if ( AppConfig::$FAST_ANALYSIS_MEMORY_LIMIT != null ) {
    ini_set( "memory_limit", AppConfig::$FAST_ANALYSIS_MEMORY_LIMIT );
}

use Utils\AsyncTasks\Workers\Analysis\FastAnalysis;
use Utils\Registry\AppConfig;

FastAnalysis::getInstance( @$argv[ 1 ] )->main();
