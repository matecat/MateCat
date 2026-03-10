<?php

include_once realpath( getenv( "MATECAT_HOME" ) ) . "/lib/Bootstrap.php";
Bootstrap::start();

use Utils\AsyncTasks\Workers\Analysis\FastAnalysis;
use Utils\Registry\AppConfig;

if ( AppConfig::$FAST_ANALYSIS_MEMORY_LIMIT != null ) {
    ini_set( "memory_limit", AppConfig::$FAST_ANALYSIS_MEMORY_LIMIT );
}


FastAnalysis::getInstance( @$argv[ 1 ] )->main();
