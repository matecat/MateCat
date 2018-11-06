<?php
/**
 * Created by PhpStorm.
 */

/**
 * Manager for a Multi Curl connection
 *
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 14/04/14
 * Time: 18.35
 *
 */
class MultiCurlHandler {

    /**
     * The multi curl resource
     *
     * @var resource
     */
    protected $multi_handler;

    /**
     * Pool to manage the curl instances and retrieve them by unique hash identifier
     *
     * @var array
     */
    protected $curl_handlers = array();

    /**
     * Array to manage the requests for headers
     *
     * @var array
     */
    protected $curl_headers_requests = array();

    /**
     * Array to store the options passed when creating the resource, for debug purpose
     *
     * @var array
     */
    protected $curl_options_requests = array();

    /**
     * Container for the curl results
     *
     * @var array
     */
    protected $multi_curl_results = array();

    /**
     * Container for the curl results
     *
     * @var array
     */
    protected $multi_curl_info = array();

    public $verbose = false ;

    /**
     * Class Constructor, init the multi curl handler
     *
     */
    public function __construct() {
        $this->multi_handler = curl_multi_init();

        Utils::getPHPVersion();
        if ( version_compare(PHP_VERSION, '5.5.0') >= 0 ) {
//          curl_multi_setopt only for (PHP 5 >= 5.5.0) Default 10

            curl_multi_setopt( $this->multi_handler, CURLMOPT_MAXCONNECTS, 50 );
        }

    }

    /**
     * Class destructor
     *
     */
    public function __destruct() {
        $this->multiCurlCloseAll();
    }

    /**
     * Close all active curl handlers and multi curl handler itself
     *
     */
    public function multiCurlCloseAll() {

        if ( is_resource( $this->multi_handler ) ) {

            foreach ( $this->curl_handlers as $curl_handler ) {
                curl_multi_remove_handle( $this->multi_handler, $curl_handler );
                if ( is_resource( $curl_handler ) ) {
                    curl_close( $curl_handler );
                    $curl_handler = null;
                }
            }
            curl_multi_close( $this->multi_handler );
            $this->multi_handler = null;
        }

    }

    /**
     * Execute all curl in multiple parallel calls
     * Run the sub-connections of the current cURL handle and store the results
     * to a container
     *
     */
    public function multiExec() {

        $_info         = array();
        $still_running = null;

        do {
            curl_multi_exec( $this->multi_handler, $still_running );
            curl_multi_select( $this->multi_handler ); //Prevent eating CPU

            /*
             * curl_errno does not return any value in case of error ( always 0 )
             * We need to call curl_multi_info_read
             */
            if ( ( $info = curl_multi_info_read( $this->multi_handler ) ) !== false ) {
                //Strict standards:  Resource ID#16 used as offset, casting to integer (16)
                $_info[ (int)$info[ 'handle' ] ] = $info;
            }

        } while ( $still_running > 0 );

        foreach ( $this->curl_handlers as $tokenHash => $curl_resource ) {

            $this->multi_curl_results[ $tokenHash ]                                        = curl_multi_getcontent( $curl_resource );
            $this->multi_curl_info[ $tokenHash ][ 'curlinfo_total_time' ]                  = curl_getinfo( $curl_resource, CURLINFO_TOTAL_TIME );
            $this->multi_curl_info[ $tokenHash ][ 'curlinfo_connect_time' ]                = curl_getinfo( $curl_resource, CURLINFO_CONNECT_TIME );
            $this->multi_curl_info[ $tokenHash ][ 'curlinfo_pretransfer_time' ]            = curl_getinfo( $curl_resource, CURLINFO_PRETRANSFER_TIME );
            $this->multi_curl_info[ $tokenHash ][ 'curlinfo_starttransfer_transfer_time' ] = curl_getinfo( $curl_resource, CURLINFO_STARTTRANSFER_TIME );
            $this->multi_curl_info[ $tokenHash ][ 'curlinfo_effective_url' ]               = curl_getinfo( $curl_resource, CURLINFO_EFFECTIVE_URL );
            $this->multi_curl_info[ $tokenHash ][ 'curlinfo_size_upload' ]                 = curl_getinfo( $curl_resource, CURLINFO_SIZE_UPLOAD );
            $this->multi_curl_info[ $tokenHash ][ 'curlinfo_size_download' ]               = curl_getinfo( $curl_resource, CURLINFO_SIZE_DOWNLOAD );
            $this->multi_curl_info[ $tokenHash ][ 'curlinfo_header_size' ]                 = curl_getinfo( $curl_resource, CURLINFO_HEADER_SIZE );
            $this->multi_curl_info[ $tokenHash ][ 'curlinfo_header_out' ]                  = curl_getinfo( $curl_resource, CURLINFO_HEADER_OUT );
            $this->multi_curl_info[ $tokenHash ][ 'http_code' ]                            = curl_getinfo( $curl_resource, CURLINFO_HTTP_CODE );
            $this->multi_curl_info[ $tokenHash ][ 'primary_ip' ]                           = curl_getinfo( $curl_resource, CURLINFO_PRIMARY_IP );
            $this->multi_curl_info[ $tokenHash ][ 'error' ]                                = curl_error( $curl_resource );

            //Strict standards:  Resource ID#16 used as offset, casting to integer (16)
            $this->multi_curl_info[ $tokenHash ][ 'errno' ] = @$_info[ (int)$curl_resource ][ 'result' ];

            //TIMING
            $timing = array(
                    'Total Time: '          => $this->multi_curl_info[ $tokenHash ][ 'curlinfo_total_time' ],
                    'Connect Time: '        => $this->multi_curl_info[ $tokenHash ][ 'curlinfo_connect_time' ],
                    'Pre-Transfer Time: '   => $this->multi_curl_info[ $tokenHash ][ 'curlinfo_pretransfer_time' ],
                    'Start Transfer Time: ' => $this->multi_curl_info[ $tokenHash ][ 'curlinfo_starttransfer_transfer_time' ],
                    'Transfer Time: '       => round( (
                                    (
                                            $this->multi_curl_info[ $tokenHash ][ 'curlinfo_total_time' ] -
                                            $this->multi_curl_info[ $tokenHash ][ 'curlinfo_starttransfer_transfer_time' ]
                                    ) * 1000000 ) ) . "μs"
            );
            $this->multi_curl_info[ $tokenHash ][ 'timing' ] = $timing;

            //HEADERS
            if ( isset( $this->curl_headers_requests[ $tokenHash ] ) ) {
                $header = substr( $this->multi_curl_results[ $tokenHash ], 0, $this->multi_curl_info[ $tokenHash ][ 'curlinfo_header_size' ] );
                $header = explode( "\r\n", $header );
                $this->multi_curl_results[ $tokenHash ] = substr(
                        $this->multi_curl_results[ $tokenHash ],
                        $this->multi_curl_info[ $tokenHash ][ 'curlinfo_header_size' ]
                );
                $this->curl_headers_requests[ $tokenHash ] = $header;
            }

            Log::doLog( "$tokenHash ... Called: " . $this->multi_curl_info[ $tokenHash ][ 'curlinfo_effective_url' ] . "\n Timing " . print_r( $timing, true ) );

            if ( $this->verbose ) {

                Log::doLog("$tokenHash options: " . var_export( @$this->curl_options_requests[ $tokenHash ], true ) ) ;

                if ( $this->hasError( $tokenHash ) ) {
                    Log::doLog("$tokenHash error: " . var_export( $this->getError($tokenHash), true ) ) ;
                    Log::doLog("$tokenHash body: "  . var_export( $this->getSingleContent($tokenHash), true ) ) ;
                }
            }
        }
    }

    /**
     * Explicitly set that we want the response header for this token
     *
     * @param $tokenHash
     *
     * @return $this
     */
    public function setRequestHeader( $tokenHash ){

        $resource = $this->curl_handlers[ $tokenHash ];
        curl_setopt( $resource, CURLOPT_HEADER, true );
        $this->curl_headers_requests[ $tokenHash ] = true;

        return $this;
    }

    /**
     * Get the response header for the requested token
     * @param $tokenHash
     *
     * @return mixed
     */
    public function getSingleHeader( $tokenHash ){
        return $this->curl_headers_requests[ $tokenHash ];
    }

    /**
     * Get the response header for the requested token
     *
     * @return mixed
     */
    public function getAllHeaders(){
        return $this->curl_headers_requests;
    }

    /**
     * Create a curl resource and add it to the pool indexing it with an unique identifier
     *
     * @param $url       string
     * @param $options   array
     * @param $tokenHash string
     *
     * @return string Curl identifier
     *
     */
    public function createResource( $url, $options, $tokenHash = null ) {

        if ( $tokenHash === null ) {
            $tokenHash = md5( uniqid( "", true ) );
        }

        $curl_resource = curl_init();

        curl_setopt( $curl_resource, CURLOPT_URL, $url );
        @curl_setopt_array( $curl_resource, $options );

        $this->curl_options_requests[ $tokenHash ] = $options ;

        return $this->addResource( $curl_resource, $tokenHash );

    }

    /**
     * Add an already existent curl resource to the pool indexing it with an unique identifier
     *
     * @param resource    $curl_resource
     * @param null|string $tokenHash
     *
     * @return string
     * @throws LogicException
     */
    public function addResource( $curl_resource, $tokenHash = null ) {

        if ( $tokenHash === null ) {
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
    public function getAllContents() {
        return $this->multi_curl_results;
    }

    /**
     * Return all curl info
     *
     * @return array[]
     */
    public function getAllInfo() {
        return $this->multi_curl_info;
    }

    /**
     * Get single result content from responses array by it's unique Index
     *
     * @param $tokenHash
     *
     * @return string|bool|null
     */
    public function getSingleContent( $tokenHash ) {
        if ( array_key_exists( $tokenHash, $this->multi_curl_results ) ) {
            return $this->multi_curl_results[ $tokenHash ];
        }

        return null;
    }

    /**
     * Get single info from curl handlers array by it's unique Index
     *
     * @param $tokenHash
     *
     * @return array|null
     */
    public function getSingleInfo( $tokenHash ) {
        if ( array_key_exists( $tokenHash, $this->multi_curl_info ) ) {
            return $this->multi_curl_info[ $tokenHash ];
        }

        return null;
    }

    public function getOptionRequest( $tokenHash ) {
            return $this->curl_options_requests[ $tokenHash ];
    }

    public function getError( $tokenHash ) {
        $res                = array();
        $res[ 'http_code' ] = $this->multi_curl_info[ $tokenHash ][ 'http_code' ];
        $res[ 'error' ]     = $this->multi_curl_info[ $tokenHash ][ 'error' ];
        $res[ 'errno' ]     = $this->multi_curl_info[ $tokenHash ][ 'errno' ];

        return $res;
    }

    /**
     * Check for error in curl resource by passing it's unique index
     *
     * @param string $tokenHash
     *
     * @return bool
     */
    public function hasError( $tokenHash ) {
        return ( !empty( $this->multi_curl_info[ $tokenHash ][ 'error' ] ) && $this->multi_curl_info[ $tokenHash ][ 'errno' ] != 0 ) || (int)$this->multi_curl_info[ $tokenHash ][ 'http_code' ] >= 400;
    }

    /**
     * Returns an array with errors on each resource. Returns empty array in case of no errors.
     *
     * @return array
     */
    public function getErrors() {
        $map = array_map( function( $tokenHash ) {
            if ( $this->hasError( $tokenHash ) ) {
                return $this->getError( $tokenHash );
            }
        }, array_keys( $this->multi_curl_info ) ) ;

        return array_filter( $map );  // <- remove null array entries
    }

} 
