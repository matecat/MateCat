<?php

putenv('phpunit=1');

define('PROJECT_ROOT', realpath( dirname(__FILE__)) . '/../' );
define('TEST_DIR', PROJECT_ROOT . 'tests/');

set_include_path ( get_include_path() . PATH_SEPARATOR . TEST_DIR );

require( 'lib/IntegrationTest.php');
require( 'functions.php');
require( 'SchemaCopy.php');
require( 'SeedLoader.php');

prepareTestDatabase();

setEnvFile('test');

require( PROJECT_ROOT . 'inc/Bootstrap.php' );
register_shutdown_function(function() {
  echo "Resetting environment to development\n\n" ;
  setEnvFile('development');
});

Bootstrap::start();
