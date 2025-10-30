<?php

namespace Controller\API\App;

use Controller\API\Commons\Validators\JSONRequestValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\V2\ChangePasswordController;
use Exception;
use InvalidArgumentException;
use Model\Exceptions\NotFoundException;
use Model\Jobs\ChunkDao;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Teams\MembershipDao;
use Model\Teams\TeamDao;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use Plugins\Features\RevisionFactory;
use Utils\Constants\JobStatus;
use Utils\Registry\AppConfig;
use Utils\Tools\Utils;
use Utils\Validator\JSONSchema\JSONValidator;
use Utils\Validator\JSONSchema\JSONValidatorObject;

class JobsBulkActionsController extends ChangePasswordController {

    const JOBS_LIMIT = 100;

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

        $projectsArray = [];

        $response = [];

        if ( count( $jobs ) > self::JOBS_LIMIT ) {
            throw new InvalidArgumentException( "The maximum number of selectable jobs (" . self::JOBS_LIMIT . ") has been reached." );
        }

        // Actions on Jobs
        foreach ( $jobs as $job ) {

            $id       = $job[ 'id' ];
            $password = $job[ 'password' ];
            $outcome  = null;

            $jobStruct     = ChunkDao::getByIdAndPassword( (int)$id, $password );
            $projectStruct = $jobStruct->getProject();
            $user          = $this->getUser();
            $this->checkUserPermissions( $projectStruct, $user );

            // update selected jobs count
            $actualCount = isset( $projectsArray[ $projectStruct->id ][ 'jobsSelected' ] ) ? $projectsArray[ $projectStruct->id ][ 'jobsSelected' ]++ : 1;

            $projectsArray[ $projectStruct->id ] = [
                    'jobsCount'    => $projectStruct->getJobsCount(),
                    'jobsSelected' => $actualCount,
            ];

            switch ( $action ) {

                // Active the job
                case self::UNARCHIVE_ACTION:
                case self::RESUME_ACTION:
                case self::ACTIVE_ACTION:
                    $outcome = $this->changeJobStatus( $jobStruct, JobStatus::STATUS_ACTIVE );
                    break;

                // Cancel the job
                case self::CANCEL_ACTION:
                    $outcome = $this->changeJobStatus( $jobStruct, JobStatus::STATUS_CANCELLED );
                    break;

                // Delete the job
                case self::DELETE_ACTION:
                    $outcome = $this->changeJobStatus( $jobStruct, JobStatus::STATUS_DELETED );
                    break;

                // Archive the job
                case self::ARCHIVE_ACTION:
                    $outcome = $this->changeJobStatus( $jobStruct, JobStatus::STATUS_ARCHIVED );
                    break;

                // Change the job password
                case self::CHANGE_PASSWORD_ACTION:
                    $revisionNumber = filter_var( $json[ 'revision_number' ], FILTER_SANITIZE_NUMBER_INT ) ?? null;
                    $outcome        = $this->changeJobPassword( $user, (int)$id, $password, $revisionNumber );
                    break;

                // Generate second pass review
                case self::GENERATE_SECOND_PASS_ACTION:
                    $outcome = $this->createSecondPassReview( $projectStruct, $jobStruct );
                    break;
            }

            $response[] = [
                    'id'       => (int)$id,
                    'password' => $password,
                    'outcome'  => $outcome
            ];
        }

        // Actions on entire Projects
        foreach ( $projectsArray as $pid => $counts ) {

            // Check if all jobs are selected
            if ( $counts[ 'jobsCount' ] === $counts[ 'jobsSelected' ] ) {
                switch ( $action ) {

                    // Assign the project to another member
                    case self::ASSIGN_TO_MEMBER_ACTION:

                        $idAssignee = filter_var( $json[ 'id_assignee' ], FILTER_SANITIZE_NUMBER_INT ) ?? null;

                        if ( empty( $idAssignee ) ) {
                            throw new InvalidArgumentException( "Missing `id_assignee` param." );
                        }

                        $this->assignProjectToAssignee( (int)$pid, (int)$idAssignee, $response );

                        break;

                    // Assign the project to another team
                    case self::ASSIGN_TO_TEAM_ACTION:

                        $idTeam = filter_var( $json[ 'id_team' ], FILTER_SANITIZE_NUMBER_INT ) ?? null;

                        if ( empty( $idTeam ) ) {
                            throw new InvalidArgumentException( "Missing `id_team` param." );
                        }

                        $this->assignProjectToTeam( (int)$pid, (int)$idTeam, $response );

                        break;
                }
            }
        }

        $this->response->json( [
                'jobs' => $response
        ] );
    }

    /**
     * @param JobStruct $jobStruct
     * @param           $status
     *
     * @return array
     * @throws \ReflectionException
     */
    protected function changeJobStatus( JobStruct $jobStruct, $status ) {
        JobDao::updateJobStatus( $jobStruct, $status );

        return [ 'status' => $status ];
    }

    /**
     * @param UserStruct $user
     * @param int        $id
     * @param string     $password
     * @param int|null   $revisionNumber
     *
     * @return array
     * @throws Exception
     */
    protected function changeJobPassword( UserStruct $user, int $id, string $password, ?int $revisionNumber = null ) {
        $newPassword = Utils::randomString();

        if ( !empty( $revisionNumber ) and !in_array( $revisionNumber, [ 1, 2 ] ) ) {
            throw new InvalidArgumentException( '`revision_number` not valid. Allowed values [1, 2]' );
        }

        if ( $revisionNumber !== null ) {
            $chunkReviewDao    = new ChunkReviewDao();
            $chunkReviewStruct = $chunkReviewDao::findByIdJobAndPasswordAndSourcePage( $id, $password, $revisionNumber + 1 );
            $password          = $chunkReviewStruct->review_password;
        }

        $this->changeThePassword( $user, 'job', $id, $password, $newPassword, $revisionNumber );

        return [ 'newPassword' => $newPassword ];
    }


    /**
     * @param ProjectStruct $projectStruct
     * @param JobStruct     $jobStruct
     *
     * @return array
     * @throws Exception
     */
    protected function createSecondPassReview( ProjectStruct $projectStruct, JobStruct $jobStruct ) {
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

        return [ 'secondPassPassword' => $records[ 0 ]->review_password ];
    }

    /**
     * @throws Exception
     */
    private function validateJSONRequest() {
        $json   = $this->request->body();
        $schema = file_get_contents( AppConfig::$ROOT . '/inc/validation/schema/jobs_bulk_actions.json' );

        $validatorObject = new JSONValidatorObject( $json );
        $validator       = new JSONValidator( $schema, true );
        $validator->validate( $validatorObject );
    }

    /**
     * @param int   $pid
     * @param int   $idTeam
     * @param array $response
     *
     * @throws \ReflectionException
     */
    protected function assignProjectToTeam( int $pid, int $idTeam, array &$response ) {
        $team = ( new TeamDao() )->findById( $idTeam );

        if ( empty( $team ) ) {
            throw new InvalidArgumentException( "Team not found." );
        }

        $membershipDao = new MembershipDao();
        $members       = $membershipDao->getMemberListByTeamId( $idTeam );

        if ( empty( $members ) ) {
            throw new InvalidArgumentException( "Wrong team id." );
        }

        $team->setMembers( $members );

        if ( !$team->hasUser( $this->user->uid ) ) {
            throw new InvalidArgumentException( "Team not belonging to the logged user." );
        }

        ( new ProjectDao() )->assignToTeam( $pid, (int)$idTeam );
        $membershipDao->destroyCacheForListByTeamId( (int)$idTeam );
        $membershipDao->destroyCacheForListByTeamId( (int)$idTeam );
        $membershipDao->destroyCacheUserTeams( $this->user );
        $membershipDao->destroyCacheTeamByIdAndUser( (int)$idTeam, $this->user );

        for ( $i = 0; $i < count( $response ); $i++ ) {
            $response[ $i ][ 'outcome' ] = [
                    'idTeam' => $idTeam,
            ];
        }
    }

    /**
     * @param int   $pid
     * @param int   $idAssignee
     * @param array $response
     *
     * @throws \ReflectionException
     */
    protected function assignProjectToAssignee( int $pid, int $idAssignee, array &$response ) {
        $assignee = ( new UserDao() )->getByUid( $idAssignee );

        if ( empty( $assignee ) ) {
            throw new InvalidArgumentException( "Assignee not found." );
        }

        $userTeams = $this->user->getUserTeams();

        foreach ( $userTeams as $userTeam ) {
            if ( !$assignee->belongsToTeam( $userTeam->id ) ) {
                throw new InvalidArgumentException( "Assignee is not belonging to one of the logged user teams." );
            }
        };

        ( new ProjectDao() )->assignToAssignee( $pid, $idAssignee );

        for ( $i = 0; $i < count( $response ); $i++ ) {
            $response[ $i ][ 'outcome' ] = [
                    'idAssignee' => $idAssignee,
            ];
        }
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