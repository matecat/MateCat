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
class Engines_MicrosoftHub extends Engines_AbstractEngine implements Engines_EngineInterface {

    protected $rawXmlErrStruct = <<<TAG
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
            if( $key === 'body' ){ //WHY IN THIS STUPID LANGUAGE EVERY STRING EQUALS TO ZERO???......
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

        $mt_match_res = new Engines_Results_MyMemory_Matches(
                $all_args[ 1 ][ 'text' ],
                $mt_result->translatedText,
                100 - $this->getPenalty() . "%",
                "MT-" . $this->getName(),
                date( "Y-m-d" )
        );

        return $mt_match_res->get_as_array();

    }

    public function get( $_config ) {

        $cycle = @(int)func_get_arg(1);

        if( $cycle == 10 ){
            return sprintf( $this->rawXmlErrStruct, 'Too Much Recursion', 'get', 'Maximum recursion limit reached' );
        }

        $_config[ 'source' ] = $this->_fixLangCode( $_config[ 'source' ] );
        $_config[ 'target' ] = $this->_fixLangCode( $_config[ 'target' ] );

        $parameters = array();
        $parameters['text'] = $_config[ 'segment' ];
        $parameters['from'] = $_config[ 'source' ];
        $parameters['to']   = $_config[ 'target' ];

        try {

            //Check for time to live and refresh cache and token info
            if( $this->token_endlife <= time() ){
                $this->_authenticate();
            }

        } catch( Exception $e ){
            return $this->result;
        }

        $this->_setAdditionalCurlParams( array(
                        CURLOPT_HTTPHEADER     => array(
                                "Authorization: Bearer " . $this->token, "Content-Type: text/plain"
                        ),
                        CURLOPT_SSL_VERIFYPEER => false,
                )
        );

        $this->call( "translate_relative_url", $parameters );

        if( @stripos( $this->result['error']['message'], 'token has expired' ) !== false ){

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

    /**
     * Check for time to live and refresh cache and token info
     *
     * @return mixed
     * @throws Exception
     */
    protected function _authenticate(){

        $parameters = array();
        $parameters[ 'client_id' ]     = $this->client_id;
        $parameters[ 'client_secret' ] = $this->client_secret;

        /**
         * Hardcoded params, from documentation
         * @see https://msdn.microsoft.com/en-us/library/hh454950.aspx
         */
        $parameters[ 'grant_type' ]    = "client_credentials";
        $parameters[ 'scope' ]         = "http://api.microsofttranslator.com";

        $url = $this->oauth_url;
        $curl_opt = array(
                CURLOPT_POST       => true,
                CURLOPT_POSTFIELDS => http_build_query( $parameters ), //microsoft doesn't want multi-part form data
                CURLOPT_TIMEOUT    => 120
        );

        $rawValue = $this->_call( $url, $curl_opt );

        $objResponse = json_decode( $rawValue, true );
        if ( isset( $objResponse['error'] ) ) {

            //format as a normal Translate Response and send to decoder to output the data
            $rawValue = sprintf( $this->rawXmlErrStruct, $objResponse['error'], 'getToken', $objResponse['error_description'] );
            $this->result = $this->_decode( $rawValue, $parameters );

            throw new Exception( $objResponse['error_description'] );

        } else {
            $this->token = $objResponse['access_token'];

            /**
             * Gain a minute to not fallback into a recursion
             *
             * @see Engines_MicrosoftHub::get
             */
            $this->token_endlife = time() + $objResponse['expires_in'] - 60;
        }

        $record = clone( $this->engineRecord );

        $engineDAO        = new EnginesModel_EngineDAO( Database::obtain() );

        /**
         * Use a generic Engine and not Engine_MicrosoftHubStruct
         * because the Engine Factory Class built the query as generic engine
         *
         */
        $engineStruct     = EnginesModel_MicrosoftHubStruct::getStruct();
        $engineStruct->id = $record->id;

        //variable assignment only used for debugging purpose
        $debugParam = $engineDAO->destroyCache( $engineStruct );
        $engineDAO->update( $record );

    }

}