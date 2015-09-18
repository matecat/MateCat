<?php

abstract class IntegrationTest extends PHPUnit_Framework_TestCase {
    protected $path;
    protected $headers = array();
    protected $method;
    protected $params = array();
    protected $files = array();

    protected $conn ;

    function setup() {

    }

    function tearDown() {

    }

    function makeRequest() {
        $curlTest = new CurlTest( array(
            'path' => $this->path,
            'headers' => $this->headers,
            'method' => $this->method,
            'params' => $this->params,
            'files' => $this->files
        )  );

        return $curlTest->run();
    }

    function assertJSONResponse($expected) {
        $response = $this->makeRequest();
        $this->assertEquals( json_encode($expected), $response);
    }

}
