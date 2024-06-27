<?php

use Engines\MMT\MMTServiceApi;
use Engines\MMT\MMTServiceApiRequestException;

/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 17/05/16
 * Time: 13:11
 *
 * @property int id
 */
class Engines_MMT extends Engines_AbstractEngine {

    protected $_config = [
            'segment'        => null,
            'translation'    => null,
            'newsegment'     => null,
            'newtranslation' => null,
            'source'         => null,
            'target'         => null,
            'langpair'       => null,
            'email'          => null,
            'keys'           => null,
            'mt_context'     => null,
            'id_user'        => null
    ];

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

        if ( isset( $this->engineRecord->extra_parameters[ 'MMT-pretranslate' ] ) && $this->engineRecord->extra_parameters[ 'MMT-pretranslate' ] == true ) {
            $this->_skipAnalysis = false;
        }

    }

    /**
     * MMT exception name from tag_projection call
     * @see Engines_MMT::_decode
     */
    const LanguagePairNotSupportedException = 1;

    protected static $_supportedExceptions = [
            'LanguagePairNotSupportedException' => self::LanguagePairNotSupportedException
    ];

    /**
     * Get MMTServiceApi client
     *
     * @return MMTServiceApi
     */
    protected function _getClient() {

        $extraParams = $this->engineRecord->getExtraParamsAsArray();
        $license = $extraParams[ 'MMT-License' ];

        return Engines\MMT\MMTServiceApi::newInstance()
                ->setIdentity( "1.1", "MateCat", INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER )
                ->setLicense( $license );
    }

    /**
     * Get the available languages in MMT
     *
     * @return mixed
     * @throws \Engines\MMT\MMTServiceApiException
     */
    public function getAvailableLanguages() {
        $client = $this->_getClient();

        return $client->getAvailableLanguages();
    }

    /**
     * @param $_config
     * @return array|Engines_Results_AbstractResponse
     */
    public function get( $_config ) {

        //This is not really needed because by default in analysis the Engine_MMT is accepted by MyMemory
        if ( $this->_isAnalysis && $this->_skipAnalysis ) {
            return [];
        }

        $client = $this->_getClient();
        $_keys = $this->_reMapKeyList( $_config[ 'keys' ] ?? [] );

        try {
            $translation = $client->translate(
                $_config[ 'source' ],
                $_config[ 'target' ],
                $_config[ 'segment' ],
                $_config[ 'mt_context' ] ?? null,
                $_keys,
                $_config[ 'job_id' ] ?? null,
                static::GET_REQUEST_TIMEOUT,
                $_config[ 'priority' ] ?? null,
                $_config[ 'session' ] ?? null,
                $_config[ 'glossaries' ] ?? null,
                $_config[ 'ignore_glossary_case' ] ?? null
            );

            return ( new Engines_Results_MyMemory_Matches(
                    $_config[ 'segment' ],
                    $translation[ 'translation' ],
                    100 - $this->getPenalty() . "%",
                    "MT-" . $this->getName(),
                    date( "Y-m-d" )
            ) )->getMatches(1, [], $_config[ 'source' ], $_config[ 'target' ]);
        } catch ( Exception $e ) {
            return $this->GoogleTranslateFallback( $_config );
        }

    }

    /**
     * @param $_keys
     *
     * @return array
     */
    protected function _reMapKeyList( $_keys = [] ) {

        if ( !empty( $_keys ) ) {

            if ( !is_array( $_keys ) ) {
                $_keys = [ $_keys ];
            }

            $_keys = array_map( function ( $key ) {
                return 'x_mm-' . $key;
            }, $_keys );

        }

        return $_keys;

    }

    /**
     * @param $keyList TmKeyManagement_MemoryKeyStruct[]
     *
     * @return array
     */
    protected function _reMapKeyStructsList( $keyList ) {
        $keyList = array_map( function ( $kStruct ) {
            return 'x_mm-' . $kStruct->tm_key->key;
        }, $keyList );

        return $keyList;
    }

    public function set( $_config ) {

        $client = $this->_getClient();
        $_keys  = $this->_reMapKeyList( @$_config[ 'keys' ] );

        try {
            $client->addToMemoryContent( $_keys, $_config[ 'source' ], $_config[ 'target' ], $_config[ 'segment' ], $_config[ 'translation' ], $_config['session'] );
        } catch ( MMTServiceApiRequestException $e ) {
            // MMT license expired/changed (401) or account deleted (403) or whatever HTTP exception
            Log::doJsonLog( $e->getMessage() );

            return true;
        } catch ( Exception $e ) {
            return false;
        }

        return true;

    }

    /**
     * @param $_config
     *
     * @return bool
     */
    public function update( $_config ) {

        $client = $this->_getClient();
        $_keys  = $this->_reMapKeyList( @$_config[ 'keys' ] );

        try {
            $client->updateMemoryContent(
                $_config[ 'tuid' ],
                $_keys,
                $_config[ 'source' ],
                $_config[ 'target' ],
                $_config[ 'segment' ],
                $_config[ 'translation' ],
                $_config[ 'session' ]
            );
        } catch ( Exception $e ) {
            return false;
        }

        return true;

    }

    public function delete( $_config ) {
        throw new DomainException( "Method " . __FUNCTION__ . " not implemented." );
    }

    /**
     * @param      $filePath
     * @param      $key
     * @param bool $fileName
     *
     * @return mixed
     * @throws \Engines\MMT\MMTServiceApiException
     */
    public function import( $filePath, $key, $fileName = false ) {

        $fp_out = gzopen( "$filePath.gz", 'wb9' );

        if ( !$fp_out ) {
            $fp_out = null;
            @unlink( $filePath );
            $filePath = null;
            @unlink( "$fileName.gz" );
            throw new RuntimeException( 'IOException. Unable to create temporary file.' );
        }

        $tmpFileObject = new \SplFileObject( $filePath, 'r' );

        while ( !$tmpFileObject->eof() ) {
            gzwrite( $fp_out, $tmpFileObject->fgets() );
        }

        $tmpFileObject = null;
        @unlink( $filePath );
        gzclose( $fp_out );

        $client = $this->_getClient();
        $client->importIntoMemoryContent( 'x_mm-' . trim( $key ), "$filePath.gz", 'gzip' );
        $fp_out = null;
        @unlink( "$filePath.gz" );

        return $this->result;
    }

    /**
     *
     * @param $file    \SplFileObject
     * @param $source  string
     * @param $targets string[]
     *
     * @return mixed
     * @throws \Engines\MMT\MMTServiceApiException
     * @internal param array $langPairs
     *
     */
    public function getContext( \SplFileObject $file, $source, $targets ) {

        $fileName = $file->getRealPath();
        $file->rewind();

        $fp_out = gzopen( "$fileName.gz", 'wb9' );

        if ( !$fp_out ) {
            $fp_out = null;
            $file   = null;
            @unlink( $fileName );
            @unlink( "$fileName.gz" );
            throw new RuntimeException( 'IOException. Unable to create temporary file.' );
        }

        while ( !$file->eof() ) {
            gzwrite( $fp_out, $file->fgets() );
        }

        $file = null;
        gzclose( $fp_out );

        $client = $this->_getClient();
        $result = $client->getContextVectorFromFile( $source, $targets, "$fileName.gz", 'gzip' );

        $plainContexts = [];
        foreach ( $result[ 'vectors' ] as $target => $vector ) {
            $plainContexts[ "$source|$target" ] = $vector;
        }

        return $plainContexts;

    }

    /**
     * Call to check the license key validity
     * @return Engines_Results_MMT_ExceptionError
     * @throws \Engines\MMT\MMTServiceApiException
     * @throws Exception
     */
    public function checkAccount() {
        try {
            $client       = $this->_getClient();
            $this->result = $client->me();

            return $this->result;
        } catch (\Exception $exception){
            throw new Exception("MMT license not valid");
        }
    }

    /**
     * Activate the account and also update/add keys to User MMT data
     *
     * @param $keyList TmKeyManagement_MemoryKeyStruct[]
     *
     * @return mixed
     * @throws \Engines\MMT\MMTServiceApiException
     */
    public function connectKeys( array $keyList ) {

        $keyList = $this->_reMapKeyStructsList( $keyList );
        $client = $this->_getClient();

        // Avoid calling MMT if $keyList is empty
        if(!empty($keyList)){
            $this->result = $client->connectMemories( $keyList );
        }

        return $this->result;
    }

    /**
     * @param $rawValue
     *
     * @return Engines_Results_AbstractResponse
     */
    protected function _decode( $rawValue, array $parameters = [], $function = null ) {

        $args         = func_get_args();
        $functionName = $args[ 2 ];

        if ( is_string( $rawValue ) ) {
            $decoded = json_decode( $rawValue, true );
        } else {

            if ( $rawValue[ 'responseStatus' ] >= 400 ) {
                $_rawValue = json_decode( $rawValue[ 'error' ][ 'response' ], true );
                foreach ( self::$_supportedExceptions as $exception => $code ) {
                    if ( stripos( $rawValue[ 'error' ][ 'response' ], $exception ) !== false ) {
                        $_rawValue[ 'error' ][ 'code' ] = @constant( 'self::' . $rawValue[ 'error' ][ 'type' ] );
                        break;
                    }
                }
                $rawValue = $_rawValue;
            }

            $decoded = $rawValue; // already decoded in case of error

        }

        switch ( $functionName ) {
            default:
                //this case should not be reached
                $result_object = Engines_Results_MMT_ExceptionError::getInstance( [
                        'error'          => [
                                'code'     => -1100,
                                'message'  => " Unknown Error.",
                                'response' => " Unknown Error." // Some useful info might still be contained in the response body
                        ],
                        'responseStatus' => 400
                ] ); //return generic error
                break;
        }

        return $result_object;

    }

    /**
     * @param $name
     * @param null $description
     * @param null $externalId
     * @return mixed
     * @throws \Engines\MMT\MMTServiceApiException
     */
    public function createMemory($name, $description = null, $externalId = null)
    {
        $client = $this->_getClient();

        return $client->createMemory($name, $description, $externalId);
    }

    /**
     * Delete a memory associated to an MMT account
     * (id can be an external account)
     *
     * @param $id
     *
     * @return mixed
     * @throws \Engines\MMT\MMTServiceApiException
     */
    public function deleteMemory($id)
    {
        $client = $this->_getClient();

        return $client->deleteMemory($id);
    }

    /**
     * Get all memories associated to an MMT account
     * (id can be an external account)
     *
     * @return mixed
     * @throws \Engines\MMT\MMTServiceApiException
     */
    public function getAllMemories()
    {
        $client = $this->_getClient();

        return $client->getAllMemories();
    }

    /**
     * Get a memory associated to an MMT account
     * (id can be an external account)
     *
     * @param $id
     *
     * @return mixed
     * @throws \Engines\MMT\MMTServiceApiException
     */
    public function getMemory($id)
    {
        $client = $this->_getClient();

        return $client->getMemory($id);
    }

    /**
     * @param $id
     * @param $name
     * @return mixed
     * @throws \Engines\MMT\MMTServiceApiException
     */
    public function updateMemory($id, $name)
    {
        $client = $this->_getClient();

        return $client->updateMemory($id, $name);
    }

    /**
     * @param $id
     * @param $data
     * @return mixed
     * @throws \Engines\MMT\MMTServiceApiException
     */
    public function importGlossary($id, $data)
    {
        $client = $this->_getClient();

        return $client->importGlossary($id, $data);
    }

    /**
     * @param $id
     * @param $data
     * @return mixed
     * @throws \Engines\MMT\MMTServiceApiException
     */
    public function updateGlossary($id, $data)
    {
        $client = $this->_getClient();

        return $client->updateGlossary($id, $data);
    }

    /**
     * @param $uuid
     * @return mixed
     * @throws \Engines\MMT\MMTServiceApiException
     */
    public function importJobStatus($uuid)
    {
        $client = $this->_getClient();

        return $client->importJobStatus($uuid);
    }
}