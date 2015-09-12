<?php

function test_file_path( $file ) {
  return realpath(TEST_DIR . '/support/files/' . $file );
}

function prepareTestDatabase() {
  $config = parse_ini_file(PROJECT_ROOT . '/inc/config.ini', true);
  $testDatabase = new SchemaCopy($config['test']);
  $devDatabase = new SchemaCopy($config['development']);

  prepareTestSchema($testDatabase, $devDatabase);
  loadSeedData($testDatabase);
}

function loadSeedData( $database ) {
  $seeder = new SeedLoader( $database );

  $seeder->loadEngines();
}

function prepareTestSchema($testDatabase, $devDatabase) {
  $testDatabase->dropDatabase();
  $testDatabase->createDatabase();

  $tables = $devDatabase->getTablesStatements();
  foreach($tables as $k => $v) {
    $command = $v[0][1];
    $testDatabase->execSql($command);
  }
}

function setEnvFile($env) {
  file_put_contents(PROJECT_ROOT . 'inc/.env', $env);
}
