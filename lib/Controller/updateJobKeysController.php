<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 15/10/14
 * Time: 15.44
 */

class updateJobKeysController extends ajaxController {

    // for self::isRevision()
    // controller MUST have those two protected properties
    // @TODO remove this in the near future
    protected $received_password;
    protected $id_job;

    private $job_id;
    private $job_pass;

    private $tm_keys;
    private $only_private;

    private $jobData = [];

    public function __construct() {

        parent::__construct();

        //Session Enabled

        //define input filters
        $filterArgs = [
                'job_id'             => [
                        'filter' => FILTER_SANITIZE_NUMBER_INT
                ],
                'job_pass'           => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'current_password'   => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'get_public_matches' => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'data'               => [
                        'filter' => FILTER_UNSAFE_RAW,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
        ];

        //filter input
        $_postInput = filter_input_array( INPUT_POST, $filterArgs );

        //assign variables
        $this->id_job            = $_postInput[ 'job_id' ];
        $this->received_password = $_postInput[ 'current_password' ];
        $this->job_id            = $_postInput[ 'job_id' ];
        $this->job_pass          = $_postInput[ 'job_pass' ];
        $this->tm_keys           = $_postInput[ 'data' ]; // this will be filtered inside the TmKeyManagement class
        $this->only_private      = !$_postInput[ 'get_public_matches' ];

        //check for eventual errors on the input passed
        $this->result[ 'errors' ] = [];
        if ( empty( $this->job_id ) ) {
            $this->result[ 'errors' ][] = [
                    'code'    => -1,
                    'message' => "Job id missing"
            ];
        }

        if ( empty( $this->job_pass ) ) {
            $this->result[ 'errors' ][] = [
                    'code'    => -2,
                    'message' => "Job pass missing"
            ];
        }

        //get Job Info, we need only a row of job
        $this->jobData = Jobs_JobDao::getByIdAndPassword( (int)$this->job_id, $this->job_pass );

        //Check if user can access the job
        $pCheck = new AjaxPasswordCheck();

        //check for Password correctness
        if ( empty( $this->jobData ) || !$pCheck->grantJobAccessByJobData( $this->jobData, $this->job_pass ) ) {
            $this->result[ 'errors' ][] = [ "code" => -10, "message" => "Wrong password" ];
        }

        $this->readLoginInfo();
    }

    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return mixed
     */
    function doAction() {

        // moved here because self::isRevision() in constructor
        // generates an infinite loop
        if ( self::isRevision() ) {
            $this->userRole = TmKeyManagement_Filter::ROLE_REVISOR;
        } elseif ( $this->user->email == $this->jobData[ 'owner' ] ) {
            $this->userRole = TmKeyManagement_Filter::OWNER;
        }

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
        foreach ( $tm_keys[ 'mine' ] as $k => $val ) {
            $tm_keys[ 'mine' ][ $k ][ 'owner' ] = ( $this->userRole == TmKeyManagement_Filter::OWNER );
        }

        $tm_keys       = array_merge( $tm_keys[ 'ownergroup' ], $tm_keys[ 'mine' ], $tm_keys[ 'anonymous' ] );
        $this->tm_keys = json_encode( $tm_keys );

        try {
            $totalTmKeys = TmKeyManagement_TmKeyManagement::mergeJsonKeys( $this->tm_keys, $this->jobData[ 'tm_keys' ], $this->userRole, $this->user->uid );

            Log::doJsonLog( 'Before: ' . $this->jobData[ 'tm_keys' ] );
            Log::doJsonLog( 'After: ' . json_encode( $totalTmKeys ) );

            if ( $this->jobOwnerIsMe() ) {
                $this->jobData[ 'only_private_tm' ] = $this->only_private;
            }

            /** @var TmKeyManagement_TmKeyStruct $totalTmKey */
            foreach ( $totalTmKeys as $totalTmKey ){
                $totalTmKey->complete_format = true;
            }

            $this->jobData->tm_keys = json_encode( $totalTmKeys );

            $jobDao = new \Jobs_JobDao( Database::obtain() );
            $jobDao->updateStruct( $this->jobData, [ 'fields' => [ 'only_private_tm', 'tm_keys' ] ] );
            $jobDao->destroyCache( $this->jobData );

            $this->result[ 'data' ] = 'OK';

        } catch ( Exception $e ) {
            $this->result[ 'data' ]     = 'KO';
            $this->result[ 'errors' ][] = [ "code" => $e->getCode(), "message" => $e->getMessage() ];
        }

    }

    private function jobOwnerIsMe() {
        return $this->userIsLogged && $this->jobData[ 'owner' ] == $this->user->email;
    }

} 