<?php

putenv('phpunit=1');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

declare(ticks=10);
require('inc/functions.php');

define('PROJECT_ROOT', realpath(dirname(__FILE__) . '/../') . DIRECTORY_SEPARATOR);
define('TEST_DIR', realpath(dirname(__FILE__)));

set_include_path(get_include_path() . PATH_SEPARATOR . TEST_DIR);
require_once(PROJECT_ROOT . 'lib/Bootstrap.php');

if (getenv('USE_LOCAL_DEVELOPMENT_ENV')) {
    Bootstrap::start();
} elseif (getenv('TRAVIS')) {
    Bootstrap::start(new SplFileInfo(TEST_DIR . '/inc/config.travis.ini'), new SplFileInfo(TEST_DIR . '/inc/task_manager_config.ini'));
} else {
    Bootstrap::start(new SplFileInfo(TEST_DIR . '/inc/config.local.ini'), new SplFileInfo(TEST_DIR . '/inc/task_manager_config.ini'));
}

global $klein;
try {
    $klein = mockKleinFramework();
    disableAmqWorkerClientHelper();
    setupSignalHandler();
} catch (ReflectionException $ignore) {
}