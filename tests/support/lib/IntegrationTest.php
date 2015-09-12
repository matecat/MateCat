<?php

abstract class IntegrationTest extends PHPUnit_Framework_TestCase {
  public $path;
  public $headers;
  public $method;
  public $params = array();
  public $files = array();
  public $ch ;

  function setup() {

  }

  function tearDown() {

  }

  public function makeRequest() {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "http://localhost$this->path");

    if ( count($this->files) > 0 ) {
      $this->method = 'POST' ;

      foreach($this->files as $key => $file) {
        $this->params["upload_$key"] = "@" . $file;
      }
    }

    if ($this->method == 'POST') {
      curl_setopt($ch, CURLOPT_POST, 1);
    }

    if ($this->method == 'POST' && empty($this->files)) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($this->params));
    } else if (! empty($this->files)) {
      // If files are present pass an array in order to have cURL
      // use a multipart form-data.
      curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);

    if ($response === false) {
      echo 'Curl error: ' . curl_error($ch);
    }

    curl_close($ch);
    return $response;
  }

  public function assertJSONResponse($expected) {
    $response = $this->makeRequest();
    $this->assertEquals( json_encode($expected), $response);
  }
}
