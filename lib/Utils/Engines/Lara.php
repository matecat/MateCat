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
use Lara\LaraCredentials;
use Lara\LaraException;
use Lara\TranslateOptions;
use Lara\Translator;
use RedisHandler;
use ReflectionException;
use Throwable;
use TmKeyManagement_MemoryKeyStruct;
use TmKeyManagement_TmKeyManagement;

/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 04/12/24
 * Time: 17:56
 *
 */
class Lara extends Engines_AbstractEngine {

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

        if( !empty( $this->clientLoaded ) ){
            return $this->clientLoaded;
        }

        $extraParams = $this->engineRecord->getExtraParamsAsArray();
        $credentials = new LaraCredentials( $extraParams[ 'Lara-AccessKeyId' ], $extraParams[ 'Lara-AccessKeySecret' ] );

        $mmtStruct                   = EnginesModel_MMTStruct::getStruct();
        $mmtStruct->type             = Constants_Engines::MT;
        $mmtStruct->extra_parameters = [
                'MMT-License'      => $extraParams[ 'MMT-License' ],
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
        // TODO: Implement _decode() method.
    }

    /**
     * @inheritDoc
     * @throws LaraException
     * @throws ReflectionException
     * @throws Exception
     */
    public function get( $_config ) {

        $tm_keys          = TmKeyManagement_TmKeyManagement::getOwnerKeys( [ $_config[ 'all_job_tm_keys' ] ] );
        $_config[ 'keys' ] = array_map( function ( $tm_key ) {
            /**
             * @var $tm_key TmKeyManagement_MemoryKeyStruct
             */
            return $tm_key->key;
        }, $tm_keys );

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

        $client     = $this->_getClient();
        $_lara_keys = $this->_reMapKeyList( $_config[ 'keys' ] );

        $languagesList = $this->getAvailableLanguages();

        if ( in_array( $_config[ 'source' ], $languagesList ) && in_array( $_config[ 'target' ], $languagesList ) ) {
            // call lara
            $translateOptions = new TranslateOptions();
            $translateOptions->setAdaptTo( $_lara_keys );
            $translateOptions->setPriority( $_config[ 'priority' ] );
            $translation = $client->translate( $_config[ 'segment' ], $_config[ 'source' ], $_config[ 'target' ], $translateOptions );
        } else {
            // mmt fallback
            return $this->mmtUserFallback->get( $_config );
        }

        return ( new Engines_Results_MyMemory_Matches(
                $_config[ 'segment' ],
                $translation->getTranslation(),
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
        // TODO: Implement update() method.
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