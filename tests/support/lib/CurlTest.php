<?php

class CurlTest {
  public $path;
  public $headers;
  public $method;
  public $params = array();
  public $files = array();

  private $url ;

  function __construct( $options=array() ) {
    $this->path    = @$options['path'];
    $this->headers = @$options['headers'];
    $this->params  = @$options['params'];
    $this->files   = @$options['files'];
    $this->method  = @$options['method'];
  }

  function run() {
    $ch = curl_init();

    $this->url = "http://localhost$this->path";

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

    if ($this->method == 'GET' ) {
      $this->url .= "?" . http_build_query( $this->params );
    }


    $this->setHeaders($ch) ;

    curl_setopt($ch, CURLOPT_URL, $this->url );
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);

    if ($response === false) {
      echo 'Curl error: ' . curl_error($ch);
    }

    curl_close($ch);
    return $response;

  }

  private function setHeaders($ch) {
    if (! empty($this->headers) ) {
      curl_setopt( $ch, CURLOPT_HTTPHEADER, $this->headers );
    }
  }
}
