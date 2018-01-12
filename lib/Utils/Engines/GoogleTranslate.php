<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 28/12/2017
 * Time: 17:25
 */


class Engines_GoogleTranslate extends Engines_AbstractEngine {

    protected $_config = array(
            'q'     => null,
            'source'      => null,
            'target'      => null,
    );

    public function __construct($engineRecord) {
        parent::__construct($engineRecord);
        if ( $this->engineRecord->type != "MT" ) {
            throw new Exception( "Engine {$this->engineRecord->id} is not a MT engine, found {$this->engineRecord->type} -> {$this->engineRecord->class_load}" );
        }
    }

    /**
     * @param $rawValue
     *
     * @return array
     */
    protected function _decode( $rawValue ) {

        if ( is_string( $rawValue ) ) {
            $decoded = json_decode( $rawValue, true );
            if ( isset($decoded[ "data" ] )) {
                return $decoded;
            } else {
                $decoded = array(
                        'error' => array(
                                'code' => $decoded[ "code" ],
                                'message' => $decoded[ "message" ]
                        )
                );
            }
        } else {
            $resp = json_decode( $rawValue[ "error" ][ "response" ], true );
            if ( isset( $resp[ "code" ] ) && isset( $resp[ "message" ] )) {
                $rawValue[ "error" ][ "code" ] = $resp[ "code" ];
                $rawValue[ "error" ][ "message" ] = $resp[ "message" ];
            }
            $decoded = $rawValue; // already decoded in case of error
        }



        return $decoded;

    }

    public function get( $_config ) {


        $parameters = array();
        if ( $this->client_secret != '' && $this->client_secret != null ) {
            $parameters[ 'key' ] = $this->client_secret;
        }
        $parameters['target'] = $_config['target'];
        $parameters['source'] = $_config['source'];
        $parameters['q'] = $this->_preserveSpecialStrings($_config['q']);

        $this->_setAdditionalCurlParams(
                array(
                        CURLOPT_POST       => true,
                        CURLOPT_POSTFIELDS => http_build_query( $parameters )
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

}
