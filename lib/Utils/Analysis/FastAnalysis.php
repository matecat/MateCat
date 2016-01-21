<?php

ini_set( "memory_limit", "4096M" );
set_time_limit(0);

include_once realpath( dirname( __FILE__ ) . '/../../../' ) . "/inc/Bootstrap.php";
Bootstrap::start();

use Analysis\Workers\FastAnalysis;

FastAnalysis::getInstance( @$argv[1] )->main();