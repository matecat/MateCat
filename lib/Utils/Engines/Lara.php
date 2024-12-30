<?php

namespace Utils\Engines;

use Constants_Engines;
use Engine;
use Engines_AbstractEngine;
use Engines_EngineInterface;
use Engines_MMT;
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
use RedisHandler;
use ReflectionException;
use RuntimeException;
use SplFileObject;
use Throwable;
use TmKeyManagement_MemoryKeyStruct;
use TmKeyManagement_TmKeyManagement;
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
     * @see Engines_AbstractEngine::$_isAdaptive
     * @var bool
     */
    protected bool $_isAdaptive = true;

    private ?Translator $clientLoaded = null;

    /**
     * @var Engines_MMT
     */
    private Engines_EngineInterface $mmtUserFallback;

    /**
     * @throws Exception
     */
    public function __construct( $engineRecord ) {
        parent::__construct( $engineRecord );

        if ( $this->engineRecord->type != "MT" ) {
            throw new Exception( "Engine {$this->engineRecord->id} is not a MT engine, found {$this->engineRecord->type} -> {$this->engineRecord->class_load}" );
        }

        $this->_skipAnalysis = true;

    }

    /**
     * Get MMTServiceApi client
     *
     * @return Translator
     */
    protected function _getClient(): Translator {

        if ( !empty( $this->clientLoaded ) ) {
            return $this->clientLoaded;
        }

        $extraParams = $this->engineRecord->getExtraParamsAsArray();
        $credentials = new LaraCredentials( $extraParams[ 'Lara-AccessKeyId' ], $extraParams[ 'Lara-AccessKeySecret' ] );

        $mmtStruct                   = EnginesModel_MMTStruct::getStruct();
        $mmtStruct->type             = Constants_Engines::MT;
        $mmtStruct->extra_parameters = [
                'MMT-License'      => $extraParams[ 'MMT-License' ] ?? INIT::$DEFAULT_MMT_KEY,
                'MMT-pretranslate' => true,
                'MMT-preimport'    => false,
        ];

        $this->mmtUserFallback = Engine::createTempInstance( $mmtStruct );

        $this->clientLoaded = new Translator( $credentials );

        return $this->clientLoaded;
    }

    /**
     * Get the available languages in MMT
     *
     * @return array
     * @throws LaraException
     * @throws ReflectionException
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
        $cache->setex( "lara_languages", 86400, serialize( $value ) );

        return $value;

    }

    protected function _decode( $rawValue, array $parameters = [], $function = null ) {
        // Not used since Lara works with an external client (through composer)
    }

    /**
     * @inheritDoc
     * @throws LaraException
     * @throws ReflectionException
     * @throws Exception
     */
    public function get( $_config ) {

        $tm_keys           = TmKeyManagement_TmKeyManagement::getOwnerKeys( [ $_config[ 'all_job_tm_keys' ] ], 'r' );
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
            $_config[ 'include_score' ] = true;
            $_config[ 'priority' ]      = 'background';

            // analysis on Lara is disabled, fallback on MMT

            return $this->mmtUserFallback->get( $_config );
        } else {
            $_config[ 'priority' ] = 'normal';
        }

        $_lara_keys = $this->_reMapKeyList( $_config[ 'keys' ] );

        $languagesList = $this->getAvailableLanguages();

        if ( in_array( $_config[ 'source' ], $languagesList ) && in_array( $_config[ 'target' ], $languagesList ) ) {
            // call lara
            $translateOptions = new TranslateOptions();
            $translateOptions->setAdaptTo( $_lara_keys );
            $translateOptions->setPriority( $_config[ 'priority' ] );
            $translateOptions->setMultiline( true );

            $request_translation = [];

            foreach ( $_config[ 'context_list_before' ] as $c ) {
                $request_translation[] = new TextBlock( $c, false );
            }

            $request_translation[] = new TextBlock( $_config[ 'segment' ] );

            foreach ( $_config[ 'context_list_after' ] as $c ) {
                $request_translation[] = new TextBlock( $c, false );
            }

            $translationResponse = $client->translate( $request_translation, $_config[ 'source' ], $_config[ 'target' ], $translateOptions );

            $translation = "";
            $tList       = $translationResponse->getTranslation();
            foreach ( $tList as $t ) {
                if ( $t->isTranslatable() ) {
                    $translation = $t->getText();
                    break;
                }
            }

        } else {
            // mmt fallback
            return $this->mmtUserFallback->get( $_config );
        }

        return ( new Engines_Results_MyMemory_Matches(
                $_config[ 'segment' ],
                $translation,
                100 - $this->getPenalty() . "%",
                "MT-" . $this->getName(),
                date( "Y-m-d" )
        ) )->getMatches( 1, [], $_config[ 'source' ], $_config[ 'target' ] );

    }

    /**
     * @inheritDoc
     */
    public function set( $_config ) {
        // TODO: Implement set() method.
    }

    /**
     * @inheritDoc
     */
    public function update( $_config ) {

        $client = $this->_getClient();
        $_keys  = $this->_reMapKeyList( $_config[ 'keys' ] ?? [] );
        try {

            $languagesList = $this->getAvailableLanguages();
            if ( in_array( $_config[ 'source' ], $languagesList ) && in_array( $_config[ 'target' ], $languagesList ) ) {
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
            }

        } catch ( LaraApiException $e ) {
            // Lara license expired/changed (401) or account deleted (403)
            Log::doJsonLog( $e->getMessage() );

            // DO NOT REQUEUE FOR LARA FAILURE ONLY

        } catch ( Exception $e ) {
            // for any other exception (HTTP connection or timeout) requeue
            return false;
        }

        // let MMT to have the last word on requeue
        return $this->mmtUserFallback->update( $_config );

    }

    /**
     * @throws LaraException
     */
    public function importMemory( string $filePath, string $memoryKey, Users_UserStruct $user ) {

        $clientMemories     = $this->_getClient()->memories;
        $associatedMemories = $clientMemories->getAll();
        $memoryFound        = false;

        foreach ( $associatedMemories as $memory ) {
            if ( 'ext_my_' . trim( $memoryKey ) === $memory->getExternalId() ) {
                $memoryFound = true;
                break;
            }
        }

        if ( !$memoryFound ) {
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

        $clientMemories->importTmx( 'ext_my_' . $memoryKey, "$filePath.gz", true );

        $fp_out = null;

    }

    /**
     * @inheritDoc
     */
    public function delete( $_config ) {
        // TODO: Implement delete() method.
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