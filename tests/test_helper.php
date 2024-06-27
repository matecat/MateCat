<?php
putenv( 'phpunit=1' );
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

declare( ticks = 10 );
require( 'inc/functions.php' );

setupSignalHandler();

define( 'PROJECT_ROOT', realpath( dirname( __FILE__ ) . '/../' ) . DIRECTORY_SEPARATOR );
define( 'TEST_DIR', realpath( dirname( __FILE__ ) ) );

set_include_path( get_include_path() . PATH_SEPARATOR . TEST_DIR );
require( PROJECT_ROOT . 'inc/Bootstrap.php' );

if( getenv( 'TRAVIS' ) ){
    Bootstrap::start( new SplFileInfo( TEST_DIR . '/inc/config.travis.ini' ), new SplFileInfo( TEST_DIR . '/inc/task_manager_config.ini' ) );
} else {
    Bootstrap::start( new SplFileInfo( TEST_DIR . '/inc/config.local.ini' ), new SplFileInfo( TEST_DIR . '/inc/task_manager_config.ini' ) );
}

disableAmqWorkerClientHelper();