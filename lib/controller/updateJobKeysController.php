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

    private $userRole = TmKeyManagement_Filter::ROLE_TRANSLATOR;

    private $jobData = array();

    public function __construct() {

        //Session Enabled

        //define input filters
        $filterArgs = array(
                'job_id'   => array(
                        'filter' => FILTER_SANITIZE_NUMBER_INT
                ),
                'job_pass' => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => array( FILTER_FLAG_STRIP_LOW, FILTER_FLAG_STRIP_HIGH )
                ),
                'data'  => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => array( FILTER_FLAG_STRIP_LOW, FILTER_FLAG_STRIP_HIGH )
                )
        );

        //filter input
        $_postInput = filter_input_array( INPUT_POST, $filterArgs );

        //assign variables
        $this->job_id   = $_postInput[ 'job_id' ];
        $this->job_pass = $_postInput[ 'job_pass' ];
        $this->tm_keys  = $_postInput[ 'data' ];

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

        $this->tm_keys = html_entity_decode( $this->tm_keys );

        //get job data
        $this->jobData = getJobData( $this->job_id, $this->job_pass );

        //Check if user can access the job
        $pCheck = new AjaxPasswordCheck();

        //check for Password correctness
        if ( empty( $this->jobData ) || !$pCheck->grantJobAccessByJobData( $this->jobData, $this->job_pass ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -10, "message" => "Wrong password" );
        }

        $this->checkLogin();

        $_from_url = parse_url( @$_SERVER['HTTP_REFERER'] );
        $url_request = strpos( $_from_url['path'] , "/revise" ) === 0;
        if ( $url_request ) {
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
        *   $clientDecodedJson = stdClass Object
        *   (
        *       [owner] => Array
        *           (
        *               [0] => stdClass Object
        *                   (
        *                       [key] => ***************b273b
        *                       [name] =>
        *                       [r] => 1
        *                       [w] => 1
        *                   )
        *
        *               [1] => stdClass Object
        *                   (
        *                       [key] => ***************57f69
        *                       [name] => My personal Key
        *                       [r] => 1
        *                       [w] => 0
        *                   )
        *
        *           )
        *
        *       [mine] => Array
        *           (
        *               [0] => stdClass Object
        *                   (
        *                       [key] => 5e01bafb688229b33bde
        *                       [name] => La chiave
        *                       [r] => 1
        *                       [w] => 1
        *                   )
        *
        *           )
        *
        *       [anonymous] => Array
        *           (
        *           )
        *
        *   )
        *
        */
        $tm_keys = json_decode( $this->tm_keys, true );
        $this->tm_keys = json_encode( array_merge( $tm_keys['owner'], $tm_keys['mine'],$tm_keys['anonymous'] ) );

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