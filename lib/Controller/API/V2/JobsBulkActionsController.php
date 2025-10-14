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
    const ASSIGN_TO_TEAM_ACTION       = 'assign_to_team';

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

            $jobStruct     = ChunkDao::getByIdAndPassword( (int)$id, $password );
            $projectStruct = $jobStruct->getProject();
            $user          = $this->getUser();
            $this->checkUserPermissions( $projectStruct, $user );

            switch ( $action ) {

                // Active the job
                case self::UNARCHIVE_ACTION:
                case self::RESUME_ACTION:
                case self::ACTIVE_ACTION:
                    JobDao::updateJobStatus( $jobStruct, JobStatus::STATUS_ACTIVE );
                    $outcome = [ 'status' => JobStatus::STATUS_ACTIVE ];
                    break;

                // Cancel the job
                case self::CANCEL_ACTION:
                    JobDao::updateJobStatus( $jobStruct, JobStatus::STATUS_CANCELLED );
                    $outcome = [ 'status' => JobStatus::STATUS_CANCELLED ];
                    break;

                // Delete the job
                case self::DELETE_ACTION:
                    JobDao::updateJobStatus( $jobStruct, JobStatus::STATUS_DELETED );
                    $outcome = [ 'status' => JobStatus::STATUS_DELETED ];
                    break;

                // Archive the job
                case self::ARCHIVE_ACTION:
                    JobDao::updateJobStatus( $jobStruct, JobStatus::STATUS_ARCHIVED );
                    $outcome = [ 'status' => JobStatus::STATUS_ARCHIVED ];
                    break;

                // Change the job password
                case self::CHANGE_PASSWORD_ACTION:
                    $newPassword    = Utils::randomString();
                    $revisionNumber = filter_var( $json[ 'revision_number' ], FILTER_SANITIZE_NUMBER_INT ) ?? null;

                    if ( !empty( $revisionNumber ) and !in_array( $revisionNumber, [ 1, 2 ] ) ) {
                        throw new InvalidArgumentException( '`revision_number` not valid. Allowed values [1, 2]' );
                    }

                    if ( $revisionNumber !== null ) {
                        $chunkReviewDao    = new ChunkReviewDao();
                        $chunkReviewStruct = $chunkReviewDao::findByIdJobAndPasswordAndSourcePage( $id, $password, $revisionNumber + 1 );
                        $password          = $chunkReviewStruct->review_password;
                    }

                    $this->changeThePassword( $user, 'job', $id, $password, $newPassword, $revisionNumber );
                    $outcome = [ 'newPassword' => $newPassword ];
                    break;

                // Generate second pass review
                case self::GENERATE_SECOND_PASS_ACTION:
                    $records = RevisionFactory::initFromProject( $projectStruct )->getRevisionFeature()->createQaChunkReviewRecords(
                            [ $jobStruct ],
                            $projectStruct,
                            [
                                    'source_page' => 3
                            ]
                    );

                    // destroy project data cache
                    ( new ProjectDao() )->destroyCacheForProjectData( $projectStruct->id, $projectStruct->password );

                    // destroy the 5 minutes chunk review cache
                    $chunk = ( new ChunkDao() )->getByIdAndPassword( $records[ 0 ]->id_job, $records[ 0 ]->password );
                    ( new ChunkReviewDao() )->destroyCacheForFindChunkReviews( $chunk );
                    ChunkReviewDao::destroyCacheByProjectId( $projectStruct->id );
                    $outcome = [ 'secondPassPassword' => $records[ 0 ]->review_password ];
                    break;

                case self::ASSIGN_TO_MEMBER_ACTION:
                case self::ASSIGN_TO_TEAM_ACTION:
                    // do something
                    break;
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