<?php

namespace API\V2;

use API\V2\ChunkController;
use API\V2\Validators\LoginValidator;
use CatUtils;
use Chunks_ChunkStruct;
use Database;
use Exception;
use Features\ReviewExtended\ReviewUtils;
use Jobs_JobDao;
use LQA\ChunkReviewDao;
use Projects_ProjectDao;
use Projects_ProjectStruct;
use Teams\MembershipDao;
use Users_UserStruct;
use Utils;

class ChangePasswordController extends ChunkController
{
    protected function afterConstruct()
    {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function changePassword()
    {
        $res             = filter_var($this->request->param('res'), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $id              = filter_var($this->request->param('id'), FILTER_SANITIZE_NUMBER_INT );
        $password        = filter_var($this->request->param('password'), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $new_password    = filter_var($this->request->param('new_password'), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $revision_number = filter_var($this->request->param('revision_number'), FILTER_SANITIZE_NUMBER_INT );
        $undo            = filter_var($this->request->param('undo'), FILTER_VALIDATE_BOOLEAN );

        if(
            empty($id) or
            empty($password)
        ){
            $code = 400;
            $this->response->status()->setCode( $code );
            $this->response->json( [
                'error' => 'Missing required parameters [`id `, `password`]'
            ] );
            exit();
        }

        if ( $undo ) {

            // in this case new_password is mandatory
            if(empty($new_password)){
                $this->response->json( [
                    'error' => 'Missing required parameters [`id `, `password`, `new_password`]'
                ] );
                exit();
            }

            $new_pwd    = $new_password;
            $actual_pwd = $password;
        } else {
            $new_pwd    = Utils::randomString( 15, true );
            $actual_pwd = $password;
        }

        if(!empty($revision_number) and !in_array($revision_number, [1,2])){
            $this->response->json( [
                'error' => '`revision_number` not valid. Allowed values [1, 2]'
            ] );
            exit();
        }

        $res = (!empty($res)) ? $res : 'job';

        if(!in_array($res, ['prj', 'job'])){
            $code = 400;
            $this->response->status()->setCode( $code );
            $this->response->json( [
                'error' => '`res` not valid. Allowed values [`prj`, `job`]'
            ] );
            exit();
        }

        try {
            $user = $this->getUser();
            $this->changeThePassword($user, $res, $id, $actual_pwd, $new_pwd, $revision_number);

            $this->response->status()->setCode(200);
            $this->response->json( [
                'id'         => $id,
                'new_pwd'    => $new_pwd,
                'old_pwd' => $actual_pwd,
            ] );
            exit();


        } catch (Exception $exception){
            $this->response->status()->setCode(500);
            $this->response->json( [
                'error' => $exception->getMessage()
            ] );
            exit();
        }
    }

    /**
     * @param Users_UserStruct $user
     * @param $res
     * @param $id
     * @param $actual_pwd
     * @param $new_password
     * @param null $revision_number
     * @throws Exception
     */
    private function changeThePassword(Users_UserStruct $user, $res, $id, $actual_pwd, $new_password, $revision_number = null)
    {
        // change project password
        if ( $res == "prj" ) {

            $pStruct = Projects_ProjectDao::findByIdAndPassword( $id, $actual_pwd );

            if($pStruct === null){
                throw new Exception('Project not found');
            }

            $this->checkUserPermissions($pStruct, $user);

            $pDao    = new Projects_ProjectDao();
            $pDao->changePassword( $pStruct, $new_password );
            $pDao->destroyCacheById( $id );
            $pDao->destroyCacheForProjectData($pStruct->id, $pStruct->password);

            $pStruct->getFeaturesSet()->run( 'project_password_changed', $pStruct, $actual_pwd );

        } else { // change job passwords

            Database::obtain()->begin();

            if ( $revision_number ) { // change job revision password

                $jStruct = CatUtils::getJobFromIdAndAnyPassword( $id, $actual_pwd );

                if($jStruct === null){
                    throw new Exception('Job not found');
                }

                $this->checkUserPermissions($jStruct->getProject(), $user);

                $source_page = ReviewUtils::revisionNumberToSourcePage( $revision_number );
                $dao         = new ChunkReviewDao();
                $dao->updateReviewPassword( $id, $actual_pwd, $new_password, $source_page );
                $jStruct->getProject()
                    ->getFeaturesSet()
                    ->run( 'review_password_changed', $id, $actual_pwd, $new_password, $revision_number );


            } else { // change job password
                $jStruct = Jobs_JobDao::getByIdAndPassword( $id, $actual_pwd );
                $jDao    = new Jobs_JobDao();

                $this->checkUserPermissions($jStruct->getProject(), $user);

                $jDao->changePassword( $jStruct, $new_password );
                $jStruct->getProject()
                    ->getFeaturesSet()
                    ->run( 'job_password_changed', $jStruct, $actual_pwd );
            }

            // invalidate ChunkReviewDao cache for the job
            if($jStruct instanceof Chunks_ChunkStruct){
                $chunkReviewDao = new ChunkReviewDao();
                $chunkReviewDao->destroyCacheForFindChunkReviews($jStruct, 60 * 5 );
            }

            // invalidate cache for ProjectData
            $pDao = new Projects_ProjectDao();
            $pDao->destroyCacheForProjectData($jStruct->getProject()->id, $jStruct->getProject()->password);
            $pDao->destroyCacheById( $jStruct->getProject()->id );

            Database::obtain()->commit();
        }
    }

    /**
     * Check if the logged user has the permissions to change the password
     *
     * @param Projects_ProjectStruct $project
     * @param Users_UserStruct $user
     * @throws Exception
     */
    private function checkUserPermissions(Projects_ProjectStruct $project, Users_UserStruct $user)
    {
        // check if user is belongs to the project team
        $team  = $project->getTeam();
        $check = (new MembershipDao())->findTeamByIdAndUser($team->id, $user);

        if($check === null){
            throw new Exception('The logged user does not belong to the right team', 403);
        }
    }
}