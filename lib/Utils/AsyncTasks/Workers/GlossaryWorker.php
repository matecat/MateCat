<?php

namespace AsyncTasks\Workers;

use Database;
use Engine;
use Stomp;
use Matecat\SubFiltering\MateCatFilter;
use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\AbstractWorker;
use TmKeyManagement_Filter;
use TmKeyManagement_MemoryKeyDao;
use TmKeyManagement_MemoryKeyStruct;
use TmKeyManagement_TmKeyManagement;
use TmKeyManagement_TmKeyStruct;
use TMSService;
use Utils;

class GlossaryWorker extends AbstractWorker {

    const DELETE_ACTION = 'delete';
    const GET_ACTION    = 'get';
    const SET_ACTION    = 'set';
    const UPDATE_ACTION = 'update';

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

        if ( false === in_array( $action, [ self::DELETE_ACTION, self::GET_ACTION, self::SET_ACTION, self::UPDATE_ACTION ] ) ) {
            throw new \InvalidArgumentException( $action . ' is not an allowed action. ' );
        }

        $this->_checkDatabaseConnection();

        $this->_doLog( 'GLOSSARY: ' . $action . ' action was executed with payload ' . json_encode( $payload ) );

        $this->{$action}( $payload );
    }

    /**
     * Delete a key from MyMemory
     *
     * @param $payload
     *
     * @throws \Exception
     */
    private function delete( $payload ) {

        $tm_keys    = $payload[ 'tm_keys' ];
        $user       = $this->getUser( $payload[ 'user' ] );
        $featureSet = $this->getFeatureSetFromString( $payload[ 'featuresString' ] );
        $_TMS       = $this->getEngine( $featureSet );
        $userRole   = $payload[ 'userRole' ];
        $id_matches = $payload[ 'id_match' ];

        // $payload[ 'config' ] is is a Params class (stdClass), it implements ArrayAccess interface,
        // but it is defined to allow "_set" only for existing properties
        // so, trying to set $payload[ 'config' ]['whatever'] will result in a not found key name and the value will be ignored
        // Fix: reassign an array
        $config = $payload[ 'config' ]->toArray();

        //get TM keys with read grants
        $tm_keys = TmKeyManagement_TmKeyManagement::getJobTmKeys( $tm_keys, 'w', 'glos', $user->uid, $userRole );

        $keys_hashes = [];
        foreach ( $tm_keys as $tm_key ) {
            $keys_hashes[] = $tm_key->key;
        }

        $Filter                  = MateCatFilter::getInstance( $featureSet, null, null, [] );
        $config[ 'segment' ]     = $Filter->fromLayer2ToLayer0( $config[ 'segment' ] );
        $config[ 'translation' ] = $Filter->fromLayer2ToLayer0( $config[ 'translation' ] );

        //prepare the error report
        $set_code = [];

        //delete id from the keys
        if($id_matches !== null){
            $config[ 'id_user' ]  = $keys_hashes;
            $config[ 'id_match' ] = $id_matches;

            $TMS_RESULT           = $_TMS->delete( $config );
            $set_code[]           = $TMS_RESULT;
        }

        $set_successful = true;
        if ( array_search( false, $set_code, true ) !== false ) {
            $set_successful = false;
        }

        $this->publishMessage(
                $this->setResponsePayload(
                        'glossary_delete',
                        $payload[ 'id_client' ],
                        $payload[ 'jobData' ],
                        [
                                'data'       => ( $set_successful ? 'OK' : 'KO' ),
                                'id_segment' => $payload[ 'id_segment' ]
                        ]
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
    private function get( $payload ) {

        $user       = $this->getUser( $payload[ 'user' ] );
        $featureSet = $this->getFeatureSetFromString( $payload[ 'featuresString' ] );
        $_TMS       = $this->getEngine( $featureSet );
        $tm_keys    = $payload[ 'tm_keys' ];
        $userRole   = $payload[ 'userRole' ];
        $jobData    = $payload[ 'jobData' ];

        // $config['id_user'] is is a Params class (stdClass), it implements ArrayAccess interface,
        // but it is defined to allow "_set" only for existing properties
        // so, trying to set $config['id_user'][] will result in an empty key name and the value will be ignored
        // Fix: reassign an array
        $config = $payload[ 'config' ]->toArray();

        $segment      = $payload[ 'segment' ];
        $userIsLogged = $payload[ 'userIsLogged' ];
        $fromtarget   = $payload[ 'fromtarget' ];

        //get TM keys with read grants
        $tm_keys = TmKeyManagement_TmKeyManagement::getJobTmKeys( $tm_keys, 'r', 'glos', $user->uid, $userRole );

        unset( $config[ 'id_user' ] );
        if ( count( $tm_keys ) ) {
            $config[ 'id_user' ] = [];
            foreach ( $tm_keys as $tm_key ) {
                $config[ 'id_user' ][] = $tm_key->key;
            }
        }

        $TMS_RESULT = $_TMS->get( $config )->get_glossary_matches_as_array();

        //check if user is logged. If so, get the uid.
        $uid = null;
        if ( $userIsLogged ) {
            $uid = $user->uid;
        }

        /**
         * Return only exact matches in glossary when a search is executed over the entire segment
         * Reordered by positional status of matches in source
         *
         * Example:
         * Segment: On average, Members of the House of Commons have 4,2 support staff.
         *
         * Glossary terms found: House of Commons, House of Lords
         *
         * Return: House of Commons
         *
         */
        $tmp_result = [];
        foreach ( $TMS_RESULT as $k => $val ) {
            // cleaning 'ZERO WIDTH SPACE' unicode char \xE2\x80\x8B
            if ( ( $res = mb_stripos( $segment, preg_replace( '/([ \t\n\r\0\x0A\xA0]|\xE2\x80\x8B)+$/', '', $k ) ) ) === false ) {
                unset( $TMS_RESULT[ $k ] ); // unset glossary terms not contained in the request
            } else {
                $tmp_result[ $k ] = $res;
            }
        }

        asort( $tmp_result );
        $tmp_result = array_keys( $tmp_result );

        $matches = [];
        foreach ( $tmp_result as $glossary_matches ) {

            $current_match = array_pop( $TMS_RESULT[ $glossary_matches ] );

            $current_match[ 'segment' ]         = preg_replace( '/\xE2\x80\x8B$/', '', $current_match[ 'segment' ] ); // cleaning 'ZERO WIDTH SPACE' unicode char \xE2\x80\x8B
            $current_match[ 'raw_segment' ]     = preg_replace( '/\xE2\x80\x8B$/', '', $current_match[ 'raw_segment' ] ); // cleaning 'ZERO WIDTH SPACE' unicode char \xE2\x80\x8B
            $current_match[ 'translation' ]     = preg_replace( '/\xE2\x80\x8B$/', '', $current_match[ 'translation' ] ); // cleaning 'ZERO WIDTH SPACE' unicode char \xE2\x80\x8B
            $current_match[ 'raw_translation' ] = preg_replace( '/\xE2\x80\x8B$/', '', $current_match[ 'raw_translation' ] ); // cleaning 'ZERO WIDTH SPACE' unicode char \xE2\x80\x8B

            $current_match[ 'last_updated_by' ] = Utils::changeMemorySuggestionSource(
                    $current_match,
                    $jobData[ 'tm_keys' ],
                    $jobData[ 'owner' ],
                    $uid
            );

            $current_match[ 'created_by' ] = $current_match[ 'last_updated_by' ];

            if ( $fromtarget ) { //Search by target
                $source                             = $current_match[ 'segment' ];
                $rawsource                          = $current_match[ 'raw_segment' ];
                $current_match[ 'segment' ]         = $current_match[ 'translation' ];
                $current_match[ 'translation' ]     = $source;
                $current_match[ 'raw_segment' ]     = $current_match[ 'raw_translation' ];
                $current_match[ 'raw_translation' ] = $rawsource;
            }

            $matches[] = $current_match;

        }

        $this->publishMessage(
                $this->setResponsePayload(
                        'glossary_get',
                        $payload[ 'id_client' ],
                        $payload[ 'jobData' ],
                        [
                                'matches'    => $matches,
                                'id_segment' => $payload[ 'id_segment' ]
                        ]
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

        $user         = $this->getUser( $payload[ 'user' ] );
        $featureSet   = $this->getFeatureSetFromString( $payload[ 'featuresString' ] );
        $_TMS         = $this->getEngine( $featureSet );
        $tm_keys      = $payload[ 'tm_keys' ];
        $userRole     = $payload[ 'userRole' ];
        $jobData      = $payload[ 'jobData' ];
        $tmProps      = $payload[ 'tmProps' ];
        $config       = $payload[ 'config' ];
        $id_job       = $payload[ 'id_job' ];
        $password     = $payload[ 'password' ];
        $userIsLogged = $payload[ 'userIsLogged' ];

        //get TM keys with read grants
        $tm_keys = TmKeyManagement_TmKeyManagement::getJobTmKeys( $tm_keys, 'w', 'glos', $user->uid, $userRole );

        if ( empty( $tm_keys ) ) {

            $APIKeySrv = new TMSService();
            $newUser   = (object)$APIKeySrv->createMyMemoryKey(); //throws exception

            //fallback
            $config[ 'id_user' ] = $newUser->id;

            $new_key        = TmKeyManagement_TmKeyManagement::getTmKeyStructure();
            $new_key->tm    = 1;
            $new_key->glos  = 1;
            $new_key->key   = $newUser->key;
            $new_key->owner = ( $user->email == $jobData[ 'owner' ] );

            if ( !$new_key->owner ) {
                $new_key->{TmKeyManagement_Filter::$GRANTS_MAP[ $userRole ][ 'r' ]} = 1;
                $new_key->{TmKeyManagement_Filter::$GRANTS_MAP[ $userRole ][ 'w' ]} = 1;
            } else {
                $new_key->r = 1;
                $new_key->w = 1;
            }

            if ( $new_key->owner ) {
                //do nothing, this is a greedy if
            } elseif ( $userRole == TmKeyManagement_Filter::ROLE_TRANSLATOR ) {
                $new_key->uid_transl = $user->uid;
            } elseif ( $userRole == TmKeyManagement_Filter::ROLE_REVISOR ) {
                $new_key->uid_rev = $user->uid;
            }

            //create an empty array
            $tm_keys = [];
            //append new key
            $tm_keys[] = $new_key;

            //put the key in the job
            TmKeyManagement_TmKeyManagement::setJobTmKeys( $id_job, $password, $tm_keys );

            //put the key in the user keiring
            if ( $userIsLogged ) {
                $newMemoryKey         = new TmKeyManagement_MemoryKeyStruct();
                $newMemoryKey->tm_key = $new_key;
                $newMemoryKey->uid    = $user->uid;

                $mkDao = new TmKeyManagement_MemoryKeyDao( Database::obtain() );

                $mkDao->create( $newMemoryKey );
            }
        }

        $config[ 'segment' ]     = htmlspecialchars( $config[ 'segment' ], ENT_XML1 | ENT_QUOTES, 'UTF-8', false ); //no XML sanitization is needed because those requests are plain text from UI
        $config[ 'translation' ] = htmlspecialchars( $config[ 'translation' ], ENT_XML1 | ENT_QUOTES, 'UTF-8', false ); //no XML sanitization is needed because those requests are plain text from UI

        $config[ 'prop' ] = $tmProps;
        $featureSet->filter( 'filterGlossaryOnSetTranslation', $config[ 'prop' ], $user );
        $config[ 'prop' ] = json_encode( $config[ 'prop' ] );

        //prepare the error report
        $set_code = [];
        //set the glossary entry for each key with write grants
        if ( count( $tm_keys ) ) {
            /**
             * @var $tm_keys TmKeyManagement_TmKeyStruct[]
             */
            foreach ( $tm_keys as $tm_key ) {
                $config[ 'id_user' ] = $tm_key->key;
                $TMS_RESULT          = $_TMS->set( $config );
                $set_code[]          = $TMS_RESULT;
            }
        }

        $set_successful = true;
        if ( array_search( false, $set_code, true ) !== false ) {
            //There's an error, for now skip, let's assume that are not errors
            $set_successful = false;
        }

        $message = [
                'id_segment' => $payload[ 'id_segment' ],
        ];

        if ( $set_successful ) {
//          Often the get method after a set is not in real time, so return the same values ( FAKE )
            $message[ 'matches' ] = [
                    [
                            'segment'          => $config[ 'segment' ],
                            'translation'      => $config[ 'translation' ],
                            'last_update_date' => date_create()->format( 'Y-m-d H:i:m' ),
                            'last_updated_by'  => "Matecat user",
                            'created_by'       => "Matecat user",
                            'target_note'      => $config[ 'tnote' ],
                            'id_match'         => $set_code
                    ]
            ];

            if ( isset( $new_key ) ) {
                $message[ 'new_tm_key' ] = $new_key->key;
            }

        } else {
            $message[ 'error' ] = [ "code" => -1, "message" => "We got an error, please try again." ];
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

        $user       = $this->getUser( $payload[ 'user' ] );
        $featureSet = $this->getFeatureSetFromString( $payload[ 'featuresString' ] );
        $_TMS       = $this->getEngine( $featureSet );
        $tm_keys    = $payload[ 'tm_keys' ];
        $userRole   = $payload[ 'userRole' ];
        $tmProps    = $payload[ 'tmProps' ];
        $config     = $payload[ 'config' ]->toArray(); // get Array

        //get TM keys with read grants
        $tm_keys = TmKeyManagement_TmKeyManagement::getJobTmKeys( $tm_keys, 'w', 'glos', $user->uid, $userRole );

        $config[ 'segment' ]     = htmlspecialchars( $config[ 'segment' ], ENT_XML1 | ENT_QUOTES, 'UTF-8', false ); //no XML sanitization is needed because those requests are plain text from UI
        $config[ 'translation' ] = htmlspecialchars( $config[ 'translation' ], ENT_XML1 | ENT_QUOTES, 'UTF-8', false ); //no XML sanitization is needed because those requests are plain text from UI

        $config[ 'prop' ] = $tmProps;
        $featureSet->filter( 'filterGlossaryOnSetTranslation', $config[ 'prop' ], $user );
        $config[ 'prop' ] = json_encode( $config[ 'prop' ] );

        if ( $config[ 'newsegment' ] && $config[ 'newtranslation' ] ) {
            $config[ 'newsegment' ]     = htmlspecialchars( $config[ 'newsegment' ], ENT_XML1 | ENT_QUOTES, 'UTF-8', false ); //no XML sanitization is needed because those requests are plain text from UI
            $config[ 'newtranslation' ] = htmlspecialchars( $config[ 'newtranslation' ], ENT_XML1 | ENT_QUOTES, 'UTF-8', false ); //no XML sanitization is needed because those requests are plain text from UI
        }

        //prepare the error report
        $set_code = [];
        //set the glossary entry for each key with write grants
        if ( count( $tm_keys ) ) {
            /**
             * @var $tm_key TmKeyManagement_TmKeyStruct
             */
            foreach ( $tm_keys as $tm_key ) {
                $config[ 'id_user' ] = $tm_key->key;
                $TMS_RESULT          = $_TMS->updateGlossary( $config );
                $set_code[]          = $TMS_RESULT;
            }
        }

        $set_successful = true;
        if ( array_search( false, $set_code, true ) !== false ) {
            $set_successful = false;
        }

        //reset key list
        $config[ 'id_user' ] = [];
        foreach ( $tm_keys as $tm_key ) {
            $config[ 'id_user' ][] = $tm_key->key;
        }

        $message = [];
        if ( $set_successful ) {
            //remove ugly structure from mymemory
            $raw_matches          = $_TMS->get( $config )->get_glossary_matches_as_array();
            $matches              = array_pop( $raw_matches );
            $message[ 'matches' ] = array_pop( $matches );
        } else {
            $message[ 'error' ] = [ "code" => -1, "message" => "We got an error, please try again." ];
        }
        $message[ 'id_segment' ] = $payload[ 'id_segment' ];

        $this->publishMessage(
                $this->setResponsePayload(
                        'glossary_update',
                        $payload[ 'id_client' ],
                        $payload[ 'jobData' ],
                        $message
                )
        );
    }

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
     * @param string $type
     * @param array  $message
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