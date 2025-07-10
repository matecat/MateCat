<?php

namespace Utils\Engines;

use AMQHandler;
use Constants_Engines;
use Engine;
use Engines\MMT\MMTServiceApiException;
use Engines_AbstractEngine;
use Engines_EngineInterface;
use Engines_MMT;
use Engines_Results_AbstractResponse;
use Engines_Results_MyMemory_Matches;
use EnginesModel_MMTStruct;
use Exception;
use Features\Mmt;
use INIT;
use Lara\LaraApiException;
use Lara\LaraCredentials;
use Lara\LaraException;
use Lara\TextBlock;
use Lara\TranslateOptions;
use Lara\Translator;
use Log;
use Projects_ProjectDao;
use RedisHandler;
use ReflectionException;
use RuntimeException;
use SplFileObject;
use Stomp\Transport\Message;
use Throwable;
use TmKeyManagement_MemoryKeyStruct;
use TmKeyManagement_TmKeyManagement;
use TmKeyManagement_TmKeyStruct;
use Users_UserDao;
use Users_UserStruct;

/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 04/12/24
 * Time: 17:56
 *
 */
class Lara extends Engines_AbstractEngine {

    /**
     * @inheritdoc
     * @see Engines_AbstractEngine::$_isAdaptiveMT
     * @var bool
     */
    protected bool $_isAdaptiveMT = true;

    private ?Translator $clientLoaded = null;

    /**
     * @var Engines_MMT
     */
    private Engines_EngineInterface $mmt_GET_Fallback;

    /**
     * @var ?Engines_MMT
     */
    private ?Engines_EngineInterface $mmt_SET_PrivateLicense = null;

    /**
     * @throws Exception
     */
    public function __construct( $engineRecord ) {
        parent::__construct( $engineRecord );

        if ( $this->getEngineRecord()->type != Constants_Engines::MT ) {
            throw new Exception( "Engine {$this->getEngineRecord()->id} is not a MT engine, found {$this->getEngineRecord()->type} -> {$this->getEngineRecord()->class_load}" );
        }

        $this->_skipAnalysis = true;

    }

    /**
     * Get MMTServiceApi client
     *
     * @return Translator
     * @throws Exception
     */
    protected function _getClient(): Translator {

        if ( !empty( $this->clientLoaded ) ) {
            return $this->clientLoaded;
        }

        $extraParams = $this->getEngineRecord()->getExtraParamsAsArray();
        $credentials = new LaraCredentials( $extraParams[ 'Lara-AccessKeyId' ], $extraParams[ 'Lara-AccessKeySecret' ] );

        $mmtStruct                   = EnginesModel_MMTStruct::getStruct();
        $mmtStruct->type             = Constants_Engines::MT;
        $mmtStruct->extra_parameters = [
                'MMT-License'      => $extraParams[ 'MMT-License' ] ?: INIT::$DEFAULT_MMT_KEY,
                'MMT-pretranslate' => true,
                'MMT-preimport'    => false,
        ];
        $this->mmt_GET_Fallback      = Engine::createTempInstance( $mmtStruct );

        if ( !empty( $extraParams[ 'MMT-License' ] ) ) {
            $mmtStruct                    = EnginesModel_MMTStruct::getStruct();
            $mmtStruct->type              = Constants_Engines::MT;
            $mmtStruct->extra_parameters  = [
                    'MMT-License'      => $extraParams[ 'MMT-License' ],
                    'MMT-pretranslate' => true,
                    'MMT-preimport'    => false,
            ];
            $this->mmt_SET_PrivateLicense = Engine::createTempInstance( $mmtStruct );
        }

        $this->clientLoaded = new Translator( $credentials );

        return $this->clientLoaded;
    }

    /**
     * Get the available languages in MMT
     *
     * @return array
     * @throws LaraException
     * @throws ReflectionException
     * @throws Exception
     */
    public function getAvailableLanguages(): array {

        $cache = ( new RedisHandler() )->getConnection();

        $value = [];

        try {
            $value = unserialize( $cache->get( "lara_languages" ) );
        } catch ( Throwable $e ) {
        }

        if ( !empty( $value ) ) {
            return $value;
        }

        $client = $this->_getClient();
        $value  = $client->getLanguages();

        if ( !empty( $value ) ) {
            $value = array_map( function ( $v ) {
                $code = explode( '-', $v );

                return $code[ 0 ];
            }, $value );
            $value = array_unique( $value );
        }

        $cache->setex( "lara_languages", 86400, serialize( $value ) );

        return $value;

    }

    protected function _decode( $rawValue, array $parameters = [], $function = null ) {
        // Not used since Lara works with an external client (through composer)
    }

    /**
     * @inheritDoc
     *
     * @param $_config
     *
     * @return array|Engines_Results_AbstractResponse
     * @throws ReflectionException
     * @throws LaraException
     * @throws Exception
     */
    public function get( $_config ) {

        $tm_keys           = TmKeyManagement_TmKeyManagement::getOwnerKeys( [ $_config[ 'all_job_tm_keys' ] ?? '[]' ], 'r' );
        $_config[ 'keys' ] = array_map( function ( $tm_key ) {
            /**
             * @var $tm_key TmKeyManagement_MemoryKeyStruct
             */
            return $tm_key->key;
        }, $tm_keys );

        // init lara client and mmt fallback
        $client = $this->_getClient();

        // configuration for mmt fallback
        $_config[ 'secret_key' ] = Mmt::getG2FallbackSecretKey();
        if ( $this->_isAnalysis && $this->_skipAnalysis ) {
            // for MMT
            $_config[ 'priority' ] = 'background';

            // analysis on Lara is disabled, fallback on MMT
            return $this->mmt_GET_Fallback->get( $_config );
        } else {
            $_config[ 'priority' ] = 'normal';
        }

        $_lara_keys = $this->_reMapKeyList( $_config[ 'keys' ] );

        try {

            // call lara
            $translateOptions = new TranslateOptions();
            $translateOptions->setAdaptTo( $_lara_keys );
            $translateOptions->setMultiline( false );
            $translateOptions->setContentType( 'application/xliff+xml' );

            $request_translation = [];

            foreach ( $_config[ 'context_list_before' ] ?? [] as $c ) {
                $request_translation[] = new TextBlock( $c, false );
            }

            $request_translation[] = new TextBlock( $_config[ 'segment' ] );

            foreach ( $_config[ 'context_list_after' ] ?? [] as $c ) {
                $request_translation[] = new TextBlock( $c, false );
            }

            $time_start          = microtime( true );
            $translationResponse = $client->translate( $request_translation, $_config[ 'source' ], $_config[ 'target' ], $translateOptions );
            $time_end            = microtime( true );
            $time                = $time_end - $time_start;

            Log::doJsonLog( [
                    'LARA REQUEST' => 'GET https://api.laratranslate.com/translate',
                    'timing'       => [ 'Total Time' => $time, 'Request Start Time' => $time_start, 'Request End Time' => $time_end ],
                    'q'            => $request_translation,
                    'adapt_to'     => $_lara_keys,
                    'source'       => $_config[ 'source' ],
                    'target'       => $_config[ 'target' ],
                    'content_type' => 'application/xliff+xml',
                    'multiline'    => false,
            ] );

            $translation = "";
            $tList       = $translationResponse->getTranslation();
            foreach ( $tList as $t ) {
                if ( $t->isTranslatable() ) {
                    $translation = $t->getText();
                    break;
                }
            }

            // Get score from MMT Quality Estimation
            if ( isset( $_config[ 'include_score' ] ) and $_config[ 'include_score' ] ) {
                $score = $this->getQualityEstimation( $_config[ 'source' ], $_config[ 'target' ], $_config[ 'segment' ], $translation, $_config[ 'mt_qe_engine_id' ] ?? '2' );
            }

        } catch ( LaraException $t ) {
            if ( $t->getCode() == 429 ) {

                Log::doJsonLog( "Lara quota exceeded. You have exceeded your 'api_translation_chars' quota" );

                $engine_type = explode( "\\", self::class );
                $engine_type = array_pop( $engine_type );
                $message     = json_encode( [
                        '_type' => 'quota_exceeded',
                        'data'  => [
                                'id_job'  => $_config[ 'job_id' ],
                                'payload' => [
                                        'engine'  => $engine_type,
                                        'code'    => $t->getCode(),
                                        'message' => "Lara quota exceeded. " . $t->getMessage()
                                ]
                        ]
                ] );

                $queueHandler = AMQHandler::getNewInstanceForDaemons();
                $queueHandler->publishToNodeJsClients( INIT::$SOCKET_NOTIFICATIONS_QUEUE_NAME, new Message( $message ) );

                return [];
            } elseif ( $t->getCode() == 401 || $t->getCode() == 403 ) {
                Log::doJsonLog( [ "Missing or invalid authentication header.", $t->getMessage(), $t->getCode() ] );
                throw new LaraException( "Lara credentials not valid, please verify their validity and try again", $t->getCode(), $t );
            }

            // mmt fallback
            return $this->mmt_GET_Fallback->get( $_config );
        }

        return ( new Engines_Results_MyMemory_Matches( [
                'source'          => $_config[ 'source' ],
                'target'          => $_config[ 'target' ],
                'raw_segment'     => $_config[ 'segment' ],
                'raw_translation' => $translation,
                'match'           => $this->getStandardMtPenaltyString(),
                'created-by'      => $this->getMTName(),
                'create-date'     => date( "Y-m-d" ),
                'score'           => $score ?? null
        ] ) )->getMatches( 1, [], $_config[ 'source' ], $_config[ 'target' ] );
    }

    /**
     * @param string $source
     * @param string $target
     * @param string $sentence
     * @param string $translation
     * @param string $mt_qe_engine_id
     *
     * @return float|null
     */
    public function getQualityEstimation( string $source, string $target, string $sentence, string $translation, string $mt_qe_engine_id = '2' ): ?float {

        $score = null;

        try {
            $score = $this->mmt_GET_Fallback->getQualityEstimation( $source, $target, $sentence, $translation, $mt_qe_engine_id );

            Log::doJsonLog( [
                    'MMT QUALITY ESTIMATION' => 'GET https://api.modernmt.com/translate/qe',
                    'source'                 => $source,
                    'target'                 => $target,
                    'segment'                => $sentence,
                    'translation'            => $translation,
                    'score'                  => $score,
                    'purfect_version'        => $mt_qe_engine_id
            ] );

        } catch ( MMTServiceApiException $exception ) {
            Log::doJsonLog( [
                    'MMT QUALITY ESTIMATION ERROR' => 'GET https://api.modernmt.com/translate/qe',
                    'error'                        => $exception->getMessage(),
            ] );
        } finally {
            return $score;
        }

    }

    /**
     * @inheritDoc
     */
    public function set( $_config ) {
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function update( $_config ) {

        $client = $this->_getClient();
        $_keys  = $this->_reMapKeyList( $_config[ 'keys' ] ?? [] );

        if ( empty( $_keys ) ) {
            Log::doJsonLog( [ "LARA: update skipped. No keys provided." ] );

            return true;
        }

        try {

            $time_start = microtime( true );
            // call lara
            $client->memories->addTranslation(
                    $_keys,
                    $_config[ 'source' ],
                    $_config[ 'target' ],
                    $_config[ 'segment' ],
                    $_config[ 'translation' ],
                    $_config[ 'tuid' ],
                    $_config[ 'context_before' ],
                    $_config[ 'context_after' ],
            );
            $time_end = microtime( true );
            $time     = $time_end - $time_start;

            Log::doJsonLog( [
                    'LARA REQUEST'    => 'PUT https://api.laratranslate.com/memories/content',
                    'timing'          => [ 'Total Time' => $time, 'Request Start Time' => $time_start, 'Request End Time' => $time_end ],
                    'keys'            => $_keys,
                    'source'          => $_config[ 'source' ],
                    'target'          => $_config[ 'target' ],
                    'sentence'        => $_config[ 'segment' ],
                    'translation'     => $_config[ 'translation' ],
                    'tuid'            => $_config[ 'tuid' ],
                    'sentence_before' => $_config[ 'context_before' ],
                    'sentence_after'  => $_config[ 'context_after' ],
            ] );

        } catch ( Exception $e ) {
            // for any exception (HTTP connection or timeout) requeue
            return false;
        }

        // let MMT to have the last word on requeue
        return !empty( $this->mmt_SET_PrivateLicense ) ? $this->mmt_SET_PrivateLicense->update( $_config ) : true;

    }

    /**
     * @param TmKeyManagement_MemoryKeyStruct $memoryKey
     *
     * @return array|null
     * @throws LaraException
     * @throws Exception
     */
    public function memoryExists( TmKeyManagement_MemoryKeyStruct $memoryKey ): ?array {
        $clientMemories = $this->_getClient()->memories;
        $memory         = $clientMemories->get( 'ext_my_' . trim( $memoryKey->tm_key->key ) );
        if ( $memory ) {
            return $memory->jsonSerialize();
        }

        return null;
    }

    /**
     * @throws LaraException
     * @throws Exception
     */
    public function deleteMemory( array $memoryKey ): array {
        $clientMemories = $this->_getClient()->memories;
        try {

            if ( !empty( $this->mmt_SET_PrivateLicense ) ) {
                $memoryKeyToUpdate         = new TmKeyManagement_MemoryKeyStruct();
                $memoryKeyToUpdate->tm_key = new TmKeyManagement_TmKeyStruct( [ 'key' => str_replace( 'ext_my_', '', $memoryKey[ 'externalId' ] ) ] );

                $memoryMMT = $this->mmt_SET_PrivateLicense->getMemoryIfMine( $memoryKeyToUpdate );
                if ( !empty( $memoryMMT ) ) {
                    $this->mmt_SET_PrivateLicense->deleteMemory( $memoryMMT );
                }
            }

            return $clientMemories->delete( trim( $memoryKey[ 'id' ] ) )->jsonSerialize();
        } catch ( LaraApiException $e ) {
            if ( $e->getCode() == 404 ) {
                return [];
            }
            throw $e;
        }
    }

    /**
     * In 'Lara', there is no need to check the ownership of the memory because if a memory exists within an account, it definitely ALSO belongs to me and can be safely deleted (unlinked from my account).
     * Therefore, unlike ModernMT, this method is simply an alias of the memoryExists method.
     * @throws LaraException
     */
    public function getMemoryIfMine( TmKeyManagement_MemoryKeyStruct $memoryKey ): ?array {
        return $this->memoryExists( $memoryKey );
    }


    /**
     * @throws LaraException
     * @throws Exception
     */
    public function importMemory( string $filePath, string $memoryKey, Users_UserStruct $user ) {

        $clientMemories = $this->_getClient()->memories;

        if ( !$clientMemories->get( 'ext_my_' . trim( $memoryKey ) ) ) {
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

        $res = $clientMemories->importTmx( 'ext_my_' . $memoryKey, "$filePath.gz", true );
        Log::doJsonLog( $res );

        $fp_out = null;

        if ( !empty( $this->mmt_SET_PrivateLicense ) ) {
            $this->mmt_SET_PrivateLicense->importMemory( $filePath, $memoryKey, $user );
        }

    }

    /**
     * @param array      $projectRow
     * @param array|null $segments
     *
     * @return void
     */
    public function syncMemories( array $projectRow, ?array $segments = [] ) {

        try {

            // get jobs keys
            $project = Projects_ProjectDao::findById( $projectRow[ 'id' ] );
            $user    = ( new Users_UserDao )->getByEmail( $projectRow[ 'id_customer' ] );

            foreach ( $project->getJobs() as $job ) {

                $keyIds          = [];
                $jobKeyListRead  = TmKeyManagement_TmKeyManagement::getJobTmKeys( $job->tm_keys, 'r', 'tm', $user->uid );
                $jobKeyListWrite = TmKeyManagement_TmKeyManagement::getJobTmKeys( $job->tm_keys, 'w', 'tm', $user->uid );
                $jobKeyList      = array_merge( $jobKeyListRead, $jobKeyListWrite );

                foreach ( $jobKeyList as $memKey ) {
                    $keyIds[] = $memKey->key;
                }

                $keyIds = $this->_reMapKeyList( array_values( array_unique( $keyIds ) ) );
                $client = $this->_getClient();
                $res    = $client->memories->connect( $keyIds );
                Log::doJsonLog( "Keys connected: " . implode( ',', $keyIds ) . " -> " . json_encode( $res ) );

            }

        } catch ( Exception $e ) {
            Log::doJsonLog( $e->getMessage() );
            Log::doJsonLog( $e->getTraceAsString() );
        }

    }

    /**
     * @inheritDoc
     */
    public function delete( $_config ) {
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
                return 'ext_my_' . $key;
            }, $_keys );

        }

        return $_keys;

    }

}