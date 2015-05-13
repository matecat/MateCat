<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 15/10/14
 * Time: 15.44
 */

class updateJobKeysController extends ajaxController {

    private $job_id;

    private $job_pass;

    private $tm_keys;

    private $jobData = array();

    public function __construct() {

        parent::__construct();
        
        //Session Enabled

        //define input filters
        $filterArgs = array(
                'job_id'   => array(
                        'filter' => FILTER_SANITIZE_NUMBER_INT
                ),
                'job_pass' => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
        );

        //filter input
        $_postInput = filter_input_array( INPUT_POST, $filterArgs );

        //assign variables
        $this->job_id   = $_postInput[ 'job_id' ];
        $this->job_pass = $_postInput[ 'job_pass' ];
        $this->tm_keys  = $_POST[ 'data' ]; // this will be filtered inside the TmKeyManagement class

        //check for eventual errors on the input passed
        $this->result[ 'errors' ] = array();
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

        //get job data
        $this->jobData = getJobData( $this->job_id, $this->job_pass );

        //Check if user can access the job
        $pCheck = new AjaxPasswordCheck();

        //check for Password correctness
        if ( empty( $this->jobData ) || !$pCheck->grantJobAccessByJobData( $this->jobData, $this->job_pass ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -10, "message" => "Wrong password" );
        }

        $this->checkLogin();

        if ( self::isRevision() ) {
            $this->userRole = TmKeyManagement_Filter::ROLE_REVISOR;
        } elseif( $this->userMail == $this->jobData['owner'] ){
            $this->userRole = TmKeyManagement_Filter::OWNER;
        }

    }


    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return mixed
     */
    function doAction() {

        //if some error occured, stop execution.
        if ( count( @$this->result[ 'errors' ] ) ) {
            return false;
        }

        /*
         * The client send data as structured json, for now take it as a plain structure
         *
         *   $clientDecodedJson = Array
         *       (
         *           [owner] => Array
         *               (
         *                   [0] => Array
         *                       (
         *                           [tm] => 1
         *                           [glos] => 1
         *                           [owner] => 1
         *                           [key] => ***************da9a9
         *                           [name] =>
         *                           [r] => 1
         *                           [w] => 1
         *                       )
         *
         *               )
         *
         *           [mine] => Array
         *               (
         *                   [0] => Array
         *                       (
         *                           [tm] => 1
         *                           [glos] => 1
         *                           [owner] => 0
         *                           [key] => 952681baffb9c147b346
         *                           [name] => cgjhkmfgdcjkfh
         *                           [r] => 1
         *                           [w] => 1
         *                       )
         *
         *               )
         *
         *           [anonymous] => Array
         *               (
         *                   [0] => Array
         *                       (
         *                           [tm] => 1
         *                           [glos] => 1
         *                           [owner] => 0
         *                           [key] => ***************882eb
         *                           [name] => Chiave di anonimo
         *                           [r] => 0
         *                           [w] => 0
         *                       )
         *
         *               )
         *
         *       )
         *
         */
        $tm_keys = json_decode( $this->tm_keys, true );

        /*
         * sanitize owner role key type
         */
        foreach( $tm_keys['mine'] as $k => $val ){
            $tm_keys['mine'][$k]['owner'] = ( $this->userRole == TmKeyManagement_Filter::OWNER );
        }

        $tm_keys = array_merge( $tm_keys['ownergroup'], $tm_keys['mine'], $tm_keys['anonymous'] );
        $this->tm_keys = json_encode( $tm_keys );

        try {
            $totalTmKeys = TmKeyManagement_TmKeyManagement::mergeJsonKeys( $this->tm_keys, $this->jobData['tm_keys'], $this->userRole, $this->uid );

            Log::doLog('Before:');
            Log::doLog($this->jobData['tm_keys']);
            Log::doLog('After:');
            Log::doLog(json_encode($totalTmKeys));
            TmKeyManagement_TmKeyManagement::setJobTmKeys( $this->job_id, $this->job_pass, $totalTmKeys );

            $this->result['data'] = 'OK';

        } catch ( Exception $e ){
            $this->result[ 'data' ]      = 'KO';
            $this->result[ 'errors' ][ ] = array( "code" => $e->getCode(), "message" => $e->getMessage() );
        }

    }

} 