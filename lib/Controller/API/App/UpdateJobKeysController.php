<?php

namespace Controller\API\App;

use AjaxPasswordCheck;
use CatUtils;
use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\APISourcePageGuesserTrait;
use Database;
use DomainException;
use Exception;
use INIT;
use InvalidArgumentException;
use Model\Jobs\JobDao;
use Model\Jobs\MetadataDao;
use ReflectionException;
use Swaggest\JsonSchema\InvalidValue;
use TmKeyManagement_ClientTmKeyStruct;
use TmKeyManagement_Filter;
use TmKeyManagement_TmKeyManagement;
use TmKeyManagement_TmKeyStruct;
use Validator\JSONSchema\Errors\JSONValidatorException;
use Validator\JSONSchema\Errors\JsonValidatorGenericException;
use Validator\JSONSchema\JSONValidator;
use Validator\JSONSchema\JSONValidatorObject;

class UpdateJobKeysController extends KleinController {

    use APISourcePageGuesserTrait;

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * @throws ReflectionException
     * @throws AuthenticationError
     * @throws Exception
     */
    public function update(): void {

        $request = $this->validateTheRequest();

        // moved here because self::isRevision() in constructor
        // generates an infinite loop
        $userRole = TmKeyManagement_Filter::ROLE_TRANSLATOR;
        if ( $this->user->email == $request[ 'jobData' ][ 'owner' ] ) {
            $userRole = TmKeyManagement_Filter::OWNER;
        } elseif ( $this->isRevision() ) {
            $userRole = TmKeyManagement_Filter::ROLE_REVISOR;
        } else {
            $userRole = TmKeyManagement_Filter::ROLE_TRANSLATOR;
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
        $tm_keys    = json_decode( $request[ 'tm_keys' ], true );
        $clientKeys = $request[ 'jobData' ]->getClientKeys( $this->user, $userRole );

        /*
         * sanitize owner role key type
         */
        foreach ( $tm_keys[ 'mine' ] as $k => $val ) {

            // check if logged user is owner of $val['key']
            $check = array_filter( $clientKeys[ 'job_keys' ], function ( TmKeyManagement_ClientTmKeyStruct $element ) use ( $val ) {
                if ( $element->isEncryptedKey() ) {
                    return false;
                }

                return $val[ 'key' ] === $element->key;
            } );

            $tm_keys[ 'mine' ][ $k ][ 'owner' ] = !empty( $check );
        }

        $tm_keys = array_merge( $tm_keys[ 'ownergroup' ], $tm_keys[ 'mine' ], $tm_keys[ 'anonymous' ] );
        $tm_keys = json_encode( $tm_keys );


        $totalTmKeys = TmKeyManagement_TmKeyManagement::mergeJsonKeys( $tm_keys, $request[ 'jobData' ][ 'tm_keys' ], $userRole, $this->user->uid );

        $this->log( 'Before: ' . $request[ 'jobData' ][ 'tm_keys' ] );
        $this->log( 'After: ' . json_encode( $totalTmKeys ) );

        if ( $this->jobOwnerIsMe( $request[ 'jobData' ][ 'owner' ] ) ) {
            $request[ 'jobData' ][ 'only_private_tm' ] = $request[ 'only_private' ];
        }

        /** @var TmKeyManagement_TmKeyStruct $totalTmKey */
        foreach ( $totalTmKeys as $totalTmKey ) {
            $totalTmKey->complete_format = true;
        }

        $request[ 'jobData' ]->tm_keys     = json_encode( $totalTmKeys );
        $request[ 'jobData' ]->last_update = date( "Y-m-d H:i:s" );

        $jobDao = new JobDao( Database::obtain() );
        $jobDao->updateStruct( $request[ 'jobData' ], [ 'fields' => [ 'only_private_tm', 'tm_keys', 'last_update' ] ] );
        $jobDao->destroyCache( $request[ 'jobData' ] );

        $jobsMetadataDao = new MetadataDao();

        // update tm_prioritization job metadata
        if ( $request[ 'tm_prioritization' ] !== null ) {
            $tm_prioritization = $request[ 'tm_prioritization' ] ? "1" : "0";
            $jobsMetadataDao->set( $request[ 'job_id' ], $request[ 'job_pass' ], 'tm_prioritization', $tm_prioritization );
        }

        // update character_counter_count_tags job metadata
        if ( $request[ 'character_counter_count_tags' ] !== null ) {
            $character_counter_count_tags = $request[ 'character_counter_count_tags' ] == true ? "1" : "0";
            $jobsMetadataDao->set( $request[ 'job_id' ], $request[ 'job_pass' ], 'character_counter_count_tags', $character_counter_count_tags );
        }

        // update character_counter_mode job metadata
        if ( $request[ 'character_counter_mode' ] !== null ) {
            $jobsMetadataDao->set( $request[ 'job_id' ], $request[ 'job_pass' ], 'character_counter_mode', $request[ 'character_counter_mode' ] );
        }

        $this->response->json( [
                'data' => 'OK'
        ] );

    }

    /**
     * @return array
     * @throws AuthenticationError
     * @throws ReflectionException
     */
    private function validateTheRequest(): array {
        $character_counter_mode       = ( $this->request->param( 'character_counter_mode' ) !== null ) ? filter_var( $this->request->param( 'character_counter_mode' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] ) : null;
        $character_counter_count_tags = ( $this->request->param( 'character_counter_count_tags' ) !== null ) ? filter_var( $this->request->param( 'character_counter_count_tags' ), FILTER_VALIDATE_BOOLEAN ) : null;
        $tm_prioritization            = ( $this->request->param( 'tm_prioritization' ) !== null ) ? filter_var( $this->request->param( 'tm_prioritization' ), FILTER_VALIDATE_BOOLEAN ) : null;
        $job_id                       = filter_var( $this->request->param( 'job_id' ), FILTER_SANITIZE_NUMBER_INT );
        $job_pass                     = filter_var( $this->request->param( 'job_pass' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $current_password             = filter_var( $this->request->param( 'current_password' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $get_public_matches           = filter_var( $this->request->param( 'get_public_matches' ), FILTER_VALIDATE_BOOLEAN );
        $tm_keys                      = filter_var( $this->request->param( 'data' ), FILTER_UNSAFE_RAW, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        $tm_keys                      = CatUtils::sanitizeJSON( $tm_keys );

        if ( empty( $job_id ) ) {
            throw new InvalidArgumentException( "Job id missing", -1 );
        }

        if ( empty( $job_pass ) ) {
            throw new InvalidArgumentException( "Job password missing", -2 );
        }

        // Get Job Info, we need only a row of job
        $jobData = JobDao::getByIdAndPassword( (int)$job_id, $job_pass );

        // Check if user can access the job
        $pCheck = new AjaxPasswordCheck();

        // Check for Password correctness
        if ( empty( $jobData ) or !$pCheck->grantJobAccessByJobData( $jobData, $job_pass ) ) {
            throw new AuthenticationError( "Wrong password", -10 );
        }

        // validate $tm_keys
        try {
            $this->validateTMKeysArray( $tm_keys );
        } catch ( Exception $exception ) {
            throw new DomainException( $exception->getMessage() );
        }

        $this->id_job           = $job_id;
        $this->request_password = $current_password;

        return [
                'job_id'                       => $job_id,
                'job_pass'                     => $job_pass,
                'jobData'                      => $jobData,
                'current_password'             => $current_password,
                'get_public_matches'           => $get_public_matches,
                'tm_keys'                      => $tm_keys, // this will be filtered inside the TmKeyManagement class
                'only_private'                 => !$get_public_matches,
                'tm_prioritization'            => $tm_prioritization,
                'character_counter_mode'       => $character_counter_mode,
                'character_counter_count_tags' => $character_counter_count_tags,
        ];
    }

    /**
     * @param $owner
     *
     * @return bool
     */
    private function jobOwnerIsMe( $owner ): bool {
        return $this->userIsLogged && $owner == $this->user->email;
    }

    /**
     * @param $tm_keys
     *
     * @throws \Swaggest\JsonSchema\Exception
     * @throws InvalidValue
     * @throws JSONValidatorException
     * @throws JsonValidatorGenericException
     */
    private function validateTMKeysArray( $tm_keys ) {
        $schema = file_get_contents( INIT::$ROOT . '/inc/validation/schema/job_keys.json' );

        $validatorObject       = new JSONValidatorObject();
        $validatorObject->json = $tm_keys;

        $validator = new JSONValidator( $schema );
        $validator->validate( $validatorObject );
    }
}
