<?php

$matecatHome = getenv("MATECAT_HOME");
if ($matecatHome === false) {
    fwrite(STDERR, "MATECAT_HOME environment variable is not set\n");
    exit(1);
}

include_once realpath($matecatHome) . "/lib/Bootstrap.php";
Bootstrap::start();

use Utils\AsyncTasks\Workers\Analysis\FastAnalysis;
use Utils\Registry\AppConfig;

if ( AppConfig::$FAST_ANALYSIS_MEMORY_LIMIT != null ) {
    ini_set( "memory_limit", AppConfig::$FAST_ANALYSIS_MEMORY_LIMIT );
}


FastAnalysis::getInstance( @$argv[ 1 ] )->main();
