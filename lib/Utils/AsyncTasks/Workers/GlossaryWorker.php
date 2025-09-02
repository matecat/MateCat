<?php

namespace Utils\AsyncTasks\Workers;

use Exception;
use Model\FeaturesBase\FeatureSet;
use Stomp\Exception\StompException;
use Utils\Engines\EnginesFactory;
use Utils\Engines\MyMemory;
use Utils\Engines\Results\MyMemory\UpdateGlossaryResponse;
use Utils\TaskRunner\Commons\AbstractElement;
use Utils\TaskRunner\Commons\AbstractWorker;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EndQueueException;

/**
 * Class GlossaryWorker
 * @package Utils\AsyncTasks\Workers
 *
 */
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

        /**
         * @var $queueElement QueueElement
         */
        $params  = $queueElement->params->toArray();
        $action  = $params[ 'action' ];
        $payload = $params[ 'payload' ];

        $this->_checkDatabaseConnection();

        $this->_doLog( 'GLOSSARY: ' . $action . ' action invoked with payload ' . json_encode( $payload ) );

        switch ( $action ) {
            case self::CHECK_ACTION:
                $this->check( $payload );
                break;
            case self::DELETE_ACTION:
                $this->delete( $payload );
                break;
            case self::DOMAINS_ACTION:
                $this->domains( $payload );
                break;
            case self::GET_ACTION:
                $this->get( $payload );
                break;
            case self::KEYS_ACTION:
                $this->keys( $payload );
                break;
            case self::SEARCH_ACTION:
                $this->search( $payload );
                break;
            case self::SET_ACTION:
                $this->set( $payload );
                break;
            case self::UPDATE_ACTION:
                $this->update( $payload );
                break;
            default:
                throw new EndQueueException( $action . ' is not an allowed action. ' );
        }

    }

    /**
     * Check a key on Match
     *
     * @param $payload
     *
     * @throws Exception
     */
    private function check( $payload ) {

        $client = $this->getMyMemoryClient();

        $response = $client->glossaryCheck( $payload[ 'source' ], $payload[ 'target' ], $payload[ 'source_language' ], $payload[ 'target_language' ], $payload[ 'keys' ] );
        $matches  = $response->matches;

        if ( empty( $matches[ 'id_segment' ] ) ) {
            $id_segment              = $payload[ 'id_segment' ] ?? null;
            $matches[ 'id_segment' ] = $id_segment;
        }

        $this->publishToNodeJsClients(
                $this->setResponsePayload(
                        'glossary_check',
                        $payload[ 'id_client' ],
                        $payload[ 'jobData' ],
                        $matches
                )
        );
    }

    /**
     * Delete a key from Match
     *
     * @param $payload
     *
     * @throws Exception
     */
    private function delete( $payload ) {

        $client = $this->getMyMemoryClient();

        /** @var UpdateGlossaryResponse $response */
        $response   = $client->glossaryDelete( $payload[ 'id_segment' ], $payload[ 'id_job' ], $payload[ 'password' ], $payload[ 'term' ] );
        $id_segment = $payload[ 'id_segment' ] ?? null;

        $message = [
                'id_segment' => $id_segment,
                'payload'    => null,
        ];

        if ( $response->responseStatus != 200 ) {

            switch ( $response->responseStatus ) {
                case 202:
                    $errMessage = "Match is busy, please try later";
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

        $this->publishToNodeJsClients(
                $this->setResponsePayload(
                        'glossary_delete',
                        $payload[ 'id_client' ],
                        $payload[ 'jobData' ],
                        $message
                )
        );
    }

    /**
     * Exposes domains from Match
     *
     * @param $payload
     *
     * @throws StompException
     * @throws Exception
     */
    private function domains( $payload ) {

        $message    = [];
        $id_segment = $payload[ 'id_segment' ] ?? null;
        $client     = $this->getMyMemoryClient();

        $domains = $client->glossaryDomains( $payload[ 'keys' ] );

        $message[ 'entries' ]    = ( !empty( $domains->entries ) ) ? $domains->entries : [];
        $message[ 'id_segment' ] = $id_segment;

        $this->publishToNodeJsClients(
                $this->setResponsePayload(
                        'glossary_domains',
                        $payload[ 'id_client' ],
                        $payload[ 'jobData' ],
                        $message
                )
        );
    }

    /**
     * Get a key from Match
     *
     * @param $payload
     *
     * @throws Exception
     */
    private function get( $payload ) {

        if (
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

        $this->publishToNodeJsClients(
                $this->setResponsePayload(
                        'glossary_get',
                        $payload[ 'id_client' ],
                        $payload[ 'jobData' ],
                        $matches
                )
        );
    }

    /**
     * Check a key on Match
     *
     * @param $payload
     *
     * @throws Exception
     */
    private function keys( $payload ) {

        $client = $this->getMyMemoryClient();

        $response = $client->glossaryKeys( $payload[ 'source_language' ], $payload[ 'target_language' ], $payload[ 'keys' ] );

        $this->publishToNodeJsClients(
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
     * Search sentence in Match
     *
     * @param $payload
     *
     * @throws EndQueueException
     * @throws Exception
     */
    private function search( $payload ) {
        $keys = [];
        foreach ( $payload[ 'tmKeys' ] as $key ) {
            $keys[] = $key[ 'key' ];
        }

        $client = $this->getMyMemoryClient();

        $response = $client->glossarySearch( $payload[ 'sentence' ], $payload[ 'source_language' ], $payload[ 'target_language' ], $keys );
        $matches  = $response->matches;
        $matches  = $this->formatGetGlossaryMatches( $matches, $payload );

        $this->publishToNodeJsClients(
                $this->setResponsePayload(
                        'glossary_search',
                        $payload[ 'id_client' ],
                        $payload[ 'jobData' ],
                        $matches
                )
        );
    }

    /**
     * @param array $matches
     * @param array $payload
     *
     * @return array
     * @throws EndQueueException
     */
    private function formatGetGlossaryMatches( array $matches, array $payload ): array {

        if ( empty( $matches ) ) {
            throw new EndQueueException( "Empty response received from Glossary" );
        }

        if ( $matches[ 'id_segment' ] === null or $matches[ 'id_segment' ] === "" ) {
            $matches[ 'id_segment' ] = $payload[ 'id_segment' ] ?? null;
        }

        return $matches;
    }

    /**
     * Set a key in Match
     *
     * @param $payload
     *
     * @throws Exception
     */
    private function set( $payload ) {

        $client = $this->getMyMemoryClient();

        $response   = $client->glossarySet( $payload[ 'id_segment' ], $payload[ 'id_job' ], $payload[ 'password' ], $payload[ 'term' ] );
        $id_segment = $payload[ 'id_segment' ] ?? null;

        $message = [
                'id_segment' => $id_segment,
                'payload'    => null,
        ];

        if ( $response->responseStatus != 200 ) {

            switch ( $response->responseStatus ) {
                case 202:
                    $errMessage = "Match is busy, please try later";
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

            $payload[ 'request_id' ] = $response->responseDetails;

            $message[ 'payload' ] = $payload;
        }

        $this->publishToNodeJsClients(
                $this->setResponsePayload(
                        'glossary_set',
                        $payload[ 'id_client' ],
                        $payload[ 'jobData' ],
                        $message
                )
        );
    }

    /**
     * Update a key from Match
     *
     * @param $payload
     *
     * @throws Exception
     */
    private function update( $payload ) {

        $client = $this->getMyMemoryClient();

        $response   = $client->glossaryUpdate( $payload[ 'id_segment' ], $payload[ 'id_job' ], $payload[ 'password' ], $payload[ 'term' ] );
        $id_segment = $payload[ 'id_segment' ] ?? null;

        $message = [
                'id_segment' => $id_segment,
                'payload'    => null,
        ];

        if ( $response->responseStatus != 200 ) {

            switch ( $response->responseStatus ) {
                case 202:
                    $errMessage = "Match is busy, please try later";
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
            $payload[ 'request_id' ]               = $response->responseDetails;
            $message[ 'payload' ]                  = $payload;
        }

        $this->publishToNodeJsClients(
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
    private function setResponsePayload( $type, $id_client, $jobData, $message ): array {

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
     * @param FeatureSet $featureSet
     *
     * @return MyMemory
     * @throws Exception
     */
    private function getEngine( FeatureSet $featureSet ): MyMemory {
        $_TMS = EnginesFactory::getInstance( 1 );
        $_TMS->setFeatureSet( $featureSet );

        /** @var MyMemory $_TMS */
        return $_TMS;
    }

    /**
     * @return MyMemory
     * @throws Exception
     */
    private function getMyMemoryClient(): MyMemory {
        return $this->getEngine( new FeatureSet() );
    }
}