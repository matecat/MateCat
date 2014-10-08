<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 06/10/14
 * Time: 15.03
 */

include_once INIT::$MODEL_ROOT . "/queries.php";

class jobTMController extends ajaxController {

    /**
     * @var int
     */
    private $job_id;

    /**
     * @var string The job's password
     */
    private $job_pass;

    /**
     * @var string One of the $ALLOWED_ACTIONS string.
     * @see jobTMController::$ALLOWED_ACTIONS
     */
    private $exec;

    /**
     * @var string A json_encoded array of TmKeyManagement_TmKeyStruct objects
     * @see TmKeyManagement_TmKeyStruct
     */
    private $tm_keys;

    /**
     * @var array The stored job's data
     */
    private $job_data;

    /**
     * @var string A json_encoded array of TmKeyManagement_TmKeyStruct objects
     * @see TmKeyManagement_TmKeyStruct
     */
    private $deleted_tm_keys;

    private $isLogged;

    private $ownerID;

    /**
     * @var array Allowed $exec actions
     */
    private static $ALLOWED_ACTIONS = array( "update" );

    public function __construct() {

        // Set-up a filter array for each input parameter
        $filterArgs = array(
                'job_id'   => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'job_pass' => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => array( FILTER_FLAG_STRIP_LOW, FILTER_FLAG_STRIP_HIGH )
                ),
                'exec'     => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => array( FILTER_FLAG_STRIP_LOW, FILTER_FLAG_STRIP_HIGH )
                )
        );

        $postInput = filter_input_array( INPUT_POST, $filterArgs );
        try {
            $postTmKeys = array_filter( $_POST[ 'tm_keys' ], array(
                    "TmKeyManagement_TmKeyManagement", "isValidStructure"
            ) );

            $postDeletedTmKeys = array_filter( $_POST[ 'deleted_tm_keys' ], array(
                    "TmKeyManagement_TmKeyManagement", "isValidStructure"
            ) );

            $postInput[ 'tm_keys' ]         = $postTmKeys;
            $postInput[ 'deleted_tm_keys' ] = $postDeletedTmKeys;
        } catch ( Exception $e ) {
            $this->result[ 'errors' ][ ] = array( "code" => -1, "message" => $e->getMessage() );
        }

        // Assign input variables
        $this->job_id          = $postInput[ 'job_id' ];
        $this->job_pass        = $postInput[ 'job_pass' ];
        $this->tm_keys         = $postInput[ 'tm_keys' ];
        $this->deleted_tm_keys = $postInput[ 'deleted_tm_keys' ];
        $this->exec            = $postInput[ 'exec' ];

        // Check for errors in input
        if ( $this->job_id == null || empty( $this->job_id ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -2, "message" => "Job id required." );
        }

        if ( $this->job_pass == null || empty( $this->job_id ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -3, "message" => "Job password required." );
        }

        if ( $this->exec == null || empty( $this->exec ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -4, "message" => "Exec parameter required." );
        }

        if ( $this->tm_keys == null || empty( $this->tm_keys ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -5, "message" => "Tm keys json required." );
        }

        if ( !in_array( $this->exec, self::$ALLOWED_ACTIONS ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -6, "message" => "Action not allowed." );
        }

        //Get job data
        $jobData = $this->job_data = getJobData( (int)$this->job_id, $this->job_pass );
        if ( empty( $jobData ) ) {

            $msg = "Error : empty job data \n\n " . var_export( $_POST, true ) . "\n";
            Log::doLog( $msg );
            Utils::sendErrMailReport( $msg );

            return;
        }

        //Check if user can access the job
        $pCheck = new AjaxPasswordCheck();

        //check for Password correctness
        if ( empty( $jobData ) || !$pCheck->grantJobAccessByJobData( $jobData, $this->job_pass ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -10, "message" => "Wrong password" );
        }
    }

    public function doAction() {
        // If there were some input errors, stop execution
        if ( count( $this->result[ 'errors' ] ) ) {
            return;
        }

        switch($this->exec){
            case "update":
                $this->_doUpdate();
                break;

            default:
                break;
        }


    }

    /**
     * Check user logged
     *
     * @return bool
     */
    public function checkLogin() {
        //Warning, sessions enabled, disable them after check, $_SESSION is in read only mode after disable
        parent::sessionStart();
        $this->isLogged = ( isset( $_SESSION[ 'cid' ] ) && !empty( $_SESSION[ 'cid' ] ) );
        $this->ownerID  = ( isset( $_SESSION[ 'cid' ] ) && !empty( $_SESSION[ 'cid' ] ) ? $_SESSION[ 'cid' ] : null );
        parent::disableSessions();

        return $this->isLogged;
    }

    /**
     * Update tm_keys.
     * TODO: describe use case
     * @throws Exception
     */
    private function _doUpdate(){
        $tmKeys        = json_encode( $this->tm_keys );
        $deletedTmKeys = json_encode( $this->deleted_tm_keys );

        $job_tmKeys = $this->job_data[ 'tm_keys' ];

        //this will return all the keys for the current job.
        $totalTmKeys = TmKeyManagement_TmKeyManagement::array2TmKeyStructs(
                array(
                        $job_tmKeys, $tmKeys
                )
        );

        TmKeyManagement_TmKeyManagement::setJobTmKeys( $this->job_id, $this->job_pass, $totalTmKeys );

        $this->checkLogin();

        if ( $this->isLogged ) {

        }
    }
}