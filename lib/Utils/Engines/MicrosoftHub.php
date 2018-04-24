<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 03/03/15
 * Time: 12.12
 * 
 */

/**
 * Class Engines_MicrosoftHub
 * @property string oauth_url
 * @property int token_endlife
 * @property string token
 * @property string   client_id
 * @property string   client_secret
 */
class Engines_MicrosoftHub extends Engines_AbstractEngine {

    use \Engines\Traits\Oauth, \Engines\Traits\FormatResponse;

    private $rawXmlErrStruct = <<<TAG
            <html>
                <body>
                    <h1>%s</h1>
                    <p>Method: %s</p>
                    <p>Parameter: </p>
                    <p>Message: %s</p>
                    <code></code>
                </body>
            </html>
TAG;

    protected $_config = array(
            'segment'     => null,
            'translation' => null,
            'source'      => null,
            'target'      => null,
    );

    protected $_auth_parameters = array(
            'client_id'     => "",
            'client_secret' => "",

            /**
             * Hardcoded params, from documentation
             * @see https://msdn.microsoft.com/en-us/library/hh454950.aspx
             */
            'grant_type'    => "client_credentials",
            'scope'         => "http://api.microsofttranslator.com"
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


    public function get($_config){
        $auth_curl_options = [
                CURLOPT_POST       => true,
                CURLOPT_POSTFIELDS => "",
                CURLOPT_HTTPHEADER => [
                        'Ocp-Apim-Subscription-Key: '. $this->client_id,
                        'Accept: application/jwt',
                        'Content-Type: application/json',
                ]
        ];

        try {

            //Check for time to live and refresh cache and token info
            if( $this->token_endlife <= time() ){
                $this->_authenticate($auth_curl_options);
            }

        } catch( Exception $e ){
            return $this->result;
        }


        $parameters = array();
        if ( $this->client_secret != '' && $this->client_secret != null ) {
            $parameters[ 'Ocp-Apim-Subscription-Key' ] = $this->client_id;

        }

        $parameters['appId'] = 'Bearer '.$this->token;
        $parameters['to'] = $this->_fixLangCode( $_config['target'] );
        $parameters['from'] = $this->_fixLangCode( $_config['source'] );
        $parameters['text'] = $this->_preserveSpecialStrings($_config['segment']);


        $this->call( "translate_relative_url", $parameters, false );

        return $this->result;
    }

    protected function _decode( $rawValue ) {

        $all_args =  func_get_args();

        if(isset($rawValue['error']) && !empty($rawValue['error'])){
            return $rawValue;
        }

        return $this->_composeResponseAsMatch( $all_args );

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

    protected function _formatAuthenticateError( $objResponse ){

        //format as a normal Translate Response and send to decoder to output the data
        return sprintf( $this->rawXmlErrStruct, $objResponse['error'], 'getToken', $objResponse['error_description'] );

    }

    protected function _getEngineStruct(){

        return  EnginesModel_MicrosoftHubStruct::getStruct();

    }

    protected function _setTokenEndLife( $expires_in_seconds = null ){

        /**
         * Gain a minute to not fallback into a recursion
         *
         * @see static::get
         */
        $this->token_endlife = time() + $expires_in_seconds - 60;

    }

    protected function _checkAuthFailure(){
        return ( @stripos( $this->result['error']['message'], 'token has expired' ) !== false );
    }

    protected function _formatRecursionError(){

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
        $parameters = array();
        $parameters['text']       = $_config[ 'segment' ];
        $parameters['from']       = $_config[ 'source' ];
        $parameters['to']         = $_config[ 'target' ];
        $parameters['category']   = $this->engineRecord->extra_parameters[ 'category' ];
        return $parameters;

    }



}