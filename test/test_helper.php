<?php

putenv('phpunit=1');

define('PROJECT_ROOT', realpath( dirname(__FILE__)) . '/../' );
define('TEST_DIR', realpath( dirname(__FILE__)));

set_include_path ( get_include_path() . PATH_SEPARATOR . TEST_DIR );

require( 'lib/IntegrationTest.php');
require( 'functions.php');
require( 'SchemaCopy.php');
require( 'SeedLoader.php');

prepareTestDatabase();

setEnvFile('test');

require( PROJECT_ROOT . 'inc/Bootstrap.php' );
register_shutdown_function(function() {
  echo "** Resetting environment to development\n\n" ;
  restoreEnvFile();
});

Bootstrap::start();

// delete all stored work files
shell_exec("rm -rf " . INIT::$CACHE_REPOSITORY );
shell_exec("rm -rf " . INIT::$FILES_REPOSITORY );

function startConnection() {
    $conn = Database::obtain (
        INIT::$DB_SERVER, INIT::$DB_USER,
        INIT::$DB_PASS, INIT::$DB_DATABASE
    );
    $conn->getConnection();
}

startConnection();
