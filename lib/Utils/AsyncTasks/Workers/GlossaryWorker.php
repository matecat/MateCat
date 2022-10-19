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

        //
        // SU TUTTE VA FATTO PRIMA UN CHECK QUI
        //
        //https://api-test.mymemory.translated.net/glossary/keys_with_glossary
        //
        //{
        //	"keys": [
        //             "7e0246e854a2f09787f0",
        //             "ec6e1f40c07ec12fba83",
        //             "a7332b8b83e152710ba1"
        //             ],
        //}


        // RISPOSTA
        //{
        //    "entries": {
        //        "7e0246e854a2f09787f0": false,
        //        "ec6e1f40c07ec12fba83": false,
        //        "a7332b8b83e152710ba1": true
        //    }
        //}


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

        //https://api-test.mymemory.translated.net/glossary/check_glossary
        //
        //        {
        //            "id_segment" : "123",
        //    "source": "i contatti, CG verificato per qualità e design CG e CG ma anche verificando per qualità e design e natura",
        //    "target": "the contacts verified for quality and design but also for",
        //    "source_language": "it-IT",
        //    "target_language": "en-GB",
        //    "keys": [ "7e0246e854a2f09787f0", "ec6e1f40c07ec12fba83", "a7332b8b83e152710ba1" ],
        //    "de": "pro_655321@matecat.com"
        //}
        //

        // RISPOSTA
        //{
        //    "responseData": null,
        //    "quotaFinished": null,
        //    "mtLangSupported": null,
        //    "responseDetails": "",
        //    "responseStatus": 200,
        //    "responderId": null,
        //    "exception_code": null,
        //    "matches": {
        //        "missing_terms": [
        //            {
        //                "term_id": "3126",
        //                "source_language": "it-IT",
        //                "target_language": "en-GB",
        //                "source": {
        //                    "term": "Design e natura",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "target": {
        //                    "term": "Design in the Wild",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "matching_words": [
        //                    "design e natura"
        //                ],
        //                "metadata": {
        //                    "definition": "",
        //                    "key": "a7332b8b83e152710ba1",
        //                    "key_name": "",
        //                    "domain": "",
        //                    "subdomain": "",
        //                    "create_date": "2022-10-13 12:52:58",
        //                    "last_update_date": "2022-10-14 15:31:11"
        //                }
        //            },
        //            {
        //                "term_id": "2433",
        //                "source_language": "it-IT",
        //                "target_language": "en-GB",
        //                "source": {
        //                    "term": "Design e natura",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "target": {
        //                    "term": "Design in the Wild",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "matching_words": [
        //                    "design e natura"
        //                ],
        //                "metadata": {
        //                    "definition": "",
        //                    "key": "a7332b8b83e152710ba1",
        //                    "key_name": "",
        //                    "domain": "",
        //                    "subdomain": "",
        //                    "create_date": "2022-10-13 12:52:58",
        //                    "last_update_date": "2022-10-14 11:39:01"
        //                }
        //            },
        //            {
        //                "term_id": "1740",
        //                "source_language": "it-IT",
        //                "target_language": "en-GB",
        //                "source": {
        //                    "term": "Design e natura",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "target": {
        //                    "term": "Design in the Wild",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "matching_words": [
        //                    "design e natura"
        //                ],
        //                "metadata": {
        //                    "definition": "",
        //                    "key": "a7332b8b83e152710ba1",
        //                    "key_name": "",
        //                    "domain": "",
        //                    "subdomain": "",
        //                    "create_date": "2022-10-13 12:52:58",
        //                    "last_update_date": "2022-10-14 11:28:05"
        //                }
        //            }
        //        ],
        //        "blacklisted_terms": [],
        //        "id_segment": "123"
        //    }
        //}


        $id_segment = isset($payload['id_segment']) ? $payload['id_segment'] : null;

        $message = [
            'id_segment' => $id_segment,
            'missing_terms' => [
                [
                    'term_id' => '123456',
                    'source_language' => 'en-US',
                    'target_language' => 'it-IT',
                    'source' => [
                        'term' => 'Verified',
                        'note' => 'The amount a Rider ...',
                        'sentence' => 'Example phrase',
                    ],
                    'target' => [
                        'term' => 'Verificato',
                        'note' => 'L\'ammontare che un Rider ...',
                        'sentence' => 'Frase di esempio',
                    ],
                    'matching_words' => [
                        'experience',
                        'verified',
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
                        'consegnato',
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
                [
                    'term_id' => '1234567',
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
                        'abc',
                        "verificato"
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


        //https://api-test.mymemory.translated.net/glossary/get_glossary
        //
        //{
        //	"id_segment" : 129,
        //    "source": "Is maybe accessible, verify quality and design but quality not so good",
        //    "source_language": "en-GB",
        //    "target_language": "it-IT",
        //    "keys": [ "7e0246e854a2f09787f0", "ec6e1f40c07ec12fba83", "a7332b8b83e152710ba1" ],
        //    "de": "pro_655321@matecat.com"
        //}


        // RISPOSTA
        //
        //{
        //    "responseData": null,
        //    "quotaFinished": null,
        //    "mtLangSupported": null,
        //    "responseDetails": "",
        //    "responseStatus": 200,
        //    "responderId": null,
        //    "exception_code": null,
        //    "matches": {
        //        "terms": [
        //            {
        //                "term_id": "3587",
        //                "source_language": "en-GB",
        //                "target_language": "it-IT",
        //                "source": {
        //                    "term": "verify",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "target": {
        //                    "term": "verificare",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "matching_words": [
        //                    "verify"
        //                ],
        //                "metadata": {
        //                    "definition": "To inspect a place to confirm that it meets the Airbnb Plus standards and criteria. ",
        //                    "key": "a7332b8b83e152710ba1",
        //                    "key_name": "",
        //                    "domain": "[Plus]",
        //                    "subdomain": "",
        //                    "create_date": "2022-10-13 12:52:58",
        //                    "last_update_date": "2022-10-14 15:31:36"
        //                }
        //            },
        //            {
        //                "term_id": "3425",
        //                "source_language": "en-GB",
        //                "target_language": "it-IT",
        //                "source": {
        //                    "term": "quality",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "target": {
        //                    "term": "qualità",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "matching_words": [
        //                    "quality"
        //                ],
        //                "metadata": {
        //                    "definition": "One of the things that guests get in Airbnb Plus homes and what homes in Airbnb Plus are verified for.",
        //                    "key": "a7332b8b83e152710ba1",
        //                    "key_name": "",
        //                    "domain": "[Plus]",
        //                    "subdomain": "",
        //                    "create_date": "2022-10-13 12:52:58",
        //                    "last_update_date": "2022-10-14 15:31:31"
        //                }
        //            },
        //            {
        //                "term_id": "3125",
        //                "source_language": "en-GB",
        //                "target_language": "it-IT",
        //                "source": {
        //                    "term": "Design",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "target": {
        //                    "term": "Design",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "matching_words": [
        //                    "design"
        //                ],
        //                "metadata": {
        //                    "definition": "A Design stay is a thoughtfully curated, aesthetically inspiring, and globally diverse boutique-style home that has cohesive, consistent design style across all rooms in the listing",
        //                    "key": "a7332b8b83e152710ba1",
        //                    "key_name": "",
        //                    "domain": "Home is the destination",
        //                    "subdomain": "",
        //                    "create_date": "2022-10-13 12:52:58",
        //                    "last_update_date": "2022-10-14 15:31:11"
        //                }
        //            },
        //            {
        //                "term_id": "2948",
        //                "source_language": "en-GB",
        //                "target_language": "it-IT",
        //                "source": {
        //                    "term": "accessible",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "target": {
        //                    "term": "accessibile",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "matching_words": [
        //                    "accessible"
        //                ],
        //                "metadata": {
        //                    "definition": "The quality of having any number of accessibility features that make products, services, or environment available, usable, safe, and welcoming for people with accessibility needs. (Don't equate accessibility needs with disabilities.)\nEXAMPLE: Here’s how we’re building a more accessible Airbnb.",
        //                    "key": "a7332b8b83e152710ba1",
        //                    "key_name": "",
        //                    "domain": "[In-Home Accessibility] ",
        //                    "subdomain": "",
        //                    "create_date": "2022-10-13 12:52:58",
        //                    "last_update_date": "2022-10-14 15:31:01"
        //                }
        //            },
        //            {
        //                "term_id": "2894",
        //                "source_language": "en-GB",
        //                "target_language": "it-IT",
        //                "source": {
        //                    "term": "verify",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "target": {
        //                    "term": "verificare",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "matching_words": [
        //                    "verify"
        //                ],
        //                "metadata": {
        //                    "definition": "To inspect a place to confirm that it meets the Airbnb Plus standards and criteria. ",
        //                    "key": "a7332b8b83e152710ba1",
        //                    "key_name": "",
        //                    "domain": "[Plus]",
        //                    "subdomain": "",
        //                    "create_date": "2022-10-13 12:52:58",
        //                    "last_update_date": "2022-10-14 11:39:26"
        //                }
        //            },
        //            {
        //                "term_id": "2432",
        //                "source_language": "en-GB",
        //                "target_language": "it-IT",
        //                "source": {
        //                    "term": "Design",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "target": {
        //                    "term": "Design",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "matching_words": [
        //                    "design"
        //                ],
        //                "metadata": {
        //                    "definition": "A Design stay is a thoughtfully curated, aesthetically inspiring, and globally diverse boutique-style home that has cohesive, consistent design style across all rooms in the listing",
        //                    "key": "a7332b8b83e152710ba1",
        //                    "key_name": "",
        //                    "domain": "Home is the destination",
        //                    "subdomain": "",
        //                    "create_date": "2022-10-13 12:52:58",
        //                    "last_update_date": "2022-10-14 11:39:01"
        //                }
        //            },
        //            {
        //                "term_id": "2255",
        //                "source_language": "en-GB",
        //                "target_language": "it-IT",
        //                "source": {
        //                    "term": "accessible",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "target": {
        //                    "term": "accessibile",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "matching_words": [
        //                    "accessible"
        //                ],
        //                "metadata": {
        //                    "definition": "The quality of having any number of accessibility features that make products, services, or environment available, usable, safe, and welcoming for people with accessibility needs. (Don't equate accessibility needs with disabilities.)\nEXAMPLE: Here’s how we’re building a more accessible Airbnb.",
        //                    "key": "a7332b8b83e152710ba1",
        //                    "key_name": "",
        //                    "domain": "[In-Home Accessibility] ",
        //                    "subdomain": "",
        //                    "create_date": "2022-10-13 12:52:58",
        //                    "last_update_date": "2022-10-14 11:38:51"
        //                }
        //            },
        //            {
        //                "term_id": "2201",
        //                "source_language": "en-GB",
        //                "target_language": "it-IT",
        //                "source": {
        //                    "term": "verify",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "target": {
        //                    "term": "verificare",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "matching_words": [
        //                    "verify"
        //                ],
        //                "metadata": {
        //                    "definition": "To inspect a place to confirm that it meets the Airbnb Plus standards and criteria. ",
        //                    "key": "a7332b8b83e152710ba1",
        //                    "key_name": "",
        //                    "domain": "[Plus]",
        //                    "subdomain": "",
        //                    "create_date": "2022-10-13 12:52:58",
        //                    "last_update_date": "2022-10-14 11:28:22"
        //                }
        //            },
        //            {
        //                "term_id": "2039",
        //                "source_language": "en-GB",
        //                "target_language": "it-IT",
        //                "source": {
        //                    "term": "quality",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "target": {
        //                    "term": "qualità",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "matching_words": [
        //                    "quality"
        //                ],
        //                "metadata": {
        //                    "definition": "One of the things that guests get in Airbnb Plus homes and what homes in Airbnb Plus are verified for.",
        //                    "key": "a7332b8b83e152710ba1",
        //                    "key_name": "",
        //                    "domain": "[Plus]",
        //                    "subdomain": "",
        //                    "create_date": "2022-10-13 12:52:58",
        //                    "last_update_date": "2022-10-14 11:28:19"
        //                }
        //            },
        //            {
        //                "term_id": "1739",
        //                "source_language": "en-GB",
        //                "target_language": "it-IT",
        //                "source": {
        //                    "term": "Design",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "target": {
        //                    "term": "Design",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "matching_words": [
        //                    "design"
        //                ],
        //                "metadata": {
        //                    "definition": "A Design stay is a thoughtfully curated, aesthetically inspiring, and globally diverse boutique-style home that has cohesive, consistent design style across all rooms in the listing",
        //                    "key": "a7332b8b83e152710ba1",
        //                    "key_name": "",
        //                    "domain": "Home is the destination",
        //                    "subdomain": "",
        //                    "create_date": "2022-10-13 12:52:58",
        //                    "last_update_date": "2022-10-14 11:28:05"
        //                }
        //            },
        //            {
        //                "term_id": "1562",
        //                "source_language": "en-GB",
        //                "target_language": "it-IT",
        //                "source": {
        //                    "term": "accessible",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "target": {
        //                    "term": "accessibile",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "matching_words": [
        //                    "accessible"
        //                ],
        //                "metadata": {
        //                    "definition": "The quality of having any number of accessibility features that make products, services, or environment available, usable, safe, and welcoming for people with accessibility needs. (Don't equate accessibility needs with disabilities.)\nEXAMPLE: Here’s how we’re building a more accessible Airbnb.",
        //                    "key": "a7332b8b83e152710ba1",
        //                    "key_name": "",
        //                    "domain": "[In-Home Accessibility] ",
        //                    "subdomain": "",
        //                    "create_date": "2022-10-13 12:52:58",
        //                    "last_update_date": "2022-10-14 11:27:55"
        //                }
        //            },
        //            {
        //                "term_id": "1508",
        //                "source_language": "en-GB",
        //                "target_language": "it-IT",
        //                "source": {
        //                    "term": "verify",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "target": {
        //                    "term": "verificare",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "matching_words": [
        //                    "verify"
        //                ],
        //                "metadata": {
        //                    "definition": "To inspect a place to confirm that it meets the Airbnb Plus standards and criteria. ",
        //                    "key": "a7332b8b83e152710ba1",
        //                    "key_name": "",
        //                    "domain": "[Plus]",
        //                    "subdomain": "",
        //                    "create_date": "2022-10-13 12:52:58",
        //                    "last_update_date": "2022-10-13 17:10:57"
        //                }
        //            },
        //            {
        //                "term_id": "1346",
        //                "source_language": "en-GB",
        //                "target_language": "it-IT",
        //                "source": {
        //                    "term": "quality",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "target": {
        //                    "term": "qualità",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "matching_words": [
        //                    "quality"
        //                ],
        //                "metadata": {
        //                    "definition": "One of the things that guests get in Airbnb Plus homes and what homes in Airbnb Plus are verified for.",
        //                    "key": "a7332b8b83e152710ba1",
        //                    "key_name": "",
        //                    "domain": "[Plus]",
        //                    "subdomain": "",
        //                    "create_date": "2022-10-13 12:52:58",
        //                    "last_update_date": "2022-10-13 17:10:52"
        //                }
        //            },
        //            {
        //                "term_id": "815",
        //                "source_language": "en-GB",
        //                "target_language": "it-IT",
        //                "source": {
        //                    "term": "verify",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "target": {
        //                    "term": "verificare",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "matching_words": [
        //                    "verify"
        //                ],
        //                "metadata": {
        //                    "definition": "To inspect a place to confirm that it meets the Airbnb Plus standards and criteria. ",
        //                    "key": "a7332b8b83e152710ba1",
        //                    "key_name": "",
        //                    "domain": "[Plus]",
        //                    "subdomain": "",
        //                    "create_date": "2022-10-13 12:52:58",
        //                    "last_update_date": "2022-10-13 17:09:39"
        //                }
        //            },
        //            {
        //                "term_id": "653",
        //                "source_language": "en-GB",
        //                "target_language": "it-IT",
        //                "source": {
        //                    "term": "quality",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "target": {
        //                    "term": "qualità",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "matching_words": [
        //                    "quality"
        //                ],
        //                "metadata": {
        //                    "definition": "One of the things that guests get in Airbnb Plus homes and what homes in Airbnb Plus are verified for.",
        //                    "key": "a7332b8b83e152710ba1",
        //                    "key_name": "",
        //                    "domain": "[Plus]",
        //                    "subdomain": "",
        //                    "create_date": "2022-10-13 12:52:58",
        //                    "last_update_date": "2022-10-13 17:09:35"
        //                }
        //            },
        //            {
        //                "term_id": "3583",
        //                "source_language": "en-GB",
        //                "target_language": "it-IT",
        //                "source": {
        //                    "term": "verified",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "target": {
        //                    "term": "verificato",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "matching_words": [
        //                    "verify"
        //                ],
        //                "metadata": {
        //                    "definition": "Used to imply that the home has received a stamp of approval through a rigorous inspection process. May appear alone (as a badge), as \"\"verified homes,\"\" or part of a phrase (e.g. \"\"verified for comfort and style\"\"). Note: We can't use any language around \"\"guaranteeing\"\" quality and we also can't say that it's verified \"\"by Airbnb\"\".",
        //                    "key": "a7332b8b83e152710ba1",
        //                    "key_name": "",
        //                    "domain": "[Plus]",
        //                    "subdomain": "",
        //                    "create_date": "2022-10-13 12:52:58",
        //                    "last_update_date": "2022-10-14 15:31:36"
        //                }
        //            },
        //            {
        //                "term_id": "2890",
        //                "source_language": "en-GB",
        //                "target_language": "it-IT",
        //                "source": {
        //                    "term": "verified",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "target": {
        //                    "term": "verificato",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "matching_words": [
        //                    "verify"
        //                ],
        //                "metadata": {
        //                    "definition": "Used to imply that the home has received a stamp of approval through a rigorous inspection process. May appear alone (as a badge), as \"\"verified homes,\"\" or part of a phrase (e.g. \"\"verified for comfort and style\"\"). Note: We can't use any language around \"\"guaranteeing\"\" quality and we also can't say that it's verified \"\"by Airbnb\"\".",
        //                    "key": "a7332b8b83e152710ba1",
        //                    "key_name": "",
        //                    "domain": "[Plus]",
        //                    "subdomain": "",
        //                    "create_date": "2022-10-13 12:52:58",
        //                    "last_update_date": "2022-10-14 11:39:26"
        //                }
        //            },
        //            {
        //                "term_id": "1504",
        //                "source_language": "en-GB",
        //                "target_language": "it-IT",
        //                "source": {
        //                    "term": "verified",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "target": {
        //                    "term": "verificato",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "matching_words": [
        //                    "verify"
        //                ],
        //                "metadata": {
        //                    "definition": "Used to imply that the home has received a stamp of approval through a rigorous inspection process. May appear alone (as a badge), as \"\"verified homes,\"\" or part of a phrase (e.g. \"\"verified for comfort and style\"\"). Note: We can't use any language around \"\"guaranteeing\"\" quality and we also can't say that it's verified \"\"by Airbnb\"\".",
        //                    "key": "a7332b8b83e152710ba1",
        //                    "key_name": "",
        //                    "domain": "[Plus]",
        //                    "subdomain": "",
        //                    "create_date": "2022-10-13 12:52:58",
        //                    "last_update_date": "2022-10-13 17:10:57"
        //                }
        //            },
        //            {
        //                "term_id": "811",
        //                "source_language": "en-GB",
        //                "target_language": "it-IT",
        //                "source": {
        //                    "term": "verified",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "target": {
        //                    "term": "verificato",
        //                    "note": "",
        //                    "sentence": "example"
        //                },
        //                "matching_words": [
        //                    "verify"
        //                ],
        //                "metadata": {
        //                    "definition": "Used to imply that the home has received a stamp of approval through a rigorous inspection process. May appear alone (as a badge), as \"\"verified homes,\"\" or part of a phrase (e.g. \"\"verified for comfort and style\"\"). Note: We can't use any language around \"\"guaranteeing\"\" quality and we also can't say that it's verified \"\"by Airbnb\"\".",
        //                    "key": "a7332b8b83e152710ba1",
        //                    "key_name": "",
        //                    "domain": "[Plus]",
        //                    "subdomain": "",
        //                    "create_date": "2022-10-13 12:52:58",
        //                    "last_update_date": "2022-10-13 17:09:39"
        //                }
        //            }
        //        ],
        //        "id_segment": 129
        //    }
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
                        "places",
                        "delivered"
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



        // questa corrisponde alla search e alla get (che sono la stessa cosa, cambia solo che nel source gli passi la ricerca invece del segmento)
        //https://api-test.mymemory.translated.net/glossary/get_glossary
        //
        //{
        //	"id_segment" : 129,
        //    "source": "Is maybe accessible, verify quality and design but quality not so good",
        //    "source_language": "en-GB",
        //    "target_language": "it-IT",
        //    "keys": [ "7e0246e854a2f09787f0", "ec6e1f40c07ec12fba83", "a7332b8b83e152710ba1" ],
        //    "de": "pro_655321@matecat.com"
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