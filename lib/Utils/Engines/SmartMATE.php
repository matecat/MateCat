<?php

/**
 * Created by PhpStorm.
 * @property string client_secret
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/03/15
 * Time: 12.10
 *
 */
class Engines_SmartMATE extends Engines_AbstractEngine {

    use \Engines\Traits\Oauth, \Engines\Traits\FormatResponse;

    protected $_auth_parameters = array(
            'client_id'     => null,
            'client_secret' => null,

        /**
         * Hardcoded params, from documentation
         * @see https://mt.smartmate.co/translate
         */
            'grant_type'    => "client_credentials",
            'scope'         => "translate"
    );

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
        $l = explode( "-", strtolower( trim( $lang ) ) );

        return $l[ 0 ];
    }

    protected function _formatAuthenticateError( $objResponse ) {

        //format as a normal Translate Response and send to decoder to output the data
        return $objResponse;

    }

    protected function _decode( $rawValue, array $parameters = [], $function = null ) {

        $all_args = func_get_args();

        if ( is_string( $rawValue ) ) {
            $decoded = json_decode( $rawValue, true );
            $decoded = array(
                    'data' => array(
                            "translations" => array(
                                    array( 'translatedText' => $this->_resetSpecialStrings( $decoded[ "translation" ] ) )
                            )
                    )
            );
        } else {

            if ( $rawValue[ 'error' ][ 'code' ] == 0 && $rawValue[ 'responseStatus' ] >= 400 ) {
                $rawValue[ 'error' ][ 'code' ] = -$rawValue[ 'responseStatus' ];
            }

            $decoded = $rawValue; // already decoded in case of error
        }

        return $this->_composeResponseAsMatch( $all_args, $decoded );

    }

    protected function _getEngineStruct() {

        return EnginesModel_SmartMATEStruct::getStruct();

    }

    protected function _setTokenEndLife( $expires_in_seconds = null ) {

        if ( !is_null( $expires_in_seconds ) ) {
            $this->token_endlife = $expires_in_seconds;

            return;
        }

        /**
         * Gain 2 minutes to not fallback into a recursion
         *
         * @see static::get
         */
        $this->token_endlife = time() + 3480;

    }

    protected function _checkAuthFailure() {
        $expiration   = ( @stripos( $this->result[ 'error' ][ 'message' ], 'token is expired' ) !== false );
        $auth_failure = $this->result[ 'error' ][ 'code' ] < 0;

        return $expiration | $auth_failure;
    }


    public function set( $_config ) {
        // SmartMATE does not have this method
        return true;
    }

    public function update( $_config ) {
        // SmartMATE does not have this method
        return true;
    }

    public function delete( $_config ) {
        // SmartMATE does not have this method
        return true;
    }

    protected function _formatRecursionError() {
        return $this->_composeResponseAsMatch(
                [],
                array(
                        'error' => array(
                                'code'     => -499,
                                'message'  => "Client Closed Request",
                                'response' => 'Maximum recursion limit reached'
                            // Some useful info might still be contained in the response body
                        ),
                        'responseStatus' => 499
                ) //return negative number
        );
    }

    protected function _fillCallParameters( $_config ) {
        $parameters           = array();
        $parameters[ 'text' ] = $_config[ 'segment' ];
        $parameters[ 'from' ] = $_config[ 'source' ];
        $parameters[ 'to' ]   = $_config[ 'target' ];

        return $parameters;
    }


}