<?php

namespace AsyncTasks\Workers;

use Engine;
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

        $id_segment = isset($payload['id_segment']) ? $payload['id_segment'] : null;

        $message = [
            'id_segment' => $id_segment,
            'missing_terms' => [
                [
                    'term_id' => '123456',
                    'source_language' => 'en-US',
                    'target_language' => 'it-IT',
                    'source' => [
                        'term' => 'Buona',
                        'note' => 'The amount a Rider ...',
                        'sentence' => 'Example phrase',
                    ],
                    'target' => [
                        'term' => 'good',
                        'note' => 'L\'ammontare che un Rider ...',
                        'sentence' => 'Frase di esempio',
                    ],
                    'matching_words' => [
                        'experience',
                        'buona',
                    ],
                    'metadata' => [
                        'definition' => 'Non se sa che è ma definisce la parole',
                        'key' => 'c52da4a03d6aea33f242',
                        'key_name' => 'Uber Glossary',
                        'domain' => 'Uber',
                        'subdomain' => 'Eats',
                        'create_date' => '2022-08-10',
                        'last_update' => '2022-09-01',
                    ],
                ],
            ],
            'blacklisted_terms' => [
                [
                    'term_id' => '123456',
                    'source_language' => 'en-US',
                    'target_language' => 'it-IT',
                    'source' => [
                        'term' => 'Payment',
                        'note' => 'The amount a Rider ...',
                        'sentence' => 'Example phrase',
                    ],
                    'target' => [
                        'term' => 'Pagamento',
                        'note' => 'L\'ammontare che un Rider ...',
                        'sentence' => 'Frase di esempio',
                    ],
                    'matching_words' => [
                        'toegang',
                    ],
                    'metadata' => [
                        'definition' => 'Non se sa che è ma definisce la parole',
                        'key' => 'c52da4a03d6aea33f242',
                        'key_name' => 'Uber Glossary',
                        'domain' => 'Uber',
                        'subdomain' => 'Eats',
                        'create_date' => '2022-08-10',
                        'last_update' => '2022-09-01',
                    ],
                ],
            ],
        ];

        $this->publishMessage(
                $this->setResponsePayload(
                        'glossary_check',
                        $payload[ 'id_client' ],
                        $payload[ 'jobData' ],
                        $message
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
     */
    private function domains( $payload ) {

        // @TODO HARD-CODED
        // get domain -> MateCat -> Mymemory
        // {
        //   "key": "xxxxx-x-xx-xx-x",
        //	 "source_language": "en-US",
        //	 "target_language": "it-IT"
        // }

        $message = [];

        foreach ($payload['keys'] as $key){
            $message['entries'][$key] = [
                [
                    "domain" => "Uber",
                    "subdomains" => [
                        "Rider",
                        "Eats"
                    ]
                ],
                [
                    "domain" => "Airbnb",
                    "subdomains" => [
                        "Tech",
                        "Marketing",
                        "Legal"
                    ]
                ]
            ];
        }

        $id_segment = isset($payload['id_segment']) ? $payload['id_segment'] : null;
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
        if(isset($payload['id_segment'])){
            $segment = (new \Segments_SegmentDao())->getById($payload['id_segment']);
            $payload['source'] = $segment->segment;
        }

        // @TODO HARD-CODED
        //  "source": $payload['source']
        //  "target": $payload['target']
        //	"source_language": "en-US",
        //	"target_language": "it-IT",
        //	"keys": $payload['tm_keys'] ---->  [ "xxx", "yyy" ]

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
                        "key" => "c52da4a03d6aea33f242", // mocked key
                        "key_name" => "Uber Glossary",
                        "domain" => "Uber",
                        "subdomain" => "Eats",
                        "create_date" => "2022-08-10",
                        "last_update" => "2022-09-01"
                    ]
                ],
                [
                    "term_id" => "1234567",
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
                        "guests",
                        "places"
                    ],
                    "metadata" => [
                        "definition" => "Non se sa che è ma definisce la parole",
                        "key" => "c52da4a03d6aea33f242", // mocked key
                        "key_name" => "Uber Glossary",
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
                        'glossary_get',
                        $payload[ 'id_client' ],
                        $payload[ 'jobData' ],
                        $message
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
        // @TODO HARD-CODED
        //  "source": $payload['sentence'],
        //	"source_language": "en-US",
        //	"target_language": "it-IT",
        //	"keys": [ "xxx", "yyy" ]

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
                        "key" => "c52da4a03d6aea33f242",
                        "key_name" => "Uber Glossary",
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
                'glossary_search',
                $payload[ 'id_client' ],
                $payload[ 'jobData' ],
                $message
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
}