<?php

use Engines\MMT\MMTServiceApi;
use Engines\MMT\MMTServiceApiException;
use Engines\MMT\MMTServiceApiRequestException;
use Features\Mmt;
use Jobs\MetadataDao;
use TaskRunner\Commons\QueueElement;

/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 17/05/16
 * Time: 13:11
 *
 * @property int id
 */
class Engines_MMT extends Engines_AbstractEngine {

    /**
     * @inheritdoc
     * @see Engines_AbstractEngine::$_isAdaptiveMT
     * @var bool
     */
    protected bool $_isAdaptiveMT = true;

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

        if ( $this->getEngineRecord()->type != Constants_Engines::MT ) {
            throw new Exception( "Engine {$this->getEngineRecord()->id} is not a MT engine, found {$this->getEngineRecord()->type} -> {$this->getEngineRecord()->class_load}" );
        }

        if ( isset( $this->getEngineRecord()->extra_parameters[ 'MMT-pretranslate' ] ) && $this->getEngineRecord()->extra_parameters[ 'MMT-pretranslate' ] ) {
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

        $extraParams = $this->getEngineRecord()->getExtraParamsAsArray();
        $license     = $extraParams[ 'MMT-License' ];

        return Engines\MMT\MMTServiceApi::newInstance()
                ->setIdentity( "Matecat", ltrim( INIT::$BUILD_NUMBER, 'v' ) )
                ->setLicense( $license );
    }

    /**
     * Get the available languages in MMT
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function getAvailableLanguages() {
        $client = $this->_getClient();

        return $client->getAvailableLanguages();
    }

    /**
     * @param $_config
     *
     * @return array|Engines_Results_AbstractResponse
     * @throws ReflectionException
     */
    public function get( $_config ) {

        //This is not really needed because by default in analysis the Engine_MMT is accepted by MyMemory
        if ( $this->_isAnalysis && $this->_skipAnalysis ) {
            return [];
        }

        $client = $this->_getClient();
        $_keys  = $this->_reMapKeyList( $_config[ 'keys' ] ?? [] );

        $metadata = null;
        if ( !empty( $_config[ 'project_id' ] ) ) {
            $metadataDao = new Projects_MetadataDao();
            $metadata    = $metadataDao->setCacheTTL( 86400 )->get( $_config[ 'project_id' ], 'mmt_glossaries' );
        }

        if ( $metadata !== null ) {
            $metadata           = html_entity_decode( $metadata->value );
            $mmtGlossariesArray = json_decode( $metadata, true );

            $_config[ 'glossaries' ]           = implode( ",", $mmtGlossariesArray[ 'glossaries' ] );
            $_config[ 'ignore_glossary_case' ] = $mmtGlossariesArray[ 'ignore_glossary_case' ];
        }

        $_config = $this->configureAnalysisContribution($_config);

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
                    $_config[ 'ignore_glossary_case' ] ?? null,
                    $_config[ 'include_score' ] ?? null
            );

            return ( new Engines_Results_MyMemory_Matches(
                    $_config[ 'segment' ],
                    $translation[ 'translation' ],
                    100 - $this->getPenalty() . "%",
                    "MT-" . $this->getName(),
                    date( "Y-m-d" ),
                    $translation[ 'score' ] ?? null
            ) )->getMatches( 1, [], $_config[ 'source' ], $_config[ 'target' ] );

        } catch ( Exception $e ) {
            return $this->GoogleTranslateFallback( $_config );
        }

    }

    /**
     * @param array $_keys
     *
     * @return array
     */
    protected function _reMapKeyList( array $_keys = [] ): array {

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
            $client->addToMemoryContent( $_keys, $_config[ 'source' ], $_config[ 'target' ], $_config[ 'segment' ], $_config[ 'translation' ], $_config[ 'session' ] );
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
        $_keys  = $this->_reMapKeyList( $_config[ 'keys' ] ?? [] );

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
            return false; // requeue
        }

        return true;

    }

    public function delete( $_config ) {
        throw new DomainException( "Method " . __FUNCTION__ . " not implemented." );
    }

    /**
     * @throws MMTServiceApiException
     */
    public function memoryExists( TmKeyManagement_MemoryKeyStruct $memoryKey ): ?array {
        $client = $this->_getClient();

        try {
            $response = $client->getMemory( 'x_mm-' . trim( $memoryKey->tm_key->key ) );
        } catch ( MMTServiceApiRequestException $e ) {
            return null;
        }

        return $response;
    }


    /**
     *
     * @param string           $filePath
     * @param string           $memoryKey
     * @param Users_UserStruct $user *
     *
     * @return void
     * @throws MMTServiceApiException
     * @throws Exception
     */
    public function importMemory( string $filePath, string $memoryKey, Users_UserStruct $user ) {

        $client   = $this->_getClient();
        $response = $client->getMemory( 'x_mm-' . trim( $memoryKey ) );

        if ( empty( $response ) ) {
            return null;
        }

        $fp_out = gzopen( "$filePath.gz", 'wb9' );

        if ( !$fp_out ) {
            $fp_out = null;
            throw new RuntimeException( 'IOException. Unable to create temporary file.' );
        }

        $tmpFileObject = new SplFileObject( $filePath, 'r' );

        while ( !$tmpFileObject->eof() ) {
            gzwrite( $fp_out, $tmpFileObject->fgets() );
        }

        $tmpFileObject = null;
        gzclose( $fp_out );

        $client->importIntoMemoryContent( 'x_mm-' . trim( $memoryKey ), "$filePath.gz", 'gzip' );
        $fp_out = null;

    }

    /**
     * @throws Exception
     */
    public function syncMemories( array $projectRow, ?array $segments = [] ) {

        $pid = $projectRow[ 'id' ];

        if ( !empty( $this->getEngineRecord()->getExtraParamsAsArray()[ 'MMT-context-analyzer' ] ) ) {

            $source       = $segments[ 0 ][ 'source' ];
            $targets      = [];
            $jobLanguages = [];
            foreach ( explode( ',', $segments[ 0 ][ 'target' ] ) as $jid_Lang ) {
                [ $jobId, $target ] = explode( ":", $jid_Lang );
                $jobLanguages[ $jobId ] = $source . "|" . $target;
                $targets[]              = $target;
            }

            $tmp_name      = tempnam( sys_get_temp_dir(), 'mmt_cont_req-' );
            $tmpFileObject = new SplFileObject( tempnam( sys_get_temp_dir(), 'mmt_cont_req-' ), 'w+' );
            foreach ( $segments as $segment ) {
                $tmpFileObject->fwrite( $segment[ 'segment' ] . "\n" );
            }

            try {

                /*
                    $result = Array
                    (
                        [en-US|es-ES] => 1:0.14934476,2:0.08131008,3:0.047170084
                        [en-US|it-IT] =>
                    )
                */
                $result = $this->getContext( $tmpFileObject, $source, $targets );

                $jMetadataDao = new MetadataDao();

                Database::obtain()->begin();
                foreach ( $result as $langPair => $context ) {
                    $jMetadataDao->setCacheTTL( 60 * 60 * 24 * 30 )->set( array_search( $langPair, $jobLanguages ), "", 'mt_context', $context );
                }
                Database::obtain()->commit();

            } catch ( Exception $e ) {
                Log::doJsonLog( $e->getMessage() );
                Log::doJsonLog( $e->getTraceAsString() );
            } finally {
                unset( $tmpFileObject );
                @unlink( $tmp_name );
            }

        }

        try {

            //
            // ==============================================
            // If the MMT-preimport flag is disabled
            // and user is logged in
            // send user keys on a project basis
            // ==============================================
            //
            $preImportIsDisabled = empty( $this->getEngineRecord()->getExtraParamsAsArray()[ 'MMT-preimport' ] );
            $user                = ( new Users_UserDao )->getByEmail( $projectRow[ 'id_customer' ] );

            if ( $preImportIsDisabled ) {

                // get jobs keys
                $project = Projects_ProjectDao::findById( $pid );

                foreach ( $project->getJobs() as $job ) {

                    $memoryKeyStructs = [];
                    $jobKeyList       = TmKeyManagement_TmKeyManagement::getJobTmKeys( $job->tm_keys, 'r', 'tm', $user->uid );

                    foreach ( $jobKeyList as $memKey ) {
                        $memoryKeyStructs[] = new TmKeyManagement_MemoryKeyStruct(
                                [
                                        'uid'    => $user->uid,
                                        'tm_key' => $memKey
                                ]
                        );
                    }

                    $this->connectKeys( $memoryKeyStructs );

                }

            }
        } catch ( Exception $e ) {
            Log::doJsonLog( $e->getMessage() );
            Log::doJsonLog( $e->getTraceAsString() );
        }

    }

    /**
     *
     * @param $file    SplFileObject
     * @param $source  string
     * @param $targets string[]
     *
     * @return mixed
     * @throws MMTServiceApiException
     * @internal param array $langPairs
     *
     */
    protected function getContext( SplFileObject $file, $source, $targets ) {

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
     * @throws MMTServiceApiException
     * @throws Exception
     */
    public function checkAccount() {
        try {
            $client       = $this->_getClient();
            $this->result = $client->me();

            return $this->result;
        } catch ( Exception $exception ) {
            throw new Exception( "MMT license not valid" );
        }
    }

    /**
     * Activate the account and also update/add keys to User MMT data
     *
     * @param $keyList TmKeyManagement_MemoryKeyStruct[]
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function connectKeys( array $keyList ) {

        $keyList = $this->_reMapKeyStructsList( $keyList );
        $client  = $this->_getClient();

        // Avoid calling MMT if $keyList is empty
        if ( !empty( $keyList ) ) {
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
     * @param      $name
     * @param null $description
     * @param null $externalId
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function createMemory( $name, $description = null, $externalId = null ) {
        $client = $this->_getClient();

        return $client->createMemory( $name, $description, $externalId );
    }

    /**
     * Delete a memory associated to an MMT account
     * (id can be an external account)
     *
     * @param array $memoryKey
     *
     * @return array
     * @throws MMTServiceApiException
     */
    public function deleteMemory( array $memoryKey ): array {
        $client = $this->_getClient();

        return $client->deleteMemory( trim( $memoryKey[ 'id' ] ) );
    }

    /**
     * Get all memories associated to an MMT account
     * (id can be an external account)
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function getAllMemories() {
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
     * @throws MMTServiceApiException
     */
    public function getMemory( $id ) {
        $client = $this->_getClient();

        return $client->getMemory( $id );
    }

    /**
     * @param $id
     * @param $name
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function updateMemory( $id, $name ) {
        $client = $this->_getClient();

        return $client->updateMemory( $id, $name );
    }

    /**
     * @param $id
     * @param $data
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function importGlossary( $id, $data ) {
        $client = $this->_getClient();

        return $client->importGlossary( $id, $data );
    }

    /**
     * @param $id
     * @param $data
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function updateGlossary( $id, $data ) {
        $client = $this->_getClient();

        return $client->updateGlossary( $id, $data );
    }

    /**
     * @param $uuid
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function importJobStatus( $uuid ) {
        $client = $this->_getClient();

        return $client->importJobStatus( $uuid );
    }

    /**
     * @throws MMTServiceApiException
     */
    public function getMemoryIfMine( TmKeyManagement_MemoryKeyStruct $memoryKey ): ?array {
        //Get the user account, check if the memory exists and, if so, check if the key owner's ID is mine.
        $me     = $this->checkAccount();
        $memory = $this->memoryExists( $memoryKey );
        if ( !empty( $memory ) && $memory[ 'owner' ][ 'user' ] == $me[ 'id' ] ) {
            return $memory;
        }
        return null;
    }

    /**
     * @param $source
     * @param $target
     * @param $sentence
     * @param $translation
     * @return float|null
     * @throws MMTServiceApiException
     */
    public function getQualityEstimation($source, $target, $sentence, $translation): ?float
    {
        $client = $this->_getClient();
        $qualityEstimation = $client->qualityEstimation($source, $target, $sentence, $translation);

        return $qualityEstimation['score'];
    }

    /**
     * @param $config
     * @return mixed
     */
    private function configureAnalysisContribution($config)
    {
        $id_job        = $config[ 'job_id' ] ?? null;
        $mt_evaluation = $config[ 'mt_evaluation' ] ?? null;

        if($id_job and $this->_isAnalysis){
            $contextRs  = ( new MetadataDao() )->setCacheTTL( 60 * 60 * 24 * 30 )->getByIdJob( $id_job, 'mt_context' );
            $mt_context = @array_pop( $contextRs );

            if ( !empty( $mt_context ) ) {
                $config[ 'mt_context' ] = $mt_context->value;
            }

            if ( empty( $mt_evaluation ) ) {
                $mt_evaluation  = ( new MetadataDao() )->setCacheTTL( 60 * 60 * 24 * 30 )->getByIdJob( $id_job, 'mt_evaluation' );
            }

            if ( $mt_evaluation ) {
                $config[ 'include_score' ] = true;
            }

            $config[ 'secret_key' ] = Mmt::getG2FallbackSecretKey();
            $config[ 'priority' ]   = 'background';
            $config[ 'keys' ]       = $config[ 'id_user' ] ?? [];
        }

        return $config;
    }
}
