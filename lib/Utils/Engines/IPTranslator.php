<?php

/**
 * Created by PhpStorm.
 * @property string client_secret
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/03/15
 * Time: 12.10
 * 
 */

class Engines_IPTranslator extends Engines_AbstractEngine {

    protected $_config = array(
            'segment'     => null,
            'source'      => null,
            'target'      => null,
            'key'     => null,
    );

    public function __construct($engineRecord) {
        parent::__construct($engineRecord);
        if ( $this->engineRecord->type != "MT" ) {
            throw new Exception( "Engine {$this->engineRecord->id} is not a MT engine, found {$this->engineRecord->type} -> {$this->engineRecord->class_load}" );
        }
    }

    /**
     * @param $lang
     *
     * @return mixed
     * @throws Exception
     */
    protected function _fixLangCode( $lang ) {

        $acceptedLangs = array( "En", "Fr", "De", "Pt", "Es", "Ja", "ZhCn", "ZhTw", "Ru", "Ko" );

        if( $lang == 'zh-CN' ) $lang = "ZhCn"; //chinese zh-CHS simplified
        if( $lang == 'zh-TW' ) $lang = "ZhTw"; //chinese zh-CHT traditional

        $l = explode( "-", ucfirst( trim( $lang ) ) );

        if( !in_array( $l[0], $acceptedLangs ) ){
            throw new Exception( "Language Not Supported", -1 );
        }

        return $l[0];

    }

    /**
     * @param $rawValue
     *
     * @return array
     */
    protected function _decode( $rawValue ){

        $all_args =  func_get_args();

        if( is_string( $rawValue ) ) {

            $all_args[0] = json_decode( $all_args[0] , true );

            $decoded = json_decode( $rawValue, true );

            if ( $all_args[ 2 ] == 'ping_url' ) {

                if ( !isset( $decoded[ 'status' ] ) && !isset( $decoded[ 'error' ] ) ) {
                    $decoded = array(
                            'error' => array( "message" => "Connection Failed. Please contact IPTranslator support", 'code' => -1 )
                    );

                } elseif( isset( $decoded['error'] ) ) {
                    $decoded = array(
                            'error' => array( "message" => $decoded['description'], 'code' => -2 )
                    );
                } else {
                    return array(); //All right
                }

            } else {
                preg_match( '#<source>(.*)</source>#', $decoded[ 'xliff' ][ 0 ], $translated_text );
                $decoded = array(
                        'data' => array(
                                "translations" => array(
                                        array( 'translatedText' =>  $this->_resetSpecialStrings( $translated_text[ 1 ] ) )
                                )
                        )
                );
            }

        } else {
            $decoded = $rawValue; // already decoded in case of error
        }

        $mt_result = new Engines_Results_MT( $decoded );

        if ( $mt_result->error->code < 0 ) {
            $mt_result = $mt_result->get_as_array();
            $mt_result['error'] = (array)$mt_result['error'];
            return $mt_result;
        }

        preg_match( '#<source>(.*)</source>#', $all_args[1][ 'input' ][0], $original_text );

        $mt_match_res = new Engines_Results_MyMemory_Matches(
                $this->_resetSpecialStrings( $original_text[ 1 ] ),
                $mt_result->translatedText,
                100 - $this->getPenalty() . "%",
                "MT-" . $this->getName(),
                date( "Y-m-d" )
        );

        $mt_res = $mt_match_res->getMatches();

        return $mt_res;

    }

    public function get( $_config ) {

        try {
            $_config[ 'source' ] = $this->_fixLangCode( $_config[ 'source' ] );
            $_config[ 'target' ] = $this->_fixLangCode( $_config[ 'target' ] );
        } catch ( Exception $e ){
            return array(
                    'error' => array( "message" => $e->getMessage(), 'code' => $e->getCode() )
            );
        }

        $_config[ 'segment' ] = $this->_preserveSpecialStrings( $_config[ 'segment' ] );

        $parameters = array();
        $parameters['input'] = array( "<trans-unit id='" . uniqid() . "'><source>" . $_config[ 'segment' ] . "</source></trans-unit>" );
        $parameters['from'] = $_config[ 'source' ];
        $parameters['to'] = $_config[ 'target' ];

        if (  $this->client_secret != '' && $this->client_secret != null ) {
            $parameters[ 'key' ] = $this->client_secret;
        }

        $this->_setAdditionalCurlParams( array(
                        CURLOPT_HTTPHEADER     => array(
                                "Content-Type: application/json"
                        ),
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_TIMEOUT        => 10,
                        CURLOPT_POST       => true,
                        CURLOPT_POSTFIELDS => json_encode( $parameters )
                )
        );

		$this->call( "translate_relative_url", $parameters, true );

        return $this->result;

    }

    public function set( $_config ) {

        //if engine does not implement SET method, exit
        return true;
    }

    public function update( $config ) {

        //if engine does not implement UPDATE method, exit
        return true;
    }

    public function delete( $_config ) {

        //if engine does not implement DELETE method, exit
        return true;

    }

    public function ping( $_config ){

        try {
            $_config[ 'source' ] = $this->_fixLangCode( $_config[ 'source' ] );
            $_config[ 'target' ] = $this->_fixLangCode( $_config[ 'target' ] );
        } catch ( Exception $e ){
            return array(
                    'error' => array( "message" => $e->getMessage(), 'code' => $e->getCode() )
            );
        }

        $parameters            = array();
        $parameters[ 'from' ]  = $_config[ 'source' ];
        $parameters[ 'to' ]    = $_config[ 'target' ];

        if (  $this->client_secret != '' && $this->client_secret != null ) {
            $parameters[ 'key' ] = $this->client_secret;
        }

        $this->_setAdditionalCurlParams( array(
                        CURLOPT_HTTPHEADER     => array(
                                "Content-Type: application/json"
                        ),
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_TIMEOUT        => 10,
                        CURLOPT_POST       => true,
                        CURLOPT_POSTFIELDS => json_encode( $parameters )
                )
        );

        $this->call( "ping_url", array(), true );

        return $this->result;

    }

}