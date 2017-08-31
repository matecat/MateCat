<?php
putenv( 'phpunit=1' );

declare( ticks = 10 );
require( 'functions.php' );
setupSignalHandler();

define( 'PROJECT_ROOT', realpath( dirname( __FILE__ ) . '/../' ) . DIRECTORY_SEPARATOR );
define( 'TEST_DIR', realpath( dirname( __FILE__ ) ) );

set_include_path( get_include_path() . PATH_SEPARATOR . TEST_DIR );

require( 'lib/IntegrationTest.php' );
require( 'php_versions_override.php' );

require( PROJECT_ROOT . 'inc/Bootstrap.php' );

register_shutdown_function( function () {
    echo "** Resetting environment to development\n\n";
    restoreDevelopmentConfigFile();
} );

setTestConfigFile();

Bootstrap::start();

require_once INIT::$MODEL_ROOT . '/queries.php';

// Configure TEST_URL_BASE ;

function startConnection() {
    $conn = Database::obtain(
            INIT::$DB_SERVER, INIT::$DB_USER,
            INIT::$DB_PASS, INIT::$DB_DATABASE
    );
    $conn->getConnection();
}

startConnection();

TestHelper::init() ;

INIT::$DQF_ID_PREFIX = INIT::$DQF_ID_PREFIX . '-test-' . rand(1,10000);
