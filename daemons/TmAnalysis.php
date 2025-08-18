<?php

include_once realpath( getenv( "MATECAT_HOME" ) ) . "/lib/Bootstrap.php";
Bootstrap::start();

use Utils\TaskRunner\TaskManager;

TaskManager::getInstance( @$argv[ 1 ], @$argv[ 2 ] )->main();
