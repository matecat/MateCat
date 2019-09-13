<?php

include_once realpath( dirname( __FILE__ ) . '/../../../' ) . "/inc/Bootstrap.php";
Bootstrap::start();

use TaskRunner\TaskManager;

TaskManager::getInstance( @$argv[1], @$argv[2] )->main();
