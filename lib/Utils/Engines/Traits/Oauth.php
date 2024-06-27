<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 23/09/16
 * Time: 13.29
 *
 */

namespace Engines\Traits;


use Database;
use EnginesModel_EngineDAO;
use Exception;

trait Oauth {

    protected function getAuthParameters(){
        return array(
                CURLOPT_POST       => true,
                CURLOPT_POSTFIELDS => http_build_query( $this->_auth_parameters ), //microsoft doesn't want multi-part form data
                CURLOPT_TIMEOUT    => 120
        );
    }

    /**
     * Check for time to live and refresh cache and token info
     *
     * @return mixed
     * @throws Exception
     */
    protected function _authenticate(){

        $this->_auth_parameters[ 'client_id' ]     = $this->client_id;
        $this->_auth_parameters[ 'client_secret' ] = $this->client_secret;

        $url = $this->oauth_url;
        $curl_opt = $this->getAuthParameters();

        $rawValue = $this->_call( $url, $curl_opt );

        if ( $this->isJson( $rawValue ) ){
            $objResponse = json_decode( $rawValue, true );
        }
        else {
            $objResponse = $rawValue;
        }

        if ( isset( $objResponse['error'] ) ) {

            //format as a normal Translate Response and send to decoder to output the data
            //$rawValue = $this->_formatAuthenticateError( $objResponse );
            $this->result = $this->_decode( $rawValue, $this->_auth_parameters );

            //no more valid token
            $this->token = null;
            $this->_setTokenEndLife( -86400 );

        } else {
            if(is_array($objResponse)){
                $this->token = $objResponse['access_token'];
                $this->_setTokenEndLife( @$objResponse['expires_in'] );
            }
            else{
                $this->token = $objResponse;
                $this->_setTokenEndLife( 60 * 10 ); // microsoft token expire in 10 minutes
            }

        }

        $record = clone( $this->engineRecord );

        $engineDAO        = new EnginesModel_EngineDAO( Database::obtain() );

        /**
         * Use a generic Engine and not Engine_MicrosoftHubStruct
         * because the Engine Factory Class built the query as generic engine
         *
         */
        $engineStruct     = $this->_getEngineStruct();
        $engineStruct->id = $record->id;

        //variable assignment only used for debugging purpose
        $debugParam = $engineDAO->destroyCache( $engineStruct );
        $engineDAO->updateByStruct( $record );

        if( is_null( $this->token ) ){
            throw new Exception( $objResponse['error_description'] );
        }

    }

    private function isJson( $string ) {
        if(is_array($string)){
            return false;
        }
        json_decode( $string );

        return ( json_last_error() == JSON_ERROR_NONE );
    }

    public function get( $_config ) {

        $cycle = @(int)func_get_arg(1);

        if( $cycle == 10 ){
            return $this->_formatRecursionError();
        }

        try {

            //Check for time to live and refresh cache and token info
            if( $this->token_endlife <= time() ){
                $this->_authenticate();
            }

        } catch( Exception $e ){
            return $this->result;
        }

        $parameters = $this->_fillCallParameters( $_config );


        $this->call( "translate_relative_url", $parameters );

        if( $this->_checkAuthFailure() ){

            //if i'm good enough this should not happen because i have the time to live
            try {

                //Check for time to live and refresh cache and token info
                $this->_authenticate();

            } catch( Exception $e ){
                return $this->result;
            }

            /**
             * Warning this is a recursion!!!
             *
             */
            return $this->get( $_config, $cycle + 1 ); //do this request again!

        }

        return $this->result;

    }

    abstract protected function _formatRecursionError();

    abstract protected function _checkAuthFailure();

    abstract protected function _setTokenEndLife( $expires_in_seconds = null );

    abstract protected function _fillCallParameters( $_config );

    abstract protected function _getEngineStruct();

    abstract protected function _formatAuthenticateError( $objResponse );

}