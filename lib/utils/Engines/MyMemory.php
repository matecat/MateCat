<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 25/02/15
 * Time: 18.53
 * 
 */

class Engines_MyMemory extends Engines_AbstractEngine implements Engines_EngineInterface {

    protected $_config = array(
            'segment'       => null,
            'translation'   => null,
            'tnote'         => null,
            'source'        => null,
            'target'        => null,
            'email'         => null,
            'prop'          => null,
            'get_mt'        => 1,
            'id_user'       => null,
            'num_result'    => 3,
            'mt_only'       => false,
            'isConcordance' => false,
            'isGlossary'    => false,
    );

    /**
     * @param $engineRecord
     *
     * @throws Exception
     */
    public function __construct( $engineRecord ) {
        parent::__construct( $engineRecord );
        if ( $this->engineRecord->type != "TM" ) {
            throw new Exception( "Engine {$this->engineRecord->id} is not a TMS engine, found {$this->engineRecord->type} -> {$this->engineRecord->class_load}" );
        }
    }

    protected function _decode( $rawValue ){
        $args = func_get_args();
        $functionName = $args[2];

        if( is_string( $rawValue ) ) {
            $decoded = json_decode( $rawValue, true );
        } else {
            $decoded = $rawValue; // already decoded in case of error
        }

        $result_object = null;
        switch ($functionName){
            case 'api_key_check_auth_url':
                $result_object =  new Engines_Results_AuthKeyResponse($decoded);
                break;
            case 'api_key_create_user_url':
                $result_object =  new Engines_Results_CreateUserResponse($decoded);
                break;
            default:
                $result_object = new Engines_Results_TMS( $decoded );;
                break;
        }

        return $result_object;
    }

    /**
     * @param $_config
     *
     * @return Engines_Results_TMS
     */
    public function get( $_config ) {

        $parameters               = array();
        $parameters[ 'q' ]        = $_config[ 'segment' ];
        $parameters[ 'langpair' ] = $_config[ 'source' ] . "|" . $_config[ 'target' ];
        $parameters[ 'de' ]       = $_config[ 'email' ];
        $parameters[ 'mt' ]       = $_config[ 'get_mt' ];
        $parameters[ 'numres' ]   = $_config[ 'num_result' ];

        ( $_config[ 'isConcordance' ] ? $parameters[ 'conc' ] = 'true' : null );
        ( $_config[ 'mt_only' ] ? $parameters[ 'mtonly' ] = '1' : null );

        if ( !empty( $_config[ 'id_user' ] ) ) {
            if ( !is_array( $_config[ 'id_user' ] ) ) {
                $_config[ 'id_user' ] = array( $_config[ 'id_user' ] );
            }
            $parameters[ 'key' ] = implode( ",", $_config[ 'id_user' ] );
        }

        ( !$_config[ 'isGlossary' ] ? $function = "translate_relative_url" : $function = "gloss_get_relative_url" );

        $this->call( $function, $parameters );

        return $this->result;

    }

    public function set( $_config ) {

        $parameters               = array();
        $parameters[ 'seg' ]      = $_config[ 'segment' ];
        $parameters[ 'tra' ]      = $_config[ 'translation' ];
        $parameters[ 'tnote' ]    = $_config[ 'tnote' ];
        $parameters[ 'langpair' ] = $_config[ 'source' ] . "|" . $_config[ 'target' ];
        $parameters[ 'de' ]       = $_config[ 'email' ];
        $parameters[ 'prop' ]     = $_config[ 'prop' ];

        if ( !empty( $_config[ 'id_user' ] ) ) {
            if( ! is_array( $_config['id_user'] ) ) $_config['id_user'] = array( $_config['id_user'] );
            $parameters['key'] = implode(",", $_config['id_user']);
        }

        ( !$_config['isGlossary']   ? $function = "contribute_relative_url" : $function = "gloss_set_relative_url" );

        $this->call( $function, $parameters );

        if ( $this->result->responseStatus != "200" ) {
            return false;
        }

        return true;

    }

    public function delete( $_config ) {

        $parameters               = array();
        $parameters[ 'seg' ]      = $_config[ 'segment' ];
        $parameters[ 'tra' ]      = $_config[ 'translation' ];
        $parameters[ 'langpair' ] = $_config[ 'source' ] . "|" . $_config[ 'target' ];
        $parameters[ 'de' ]       = $_config[ 'email' ];

        if ( !empty( $_config[ 'id_user' ] ) ) {
            if( ! is_array( $_config['id_user'] ) ) $_config['id_user'] = array( $_config['id_user'] );
            $parameters['key'] = implode(",", $_config['id_user']);
        }

        ( !$_config['isGlossary']   ? $function = "delete_relative_url" : $function = "gloss_delete_relative_url" );

        $this->call( $function, $parameters );

        if ($this->result->responseStatus != "200") {
            return false;
        }
        return true;

    }

    public function update( $_config ) {

        $parameters               = array();
        $parameters[ 'seg' ]      = $_config[ 'segment' ];
        $parameters[ 'tra' ]      = $_config[ 'translation' ];
        $parameters[ 'langpair' ] = $_config[ 'source' ] . "|" . $_config[ 'target' ];
        $parameters[ 'tnote' ]    = $_config[ 'tnote' ];
        $parameters[ 'prop' ]     = $_config[ 'prop' ];

        if ( !empty( $_config[ 'id_user' ] ) ) {
            if( ! is_array( $_config['id_user'] ) ) $_config['id_user'] = array( $_config['id_user'] );
            $parameters['key'] = implode(",", $_config['id_user']);
        }

        $this->call( "gloss_update_relative_url" , $parameters );

        if ($this->result->responseStatus != "200") {
            return false;
        }
        return true;

    }

    /***********************************************/

    public function import( $file, $key, $name = false ) {

        $postFields = array(
                'tmx'  => "@" . realpath( $file ),
                'name' => $name
        );

        $postFields[ 'key' ] = trim( $key );

        Log::doLog($postFields);
        $this->call( "tmx_import_relative_url", $postFields, true );

        return $this->result;
    }

    public function getStatus( $key, $name = false ) {

        $parameters = array();
        $parameters[ 'key' ] = trim( $key );

        //if provided, add name parameter
        if ( $name ) {
            $parameters[ 'name' ] = $name;
        }

        $this->call( 'tmx_status_relative_url', $parameters );

        return $this->result;
    }

    /**
     * Memory Export creation request.
     *
     * <ul>
     *  <li>key: MyMemory key</li>
     *  <li>source: all segments with source language ( default 'all' )</li>
     *  <li>target: all segments with target language ( default 'all' )</li>
     *  <li>strict: strict check for languages ( no back translations ), only source-target and not target-source
     * </ul>
     *
     * @param string       $key
     * @param null|string  $source
     * @param null|string  $target
     * @param null|boolean $strict
     *
     * @return array
     */
    public function createExport( $key, $source = null, $target = null, $strict = null ) {

        $parameters = array();

        $parameters[ 'key' ] = trim( $key );
        ( !empty( $source ) ? $parameters[ 'source' ] = $source : null );
        ( !empty( $target ) ? $parameters[ 'target' ] = $target : null );
        ( !empty( $strict ) ? $parameters[ 'strict' ] = $strict : null );

        $this->call( 'tmx_export_create_url', $parameters );

        return $this->result;

    }

    /**
     * Memory Export check for status,
     * <br />invoke with the same parameters of createExport
     *
     * @see TmKeyManagement_SimpleTMX::createExport
     *
     * @param      $key
     * @param null $source
     * @param null $target
     * @param null $strict
     *
     * @return array
     */
    public function checkExport(  $key, $source = null, $target = null, $strict = null  ){

        $parameters = array();

        $parameters[ 'key' ] = trim( $key );
        ( !empty( $source ) ? $parameters[ 'source' ] = $source : null );
        ( !empty( $target ) ? $parameters[ 'target' ] = $target : null );
        ( !empty( $strict ) ? $parameters[ 'strict' ] = $strict : null );

        $this->call( 'tmx_export_check_url', $parameters );

        return $this->result;

    }

    /**
     * Get the zip file with the TM inside or a "EMPTY ARCHIVE" message
     * <br> if there are not segments inside the TM
     *
     * @param $key
     * @param $hashPass
     *
     * @return resource
     *
     * @throws Exception
     */
    public function downloadExport( $key, $hashPass ){

        $parameters = array();

        $parameters[ 'key' ] = trim( $key );
        $parameters[ 'pass' ] = trim( $hashPass );

        $this->buildGetQuery( 'tmx_export_download', $parameters );

        $parsed_url = parse_url ( $this->url );

        $isSSL = stripos( $parsed_url['scheme'], "https" ) !== false;

//        if( $isSSL ){
//            $fp = fsockopen( "ssl://" . $parsed_url['host'], 443, $errno, $err_str, 120 );
//        } else {
//            $fp = fsockopen( $parsed_url['host'], 80, $errno, $err_str, 120 );
//        }
//
//        if (!$fp) {
//            throw new Exception( "$err_str ($errno)" );
//        }
//
//        $out = "GET " . $parsed_url['path'] . "?" . $parsed_url['query'] .  " HTTP/1.1\r\n";
//        $out .= "Host: {$parsed_url['host']}\r\n";
//        $out .= "Connection: Close\r\n\r\n";
//
//        Log::doLog( "Download TMX: " . $this->url );

//        fwrite($fp, $out);

        $streamFileName = tempnam("/tmp", "TMX");

        $handle = fopen( $streamFileName, "w+");

        $ch = curl_init();

        // set URL and other appropriate options
        curl_setopt( $ch, CURLOPT_URL, $this->url );
        curl_setopt( $ch, CURLOPT_HEADER, 0 );
        curl_setopt( $ch, CURLOPT_FILE, $handle ); // write curl response to file
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );

        // grab URL and pass it to the browser
        curl_exec($ch);

        rewind( $handle );

        return $handle;

    }

    /*****************************************/
    public function createMyMemoryKey(){

        //query db
        $this->call('api_key_create_user_url');

        if(!$this->result instanceof Engines_Results_CreateUserResponse) {
            if ( empty( $this->result ) || $this->result[ 'error' ] || $this->result[ 'error' ][ 'code' ] != 200 ) {
                throw new Exception( "Private TM key .", -1 );
            }
        }

        unset( $this->result->responseStatus );
        unset( $this->result->responseDetails );
        unset( $this->result->responseData );

        return $this->result;

    }

    /**
     * Checks for MyMemory Api Key correctness
     *
     * Filter Validate returns true/false for correct/not correct key and NULL is returned for all non-boolean values. ( 404, html, etc. )
     *
     * @param $apiKey
     *
     * @return bool|null
     * @throws Exception
     */
    public function checkCorrectKey( $apiKey ) {

        $postFields = array(
                'key' => trim( $apiKey )
        );

        //query db
//        $this->doQuery( 'api_key_check_auth', $postFields );
        $this->call( 'api_key_check_auth_url', $postFields );

        if ( !$this->result->responseStatus == 200 ) {
            Log::doLog( "Error: The check for MyMemory private key correctness failed: " . $this->result['error']['message'] . " ErrNum: " . $this->result['error']['code'] );
            throw new Exception( "Error: The private TM key you entered seems to be invalid. Please, check that the key is correct.", -2 );
        }

        $isValidKey = filter_var( $this->result->responseData, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

        if ( $isValidKey === null ) {
            throw new Exception( "Error: The private TM key you entered seems to be invalid.", -3 );
        }

        return $isValidKey;

    }

}