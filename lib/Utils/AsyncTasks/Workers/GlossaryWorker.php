<?php

namespace AsyncTasks\Workers;

use Engine;
use Engines_Results_MyMemory_DomainsResponse;
use EnginesModel_EngineStruct;
use Engines_MyMemory;
use Stomp;
use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\AbstractWorker;
use TaskRunner\Exceptions\EndQueueException;

class GlossaryWorker extends AbstractWorker {

    const CHECK_ACTION  = 'check';
    const DELETE_ACTION = 'delete';
    const GET_ACTION    = 'get';
    const KEYS_ACTION    = 'keys';
    const SET_ACTION    = 'set';
    const UPDATE_ACTION = 'update';
    const DOMAINS_ACTION = 'domains';
    const SEARCH_ACTION = 'search';

    /**
     * @param AbstractElement $queueElement
     *
     * @return mixed|void
     * @throws \Exception
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

        // @TODO add always "de"="tmanalysis_655321@matecat.com" when call MM

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
     * @throws \Exception
     */
    private function check( $payload ) {

        $client = $this->getMyMemoryClient();

        /** @var \Engines_Results_MyMemory_CheckGlossaryResponse $response */
        $response = $client->glossaryCheck($payload['source'], $payload['target'], $payload['source_language'], $payload['target_language'], $payload['keys']);
        $matches = $response->matches;

        if($matches['id_segment'] === null or $matches['id_segment'] === ""){
            $id_segment = isset($payload['id_segment']) ? $payload['id_segment'] : null;
            $matches['id_segment'] = $id_segment;
        }

        $this->publishMessage(
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
     * @throws \Exception
     */
    private function delete( $payload ) {

        $client = $this->getMyMemoryClient();

        /** @var \Engines_Results_MyMemory_UpdateGlossaryResponse $response */
        $response = $client->glossaryDelete($payload['id_segment'], $payload['id_job'], $payload['password'], $payload['term']);
        $id_segment = isset($payload['id_segment']) ? $payload['id_segment'] : null;

        $message = [
                'id_segment' => $id_segment,
                'payload' => null,
        ];

        if($response->responseStatus != 200){

            switch ($response->responseStatus){
                case 202:
                    $errMessage = "MyMemory is busy, please try later";
                    break;

                default:
                    $errMessage = "Error, please try later";
            }

            $message['error'] = [
                    'code' => $response->responseStatus,
                    'message' => $errMessage,
                    'payload' => $payload,
            ];
        }

        if($response->responseStatus == 200){
            $message['payload'] = $payload;
        }

        $this->publishMessage(
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
     * @throws \StompException
     * @throws \Exception
     */
    private function domains( $payload ) {

        $message = [];
        $id_segment = isset($payload['id_segment']) ? $payload['id_segment'] : null;
        $client = $this->getMyMemoryClient();

        /** @var Engines_Results_MyMemory_DomainsResponse  $domains */
        $domains = $client->glossaryDomains($payload['keys']);

        $message['entries'] = (!empty($domains->entries)) ? $domains->entries: [];
        $message['id_segment'] = $id_segment;

        $this->publishMessage(
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
     * @throws \Exception
     */
    private function get( $payload )
    {

        if( empty($payload['source']) || empty ( $payload['source_language'] ) || empty ( $payload['target_language'] ) ){
            throw new EndQueueException( "Invalid Payload" );
        }

        $keys = [];
        foreach ($payload['tmKeys'] as $key){
            $keys[] = $key['key'];
        }

        $client = $this->getMyMemoryClient();

        /** @var \Engines_Results_MyMemory_GetGlossaryResponse $response */
        $response = $client->glossaryGet($payload['source'], $payload['source_language'], $payload['target_language'], $keys);
        $matches = $response->matches;

        if( !is_array($matches) ){
            throw new EndQueueException( "Invalid response from Glossary (not an array)" );
        }

        if($matches['id_segment'] === null or $matches['id_segment'] === ""){
            $id_segment = isset($payload['id_segment']) ? $payload['id_segment'] : null;
            $matches['id_segment'] = $id_segment;
        }

        if ( empty( $matches ) ) {
            throw new EndQueueException( "Empty response from Glossary" );
        }

        $matches = $this->formatGetGlossaryMatches($matches, $payload['tmKeys']);

        $this->publishMessage(
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
     * @throws \Exception
     */
    private function keys( $payload ) {

        $client = $this->getMyMemoryClient();

        /** @var \Engines_Results_MyMemory_KeysGlossaryResponse $response */
        $response = $client->glossaryKeys($payload['source_language'], $payload['target_language'], $payload['keys']);

        $this->publishMessage(
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
     * @throws \StompException
     * @throws \Exception
     */
    private function search( $payload )
    {
        $keys = [];
        foreach ($payload['tmKeys'] as $key){
            $keys[] = $key['key'];
        }

        $client = $this->getMyMemoryClient();

        /** @var \Engines_Results_MyMemory_GetGlossaryResponse $response */
        $response = $client->glossaryGet($payload['sentence'], $payload['source_language'], $payload['target_language'], $keys);
        $matches = $response->matches;

        if($matches['id_segment'] === null or $matches['id_segment'] === ""){
            $id_segment = isset($payload['id_segment']) ? $payload['id_segment'] : null;
            $matches['id_segment'] = $id_segment;
        }

        $this->publishMessage(
            $this->setResponsePayload(
                'glossary_search',
                $payload[ 'id_client' ],
                $payload[ 'jobData' ],
                $this->formatGetGlossaryMatches($matches, $payload['tmKeys'])
            )
        );
    }

    /**
     * @param array $matches
     * @param array $tmKeys
     *
     * @return array
     */
    private function formatGetGlossaryMatches(array $matches, $tmKeys)
    {
        $key = $matches['terms']['metadata']['key'];

        foreach ($tmKeys as $index => $tmKey){
            if($tmKey['key'] === $key and $tmKey['is_shared'] === false){

                $keyLength   = strlen( $key );
                $last_digits = substr( $key, - 8 );
                $key         = str_repeat( "*", $keyLength - 8 ) . $last_digits;

                $matches['terms']['metadata']['key'] = $key;
            }
        }

        return $matches;
    }

    /**
     * Set a key in MyMemory
     *
     * @param $payload
     *
     * @throws \Exception
     */
    private function set( $payload ) {

        $client = $this->getMyMemoryClient();

        /** @var \Engines_Results_MyMemory_SetGlossaryResponse $response */
        $response = $client->glossarySet($payload['id_segment'], $payload['id_job'], $payload['password'], $payload['term']);
        $id_segment = isset($payload['id_segment']) ? $payload['id_segment'] : null;

        $message = [
            'id_segment' => $id_segment,
            'payload' => null,
        ];

        if($response->responseStatus != 200){

            switch ($response->responseStatus){
                case 202:
                    $errMessage = "MyMemory is busy, please try later";
                    break;

                default:
                    $errMessage = "Error, please try later";
            }

            $message['error'] = [
                'code' => $response->responseStatus,
                'message' => $errMessage,
                'payload' => $payload,
            ];
        }

        if($response->responseStatus == 200){

            // reduce $payload['term']['matching_words'] to simple array
            $matchingWords = $payload['term']['matching_words'];
            $matchingWordsAsArray = [];

            foreach ($matchingWords as $matchingWord){
                $matchingWordsAsArray[] = $matchingWord;
            }

            $payload['term']['matching_words'] = $matchingWordsAsArray;

            // reduce $payload['term']['metadata']['keys'] to simple array
            $keys = $payload['term']['metadata']['keys'];
            $keysAsArray = [];

            foreach ($keys as $key){
                $keysAsArray[] = $key;
            }

            $payload['term']['metadata']['keys'] = $keysAsArray;

            // return term_id
            if(isset($response->responseData['id_glossary_term']) and null !== $response->responseData['id_glossary_term']){
                $payload['term']['term_id'] = $response->responseData['id_glossary_term'];
            }

            $message['payload'] = $payload;
        }

        $this->publishMessage(
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
     * @throws \Exception
     */
    private function update( $payload ) {

        $client = $this->getMyMemoryClient();

        /** @var \Engines_Results_MyMemory_UpdateGlossaryResponse $response */
        $response = $client->glossaryUpdate($payload['id_segment'], $payload['id_job'], $payload['password'], $payload['term']);
        $id_segment = isset($payload['id_segment']) ? $payload['id_segment'] : null;

        $message = [
            'id_segment' => $id_segment,
            'payload' => null,
        ];

        if($response->responseStatus != 200){

            switch ($response->responseStatus){
                case 202:
                    $errMessage = "MyMemory is busy, please try later";
                    break;

                default:
                    $errMessage = "Error, please try later";
            }

            $message['error'] = [
                    'code' => $response->responseStatus,
                    'message' => $errMessage,
                    'payload' => $payload,
            ];
        }

        if($response->responseStatus == 200){

            // reduce $payload['term']['matching_words'] to simple array
            $matchingWords = $payload['term']['matching_words'];
            $matchingWordsAsArray = [];

            foreach ($matchingWords as $matchingWord){
                $matchingWordsAsArray[] = $matchingWord;
            }

            $payload['term']['matching_words'] = $matchingWordsAsArray;

            $message['payload'] = $payload;
        }

        $this->publishMessage(
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
     * @param null $id_segment
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
     * @param $_object
     *
     * @throws \StompException
     */
    private function publishMessage( $_object ) {

        $message = json_encode( $_object );

        $stomp = new Stomp( \INIT::$QUEUE_BROKER_ADDRESS );
        $stomp->connect();
        $stomp->send( \INIT::$SSE_NOTIFICATIONS_QUEUE_NAME,
                $message,
                [ 'persistent' => 'false' ]
        );

        $this->_doLog( $message );
    }

    /**
     * @param $featuresString
     *
     * @return \FeatureSet
     * @throws \Exception
     */
    private function getFeatureSetFromString( $featuresString ) {
        $featureSet = new \FeatureSet();
        $featureSet->loadFromString( $featuresString );

        return $featureSet;
    }

    /**
     * @param \FeatureSet $featureSet
     *
     * @return \Engines_AbstractEngine
     * @throws \Exception
     */
    private function getEngine( \FeatureSet $featureSet ) {
        $_TMS = Engine::getInstance( 1 );
        $_TMS->setFeatureSet( $featureSet );

        return $_TMS;
    }

    /**
     * @param $array
     *
     * @return \Users_UserStruct
     */
    private function getUser( $array ) {
        return new \Users_UserStruct( [
                'uid'         => $array[ 'uid' ],
                'email'       => $array[ 'email' ],
                '$first_name' => $array[ 'first_name' ],
                'last_name'   => $array[ 'last_name' ],
        ] );
    }

    /**
     * @return Engines_MyMemory
     * @throws \Exception
     */
    private function getMyMemoryClient()
    {
        $engineDAO        = new \EnginesModel_EngineDAO( \Database::obtain() );
        $engineStruct     = \EnginesModel_EngineStruct::getStruct();
        $engineStruct->id = 1;

        $eng = $engineDAO->setCacheTTL( 60 * 5 )->read( $engineStruct );

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $engineRecord = @$eng[ 0 ];

        return new Engines_MyMemory( $engineRecord );
    }
}