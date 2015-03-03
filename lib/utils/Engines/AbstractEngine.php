<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 25/02/15
 * Time: 11.59
 *
 */

abstract class Engines_AbstractEngine {

    /**
     * @var Engine_EngineStruct
     */
    protected $engineRecord;

    protected $className;
    protected $_config = array();
    protected $result;
    protected $error = array();

    protected $curl_additional_params = array();

    public function __construct( $engineRecord ) {
        $this->engineRecord = $engineRecord;
        $this->className = get_class( $this );

        $this->curl_additional_params = array(
                CURLOPT_HEADER         => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT => INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER,
                CURLOPT_CONNECTTIMEOUT => 5, // a timeout to call itself should not be too much higher :D
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
        );

    }

    public function __get( $key ) {
        if ( property_exists( $this->engineRecord, $key ) ) {
            return $this->engineRecord->$key;
        } elseif ( array_key_exists( $key, $this->engineRecord->others ) ) {
            return $this->engineRecord->others[ $key ];
        } else {
            return null;
        }
    }

    abstract protected function _decode( $rawValue );

    protected function _call( $url, Array $curl_options = array() ){

            $mh = new MultiCurlHandler();
            $uniq_uid = uniqid();

            /*
             * Append array elements from the second array
             * to the first array while not overwriting the elements from
             * the first array and not re-indexing
             *
             * Use the + array union operator
             */
            $resourceHash = $mh->createResource( $url,
                    $this->curl_additional_params + $curl_options, $uniq_uid
            );
            $mh->multiExec();

            if ( $mh->hasError( $resourceHash ) ) {
                $curl_error = $mh->getError( $resourceHash );
                Log::doLog( 'Curl Error: ' . $curl_error[ 'errno' ] . " - " . $curl_error[ 'error' ] . " " . var_export( parse_url( $url ), true ) );
                $rawValue = array(
                        'error' => array(
                                'code'    => -$curl_error[ 'errno' ],
                                'message' => " {$curl_error['error']}. Server Not Available"
                        )
                ); //return negative number
            } else {
                $rawValue = $mh->getSingleContent( $resourceHash );
            }

            $mh->multiCurlCloseAll();

            Log::doLog( $uniq_uid . " ... Received... " . $rawValue );

            return $rawValue;

    }

    public function call( $function, Array $parameters = array(), $isPostRequest = false ){

        $this->error = array(); // reset last error
        if ( !$this->$function ) {
            Log::doLog( 'Requested method ' . $function . ' not Found.' );
            $this->result = array(
                    'error' => array(
                            'code'    => -43,
                            'message' => " Bad Method Call. Requested method ' . $function . ' not Found."
                    )
            ); //return negative number
            return;
        }

        if( $isPostRequest ){
            $function  = strtolower( trim( $function ) );
            $url = "{$this->engineRecord['base_url']}/" . $this->$function;
            $curl_opt = array(
                    CURLOPT_POSTFIELDS => $parameters,
                    CURLOPT_TIMEOUT => 120
            );
        } else {
            $function  = strtolower( trim( $function ) );
            $url = "{$this->engineRecord['base_url']}/" . $this->$function . "?";
            $url .= http_build_query( $parameters );
            $curl_opt = array(
                    CURLOPT_HTTPGET => true,
                    CURLOPT_TIMEOUT => 10
            );
        }

        $rawValue = $this->_call( $url, $curl_opt );

        /*
         * $parameters['segment'] is used in MT engines,
         * they does not return original segment, only the translation.
         * Taken when needed as "variadic function parameter" ( func_get_args )
         * 
         * Pass the called $function also
        */
        $this->result = $this->_decode( $rawValue, $parameters, $function );

    }

    protected function _setAdditionalCurlParams( Array $curlOptParams = array() ){

        /*
         * Append array elements from the second array
         * to the first array while not overwriting the elements from
         * the first array and not re-indexing
         *
         * Use the + array union operator
         */
        $this->curl_additional_params = $curlOptParams + $this->curl_additional_params;
    }

    public function getConfigStruct(){
        return $this->_config;
    }

    public function getPenalty() {
        return $this->engineRecord->penalty;
    }

    public function getName() {
        return $this->engineRecord->name;
    }

}
