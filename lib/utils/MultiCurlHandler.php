<?php
/**
 * Created by PhpStorm.
 * User: domenico domenico@translated.net / ostico@gmail.com
 * Date: 14/04/14
 * Time: 18.35
 * 
 */

class MultiCurlHandler {

    protected $multi_handler;

    protected $curl_handlers = array();

    protected $multi_curl_results = array();

    public function __construct(){

        $this->multi_handler = curl_multi_init();
        curl_multi_setopt( $this->multi_handler, CURLMOPT_MAXCONNECTS, 50 );

    }

    /**
     * Class destructor
     *
     */
    public function __destruct(){
        $this->multiCurlCloseAll();
    }

    /**
     * Close all active curl handlers and multi curl handler itself
     *
     */
    public function multiCurlCloseAll(){

        if( is_resource( $this->multi_handler ) ){

            foreach( $this->curl_handlers as $curl_handler ){
                curl_multi_remove_handle( $this->multi_handler, $curl_handler );
                if( is_resource( $curl_handler ) ){
                    curl_close( $curl_handler );
                    $curl_handler = null;
                }
            }
            curl_multi_close( $this->multi_handler );
            $this->multi_handler = null;
        }

    }

    /**
     * Execute all curl in multiple parallel calls reading from stream select
     *
     */
    public function multiExec() {

        $still_running = null;

        do {
            curl_multi_exec( $this->multi_handler, $still_running );
        } while ( $still_running > 0 );

        foreach( $this->curl_handlers as $tokenHash => $curl_resource ){
            $temp = curl_multi_getcontent ( $curl_resource );
            $this->multi_curl_results[ $tokenHash ] = $temp;
        }

    }

    /**
     * @param $url string
     * @param $options array
     * @param $tokenHash string
     *
     * @return string Curl identifier
     *
     * @throws LogicException
     */
    public function createResource( $url, $options, $tokenHash = null ){

        if ( empty( $tokenHash ) ) {
            $tokenHash = md5( uniqid( "", true ) );
        }

        $curl_resource = curl_init();

        curl_setopt( $curl_resource, CURLOPT_URL, $url );
        curl_setopt_array( $curl_resource, $options );

        return $this->addResource( $curl_resource, $tokenHash );

    }

    /**
     * @param resource     $curl_resource
     * @param null|string  $tokenHash
     *
     * @return string
     * @throws LogicException
     */
    public function addResource( $curl_resource, $tokenHash = null ){

        if ( $tokenHash == null ) {
            $tokenHash = md5( uniqid( '', true ) );
        }

        if ( is_resource( $curl_resource ) ) {
            if ( get_resource_type( $curl_resource ) == 'curl' ) {
                curl_multi_add_handle( $this->multi_handler, $curl_resource );
                $this->curl_handlers[ $tokenHash ] = $curl_resource;
            } else {
                throw new LogicException( __CLASS__ . " - " . "Provided resource is not a valid Curl resource" );
            }
        } else {
            throw new LogicException( __CLASS__ . " - " . var_export( $curl_resource, true ) . " is not a valid resource type." );
        }

        return $tokenHash;

    }

    /**
     * Return all server responses
     *
     * @return array
     */
    public function getAllContents(){
        return $this->multi_curl_results;
    }

    /**
     * Get single result content from responses array
     *
     * @param $tokenHash
     *
     * @return string|bool|null
     */
    public function getSingleContent( $tokenHash ){
        if( array_key_exists( $tokenHash, $this->multi_curl_results ) ){
            return $this->multi_curl_results[ $tokenHash ];
        }
        return null;
    }

} 