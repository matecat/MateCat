<?php

/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 17/05/16
 * Time: 13:11
 */
class Engines_MMT extends Engines_AbstractEngine implements Engines_EngineInterface {

    protected $_config = array(
            'source'      => null,
            'target'      => null,
            'source_lang' => null,
            'target_lang' => null,
            'suggestion'  => null
    );

    const LanguagePairNotSupportedException = 1;

    public function get( $_config ) {
        throw new DomainException( "Method " . __FUNCTION__ . " not implemented." );
    }

    public function set( $_config ) {
        throw new DomainException( "Method " . __FUNCTION__ . " not implemented." );
    }

    public function update( $_config ) {
        throw new DomainException( "Method " . __FUNCTION__ . " not implemented." );
    }

    public function delete( $_config ) {
        throw new DomainException( "Method " . __FUNCTION__ . " not implemented." );
    }

    /**
     * @param $rawValue
     *
     * @return Engines_Results_AbstractResponse
     */
    protected function _decode( $rawValue ) {

        $args         = func_get_args();
        $functionName = $args[ 2 ];

        if ( is_string( $rawValue ) ) {
            $decoded = json_decode( $rawValue, true );
        } else {
            if ( $rawValue[ 'responseStatus' ] >= 400 ){
                $rawValue = json_decode( $rawValue[ 'error' ][ 'response' ], true );
                $rawValue[ 'error' ][ 'code' ] = @constant( 'self::' . $rawValue[ 'error' ][ 'type' ] );
            }
            $decoded = $rawValue; // already decoded in case of error
        }

        $result_object = null;

        switch ( $functionName ) {
            case 'tags_projection' :
                $result_object = Engines_Results_MMT_TagProjectionResponse::getInstance( $decoded );
                break;
            default:
                //this case should not be reached
                $result_object = Engines_Results_MMT_TagProjectionResponse::getInstance( array(
                        'error' => array(
                                'code'      => -1100,
                                'message'   => " Unknown Error.",
                                'response'  => " Unknown Error." // Some useful info might still be contained in the response body
                        ),
                        'responseStatus'    => 400
                ) ); //return generic error
                break;
        }

        return $result_object;

    }

    /**
     * TODO FixMe whit the url parameter and method extracted from engine record on the database
     * when MyMemory TagProjection will be public
     *
     * @param $config
     * @return Engines_Results_MMT_TagProjectionResponse
     */
    public function getTagProjection( $config ){

        $parameters           = array();
        $parameters[ 's' ]    = $config[ 'source' ];
        $parameters[ 't' ]    = $config[ 'target' ];
//        $parameters[ 'sl' ]   = $config[ 'source_lang' ];
//        $parameters[ 'tl' ]   = $config[ 'target_lang' ];
        $parameters[ 'hint' ] = $config[ 'suggestion' ];

        /*
         * For now override the base url and the function params
         */
        $this->engineRecord[ 'base_url' ] = 'http://149.7.212.129:10000';
        $this->engineRecord->others[ 'tags_projection' ] = 'tags-projection/' . $config[ 'source_lang' ] . "/" . $config[ 'target_lang' ] . "/";

        $this->call( 'tags_projection', $parameters );

        return $this->result;

    }

}