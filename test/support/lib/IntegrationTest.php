<?php

require 'lib/AbstractTest.php';

abstract class IntegrationTest extends AbstractTest {
    protected $path;
    protected $headers = array();
    protected $method;
    protected $params = array();
    protected $files = array();

    protected $conn ;

    protected function alterAutoIncrement() {
        $tables = [ 'projects', 'files', 'segment_translations' ];
        $conn = Database::obtain()->getConnection();
        foreach( $tables as $table ) {
            $conn->exec("ALTER table $table  AUTO_INCREMENT = " . time() );
        }

        $conn->exec(" UPDATE sequences SET id_segment = " . time() );
    }

    protected function prepareUserAndApiKey() {
        $this->test_data->user    = Factory_User::create();
        $this->test_data->api_key = Factory_ApiKey::create( array(
            'uid' => $this->test_data->user->uid,
        ) );

        $this->test_data->headers = array(
            "X-MATECAT-KEY: {$this->test_data->api_key->api_key}",
            "X-MATECAT-SECRET: {$this->test_data->api_key->api_secret}"
        );
    }

    function getResponse() {
        // This is deprecated , use curlTest instance in your tests instead
        return $this->makeRequest();
    }

    function makeRequest() {
        $curlTest = new CurlTest( array(
            'path' => $this->path,
            'headers' => $this->headers,
            'method' => $this->method,
            'params' => $this->params,
            'files' => $this->files
        )  );

        return $curlTest->getResponse();
    }

    function assertJSONResponse($expected) {
        $response = $this->getResponse();

        if ( $this->makeRequest() ) {
          $this->assertEquals( json_encode($expected), $response['body'] );
        }
    }

}
