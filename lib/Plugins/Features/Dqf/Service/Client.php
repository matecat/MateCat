<?php

namespace Features\Dqf\Service;


use INIT;
use MultiCurlHandler;

class Client {

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var MultiCurlHandler
     */
    protected $curl ;

    protected $commonHeaders = array() ;


    public function __construct() {
        $this->curl = new MultiCurlHandler();
        $this->curl->verbose = true ;
    }

    public function setCommonHeaders($headers) {
        $this->commonHeaders = $headers ;
    }

    public function execRequests() {
        $this->curl()->multiExec();
    }

    public function setSession( ISession $session ) {
        $this->session = $session ;
    }

    /**
     * @return MultiCurlHandler
     */
    public function curl() {
        return $this->curl ;
    }

    /**
     * @param       $path
     * @param       $method
     * @param array $options
     * @param       $resourceId
     *
     * @return string
     */
    public function createResource( $path, $method, $options = array(), $resourceId = null ) {

        if ( !isset( $options['pathParams'] ) ) {
            $options['pathParams'] = [];
        }

        $url = static::url( $path, $options['pathParams'] );

        return $this->curl->createResource( $url, $this->getCurlOptions($method, $options), $resourceId );
    }

    public function getAllContentsAsString() {
        return implode(', ', $this->curl->getAllContents() );
    }

    /**
     * Returns the full URL, occasionally filling path params.
     *
     * @param       $path
     * @param array $params
     *
     * @return string
     */

    // this should be static
    public static function url( $path, $params = [] ) {
        $base = preg_replace('/\/$/', '', \INIT::$DQF_BASE_URL );
        $path = $base . '/v3' . $path ;

        if ( !empty( $params ) ) {
            $path = vsprintf( $path, $params ) ;
        }
        return $path ;
    }

    private function getCurlOptions( $method, $params = [] ) {
        $curlopts = array();
        $method = strtolower( $method ) ;

        if ( !isset( $params['headers'] ) ) {
            $params['headers'] = [] ;
        }

        $params['headers'] [ 'apiKey' ] = INIT::$DQF_API_KEY ;

        if ( !is_null( $this->session ) ) {
            $params['headers'] [ 'sessionId' ] = $this->session->getSessionId() ;
        }

        $curlopts[ CURLOPT_RETURNTRANSFER ] = true ;

        if ( $method == 'post' ) {
            $curlopts[ CURLOPT_POST ] = true ;
        }

        elseif ( $method == 'get' ) {
        }

        elseif ( $method == 'put' ) {
            $curlopts[ CURLOPT_CUSTOMREQUEST ] = 'PUT' ;
        }

        elseif ( $method == 'delete' ) {
            $curlopts[ CURLOPT_CUSTOMREQUEST ] = 'DELETE' ;
        }

        if ( isset( $params['json'] ) ) {
            $json_string = json_encode( $params['json'] ) ;

            $params['headers'] ['Content-Type' ] = 'application/json' ;
            $params['headers'] ['Content-Length'] = strlen( $json_string ) ;

            $curlopts[ CURLOPT_POSTFIELDS ] = $json_string ;
        }
        elseif ( isset( $params['formData'] ) ) {
            $curlopts[ CURLOPT_POSTFIELDS ] = http_build_query( $params['formData'] );
        }

        $curlopts[ CURLOPT_HTTPHEADER ] = self::headers( $params['headers'] ) ;

        return $curlopts ;
    }

    protected static function headers($headers) {
        $out = [];
        foreach( $headers as $key => $header ) {
            $out[] = $key . ': ' . $header ;
        }
        return $out ;
    }


}
