<?php

namespace AsyncTasks\Workers;

use Engine;
use Engines_Results_MyMemory_DomainsResponse;
use EnginesModel_EngineStruct;
use Engines_MyMemory;
use Stomp;
use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\AbstractWorker;

class GlossaryWorker extends AbstractWorker {

    const CHECK_ACTION  = 'check';
    const DELETE_ACTION = 'delete';
    const GET_ACTION    = 'get';
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
            self::UPDATE_ACTION,
        ];

        // @TODO add always "de"="tmanalysis_655321@matecat.com" when call MM

        if ( false === in_array( $action, $allowedActions ) ) {
            throw new \InvalidArgumentException( $action . ' is not an allowed action. ' );
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
        $response = $client->glossaryCheck($payload['source'], $payload['target'], $payload['source_language'], $payload['target_language'],$payload['keys']);
        $matches = $response->matches;

        if($matches['id_segment'] === null){
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

        // @TODO HARD-CODED
//        "id_client": "XXXXXX"
//	"id_job": 123456,
//	"password": "dndndndnd",
//	"term":
//	{
//        "term_id": "xxxxxxxx",
//		"source_language": "en-US",
//		"target_language": "it-IT"
//		"source": null,
//		"target": null,
//		"matching_words": null,
//		"metadata": null
//	}

        //https://api-test.mymemory.translated.net/glossary/delete_glossary
        //{
        //    "id_segment": 123456567,
        //	"id_client": "XXXXXX",
        //	"id_job": 123456,
        //	"password": "dndndndnd",
        //	"term":
        //	{
        //		"term_id": "xxxxxxxx",
        //		"source_language": "en-US",
        //		"target_language": "it-IT",
        //		"source": null,
        //		"target": null,
        //		"matching_words": null,
        //		"metadata": {
        //			"definition": null,
        //			"key": "7e0246e854a2f09787f0",
        //			"key_name": null,
        //			"domain": null,
        //			"subdomain": null,
        //			"create_date": null,
        //			"last_update": null
        //		}
        //	}
        //}


        // RISPOSTA
        //
        //{
        //    "responseData": "OK",
        //    "responseStatus": 200,
        //    "responseDetails": null
        //}

        $id_segment = isset($payload['id_segment']) ? $payload['id_segment'] : null;

        $message =  [
            "id_segment" => $id_segment,
            "term" => [
                "term_id" => "123456",
                "source_language" => "en-US",
                "target_language" => "it-IT",
                "source" => null,
                "target" => null,
                "matching_words" => null,
                "metadata" => [
                    "definition" => "Non se sa che è ma definisce la parole",
                    "key" => $payload['term']['metadata']['key'],
                    "key_name" => $payload['term']['metadata']['key_name'],
                    "domain" => "Uber",
                    "subdomain" => "Eats",
                    "create_date" => "2022-08-10",
                    "last_update" => "2022-09-01"
                ]
            ]
        ];

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
        $keys = [];
        foreach ($payload['tmKeys'] as $key){
            $keys[] = $key['key'];
        }

        $client = $this->getMyMemoryClient();

        /** @var \Engines_Results_MyMemory_GetGlossaryResponse $response */
        $response = $client->glossaryGet($payload['source'], $payload['source_language'], $payload['target_language'], $keys);
        $matches = $response->matches;

        if($matches['id_segment'] === null){
            $id_segment = isset($payload['id_segment']) ? $payload['id_segment'] : null;
            $matches['id_segment'] = $id_segment;
        }

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
     * Search sentence in MyMemory
     *
     * @param $payload
     *
     * @throws \StompException
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

        if($matches['id_segment'] === null){
            $id_segment = isset($payload['id_segment']) ? $payload['id_segment'] : null;
            $matches['id_segment'] = $id_segment;
        }

        $this->publishMessage(
            $this->setResponsePayload(
                'glossary_search',
                $payload[ 'id_client' ],
                $payload[ 'jobData' ],
                    $matches
            )
        );
    }

    /**
     * Set a key in MyMemory
     *
     * @param $payload
     *
     * @throws \Exception
     */
    private function set( $payload ) {

        // @TODO HARD-CODED
//        {
//            "id_client": "XXXXXX"
//	"id_job": 123456,
//	"password": "dndndndnd",
//	"term":
//	{
//        "term_id": "xxxxxxxx",
//		"source_language": "en-US",
//		"target_language": "it-IT"
//		"source": {
//        "term": "Payment",
//			"note": "The amount a Rider ...",
//			"sentence": "Example phrase"
//		},
//		"target": {
//        "term": "Pagamento",
//			"note": "L'ammontare che un Rider ...",
//			"sentence": "Frase di esempio"
//		},
//		"matching_words": null,
//		"metadata": {
//        "definition": "Non se sa che è ma definisce la parole",
//			"key": "c52da4a03d6aea33f242",
//			"key_name": "Uber Glossary",
//			"domain": "Uber",
//			"subdomain": "Eats",
//			"create_date": "2022-08-10",
//			"last_update": "2022-09-01"
//		}
//	}
//}

        //https://api-test.mymemory.translated.net/glossary/set_glossary
        //{
        //    "id_segment": 123456567,
        //	"id_client": "XXXXXX",
        //	"id_job": 97,
        //	"password": "5257a65639c4",
        //	"term":
        //	{
        //		"term_id": null,
        //		"source_language": "en-US",
        //		"target_language": "it-IT",
        //		"source": {
        //			"term": "Payment this",
        //			"note": "The amount a Rider ...",
        //			"sentence": "Example phrase"
        //		},
        //		"target": {
        //			"term": "Pagamento",
        //			"note": "L'ammontare che un Rider ...",
        //			"sentence": "Frase di esempio"
        //		},
        //		"matching_words": null,
        //		"metadata": {
        //			"definition": "Non se sa che è ma definisce la parole",
        //			"keys": [
        //                             {
        //                                 "key": "7e0246e854a2f09787f0",
        //			        			"key_name": "Uber Glossary"
        //                             },
        //                             {
        //                                 "key": "ec6e1f40c07ec12fba83",
        //			        				"key_name": "Uber Glossary 2"
        //                             }
        //                         ],
        //			"domain": "Uber",
        //			"subdomain": "Eats",
        //			"create_date": null,
        //			"last_update":  null
        //		}
        //	}
        //}


        // RISPOSTA
        //
        //{
        //    "responseData": "OK",
        //    "responseStatus": 200,
        //    "responseDetails": null
        //}



        $id_segment = isset($payload['id_segment']) ? $payload['id_segment'] : null;

        $message =  [
            "id_segment" => $id_segment,
            "terms" => []
        ];

        foreach ($payload['term']['metadata']['keys'] as $key){
            $message['terms'][] = [
                    "term_id" => "123456",
                    "source_language" => "en-US",
                    "target_language" => "it-IT",
                    "source" => [
                            "term" => "Payment",
                            "note" => "The amount a Rider ...",
                            "sentence" => "Example phrase"
                    ],
                    "target" => [
                            "term" => "Pagamento",
                            "note" => "L'ammontare che un Rider ...",
                            "sentence" => "Frase di esempio"
                    ],
                    "matching_words" => [
                            "Pay",
                            "Payment"
                    ],
                    "metadata" => [
                            "definition" => "Non se sa che è ma definisce la parole",
                            "key" => $key['key'],
                            "key_name" => $key['name'],
                            "domain" => "Uber",
                            "subdomain" => "Eats",
                            "create_date" => "2022-08-10",
                            "last_update" => "2022-09-01"
                    ]
            ];
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

        // @TODO HARD-CODED
//        {
//            "id_client": "XXXXXX"
//	"id_job": 123456,
//	"password": "dndndndnd",
//	"term":
//	{
//        "term_id": "xxxxxxxx",
//		"source_language": "en-US",
//		"target_language": "it-IT"
//		"source": {
//        "term": "Payment",
//			"note": "The amount a Rider ...",
//			"sentence": "Example phrase"
//		},
//		"target": {
//        "term": "Pagamento",
//			"note": "L'ammontare che un Rider ...",
//			"sentence": "Frase di esempio"
//		},
//		"matching_words": null,
//		"metadata": {
//        "definition": "Non se sa che è ma definisce la parole",
//			"key": "c52da4a03d6aea33f242",
//			"key_name": "Uber Glossary",
//			"domain": "Uber",
//			"subdomain": "Eats",
//			"create_date": "2022-08-10",
//			"last_update": "2022-09-01"
//		}
//	}
//}

        //https://api-test.mymemory.translated.net/glossary/update_glossary
        //
        //{
        //    "id_segment": 123456567,
        //	"id_client": "XXXXXX",
        //	"id_job": 97,
        //	"password": "5257a65639c4",
        //	"term":
        //	{
        //		"term_id": "xxxxxxxx",
        //		"source_language": "en-US",
        //		"target_language": "it-IT",
        //		"source": {
        //			"term": "Payment",
        //			"note": "The amount a Rider ...",
        //			"sentence": "Example phrase"
        //		},
        //		"target": {
        //			"term": "Pagamento",
        //			"note": "L'ammontare che un Rider ...",
        //			"sentence": "Frase di esempio"
        //		},
        //		"matching_words": [
        //                    "Pay",
        //                    "Payment"
        //                 ],
        //		"metadata": {
        //			"definition": "Non se sa che è ma definisce la parole",
        //			"key": "7e0246e854a2f09787f0",
        //			"key_name": "Uber Glossary",
        //			"domain": "Uber",
        //			"subdomain": "Eats",
        //			"create_date": "2022-08-10",
        //			"last_update": "2022-09-01"
        //		}
        //	}
        //}

        // RISPOSTA MM
        //{
        //    "responseData": "OK",
        //    "responseStatus": 200,
        //    "responseDetails": null
        //}


        $id_segment = isset($payload['id_segment']) ? $payload['id_segment'] : null;

        $message =  [
            "id_segment" => $id_segment,
            "terms" => [
                [
                    "term_id" => "123456",
                    "source_language" => "en-US",
                    "target_language" => "it-IT",
                    "source" => [
                        "term" => "Payment",
                        "note" => "The amount a Rider ...",
                        "sentence" => "Example phrase"
                    ],
                    "target" => [
                        "term" => "Pagamento",
                        "note" => "L'ammontare che un Rider ...",
                        "sentence" => "Frase di esempio"
                    ],
                    "matching_words" => [
                        "Pay",
                        "Payment"
                    ],
                    "metadata" => [
                        "definition" => "Non se sa che è ma definisce la parole",
                        "key" => $payload['term']['metadata']['key'],
                        "key_name" => $payload['term']['metadata']['key_name'],
                        "domain" => "Uber",
                        "subdomain" => "Eats",
                        "create_date" => "2022-08-10",
                        "last_update" => "2022-09-01"
                    ]
                ]
            ]
        ];

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