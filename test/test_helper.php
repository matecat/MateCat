<?php
putenv('phpunit=1');

declare( ticks = 10 );
require( 'functions.php' );
setupSignalHandler();

define( 'PROJECT_ROOT', realpath( dirname(__FILE__) . '/../' ) . DIRECTORY_SEPARATOR );
define( 'TEST_DIR', realpath( dirname(__FILE__) ) );

set_include_path ( get_include_path() . PATH_SEPARATOR . TEST_DIR );

require( 'lib/IntegrationTest.php');
require( 'php_versions_override.php');


require( PROJECT_ROOT . 'inc/Bootstrap.php' );

register_shutdown_function(function() {
  echo "** Resetting environment to development\n\n" ;
  restoreDevelopmentConfigFile();
});

setTestConfigFile();

Bootstrap::start();
require_once INIT::$MODEL_ROOT . '/queries.php';

// Configure TEST_URL_BASE ;
$test_ini = TestHelper::parseConfigFile('test') ;

if ( @$test_ini['TEST_URL_BASE'] != null ) {
    $GLOBALS['TEST_URL_BASE'] = $test_ini['TEST_URL_BASE'];
}
else {
    echo "** TEST_URL_BASE is not set, using localhost \n" ;
    $GLOBALS['TEST_URL_BASE'] = 'localhost';
}

$schemaHelper = new SchemaCopy($test_ini['test']) ;
$schemaHelper->truncateAllTables() ;

$seeder = new SeedLoader( $schemaHelper );
$seeder->loadEngines();

function startConnection() {
    $conn = Database::obtain (
        INIT::$DB_SERVER, INIT::$DB_USER,
        INIT::$DB_PASS, INIT::$DB_DATABASE
    );
    $conn->getConnection();
}

startConnection();
