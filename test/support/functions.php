<?php

function setTestConfigFile() {
    //copyFile('config.ini', 'config.development.ini');
    copyFile('config.test.ini', 'config.ini');
}

function copyFile( $source, $destination) {
    $source = PROJECT_ROOT . '/inc/' . $source;
    $destination = PROJECT_ROOT . '/inc/' . $destination ;

    if (! copy($source, $destination) ){
        throw new Exception("Error copying $source to $destination");
    }
}

/**
 * @param Chunks_ChunkStruct $chunk
 *
 * @return Segments_SegmentStruct
 */
function firstSegmentOfChunk( Chunks_ChunkStruct $chunk ) {
    $segments = $chunk->getSegments();
    return $segments[0];
}

function restoreDevelopmentConfigFile() {
    //copyFile('config.ini', 'config.test.ini');
    copyFile('config.development.ini', 'config.ini');
}

function test_file_path( $file ) {
  return realpath(TEST_DIR . '/support/files/' . $file );
}

/**
 * Performs a test schema reset
 * @throws Exception
 */
function prepareTestDatabase() {
  $dev_ini = parse_ini_file(PROJECT_ROOT . '/inc/config.development.ini', true);
  $test_ini = parse_ini_file(PROJECT_ROOT . '/inc/config.test.ini', true);

  // TODO: move this TEST_URL_BASE config somewhere else
  if ( @$test_ini['TEST_URL_BASE'] != null ) {
      $GLOBALS['TEST_URL_BASE'] = $test_ini['TEST_URL_BASE'];
  }
  else {
      echo "** TEST_URL_BASE is not set, using localhost \n" ;
      $GLOBALS['TEST_URL_BASE'] = 'localhost';
  }

  if ( $dev_ini['ENV'] != 'development' ) {
      throw new Exception('Source config must be development');
  }

  if ( $test_ini['ENV'] != 'test') {
      throw new Exception('Destination config must be test');
  }

  $testDatabase = new SchemaCopy( $test_ini['test'] );
  $devDatabase = new SchemaCopy( $dev_ini[ 'development' ] );

  // prepareTestSchema($testDatabase, $devDatabase);
  // loadSeedData($testDatabase);

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

    if ( array_key_exists( 'headers' , $options ) ) {
        $test->headers = $options['headers'];
    }

    if ( array_key_exists( 'referer', $options ) ) {
        $test->referer = $options['referer'];
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

function do_file_conversion( $params ) {
    $upload_session = $params['upload_session'];
    unset( $params['upload_session'] );
    
    $curlTest = new CurlTest();
    
    $curlTest->path = '/index.php?action=convertFile' ;
    $curlTest->method = 'POST' ;
    $curlTest->params = $params ; 
    $curlTest->cookies[] = array('upload_session', $upload_session );

    $conversionResponse = $curlTest->getResponse();
    return $conversionResponse ; 
}

function prepare_file_in_upload_folder( $path, $upload_session )  { 
    $destDir = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $upload_session . DIRECTORY_SEPARATOR  ; 
    if ( !is_dir( $destDir ) ) {
        mkdir( $destDir );
    }
    $dest = $destDir . basename( $path );
    copy( $path, $dest ) ; 
}

function createProjectWithUIParams( $params ) {
    $upload_session = $params['upload_session'];
    $files = $params['files'] ; 
    
    unset( $params['upload_session'] );
    unset( $params['files'] );

    $curlTest = new CurlTest();

    $curlTest->path = '/index.php?action=createProject' ;
    $curlTest->params = $params ;

    $curlTest->cookies[] = array('upload_session', $upload_session );
    $curlTest->files = $files ; 

    $response = $curlTest->getResponse();
    return $response ;
}

/**
 * Creates a project via API call
 */
function integrationCreateTestProject( $options=array() ) {
    $test = new CurlTest();

    $test->method = 'POST';
    $test->path = '/api/new' ;

    if ( array_key_exists( 'headers' , $options ) ) {
        $test->headers = $options['headers'];
    }

    if ( array_key_exists( 'path' , $options ) ) {
        $test->path = $options['path'];
    }

    $test->params = array(
            'project_name' => 'foo',
            'target_lang' => 'it-IT',
            'source_lang' => 'en-US'
    );

    if ( array_key_exists( 'params', $options )) {
        $test->params = array_merge($test->params, $options['params']);
    }

    if ( array_key_exists('files', $options) ) {
        $test->files = $options['files'];
    } else {
        $test->files[] = test_file_path('xliff/amex-test.docx.xlf');
    }

    $response = $test->getResponse();

    return json_decode( $response['body'] ) ;
}

function splitJob( $params, $options=array() ) {
    $test = new CurlTest();

    if ( array_key_exists( 'headers' , $options ) ) {
        $test->headers = $options['headers'];
    }

    $test->path = '?action=splitJob';
    $test->method = 'POST';
    $test->params = array(
        'job_id'       => $params['id_job'],
        'project_id'   => $params['id_project'],
        'project_pass' => $params['project_pass'],
        'exec'         => 'apply',
        'job_pass'     => $params['job_pass'],
        'num_split'    => $params['num_split'],
        'split_values' => $params['split_values']
    );

    $response = $test->getResponse();

    if ( !in_array( (int) $response['code'], array(200, 201) ) ) {
        throw new Exception( "invalid response code " . $response['code'] );
    }

    return json_decode( $response['body'] ) ;
}

function mergeJob( $params, $options=array() ) {
    $test = new CurlTest();

    if ( array_key_exists( 'headers' , $options ) ) {
        $test->headers = $options['headers'];
    }

    $test->path = '?action=splitJob';
    $test->method = 'POST';
    $test->params = array(
        'job_id'       => $params['id_job'],
        'project_id'   => $params['id_project'],
        'exec'         => 'merge',
        'project_pass' => $params['project_pass'],
    );

    $response = $test->getResponse();

    if ( !in_array( (int) $response['code'], array(200, 201) )) {
        throw new Exception( "invalid response code " . $response['code'] );
    }

    return json_decode( $response['body'] )  ;
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
  $test->params = array_merge( $default, $options );
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

function sig_handler($signo) {

    echo "\n\033[41m" . str_pad( "Caught signal \033[1m$signo", 39, " ", STR_PAD_BOTH ). "\033[0m\n";
    switch ($signo) {
         case SIGHUP:
         case SIGTERM:
         case SIGINT:
         // handle shutdown tasks
             restoreDevelopmentConfigFile();
             exit;
             break;
         default:
             // handle all other signals
     }
}

function setupSignalHandler() {
    pcntl_signal(SIGTERM, "sig_handler");
    pcntl_signal(SIGHUP,  "sig_handler");
    pcntl_signal(SIGINT, "sig_handler");
    echo "\033[0;30;42m" . str_pad( "Signal handler installed.", 35, " ", STR_PAD_BOTH ). "\033[0m\n";
}

function toggleChunkOptions( $options ) {
    $test = new CurlTest();
    $test->params = $options[ 'features' ];
    $test->method = 'POST';
    $test->path = sprintf(
            '/api/v2/jobs/%s/%s/options',
            $options[ 'id_job' ],
            $options[ 'job_pass' ]
    );

    $response = $test->getResponse();

    if ( !in_array( (int) $response[ 'code' ], array( 200, 201 ) ) ) {
        throw new Exception( "invalid response code " . $response[ 'code' ] );
    }

    $body = json_decode( $response[ 'body'], true );

    if ( !empty( $body[ 'errors' ] ) ) {
        throw new Exception( "Ajax error detected " . var_export( $body[ 'errors' ], true ) );
    }

    return $body ;
}