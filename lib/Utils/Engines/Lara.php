<?php

namespace Utils\Engines;

use Exception;
use Lara\Glossary;
use Lara\LaraApiException;
use Lara\LaraCredentials;
use Lara\LaraException;
use Lara\TextBlock;
use Lara\TranslateOptions;
use Model\Engines\Structs\MMTStruct;
use Model\Projects\MetadataDao;
use Model\Projects\ProjectDao;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use Plugins\Features\Mmt;
use ReflectionException;
use RuntimeException;
use SplFileObject;
use Stomp\Transport\Message;
use Throwable;
use Utils\ActiveMQ\AMQHandler;
use Utils\Constants\EngineConstants;
use Utils\Engines\Lara\Headers;
use Utils\Engines\Lara\LaraClient;
use Utils\Engines\MMT as MMTEngine;
use Utils\Engines\MMT\MMTServiceApiException;
use Utils\Engines\Results\MyMemory\Matches;
use Utils\Engines\Results\TMSAbstractResponse;
use Utils\Redis\RedisHandler;
use Utils\Registry\AppConfig;
use Utils\TmKeyManagement\TmKeyManager;
use Utils\TmKeyManagement\TmKeyStruct;

/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 04/12/24
 * Time: 17:56
 *
 */
class Lara extends AbstractEngine {

    /**
     * @inheritdoc
     * @see AbstractEngine::$_isAdaptiveMT
     * @var bool
     */
    protected bool $_isAdaptiveMT = true;

    private ?LaraClient $clientLoaded = null;

    /**
     * @var MMTEngine
     */
    private MMTEngine $mmt_GET_Fallback;

    /**
     * @var ?MMTEngine
     */
    private ?MMTEngine $mmt_SET_PrivateLicense = null;

    /**
     * @throws Exception
     */
    public function __construct( $engineRecord ) {
        parent::__construct( $engineRecord );

        if ( $this->getEngineRecord()->type != EngineConstants::MT ) {
            throw new Exception( "Engine {$this->getEngineRecord()->id} is not a MT engine, found {$this->getEngineRecord()->type} -> {$this->getEngineRecord()->class_load}" );
        }

        $this->_skipAnalysis = true;

    }

    /**
     * Get MMTServiceApi client
     *
     * @return LaraClient
     * @throws Exception
     */
    protected function _getClient(): LaraClient {

        if ( !empty( $this->clientLoaded ) ) {
            return $this->clientLoaded;
        }

        $extraParams = $this->getEngineRecord()->getExtraParamsAsArray();
        $credentials = new LaraCredentials( $extraParams[ 'Lara-AccessKeyId' ], $extraParams[ 'Lara-AccessKeySecret' ] );

        $mmtStruct                   = MMTStruct::getStruct();
        $mmtStruct->type             = EngineConstants::MT;
        $mmtStruct->extra_parameters = [
                'MMT-License'      => $extraParams[ 'MMT-License' ] ?: AppConfig::$DEFAULT_MMT_KEY,
                'MMT-pretranslate' => true,
                'MMT-preimport'    => false,
        ];
        /**
         * @var MMTEngine $engine
         */
        $engine                 = EnginesFactory::createTempInstance( $mmtStruct );
        $this->mmt_GET_Fallback = $engine;

        if ( !empty( $extraParams[ 'MMT-License' ] ) ) {
            $mmtStruct                   = MMTStruct::getStruct();
            $mmtStruct->type             = EngineConstants::MT;
            $mmtStruct->extra_parameters = [
                    'MMT-License'      => $extraParams[ 'MMT-License' ],
                    'MMT-pretranslate' => true,
                    'MMT-preimport'    => false,
            ];
            /**
             * @var MMTEngine $engine
             */
            $engine                       = EnginesFactory::createTempInstance( $mmtStruct );
            $this->mmt_SET_PrivateLicense = $engine;
        }

        $this->clientLoaded = new LaraClient( $credentials );

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
     * @param array $_config
     *
     * @return array|TMSAbstractResponse
     * @throws ReflectionException
     * @throws LaraException
     * @throws Exception
     */
    public function get( array $_config ) {

        if ( $this->_isAnalysis && $this->_skipAnalysis ) {
            return [];
        }

        $tm_keys           = TmKeyManager::getOwnerKeys( [ $_config[ 'all_job_tm_keys' ] ?? '[]' ], 'r' );
        $_config[ 'keys' ] = array_map( function ( $tm_key ) {
            /**
             * @var $tm_key MemoryKeyStruct
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

            $headers = new Headers();

            if ( !empty( $_config[ 'tuid' ] ) and is_string( $_config[ 'tuid' ] ) ) {
                $headers->setTuid( $_config[ 'tuid' ] );
            }

            $translateOptions->setHeaders( $headers->getArrayCopy() );

            if ( !empty( $_config[ 'project_id' ] ) ) {
                $metadataDao = new MetadataDao();
                $metadata    = $metadataDao->setCacheTTL( 86400 )->get( $_config[ 'project_id' ], 'lara_glossaries' );

                if ( $metadata !== null ) {
                    $metadata            = html_entity_decode( $metadata->value );
                    $laraGlossariesArray = json_decode( $metadata, true );
                    $translateOptions->setGlossaries( $laraGlossariesArray );
                }
            }

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

            $this->logger->debug( [
                    'LARA REQUEST'  => 'GET https://api.laratranslate.com/translate',
                    'timing'        => [ 'Total Time' => $time, 'Get Start Time' => $time_start, 'Get End Time' => $time_end ],
                    'q'             => $request_translation,
                    'adapt_to'      => $_lara_keys,
                    'source'        => $_config[ 'source' ],
                    'target'        => $_config[ 'target' ],
                    'content_type'  => 'application/xliff+xml',
                    'multiline'     => false,
                    'translation'   => $translation,
                    'score'         => $score ?? null,
                    'extra_headers' => $headers->getArrayCopy(),
            ] );

        } catch ( LaraException $t ) {
            if ( $t->getCode() == 429 ) {

                $this->logger->debug( "Lara quota exceeded. You have exceeded your 'api_translation_chars' quota" );

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
                $queueHandler->publishToNodeJsClients( AppConfig::$SOCKET_NOTIFICATIONS_QUEUE_NAME, new Message( $message ) );

                return [];
            } elseif ( $t->getCode() == 401 || $t->getCode() == 403 ) {
                $this->logger->debug( [ "Missing or invalid authentication header.", $t->getMessage(), $t->getCode() ] );
                throw new LaraException( "Lara credentials not valid, please verify their validity and try again", $t->getCode(), $t );
            }

            // mmt fallback
            return $this->mmt_GET_Fallback->get( $_config );
        }

        return ( new Matches( [
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

            $this->logger->debug( [
                    'MMT QUALITY ESTIMATION' => 'GET https://api.modernmt.com/translate/qe',
                    'source'                 => $source,
                    'target'                 => $target,
                    'segment'                => $sentence,
                    'translation'            => $translation,
                    'score'                  => $score,
                    'purfect_version'        => $mt_qe_engine_id
            ] );

        } catch ( MMTServiceApiException $exception ) {
            $this->logger->debug( [
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
            $this->logger->debug( [ "LARA: update skipped. No keys provided." ] );

            return true;
        }

        try {

            $time_start = microtime( true );
            $headers    = new Headers( $_config[ 'tuid' ], $_config[ 'translation_origin' ] );
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
                    $headers->getArrayCopy()
            );
            $time_end = microtime( true );
            $time     = $time_end - $time_start;

            $this->logger->debug( [
                    'LARA REQUEST'    => 'PUT https://api.laratranslate.com/memories/content',
                    'timing'          => [ 'Total Time' => $time, 'Get Start Time' => $time_start, 'Get End Time' => $time_end ],
                    'keys'            => $_keys,
                    'source'          => $_config[ 'source' ],
                    'target'          => $_config[ 'target' ],
                    'sentence'        => $_config[ 'segment' ],
                    'translation'     => $_config[ 'translation' ],
                    'tuid'            => $_config[ 'tuid' ],
                    'sentence_before' => $_config[ 'context_before' ],
                    'sentence_after'  => $_config[ 'context_after' ],
                    'extra_headers'   => $headers->getArrayCopy(),
            ] );

        } catch ( Exception $e ) {
            // for any exception (HTTP connection or timeout) requeue
            return false;
        }

        // let MMT to have the last word on requeue
        return empty( $this->mmt_SET_PrivateLicense ) || $this->mmt_SET_PrivateLicense->update( $_config );

    }

    /**
     * @param MemoryKeyStruct $memoryKey
     *
     * @return array|null
     * @throws LaraException
     * @throws Exception
     */
    public function memoryExists( MemoryKeyStruct $memoryKey ): ?array {
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
                $memoryKeyToUpdate         = new MemoryKeyStruct();
                $memoryKeyToUpdate->tm_key = new TmKeyStruct( [ 'key' => str_replace( 'ext_my_', '', $memoryKey[ 'externalId' ] ) ] );

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
    public function getMemoryIfMine( MemoryKeyStruct $memoryKey ): ?array {
        return $this->memoryExists( $memoryKey );
    }


    /**
     * @throws LaraException
     * @throws Exception
     */
    public function importMemory( string $filePath, string $memoryKey, UserStruct $user ) {

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
        $this->logger->debug( $res );

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
            $project = ProjectDao::findById( $projectRow[ 'id' ] );
            $user    = ( new UserDao )->getByEmail( $projectRow[ 'id_customer' ] );

            foreach ( $project->getJobs() as $job ) {

                $keyIds          = [];
                $jobKeyListRead  = TmKeyManager::getJobTmKeys( $job->tm_keys, 'r', 'tm', $user->uid );
                $jobKeyListWrite = TmKeyManager::getJobTmKeys( $job->tm_keys, 'w', 'tm', $user->uid );
                $jobKeyList      = array_merge( $jobKeyListRead, $jobKeyListWrite );

                foreach ( $jobKeyList as $memKey ) {
                    $keyIds[] = $memKey->key;
                }

                $keyIds = $this->_reMapKeyList( array_values( array_unique( $keyIds ) ) );
                $client = $this->_getClient();
                $res    = $client->memories->connect( $keyIds );
                $this->logger->debug( "Keys connected: " . implode( ',', $keyIds ) . " -> " . json_encode( $res ) );

            }

        } catch ( Exception $e ) {
            $this->logger->debug( $e->getMessage() );
            $this->logger->debug( $e->getTraceAsString() );
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

    /**
     * @return Glossary[]
     * @throws LaraException
     * @throws Exception
     */
    public function getGlossaries(): array {
        $client     = $this->_getClient();
        $glossaries = $client->glossaries;

        if ( empty( $glossaries ) ) {
            return [];
        }

        return $glossaries->getAll();
    }

    /**
     * @inheritDoc
     */
    public function getExtraParams(): array {
        return [
                'pre_translate_files',
                'lara_glossaries',
        ];
    }
}