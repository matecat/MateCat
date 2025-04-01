<?php

class Engines_YandexTranslate extends Engines_AbstractEngine {

    protected $_config = [
            'segment' => null,
            'source'  => null,
            'target'  => null,
    ];

    public function __construct( $engineRecord ) {
        parent::__construct( $engineRecord );
        if ( $this->getEngineRecord()->type != Constants_Engines::MT ) {
            throw new Exception( "Engine {$this->getEngineRecord()->id} is not a MT engine, found {$this->getEngineRecord()->type} -> {$this->getEngineRecord()->class_load}" );
        }
    }

    /**
     * @param $lang
     *
     * @return mixed
     * @throws Exception
     */
    protected function _fixLangCode( $lang ) {
        $l = explode( "-", strtolower( trim( $lang ) ) );

        return $l[ 0 ];
    }

    /**
     * @param       $rawValue
     * @param array $parameters
     * @param null  $function
     *
     * @return array|Engines_Results_MT
     * @throws Exception
     */
    protected function _decode( $rawValue, array $parameters = [], $function = null ) {
        $all_args = func_get_args();

        if ( is_string( $rawValue ) ) {
            $decoded = json_decode( $rawValue, true );
            if ( $decoded[ "code" ] == 200 ) {
                $decoded = [
                        'data' => [
                                'translations' => [
                                        [ 'translatedText' => $decoded[ "text" ][ 0 ] ]
                                ]
                        ]
                ];
            } else {
                $decoded = [
                        'error' => [
                                'code'    => $decoded[ "code" ],
                                'message' => $decoded[ "message" ]
                        ]
                ];
            }
        } else {
            $resp = json_decode( $rawValue[ "error" ][ "response" ], true );
            if ( isset( $resp[ "code" ] ) && isset( $resp[ "message" ] ) ) {
                $rawValue[ "error" ][ "code" ]    = $resp[ "code" ];
                $rawValue[ "error" ][ "message" ] = $resp[ "message" ];
            }
            $decoded = $rawValue; // already decoded in case of error
        }

        return $this->_composeResponseAsMatch($all_args, $decoded);
    }

    public function get( $_config ) {

        $_config[ 'source' ]  = $this->_fixLangCode( $_config[ 'source' ] );
        $_config[ 'target' ]  = $this->_fixLangCode( $_config[ 'target' ] );

        $parameters = [];
        if ( $this->client_secret != '' && $this->client_secret != null ) {
            $parameters[ 'key' ] = $this->client_secret;
        }
        $parameters[ 'srv' ]    = "matecat";
        $parameters[ 'lang' ]   = $_config[ 'source' ] . "-" . $_config[ 'target' ];
        $parameters[ 'text' ]   = $_config[ 'segment' ];
        $parameters[ 'format' ] = "html";

        $this->_setAdditionalCurlParams(
                [
                        CURLOPT_POST       => true,
                        CURLOPT_POSTFIELDS => http_build_query( $parameters )
                ]
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

}
