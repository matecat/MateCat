<?php

namespace Controller\API\V2;

use Controller\API\Commons\Validators\JSONRequestValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Model\Exceptions\NotFoundException;
use Model\Jobs\ChunkDao;
use Model\Jobs\JobDao;
use Model\LQA\ChunkReviewDao;
use Model\Projects\ProjectDao;
use Plugins\Features\RevisionFactory;
use Utils\Constants\JobStatus;
use Utils\Registry\AppConfig;
use Utils\Tools\Utils;
use Utils\Validator\JSONSchema\JSONValidator;
use Utils\Validator\JSONSchema\JSONValidatorObject;

class JobsBulkActionsController extends ChangePasswordController {

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

            $jDao = new JobDao();

            switch ( $action ) {

                case self::UNARCHIVE_ACTION:
                case self::RESUME_ACTION:
                case self::ACTIVE_ACTION:
                    $jDao::updateJobStatus( $jobStruct, JobStatus::STATUS_ACTIVE );
                    $outcome = [ 'status' => JobStatus::STATUS_ACTIVE ];
                    break;

                case self::CANCEL_ACTION:
                    $jDao::updateJobStatus( $jobStruct, JobStatus::STATUS_CANCELLED );
                    $outcome = [ 'status' => JobStatus::STATUS_CANCELLED ];
                    break;

                case self::DELETE_ACTION:
                    $jDao::updateJobStatus( $jobStruct, JobStatus::STATUS_DELETED );
                    $outcome = [ 'status' => JobStatus::STATUS_DELETED ];
                    break;

                case self::ARCHIVE_ACTION:
                    $jDao::updateJobStatus( $jobStruct, JobStatus::STATUS_ARCHIVED );
                    $outcome = [ 'status' => JobStatus::STATUS_ARCHIVED ];
                    break;

                case self::CHANGE_PASSWORD_ACTION:
                    $newPassword = Utils::randomString();
                    $revisionNumber = filter_var( $json( 'revision_number' ), FILTER_SANITIZE_NUMBER_INT ) ?? null;
                    $this->changeThePassword( $this->user, 'job', $id, $password, $newPassword, $revisionNumber );
                    $outcome = [ 'newPassword' => $newPassword ];
                    break;

                case self::GENERATE_SECOND_PASS_ACTION:
                    $records = RevisionFactory::initFromProject( $jobStruct->project )->getRevisionFeature()->createQaChunkReviewRecords(
                            [ $jobStruct ],
                            $jobStruct->project,
                            [
                                    'source_page' => 3
                            ]
                    );

                    // destroy project data cache
                    ( new ProjectDao() )->destroyCacheForProjectData( $jobStruct->project->id, $jobStruct->project->password );

                    // destroy the 5 minutes chunk review cache
                    $chunk = ( new ChunkDao() )->getByIdAndPassword( $records[ 0 ]->id_job, $records[ 0 ]->password );
                    ( new ChunkReviewDao() )->destroyCacheForFindChunkReviews( $chunk );
                    ChunkReviewDao::destroyCacheByProjectId( $jobStruct->project->id );
                    $outcome = [ 'secondPassPassword' => $chunk->password ];
                    break;

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