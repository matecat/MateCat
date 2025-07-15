<?php

include_once realpath( dirname( __FILE__ ) . '/../../../' ) . "/inc/Bootstrap.php";
Bootstrap::start();

use Utils\Registry\AppConfig;
use Utils\AsyncTasks\Workers\Analysis\FastAnalysis;

if ( AppConfig::$FAST_ANALYSIS_MEMORY_LIMIT != null ) {
    ini_set( "memory_limit", AppConfig::$FAST_ANALYSIS_MEMORY_LIMIT );
}


FastAnalysis::getInstance( @$argv[ 1 ] )->main();
