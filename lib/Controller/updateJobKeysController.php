<?php

use TmKeyManagement\UserKeysModel;
use Validator\JSONValidator;
use Validator\JSONValidatorObject;

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

    private $tm_prioritization = null;

    private $character_counter_count_tags;

    private $character_counter_mode;

    public function __construct() {

        parent::__construct();

        //Session Enabled

        //define input filters
        $filterArgs = [
                'tm_prioritization'             => [
                        'filter' => FILTER_VALIDATE_BOOLEAN
                ],
                'character_counter_count_tags' => [
                        'filter' => FILTER_VALIDATE_BOOLEAN
                ],
                'character_counter_mode'  => [
                    'filter' => FILTER_SANITIZE_STRING,
                    'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
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
        $this->id_job                       = $_postInput[ 'job_id' ];
        $this->received_password            = $_postInput[ 'current_password' ];
        $this->job_id                       = $_postInput[ 'job_id' ];
        $this->job_pass                     = $_postInput[ 'job_pass' ];
        $this->tm_prioritization            = $_postInput[ 'tm_prioritization' ];
        $this->character_counter_count_tags = $_postInput[ 'character_counter_count_tags' ];
        $this->character_counter_mode       = $_postInput[ 'character_counter_mode' ];
        $this->tm_keys                      = CatUtils::sanitizeJSON($_postInput[ 'data' ]); // this will be filtered inside the TmKeyManagement class
        $this->only_private                 = !$_postInput[ 'get_public_matches' ];

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

        // validate $this->tm_keys
        try {
            $this->validateTMKeysArray();
        } catch (Exception $exception){
            $this->result[ 'errors' ][] = [ "code" => -12, "message" => $exception->getMessage() ];
        }

        $this->identifyUser();
    }

    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return mixed
     */
    function doAction() {

        // moved here because self::isRevision() in constructor
        // generates an infinite loop
        if ( $this->user->email == $this->jobData[ 'owner' ] ) {
            $this->userRole = TmKeyManagement_Filter::OWNER;
        } elseif ( self::isRevision() ) {
            $this->userRole = TmKeyManagement_Filter::ROLE_REVISOR;
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
        // validate $tm_keys

        $clientKeys =  $this->jobData->getClientKeys($this->user, $this->userRole);

        /*
         * sanitize owner role key type
         */
        foreach ( $tm_keys[ 'mine' ] as $k => $val ) {

            // check if logged user is owner of $val['key']
            $check = array_filter($clientKeys['job_keys'], function (TmKeyManagement_ClientTmKeyStruct $element) use ($val){
                if($element->isEncryptedKey()){
                    return false;
                }

                return $val['key'] === $element->key;
            });

            $tm_keys[ 'mine' ][ $k ][ 'owner' ] = !empty($check);
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
            $this->jobData->last_update = date( "Y-m-d H:i:s" );

            $jobDao = new \Jobs_JobDao( Database::obtain() );
            $jobDao->updateStruct( $this->jobData, [ 'fields' => [ 'only_private_tm', 'tm_keys', 'last_update' ] ] );
            $jobDao->destroyCache( $this->jobData );

            $jobsMetadataDao = new Jobs\MetadataDao();

            // update tm_prioritization job metadata
            if($this->tm_prioritization !== null){
                $tm_prioritization = $this->tm_prioritization == true ? "1" : "0";
                $jobsMetadataDao->set( $this->id_job, $this->job_pass, 'tm_prioritization', $tm_prioritization );
            }

            // update character_counter_count_tags job metadata
            if($this->character_counter_count_tags !== null){
                $character_counter_count_tags = $this->character_counter_count_tags == true ? "1" : "0";
                $jobsMetadataDao->set( $this->id_job, $this->job_pass, 'character_counter_count_tags', $character_counter_count_tags );
            }

            // update character_counter_mode job metadata
            if($this->character_counter_mode !== null){
                $jobsMetadataDao->set( $this->id_job, $this->job_pass, 'character_counter_mode', $this->character_counter_mode );
            }

            $this->result[ 'data' ] = 'OK';

        } catch ( Exception $e ) {
            $this->result[ 'data' ]     = 'KO';
            $this->result[ 'errors' ][] = [ "code" => $e->getCode(), "message" => $e->getMessage() ];
        }

    }

    private function jobOwnerIsMe() {
        return $this->userIsLogged && $this->jobData[ 'owner' ] == $this->user->email;
    }

    /**
     * @throws Exception
     */
    private function validateTMKeysArray()
    {
        $schema = file_get_contents( INIT::$ROOT . '/inc/validation/schema/job_keys.json' );

        $validatorObject       = new JSONValidatorObject();
        $validatorObject->json = $this->tm_keys;

        $validator = new JSONValidator( $schema );
        $validator->validate( $validatorObject );
    }

} 