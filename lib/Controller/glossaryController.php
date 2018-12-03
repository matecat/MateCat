<?php

/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 06/12/13
 * Time: 15.55
 *
 */

class glossaryController extends ajaxController {

    private $exec;
    private $id_job;
    private $password;
    private $segment;
    private $newsegment;
    private $translation;
    private $newtranslation;
    private $comment;
    private $automatic;
    /**
     * @var Engines_MyMemory
     */
    private $_TMS;

    /**
     * @var Jobs_JobStruct
     */
    private $jobData;

    public function __construct() {

        parent::__construct();

        $filterArgs = array(
            'exec'        => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'id_job'      => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'password'    => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'segment'     => array( 'filter' => FILTER_UNSAFE_RAW ),
            'newsegment'     => array( 'filter' => FILTER_UNSAFE_RAW ),
            'translation' => array( 'filter' => FILTER_UNSAFE_RAW ),
            'newtranslation' => array( 'filter' => FILTER_UNSAFE_RAW ),
            'comment'     => array( 'filter' => FILTER_UNSAFE_RAW ),
            'automatic'   => array( 'filter' => FILTER_VALIDATE_BOOLEAN ),
        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );
        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        $this->exec        = $__postInput[ 'exec' ];
        $this->id_job      = $__postInput[ 'id_job' ];
        $this->password    = $__postInput[ 'password' ];
        $this->segment     = $__postInput[ 'segment' ];
        $this->newsegment     = $__postInput[ 'newsegment' ];
        $this->translation = $__postInput[ 'translation' ];
        $this->newtranslation = $__postInput[ 'newtranslation' ];
        $this->comment     = $__postInput[ 'comment' ];
        $this->automatic   = $__postInput[ 'automatic' ];
    }

    public function doAction() {

        //get Job Info, we need only a row of jobs ( split )
        $this->jobData = Jobs_JobDao::getByIdAndPassword( (int)$this->id_job, $this->password );
        $this->featureSet->loadForProject( $this->jobData->getProject() );

        /**
         * For future reminder
         *
         * MyMemory (id=1) should not be the only Glossary provider
         *
         */
        $this->_TMS = Engine::getInstance( 1 );
        $this->_TMS->setFeatureSet( $this->featureSet );

        $this->readLoginInfo();

        try {

            $config = $this->_TMS->getConfigStruct();

            // segment related
            $config[ 'segment' ]     = $this->segment;
            $config[ 'translation' ] = $this->translation;
            $config[ 'tnote' ]       = $this->comment;

            // job related
            $config[ 'id_user' ]     = array() ; 
            $config[ 'source' ]      = $this->jobData[ 'source' ];
            $config[ 'target' ]      = $this->jobData[ 'target' ];

            $config[ 'isGlossary' ]  = true;
            $config[ 'get_mt' ]      = null;
            $config[ 'email' ]       = INIT::$MYMEMORY_API_KEY;
            $config[ 'num_result' ]  = 100; //do not want limit the results from glossary: set as a big number
            
            if ( $this->newsegment && $this->newtranslation ) {
                $config[ 'newsegment' ]     = $this->newsegment;
                $config[ 'newtranslation' ] = $this->newtranslation;
            }

            switch ( $this->exec ) {

                case 'get':
                    $this->_get( $config );
                    break;
                case 'set':
                    /**
                     * For future reminder
                     *
                     * MyMemory should not be the only Glossary provider
                     *
                     */
                    if ( $this->jobData[ 'id_tms' ] == 0 ) {
                        throw new Exception( "Glossary is not available when the TM feature is disabled", -11 );
                    }
                    $this->_set( $config );
                    break;
                case 'update':
                    $this->_update( $config );
                    break;
                case 'delete':
                    $this->_delete( $config );
                    break;

            }

        } catch ( Exception $e ) {
            $this->result[ 'errors' ][ ] = array( "code" => $e->getCode(), "message" => $e->getMessage() );
        }
        
    }

    protected function _get( $config ){

        $tm_keys = $this->jobData['tm_keys'];

        if ( self::isRevision() ) {
            $this->userRole = TmKeyManagement_Filter::ROLE_REVISOR;
        }

        //get TM keys with read grants
        $tm_keys = TmKeyManagement_TmKeyManagement::getJobTmKeys( $tm_keys, 'r', 'glos', $this->user->uid, $this->userRole );

        if ( count( $tm_keys ) ) {
            $config[ 'id_user' ] = array();
            /**
             * @var $tm_key TmKeyManagement_TmKeyStruct
             */
            foreach ( $tm_keys as $tm_key ) {
                $config[ 'id_user' ][ ] = $tm_key->key;
            }
        }

        //remove tags from requests sent to MyMemory
        $config[ 'segment' ] = CatUtils::view2rawxliff( preg_replace( '#<(?:/?[^>]+/?)>#', "", $config[ 'segment' ] ) );

        $TMS_RESULT = $this->_TMS->get( $config )->get_glossary_matches_as_array();

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
        if ( $this->automatic ) {
            $tmp_result = array();
            foreach ( $TMS_RESULT as $k => $val ) {
                // cleaning 'ZERO WIDTH SPACE' unicode char \xE2\x80\x8B
                if ( ( $res = mb_stripos( $this->segment, preg_replace( '/([ \t\n\r\0\x0A\xA0]|\xE2\x80\x8B)+$/', '', $k ) ) ) === false ) {
                    unset( $TMS_RESULT[ $k ] );
                } else {
                    $tmp_result[ $k ] = $res;
                }
            }
            asort( $tmp_result );
            $tmp_result = array_keys( $tmp_result );

            $ordered_Result = array();
            foreach ( $tmp_result as $glossary_matches ) {
                $_k = preg_replace( '/\xE2\x80\x8B$/', '', $glossary_matches ); // cleaning 'ZERO WIDTH SPACE' unicode char \xE2\x80\x8B
                $ordered_Result[ $_k ] = $TMS_RESULT[ $glossary_matches ];
            }
            $TMS_RESULT = $ordered_Result;
        }

        //check if user is logged. If so, get the uid.
        $uid = null;
        if($this->userIsLogged){
            $uid = $this->user->uid;
        }

        foreach($TMS_RESULT as $k => $glossaryMatch){

            $TMS_RESULT[$k][0]['last_updated_by'] = Utils::changeMemorySuggestionSource(
                    $glossaryMatch[0],
                    $this->jobData['tm_keys'],
                    $this->jobData['owner'],
                    $uid);

            $TMS_RESULT[$k][0]['created_by'] = $TMS_RESULT[$k][0]['last_updated_by'];
        }
        $this->result[ 'data' ][ 'matches' ] = $TMS_RESULT;

    }

    protected function _set( $config ){

        $this->result[ 'errors' ] = array();

        $tm_keys = $this->jobData['tm_keys'];

        if ( self::isRevision() ) {
            $this->userRole = TmKeyManagement_Filter::ROLE_REVISOR;
        }

        //get TM keys with read grants
        $tm_keys = TmKeyManagement_TmKeyManagement::getJobTmKeys( $tm_keys, 'w', 'glos', $this->user->uid, $this->userRole );

        if ( empty( $tm_keys ) ) {

            $APIKeySrv = new TMSService();
            $newUser   = (object)$APIKeySrv->createMyMemoryKey(); //throws exception

            //fallback
            $config[ 'id_user' ] = $newUser->id;
            
            $new_key        = TmKeyManagement_TmKeyManagement::getTmKeyStructure();
            $new_key->tm    = 1;
            $new_key->glos  = 1;
            $new_key->key   = $newUser->key;
            $new_key->owner = ( $this->user->email == $this->jobData['owner'] );

            if( !$new_key->owner ){
                $new_key->{TmKeyManagement_Filter::$GRANTS_MAP[ $this->userRole ][ 'r' ]} = 1;
                $new_key->{TmKeyManagement_Filter::$GRANTS_MAP[ $this->userRole ][ 'w' ]} = 1;
            } else {
                $new_key->r     = 1;
                $new_key->w     = 1;
            }

            if( $new_key->owner ){
                //do nothing, this is a greedy if
            } elseif ( $this->userRole == TmKeyManagement_Filter::ROLE_TRANSLATOR ) {
                $new_key->uid_transl = $this->user->uid;
            } elseif ( $this->userRole == TmKeyManagement_Filter::ROLE_REVISOR ) {
                $new_key->uid_rev = $this->user->uid;
            }

            //create an empty array
            $tm_keys   = array();
            //append new key
            $tm_keys[] = $new_key;

            //put the key in the job
            TmKeyManagement_TmKeyManagement::setJobTmKeys( $this->id_job, $this->password, $tm_keys );

            //put the key in the user keiring
            if( $this->userIsLogged  ){

                $newMemoryKey         = new TmKeyManagement_MemoryKeyStruct();
                $newMemoryKey->tm_key = $new_key;
                $newMemoryKey->uid    = $this->user->uid;

                $mkDao = new TmKeyManagement_MemoryKeyDao( Database::obtain() );

                $mkDao->create( $newMemoryKey );

            }

        }

        $config[ 'segment' ]     = CatUtils::view2rawxliff( $config[ 'segment' ] );
        $config[ 'translation' ] = CatUtils::view2rawxliff( $config[ 'translation' ] );

        $config[ 'prop' ]        = $this->jobData->getTMProps();
        $this->featureSet->filter( 'filterGlossaryOnSetTranslation', $config[ 'prop' ], $this->user );
        $config[ 'prop' ]        = json_encode( $config[ 'prop' ] );

        //prepare the error report
        $set_code = array();
        //set the glossary entry for each key with write grants
        if ( count( $tm_keys ) ) {

            /**
             * @var $tm_keys TmKeyManagement_TmKeyStruct[]
             */
            foreach ( $tm_keys as $tm_key ) {
                $config[ 'id_user' ] = $tm_key->key;
                $TMS_RESULT = $this->_TMS->set( $config );
                $set_code[ ] = $TMS_RESULT;
            }

        }

        $set_successful = true;
        if( array_search( false, $set_code, true ) ){
            //There's an error, for now skip, let's assume that are not errors
          $set_successful = false;
        }

        if ( $set_successful ) {
//          Often the get method after a set is not in real time, so return the same values ( FAKE )
//          $TMS_GET_RESULT = $this->_TMS->get($config)->get_glossary_matches_as_array();
//          $this->result['data']['matches'] = $TMS_GET_RESULT;
            $this->result[ 'data' ][ 'matches' ] = array(
                    $config[ 'segment' ] => array(
                            array(
                                    'segment'          => $config[ 'segment' ],
                                    'translation'      => $config[ 'translation' ],
                                    'last_update_date' => date_create()->format( 'Y-m-d H:i:m' ),
                                    'last_updated_by'  => "Matecat user",
                                    'created_by'       => "Matecat user",
                                    'target_note'      => $config[ 'tnote' ],
                            )
                    )
            );

            if( isset($new_key) ){
                $this->result[ 'data' ][ 'created_tm_key' ] = true;
            }

        } else {
            $this->result[ 'errors' ][ ] = array( "code" => -1, "message" => "We got an error, please try again." );
        }

    }

    protected function _update( $config ){

        $tm_keys = $this->jobData['tm_keys'];

        if ( self::isRevision() ) {
            $this->userRole = TmKeyManagement_Filter::ROLE_REVISOR;
        }

        //get TM keys with read grants
        $tm_keys = TmKeyManagement_TmKeyManagement::getJobTmKeys( $tm_keys, 'w', 'glos', $this->user->uid, $this->userRole );

        $config[ 'segment' ]     = CatUtils::view2rawxliff( $config[ 'segment' ] );
        $config[ 'translation' ] = CatUtils::view2rawxliff( $config[ 'translation' ] );

        $config[ 'prop' ]        = $this->jobData->getTMProps();
        $this->featureSet->filter( 'filterGlossaryOnSetTranslation', $config[ 'prop' ], $this->user );
        $config[ 'prop' ]        = json_encode( $config[ 'prop' ] );

        if ( $config[ 'newsegment' ] && $config[ 'newtranslation' ] ) {
            $config[ 'newsegment' ]     = CatUtils::view2rawxliff( $config[ 'newsegment' ] );
            $config[ 'newtranslation' ] = CatUtils::view2rawxliff( $config[ 'newtranslation' ] );
        }
        //prepare the error report
        $set_code = array();
        //set the glossary entry for each key with write grants
        if ( count( $tm_keys ) ) {

            /**
             * @var $tm_key TmKeyManagement_TmKeyStruct
             */
            foreach ( $tm_keys as $tm_key ) {
                $config[ 'id_user' ] = $tm_key->key;
                $TMS_RESULT = $this->_TMS->updateGlossary( $config );
                $set_code[ ] = $TMS_RESULT;
            }

        }

        $set_successful = true;
        if( array_search( false, $set_code, true ) ){
            $set_successful = false;
        }

        //reset key list
        $config[ 'id_user' ] = array();
        foreach ( $tm_keys as $tm_key ) {
            $config[ 'id_user' ][ ] = $tm_key->key;
        }

        if ( $set_successful ) {
            $TMS_GET_RESULT = $this->_TMS->get( $config )->get_glossary_matches_as_array();
            $this->result[ 'data' ][ 'matches' ] = $TMS_GET_RESULT;
        }

    }

    protected function _delete( $config ){

        $tm_keys = $this->jobData['tm_keys'];

        if ( self::isRevision() ) {
            $this->userRole = TmKeyManagement_Filter::ROLE_REVISOR;
        }

        //get TM keys with read grants
        $tm_keys = TmKeyManagement_TmKeyManagement::getJobTmKeys( $tm_keys, 'w', 'glos', $this->user->uid, $this->userRole );

        $config[ 'segment' ]     = CatUtils::view2rawxliff( $config[ 'segment' ] );
        $config[ 'translation' ] = CatUtils::view2rawxliff( $config[ 'translation' ] );

        //prepare the error report
        $set_code = array();
        //set the glossary entry for each key with write grants
        if ( count( $tm_keys ) ) {

            /**
             * @var $tm_key TmKeyManagement_TmKeyStruct
             */
            foreach ( $tm_keys as $tm_key ) {
                $config[ 'id_user' ] = $tm_key->key;
                $TMS_RESULT = $this->_TMS->delete( $config );
                $set_code[ ] = $TMS_RESULT;
            }

        }

        $set_successful = true;
        if( array_search( false, $set_code, true ) ){
            $set_successful = false;
        }

        $this->result[ 'code' ] = $set_successful;
        $this->result[ 'data' ] = ( $set_successful ? 'OK' : null );

    }
}
