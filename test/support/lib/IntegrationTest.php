<?php

require 'lib/AbstractTest.php';

abstract class IntegrationTest extends AbstractTest {
    protected $path;
    protected $headers = array();
    protected $method;
    protected $params = array();
    protected $files = array();

    protected $conn ;

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
