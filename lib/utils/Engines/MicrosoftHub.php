<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 03/03/15
 * Time: 12.12
 * 
 */

class Engines_MicrosoftHub extends Engines_AbstractEngine implements Engines_EngineInterface {

    protected $_config = array(
            'segment'     => null,
            'translation' => null,
            'source'      => null,
            'target'      => null,
    );

    public function __construct( $engineRecord ) {
        parent::__construct( $engineRecord );
        if ( $this->engineRecord->type != "MT" ) {
            throw new Exception( "Engine {$this->engineRecord->id} is not a MT engine, found {$this->engineRecord->type} -> {$this->engineRecord->class_load}" );
        }
    }

    protected function _fixLangCode( $lang ) {

        if( $lang == 'zh-CN' ) return "zh-CHS"; //chinese zh-CHS simplified
        if( $lang == 'zh-TW' ) return "zh-CHT"; //chinese zh-CHT traditional
        $l = explode( "-", strtolower( trim( $lang ) ) );
        return $l[0];

    }

    protected function _decode( $rawValue ) {

        $all_args =  func_get_args();

        $xmlObj = simplexml_load_string( $rawValue );
        foreach ( (array)$xmlObj[ 0 ] as $key => $val ) {
            if( $key == 'body' ){
                $error = (array)$val;
                $decoded = array(
                    'error' => array( "message" => $error['h1'] . ": " . $error['p'][2], 'code' => -1 )
                );
                break;
            } else {
                $decoded = array(
                        'data' => array(
                                "translations" => array(
                                        array( 'translatedText' => $val )
                                )
                        )
                );
            }

        }

        $mt_result = new Engines_Results_MT( $decoded );

        if ( $mt_result->error->code < 0 ) {
            $mt_result = $mt_result->get_as_array();
            $mt_result['error'] = (array)$mt_result['error'];
            return $mt_result;
        }

        $mt_match_res = new Engines_Results_MyMemoryMatches(
                $all_args[ 1 ][ 'text' ],
                $mt_result->translatedText,
                100 - $this->getPenalty() . "%",
                "MT-" . $this->getName(),
                date( "Y-m-d" )
        );

        return $mt_match_res->get_as_array();

    }

    public function get( $_config ) {

        $_config[ 'source' ] = $this->_fixLangCode( $_config[ 'source' ] );
        $_config[ 'target' ] = $this->_fixLangCode( $_config[ 'target' ] );

        $parameters = array();
        $parameters['text'] = $_config[ 'segment' ];
        $parameters['from'] = $_config[ 'source' ];
        $parameters['to']   = $_config[ 'target' ];

        $this->_setAdditionalCurlParams( array(
                        CURLOPT_HTTPHEADER     => array(
                                "Authorization: Bearer " . $this->extra_parameters->token, "Content-Type: text/plain"
                        ),
                        CURLOPT_SSL_VERIFYPEER => false,
                )
        );

        //TODO check for time to live

        $this->call( "translate_relative_url", $parameters );

        if( stripos( $this->result['error']['message'], 'token has expired' ) !== false ){
            //if i'm good enough this should not happen because i have the time to live
            $this->_authenticate();
            return $this->get( $_config ); //do this request again!
        }

        return $this->result;

    }

    public function set( $_config ) {
        // Microsoft Hub does not have this method
        return true;
    }

    public function update( $_config ) {
        // Microsoft Hub does not have this method
        return true;
    }

    public function delete( $_config ) {
        // Microsoft Hub does not have this method
        return true;
    }

    protected function _authenticate(){

        $parameters = array();
        $parameters[ 'client_id' ]     = $this->client_id;
        $parameters[ 'client_secret' ] = $this->client_secret;
        $parameters[ 'grant_type' ]    = $this->grant_type;
        $parameters[ 'scope' ]         = $this->scope;

        $this->call( "translate_relative_url", $parameters );

        return $this->result;

    }

}