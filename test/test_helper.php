<?php
putenv( 'phpunit=1' );

declare( ticks = 10 );
require( 'support/functions.php' );
setupSignalHandler();

define( 'PROJECT_ROOT', realpath( dirname( __FILE__ ) . '/../' ) . DIRECTORY_SEPARATOR );
define( 'TEST_DIR', realpath( dirname( __FILE__ ) ) );

set_include_path( get_include_path() . PATH_SEPARATOR . TEST_DIR );

require( 'support/lib/IntegrationTest.php' );
require( 'support/php_versions_override.php' );

require( PROJECT_ROOT . 'inc/Bootstrap.php' );

register_shutdown_function( function () {
    echo "** Resetting environment to development\n\n";
    restoreDevelopmentConfigFile();
} );


setTestConfigFile();
Bootstrap::start();

$testHelper = new TestHelper();
$testHelper->resetDb();
$test_ini = $testHelper->parseConfigFile( 'test' );

function startConnection($test_ini) {
    $conn = Database::obtain(
        isset($test_ini['test']['DB_SERVER']) ? $test_ini['test']['DB_SERVER'] : INIT::$DB_SERVER,
        isset($test_ini['test']['DB_USER']) ? $test_ini['test']['DB_USER'] : INIT::$DB_USER,
        isset($test_ini['test']['DB_PASS']) ? $test_ini['test']['DB_PASS'] :INIT::$DB_PASS,
        isset($test_ini['test']['DB_DATABASE']) ? $test_ini['test']['DB_DATABASE'] : INIT::$DB_DATABASE
    );
    $conn->getConnection();
}

startConnection($test_ini);

INIT::$DQF_ID_PREFIX = INIT::$DQF_ID_PREFIX . '-test-' . rand(1,10000);
