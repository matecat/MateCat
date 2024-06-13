<?php

namespace AsyncTasks\Workers;

use Database;
use Engine;
use Engines_AbstractEngine;
use Engines_MyMemory;
use Engines_Results_MyMemory_CheckGlossaryResponse;
use Engines_Results_MyMemory_DomainsResponse;
use Engines_Results_MyMemory_GetGlossaryResponse;
use Engines_Results_MyMemory_KeysGlossaryResponse;
use Engines_Results_MyMemory_SearchGlossaryResponse;
use Engines_Results_MyMemory_SetGlossaryResponse;
use Engines_Results_MyMemory_UpdateGlossaryResponse;
use EnginesModel_EngineDAO;
use EnginesModel_EngineStruct;
use Exception;
use FeatureSet;
use Stomp\Exception\StompException;
use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\AbstractWorker;
use TaskRunner\Exceptions\EndQueueException;
use Users_UserStruct;

class GlossaryWorker extends AbstractWorker {

    const CHECK_ACTION   = 'check';
    const DELETE_ACTION  = 'delete';
    const GET_ACTION     = 'get';
    const KEYS_ACTION    = 'keys';
    const SET_ACTION     = 'set';
    const UPDATE_ACTION  = 'update';
    const DOMAINS_ACTION = 'domains';
    const SEARCH_ACTION  = 'search';

    /**
     * @param AbstractElement $queueElement
     *
     * @return void
     * @throws Exception
     */
    public function process( AbstractElement $queueElement ) {

        $params  = $queueElement->params->toArray();
        $action  = $params[ 'action' ];
        $payload = $params[ 'payload' ];

        $allowedActions = [
                self::CHECK_ACTION,
                self::DELETE_ACTION,
                self::DOMAINS_ACTION,
                self::GET_ACTION,
                self::SEARCH_ACTION,
                self::SET_ACTION,
                self::KEYS_ACTION,
                self::UPDATE_ACTION,
        ];

        if ( false === in_array( $action, $allowedActions ) ) {
            throw new EndQueueException( $action . ' is not an allowed action. ' );
        }

        $this->_checkDatabaseConnection();

        $this->_doLog( 'GLOSSARY: ' . $action . ' action was executed with payload ' . json_encode( $payload ) );

        $this->{$action}( $payload );
    }

    /**
     * Check a key on MyMemory
     *
     * @param $payload
     *
     * @throws Exception
     */
    private function check( $payload ) {

        $client = $this->getMyMemoryClient();

        /** @var Engines_Results_MyMemory_CheckGlossaryResponse $response */
        $response = $client->glossaryCheck( $payload[ 'source' ], $payload[ 'target' ], $payload[ 'source_language' ], $payload[ 'target_language' ], $payload[ 'keys' ] );
        $matches  = $response->matches;

        if ( empty( $matches[ 'id_segment' ] ) ) {
            $id_segment              = isset( $payload[ 'id_segment' ] ) ? $payload[ 'id_segment' ] : null;
            $matches[ 'id_segment' ] = $id_segment;
        }

        $this->publishToSseTopic(
                $this->setResponsePayload(
                        'glossary_check',
                        $payload[ 'id_client' ],
                        $payload[ 'jobData' ],
                        $matches
                )
        );
    }

    /**
     * Delete a key from MyMemory
     *
     * @param $payload
     *
     * @throws Exception
     */
    private function delete( $payload ) {

        $client = $this->getMyMemoryClient();

        /** @var Engines_Results_MyMemory_UpdateGlossaryResponse $response */
        $response   = $client->glossaryDelete( $payload[ 'id_segment' ], $payload[ 'id_job' ], $payload[ 'password' ], $payload[ 'term' ] );
        $id_segment = isset( $payload[ 'id_segment' ] ) ? $payload[ 'id_segment' ] : null;

        $message = [
                'id_segment' => $id_segment,
                'payload'    => null,
        ];

        if ( $response->responseStatus != 200 ) {

            switch ( $response->responseStatus ) {
                case 202:
                    $errMessage = "MyMemory is busy, please try later";
                    break;

                default:
                    $errMessage = "Error, please try later";
            }

            $message[ 'error' ] = [
                    'code'    => $response->responseStatus,
                    'message' => $errMessage,
                    'payload' => $payload,
            ];
        }

        if ( $response->responseStatus == 200 ) {
            $message[ 'payload' ] = $payload;
        }

        $this->publishToSseTopic(
                $this->setResponsePayload(
                        'glossary_delete',
                        $payload[ 'id_client' ],
                        $payload[ 'jobData' ],
                        $message
                )
        );
    }

    /**
     * Exposes domains from MyMemory
     *
     * @param $payload
     *
     * @throws StompException
     * @throws Exception
     */
    private function domains( $payload ) {

        $message    = [];
        $id_segment = isset( $payload[ 'id_segment' ] ) ? $payload[ 'id_segment' ] : null;
        $client     = $this->getMyMemoryClient();

        /** @var Engines_Results_MyMemory_DomainsResponse $domains */
        $domains = $client->glossaryDomains( $payload[ 'keys' ] );

        $message[ 'entries' ]    = ( !empty( $domains->entries ) ) ? $domains->entries : [];
        $message[ 'id_segment' ] = $id_segment;

        $this->publishToSseTopic(
                $this->setResponsePayload(
                        'glossary_domains',
                        $payload[ 'id_client' ],
                        $payload[ 'jobData' ],
                        $message
                )
        );
    }

    /**
     * Get a key from MyMemory
     *
     * @param $payload
     *
     * @throws Exception
     */
    private function get( $payload ) {

        if (
            empty( $payload[ 'source' ] ) ||
            empty( $payload[ 'id_segment' ] ) ||
            empty( $payload[ 'source' ] ) ||
            empty ( $payload[ 'source_language' ] ) ||
            empty ( $payload[ 'target_language' ] )
        ) {
            throw new EndQueueException( "Invalid Payload" );
        }

        $keys = [];
        foreach ( $payload[ 'tmKeys' ] as $key ) {
            $keys[] = $key[ 'key' ];
        }

        $client = $this->getMyMemoryClient();

        /** @var Engines_Results_MyMemory_GetGlossaryResponse $response */
        $response = $client->glossaryGet(
            $payload[ 'id_job' ],
            $payload[ 'id_segment' ],
            $payload[ 'source' ],
            $payload[ 'source_language' ],
            $payload[ 'target_language' ],
            $keys
        );
        $matches  = $response->matches;
        $matches  = $this->formatGetGlossaryMatches( $matches, $payload );

        $this->publishToSseTopic(
                $this->setResponsePayload(
                        'glossary_get',
                        $payload[ 'id_client' ],
                        $payload[ 'jobData' ],
                        $matches
                )
        );
    }

    /**
     * Check a key on MyMemory
     *
     * @param $payload
     *
     * @throws Exception
     */
    private function keys( $payload ) {

        $client = $this->getMyMemoryClient();

        /** @var Engines_Results_MyMemory_KeysGlossaryResponse $response */
        $response = $client->glossaryKeys( $payload[ 'source_language' ], $payload[ 'target_language' ], $payload[ 'keys' ] );

        $this->publishToSseTopic(
                $this->setResponsePayload(
                        'glossary_keys',
                        $payload[ 'id_client' ],
                        $payload[ 'jobData' ],
                        [
                                'has_glossary' => $response->hasGlossary()
                        ]
                )
        );
    }

    /**
     * Search sentence in MyMemory
     *
     * @param $payload
     *
     * @throws StompException
     * @throws Exception
     */
    private function search( $payload ) {
        $keys = [];
        foreach ( $payload[ 'tmKeys' ] as $key ) {
            $keys[] = $key[ 'key' ];
        }

        $client = $this->getMyMemoryClient();

        /** @var Engines_Results_MyMemory_SearchGlossaryResponse $response */
        $response = $client->glossarySearch( $payload[ 'sentence' ], $payload[ 'source_language' ], $payload[ 'target_language' ], $keys );
        $matches  = $response->matches;
        $matches  = $this->formatGetGlossaryMatches( $matches, $payload );

        $this->publishToSseTopic(
                $this->setResponsePayload(
                        'glossary_search',
                        $payload[ 'id_client' ],
                        $payload[ 'jobData' ],
                        $matches
                )
        );
    }

    /**
     * @param $matches
     * @param $payload
     *
     * @return array
     * @throws EndQueueException
     */
    private function formatGetGlossaryMatches( $matches, $payload ) {
        $tmKeys = $payload[ 'tmKeys' ];

        if ( !is_array( $matches ) ) {
            $this->_doLog( "Invalid response received from Glossary (not an array). This is the payload that was sent: " . json_encode( $payload ) . ". Got back from MM: " . $matches );
            throw new EndQueueException( "Invalid response received from Glossary (not an array)" );
        }

        if ( empty( $matches ) ) {
            throw new EndQueueException( "Empty response received from Glossary" );
        }

        if ( $matches[ 'id_segment' ] === null or $matches[ 'id_segment' ] === "" ) {
            $matches[ 'id_segment' ] = isset( $payload[ 'id_segment' ] ) ? $payload[ 'id_segment' ] : null;
        }

        // could not have metadata, suppress warning
        $key = @$matches[ 'terms' ][ 'metadata' ][ 'key' ];

        foreach ( $tmKeys as $tmKey ) {
            if ( $tmKey[ 'key' ] === $key and $tmKey[ 'is_shared' ] === false ) {

                $keyLength   = strlen( $key );
                $last_digits = substr( $key, -8 );
                $key         = str_repeat( "*", $keyLength - 8 ) . $last_digits;

                $matches[ 'terms' ][ 'metadata' ][ 'key' ] = $key;
            }
        }

        return $matches;
    }

    /**
     * Set a key in MyMemory
     *
     * @param $payload
     *
     * @throws Exception
     */
    private function set( $payload ) {

        $client = $this->getMyMemoryClient();

        /** @var Engines_Results_MyMemory_SetGlossaryResponse $response */
        $response   = $client->glossarySet( $payload[ 'id_segment' ], $payload[ 'id_job' ], $payload[ 'password' ], $payload[ 'term' ] );
        $id_segment = isset( $payload[ 'id_segment' ] ) ? $payload[ 'id_segment' ] : null;

        $message = [
                'id_segment' => $id_segment,
                'payload'    => null,
        ];

        if ( $response->responseStatus != 200 ) {

            switch ( $response->responseStatus ) {
                case 202:
                    $errMessage = "MyMemory is busy, please try later";
                    break;

                default:
                    $errMessage = "Error, please try later";
            }

            $message[ 'error' ] = [
                    'code'    => $response->responseStatus,
                    'message' => $errMessage,
                    'payload' => $payload,
            ];
        }

        if ( $response->responseStatus == 200 ) {

            // reduce $payload['term']['matching_words'] to simple array
            $matchingWords        = $payload[ 'term' ][ 'matching_words' ];
            $matchingWordsAsArray = [];

            foreach ( $matchingWords as $matchingWord ) {
                $matchingWordsAsArray[] = $matchingWord;
            }

            $payload[ 'term' ][ 'matching_words' ] = $matchingWordsAsArray;

            // reduce $payload['term']['metadata']['keys'] to simple array
            $keys        = $payload[ 'term' ][ 'metadata' ][ 'keys' ];
            $keysAsArray = [];

            foreach ( $keys as $key ) {
                $keysAsArray[] = $key;
            }

            $payload[ 'term' ][ 'metadata' ][ 'keys' ] = $keysAsArray;

            // return term_id
            if ( isset( $response->responseData[ 'id_glossary_term' ] ) and null !== $response->responseData[ 'id_glossary_term' ] ) {
                $payload[ 'term' ][ 'term_id' ] = $response->responseData[ 'id_glossary_term' ];
            }

            $message[ 'payload' ] = $payload;
        }

        $this->publishToSseTopic(
                $this->setResponsePayload(
                        'glossary_set',
                        $payload[ 'id_client' ],
                        $payload[ 'jobData' ],
                        $message
                )
        );
    }

    /**
     * Update a key from MyMemory
     *
     * @param $payload
     *
     * @throws Exception
     */
    private function update( $payload ) {

        $client = $this->getMyMemoryClient();

        /** @var Engines_Results_MyMemory_UpdateGlossaryResponse $response */
        $response   = $client->glossaryUpdate( $payload[ 'id_segment' ], $payload[ 'id_job' ], $payload[ 'password' ], $payload[ 'term' ] );
        $id_segment = isset( $payload[ 'id_segment' ] ) ? $payload[ 'id_segment' ] : null;

        $message = [
                'id_segment' => $id_segment,
                'payload'    => null,
        ];

        if ( $response->responseStatus != 200 ) {

            switch ( $response->responseStatus ) {
                case 202:
                    $errMessage = "MyMemory is busy, please try later";
                    break;

                default:
                    $errMessage = "Error, please try later";
            }

            $message[ 'error' ] = [
                    'code'    => $response->responseStatus,
                    'message' => $errMessage,
                    'payload' => $payload,
            ];
        }

        if ( $response->responseStatus == 200 ) {

            // reduce $payload['term']['matching_words'] to simple array
            $matchingWords        = $payload[ 'term' ][ 'matching_words' ];
            $matchingWordsAsArray = [];

            foreach ( $matchingWords as $matchingWord ) {
                $matchingWordsAsArray[] = $matchingWord;
            }

            $payload[ 'term' ][ 'matching_words' ] = $matchingWordsAsArray;

            $message[ 'payload' ] = $payload;
        }

        $this->publishToSseTopic(
                $this->setResponsePayload(
                        'glossary_update',
                        $payload[ 'id_client' ],
                        $payload[ 'jobData' ],
                        $message
                )
        );
    }

    /**
     * @param      $type
     * @param      $id_client
     * @param      $jobData
     * @param      $message
     *
     * @return array
     */
    private function setResponsePayload( $type, $id_client, $jobData, $message ) {

        return [
                '_type' => $type,
                'data'  => [
                        'payload'   => $message,
                        'id_client' => $id_client,
                        'id_job'    => $jobData[ 'id' ],
                        'passwords' => $jobData[ 'password' ]
                ]
        ];
    }

    /**
     * @param $featuresString
     *
     * @return FeatureSet
     * @throws Exception
     */
    private function getFeatureSetFromString( $featuresString ) {
        $featureSet = new FeatureSet();
        $featureSet->loadFromString( $featuresString );

        return $featureSet;
    }

    /**
     * @param FeatureSet $featureSet
     *
     * @return Engines_AbstractEngine
     * @throws Exception
     */
    private function getEngine( FeatureSet $featureSet ) {
        $_TMS = Engine::getInstance( 1 );
        $_TMS->setFeatureSet( $featureSet );

        return $_TMS;
    }

    /**
     * @param $array
     *
     * @return Users_UserStruct
     */
    private function getUser( $array ) {
        return new Users_UserStruct( [
                'uid'         => $array[ 'uid' ],
                'email'       => $array[ 'email' ],
                '$first_name' => $array[ 'first_name' ],
                'last_name'   => $array[ 'last_name' ],
        ] );
    }

    /**
     * @return Engines_MyMemory
     * @throws Exception
     */
    private function getMyMemoryClient() {
        $engineDAO        = new EnginesModel_EngineDAO( Database::obtain() );
        $engineStruct     = EnginesModel_EngineStruct::getStruct();
        $engineStruct->id = 1;

        $eng = $engineDAO->setCacheTTL( 60 * 5 )->read( $engineStruct );

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $engineRecord = @$eng[ 0 ];

        return new Engines_MyMemory( $engineRecord );
    }
}