<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 15/10/14
 * Time: 15.44
 */

include INIT::$MODEL_ROOT . "/queries.php";

class updateJobKeysController extends ajaxController {

    private $job_id;

    private $job_pass;

    private $tm_keys;

    private $user_type;

    private $userIsLogged;

    private $uid;

    public function __construct() {
        //define input filters
        $filterArgs = array(
                'job_id'   => array(
                        'filter' => FILTER_SANITIZE_NUMBER_INT
                ),
                'job_pass' => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => array( FILTER_FLAG_STRIP_LOW, FILTER_FLAG_STRIP_HIGH )
                ),
                'tm_keys'  => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => array( FILTER_FLAG_STRIP_LOW, FILTER_FLAG_STRIP_HIGH )
                ),
                'user_type' => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => array( FILTER_FLAG_STRIP_LOW, FILTER_FLAG_STRIP_HIGH )
                )
        );

        //filter input
        $_postInput = filter_input_array( INPUT_POST, $filterArgs );

        //assign variables
        $this->job_id   = $_postInput[ 'job_id' ];
        $this->job_pass = $_postInput[ 'job_pass' ];
        $this->tm_keys  = $_postInput[ 'tm_keys' ];

        //check for eventual errors on the input passed
        if ( empty( $this->job_id ) ) {
            $this->result[ 'errors' ][ ] = array(
                    'code'    => -1,
                    'message' => "Job id missing"
            );
        }

        if ( empty( $this->job_pass ) ) {
            $this->result[ 'errors' ][ ] = array(
                    'code'    => -2,
                    'message' => "Job pass missing"
            );
        }

        if ( !empty( $this->tm_keys ) ) {

            //TODO:remove next line. This is for debug purposes
            $this->tm_keys = html_entity_decode( $this->tm_keys );
            $this->tm_keys = json_decode( $this->tm_keys, true );
            if ( !isset( $this->tm_keys[ 'update' ] ) || !isset( $this->tm_keys[ 'delete' ] ) ) {
                $this->result[ 'errors' ][ ] = array(
                        'code'    => -3,
                        'message' => "Malformed tm_keys array. It must contain 'update' and 'delete' keys."
                );
            }
        } else {
            $this->result[ 'errors' ][ ] = array(
                    'code'    => -3,
                    'message' => "Tm keys missing"
            );
        }

        //check if user is logged. If not, send an error
        $this->checkLogin();

        if ( !$this->userIsLogged ) {
            $this->result[ 'errors' ][ ] = array(
                    'code'    => -4,
                    'message' => "Please login to use this feature."
            );
        }

        if( $this->user_type != TmKeyManagement_Filter::OWNER &&
            $this->user_type != TmKeyManagement_Filter::ROLE_TRANSLATOR &&
            $this->user_type != TmKeyManagement_Filter::ROLE_REVISOR
        ){
            $this->result[ 'errors' ][ ] = array(
                    'code'    => -5,
                    'message' => "Invalid user type."
            );
        }
    }


    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return mixed
     */
    function doAction() {
        //if some error occured, stop execution.
        if ( count( $this->result[ 'errors' ] ) ) {
            return false;
        }

        //get job data
        $jobData = getJobData( $this->job_id, $this->job_pass );

        //parse job's tm_keys
        $_jobTmKeys = TmKeyManagement_TmKeyManagement::array2TmKeyStructs(
                array( ( $jobData[ 'tm_keys' ] ) )
        );

        //extract the IDs of the the tm_keys that have to be deleted
        $_tmKeysToBeDeleted = array();

        foreach ( $this->tm_keys[ 'delete' ] as $tmKey_arr ) {
            $_tmKey                = TmKeyManagement_TmKeyManagement::getTmKeyStructure( $tmKey_arr );
            $_tmKeysToBeDeleted[ ] = $_tmKey->key;
        }


        $resultTmKeys = array();

        //delete tm_keys
        foreach ( $_jobTmKeys as $jobTmKey){
            /**
             * @var $jobTmKey TmKeyManagement_TmKeyStruct
             */
            $key = $jobTmKey->key;

            if(in_array($key, $_tmKeysToBeDeleted)){

            }
        }


        //
    }

    public function checkLogin() {
        //Warning, sessions enabled, disable them after check, $_SESSION is in read only mode after disable
        parent::sessionStart();
        $this->userIsLogged = ( isset( $_SESSION[ 'cid' ] ) && !empty( $_SESSION[ 'cid' ] ) );
        $this->uid          = ( isset( $_SESSION[ 'uid' ] ) && !empty( $_SESSION[ 'uid' ] ) ? $_SESSION[ 'uid' ] : null );
        parent::disableSessions();

        return $this->userIsLogged;
    }

    /**
     * @param array $jobTmKeys      An array of TmKeyManagement_TmKeyStruct objects extracted from the job
     * @param array $clientTmKeys    An array of TmKeyManagement_TmKeyStruct objects from the client
     *
     * @return array
     */
    private static function _matchKeys(Array $jobTmKeys, Array $clientTmKeys){
        //iterate over client tm keys
        foreach ( $clientTmKeys as $clientTmKey ) {
            /**
             * @var $clientTmKey TmKeyManagement_TmKeyStruct
             */
            //iterate over job's tm keys
            foreach ( $jobTmKeys as $jobTmKey ) {
                /**
                 * @var $jobTmKey TmKeyManagement_TmKeyStruct
                 */
            }

        }


        //TODO: change this
        return array();
    }



} 