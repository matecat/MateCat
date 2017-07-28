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

    /**
     * @var array
     */
    protected $_head_parameters = [];

    /**
     * @var bool
     */
    protected $_skipAnalysis = true;

    public function __construct( $engineRecord ) {

        parent::__construct( $engineRecord );

        if ( $this->engineRecord->type != "MT" ) {
            throw new Exception( "Engine {$this->engineRecord->id} is not a MT engine, found {$this->engineRecord->type} -> {$this->engineRecord->class_load}" );
        }

        $this->_head_parameters = [
                'MyMemory-License' => $this->engineRecord->extra_parameters[ 'MyMemory-License' ],
                'User_id'          => $this->engineRecord->extra_parameters[ 'User_id' ],
                'Platform_type'    => "MateCat",
                'Platform_name'    => "translated_matecat",
                'Platform_version' => INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER,
                'Plugin_version'   => "1.0"
        ];
    }

    protected function _setHeader(){
        if( !isset( $this->curl_additional_params[ CURLOPT_HTTPHEADER ] ) ){
            $this->_setAdditionalCurlParams( [
                            CURLOPT_HTTPHEADER     => [
                                    "PluginHeader: " . json_encode( $this->_head_parameters )
                            ],
                            CURLOPT_SSL_VERIFYPEER => false,
                    ]
            );
        }
    }

    /**
     * @param       $url
     * @param array $curl_options
     *
     * @return array|bool|null|string
     */
    public function _call( $url, Array $curl_options = [] ) {
        $this->_setHeader();
        return parent::_call( $url, $curl_options );
    }

    /**
     * MMT exception name from tag_projection call
     * @see Engines_MMT::_decode
     */
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
     *
     * @param $file \SplFileObject
     * @param $langPairs array
     *
     * @return mixed
     */
    public function getContext( \SplFileObject $file, $langPairs ) {

        $fileName = $file->getRealPath();
        $file->rewind();

        $fp_out = gzopen( "$fileName.gz", 'wb9' );

        if( !$fp_out ){
            $fp_out = null;
            $file = null;
            @unlink( $fileName );
            @unlink( "$fileName.gz" );
            throw new RuntimeException( 'IOException. Unable to create temporary file.' );
        }

        while ( ! $file->eof() ) {
            gzwrite( $fp_out, $file->fgets() );
        }

        $file = null;
        gzclose( $fp_out );

        $postFields = [
                'content'             => "@" . realpath( "$fileName.gz" ),
                'content_compression' => 'gzip',
                'langpairs'           => implode( ",", $langPairs ),
        ];

        if ( version_compare( PHP_VERSION, '5.5.0' ) >= 0 ) {
            /**
             * Added in PHP 5.5.0 with FALSE as the default value.
             * PHP 5.6.0 changes the default value to TRUE.
             */
            $options[ CURLOPT_SAFE_UPLOAD ] = false;
            $this->_setAdditionalCurlParams( $options );
        }

        $this->call( "context_get", $postFields, true );

        @unlink( $fileName );
        @unlink( "$fileName.gz" );

        return $this->result;

    }

    /**
     * Call to check the license key validity
     * @return mixed
     */
    public function checkAccount(){
        $this->call( 'api_key_check_auth_url' );
        return $this->result;
    }

    /**
     * Activate the account and also update/add keys to User MMT data
     *
     * @param $keyList TmKeyManagement_MemoryKeyStruct[]
     *
     * @return mixed
     */
    public function activate( Array $keyList ){

        $_config = [];
        foreach ( $keyList as $p => $kStruct ){
            $_config[ $p ][ 'id' ] = $kStruct->tm_key->key;
            $_config[ $p ][ 'description' ] = $kStruct->tm_key->name;
        }

        $this->call( 'user_update_activate', $_config, true, true );
        return $this->result;

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
            case 'api_key_check_auth_url':
            case 'user_update_activate':
            case 'context_get':
                $result_object = Engines_Results_MyMemory_TMS::getInstance( $decoded );
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