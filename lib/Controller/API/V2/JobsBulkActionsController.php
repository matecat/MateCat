<?php

namespace Controller\API\V2;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\JSONRequestValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Model\Exceptions\NotFoundException;
use Model\Jobs\JobDao;
use Utils\Constants\JobStatus;
use Utils\Registry\AppConfig;
use Utils\Validator\JSONSchema\JSONValidator;
use Utils\Validator\JSONSchema\JSONValidatorObject;

class JobsBulkActionsController extends KleinController {

    const ACTIVE_ACTION               = 'active';
    const CANCEL_ACTION               = 'cancel';
    const DELETE_ACTION               = 'delete';
    const ARCHIVE_ACTION              = 'archive';
    const UNARCHIVE_ACTION            = 'unarchive';
    const RESUME_ACTION               = 'resume';
    const CHANGE_PASSWORD_ACTION      = 'change_password';
    const GENERATE_SECOND_PASS_ACTION = 'generate_second_pass';
    const ASSIGN_TO_MEMBER_ACTION     = 'assign_to_member';
    const MOVE_TO_MEMBER_ACTION       = 'move_to_member';

    /**
     * @throws Exception
     */
    public function index() {
        $this->validateJSONRequest();

        $json = $this->request->body();
        $json = json_decode( $json, true );

        $jobs   = filter_var( $json[ 'jobs' ], FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FORCE_ARRAY ] );
        $action = filter_var( $json[ 'action' ], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH );

        $response = [];

        // loop job ids
        foreach ( $jobs as $job ) {

            $id       = $job[ 'id' ];
            $password = $job[ 'password' ];
            $outcome  = null;

            $jobStruct = JobDao::getByIdAndPassword( (int)$id, $password );

            if ( $jobStruct === null ) {
                throw new InvalidArgumentException( "Job not found" );
            }

            switch ( $action ) {
                case self::ACTIVE_ACTION:
                    JobDao::updateJobStatus( $jobStruct, JobStatus::STATUS_ACTIVE );
                    $outcome = true;
                    break;

                case self::CANCEL_ACTION:
                    JobDao::updateJobStatus( $jobStruct, JobStatus::STATUS_CANCELLED );
                    $outcome = true;
                    break;

                case self::DELETE_ACTION:
                    JobDao::updateJobStatus( $jobStruct, JobStatus::STATUS_DELETED );
                    $outcome = true;
                    break;

                case self::ARCHIVE_ACTION:
                    JobDao::updateJobStatus( $jobStruct, JobStatus::STATUS_ARCHIVED );
                    $outcome = true;
                    break;

                case self::UNARCHIVE_ACTION:
                case self::RESUME_ACTION:
                case self::CHANGE_PASSWORD_ACTION:
                case self::GENERATE_SECOND_PASS_ACTION:
                case self::ASSIGN_TO_MEMBER_ACTION:
                case self::MOVE_TO_MEMBER_ACTION:
            }

            $response[] = [
                    'id'       => $id,
                    'password' => $password,
                    'outcome'  => $outcome
            ];
        }

        $this->response->json( [
                'jobs' => $response
        ] );
    }

    /**
     * @throws Exception
     */
    private function validateJSONRequest() {
        $json   = $this->request->body();
        $schema = file_get_contents( AppConfig::$ROOT . '/inc/validation/schema/jobs_bulk_actions.json' );

        $validatorObject       = new JSONValidatorObject();
        $validatorObject->json = $json;

        $validator = new JSONValidator( $schema, true );
        $validator->validate( $validatorObject );
    }

    /**
     * Perform actions after constructing an instance of the class.
     * This method sets up the necessary validators and performs further actions.
     *
     * @throws Exception If an error occurs during the validation process.
     * @throws NotFoundException If the chunk or project could not be found.
     */
    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
        $this->appendValidator( new JSONRequestValidator( $this ) );
    }

}