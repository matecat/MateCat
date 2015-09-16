<?php

abstract class IntegrationTest extends PHPUnit_Framework_TestCase {
    public $path;
    public $headers;
    public $method;
    public $params = array();
    public $files = array();

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
