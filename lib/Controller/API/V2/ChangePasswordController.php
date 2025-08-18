<?php

namespace Controller\API\V2;

use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Model\DataAccess\Database;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Teams\MembershipDao;
use Model\Users\UserStruct;
use Plugins\Features\ReviewExtended\ReviewUtils;
use Utils\Tools\CatUtils;
use Utils\Tools\Utils;

class ChangePasswordController extends ChunkController {
    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function changePassword() {
        $res             = filter_var( $this->request->param( 'res' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $id              = filter_var( $this->request->param( 'id' ), FILTER_SANITIZE_NUMBER_INT );
        $password        = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $new_password    = filter_var( $this->request->param( 'new_password' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $revision_number = filter_var( $this->request->param( 'revision_number' ), FILTER_SANITIZE_NUMBER_INT );
        $undo            = filter_var( $this->request->param( 'undo' ), FILTER_VALIDATE_BOOLEAN );

        if (
                empty( $id ) or
                empty( $password )
        ) {
            $code = 400;
            $this->response->status()->setCode( $code );
            $this->response->json( [
                    'error' => 'Missing required parameters [`id `, `password`]'
            ] );
            exit();
        }

        if ( $undo ) {

            // in this case new_password is mandatory
            if ( empty( $new_password ) ) {
                $this->response->json( [
                        'error' => 'Missing required parameters [`id `, `password`, `new_password`]'
                ] );
                exit();
            }

            $new_pwd    = $new_password;
            $actual_pwd = $password;
        } else {
            $new_pwd    = Utils::randomString();
            $actual_pwd = $password;
        }

        if ( !empty( $revision_number ) and !in_array( $revision_number, [ 1, 2 ] ) ) {
            $this->response->json( [
                    'error' => '`revision_number` not valid. Allowed values [1, 2]'
            ] );
            exit();
        }

        $res = ( !empty( $res ) ) ? $res : 'job';

        if ( !in_array( $res, [ 'prj', 'job' ] ) ) {
            $code = 400;
            $this->response->status()->setCode( $code );
            $this->response->json( [
                    'error' => '`res` not valid. Allowed values [`prj`, `job`]'
            ] );
            exit();
        }

        try {
            $user = $this->getUser();
            $this->changeThePassword( $user, $res, $id, $actual_pwd, $new_pwd, $revision_number );

            $this->response->status()->setCode( 200 );
            $this->response->json( [
                    'id'      => $id,
                    'new_pwd' => $new_pwd,
                    'old_pwd' => $actual_pwd,
            ] );
            exit();


        } catch ( Exception $exception ) {
            $this->response->status()->setCode( 500 );
            $this->response->json( [
                    'error' => $exception->getMessage()
            ] );
            exit();
        }
    }

    /**
     * @param \Model\Users\UserStruct $user
     * @param                  $res
     * @param                  $id
     * @param                  $actual_pwd
     * @param                  $new_password
     * @param null                    $revision_number
     *
     * @throws Exception
     */
    private function changeThePassword( UserStruct $user, $res, $id, $actual_pwd, $new_password, $revision_number = null ) {
        // change project password
        if ( $res == "prj" ) {

            $pStruct = ProjectDao::findByIdAndPassword( $id, $actual_pwd );

            if ( $pStruct === null ) {
                throw new Exception( 'Project not found' );
            }

            $this->checkUserPermissions( $pStruct, $user );

            $pDao = new ProjectDao();
            $pDao->changePassword( $pStruct, $new_password );
            $pDao->destroyCacheById( $id );
            $pDao->destroyCacheForProjectData( $pStruct->id, $pStruct->password );

            $pStruct->getFeaturesSet()->run( 'project_password_changed', $pStruct, $actual_pwd );

        } else { // change job passwords

            Database::obtain()->begin();

            if ( $revision_number ) { // change job revision password

                $jStruct = CatUtils::getJobFromIdAndAnyPassword( $id, $actual_pwd );

                if ( $jStruct === null ) {
                    throw new Exception( 'Job not found' );
                }

                $this->checkUserPermissions( $jStruct->getProject(), $user );

                $source_page = ReviewUtils::revisionNumberToSourcePage( $revision_number );
                $dao         = new ChunkReviewDao();
                $dao->updateReviewPassword( $id, $actual_pwd, $new_password, $source_page );
                $jStruct->getProject()
                        ->getFeaturesSet()
                        ->run( 'review_password_changed', $id, $actual_pwd, $new_password, $revision_number );


            } else { // change job password
                $jStruct = JobDao::getByIdAndPassword( $id, $actual_pwd );
                $jDao    = new JobDao();

                $this->checkUserPermissions( $jStruct->getProject(), $user );

                $jDao->changePassword( $jStruct, $new_password );
                $jStruct->getProject()
                        ->getFeaturesSet()
                        ->run( 'job_password_changed', $jStruct, $actual_pwd );
            }

            // invalidate ChunkReviewDao cache for the job
            if ( $jStruct instanceof JobStruct ) {
                $chunkReviewDao = new ChunkReviewDao();
                $chunkReviewDao->destroyCacheForFindChunkReviews( $jStruct );
            }

            // invalidate cache for ProjectData
            $pDao = new ProjectDao();
            $pDao->destroyCacheForProjectData( $jStruct->getProject()->id, $jStruct->getProject()->password );
            $pDao->destroyCacheById( $jStruct->getProject()->id );

            Database::obtain()->commit();
        }
    }

    /**
     * Check if the logged user has the permissions to change the password
     *
     * @param ProjectStruct           $project
     * @param \Model\Users\UserStruct $user
     *
     * @throws Exception
     */
    private function checkUserPermissions( ProjectStruct $project, UserStruct $user ) {
        // check if user is belongs to the project team
        $team  = $project->getTeam();
        $check = ( new MembershipDao() )->findTeamByIdAndUser( $team->id, $user );

        if ( $check === null ) {
            throw new Exception( 'The logged user does not belong to the right team', 403 );
        }
    }
}