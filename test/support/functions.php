<?php

function setTestConfigFile() {
    renameFile('config.ini', 'config.development.ini');
    renameFile('config.test.ini', 'config.ini');
}

function renameFile( $source, $destination) {
    $source = PROJECT_ROOT . '/inc/' . $source;
    $destination = PROJECT_ROOT . '/inc/' . $destination ;

    if (! rename($source, $destination) ){
        throw new Exception("Error moving $source to $destination");
    }
}

function restoreDevelopmentConfigFile() {
    renameFile('config.ini', 'config.test.ini');
    renameFile('config.development.ini', 'config.ini');
}

function test_file_path( $file ) {
  return realpath(TEST_DIR . '/support/files/' . $file );
}

function prepareTestDatabase() {
  $dev_ini = parse_ini_file(PROJECT_ROOT . '/inc/config.ini', true);
  $test_ini = parse_ini_file(PROJECT_ROOT . '/inc/config.test.ini', true);

  // TODO: move this TEST_URL_BASE config somewhere else
  if ( @$test_ini['TEST_URL_BASE'] != null ) {
      $GLOBALS['TEST_URL_BASE'] = $test_ini['TEST_URL_BASE'];
  }
  else {
      echo "** TEST_URL_BASE is not set, using localhost \n" ;
      $GLOBALS['TEST_URL_BASE'] = 'localhost';
  }

  if ( $dev_ini['ENV'] != 'development') {
      throw new Exception('Source config must be development');
  }

  if ( $test_ini['ENV'] != 'test') {
      throw new Exception('Destination config must be test');
  }

  if (
      $dev_ini['development']['DB_DATABASE'] == $test_ini['test']['DB_DATABASE']
  ) {
      throw new Exception("Development database and test database cannot have the same name");
  }

  $testDatabase = new SchemaCopy($test_ini['test']);
  $devDatabase = new SchemaCopy($dev_ini['development']);

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

function integrationSetChunkAsComplete( $options ) {
    $test = new CurlTest();

    if ( key_exists( 'headers' , $options ) ) {
        $test->headers = $options['headers'];
    }

    $test->path = '?action=Features_ProjectCompletion_SetChunkCompleted';
    $test->method = 'POST';
    $test->params = array(
        'id_job' => $options['params']['id_job'],
        'password' => $options['params']['password']
    );

    $response = $test->getResponse();

    if ( !in_array( (int) $response['code'], array(200, 201) )) {
        throw new Exception( "invalid response code " . $response['code'] );
    }

    return json_decode( $response['body'] )  ;
}

function integrationCreateTestProject( $options=array() ) {
  $test = new CurlTest();

  if ( key_exists( 'headers' , $options ) ) {
      $test->headers = $options['headers'];
  }

  $test->path = '/api/new' ;
  $test->method = 'POST';
  $test->params = array(
    'project_name' => 'foo',
    'target_lang' => 'it',
    'source_lang' => 'en'
  );

  if ( key_exists('files', $options) ) {
      $test->files = $options['files'];
  } else {
      $test->files[] = test_file_path('xliff/amex-test.docx.xlf');
  }

  $response = $test->getResponse();

  return json_decode( $response['body'] ) ;
}

function integrationSetSegmentsTranslated( $project_id ) {
    $chunksDao = new Chunks_ChunkDao( Database::obtain() ) ;
    $chunks = $chunksDao->getByProjectID( $project_id );

    foreach( $chunks as $chunk ) {
        $segments = $chunk->getSegments();
        foreach( $segments as $segment) {
            integrationSetTranslation( array(
                'id_segment' => $segment->id ,
                'id_job' => $chunk->id,
                'password' => $chunk->password,
                'status' => 'translated'
            ) ) ;
        }
    }

    return $chunks ;

}

function integrationSetTranslation($options) {
  $default = array(
    // 'id_segment' => 205,
    // 'id_job' => 12,
    // 'password' => '8ec640b5c874',
    // 'status' => 'draft',
    'translation' => "simulated translation during tests",
    'id_translator' => false,
    'version' => time() ,
    'propagate' => false,
    'status' => 'draft'
  );

  $test = new CurlTest();
  $test->params = array_merge( $default, $options);
  $test->method = 'POST';
  $test->path = '?action=setTranslation';
  $response =  $test->getResponse();


  if ( !in_array( (int) $response['code'], array(200, 201) )) {
      throw new Exception( "invalid response code " . $response['code'] );
  }

  $body = json_decode( $response['body'], true );
  if ( !empty($body['errors']) ) {
      throw new Exception( "Ajax error detected " . var_export( $body['errors'], true ) );
  }
  return $body ;


}
