<?php

namespace API\V2;

use API\Commons\Validators\LoginValidator;
use API\Commons\Validators\ProjectAccessValidator;
use API\Commons\Validators\ProjectPasswordValidator;
use CatUtils;
use Exception;
use InvalidArgumentException;
use Projects_ProjectDao;
use Projects_ProjectStruct;
use Teams\MembershipDao;
use Users_UserStruct;
use Utils;

class ChangeProjectNameController extends ChunkController
{
    /**
     * @var ProjectPasswordValidator
     */
    private $validator;

    protected function afterConstruct()
    {
        $this->validator = new ProjectPasswordValidator( $this );
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function changeName()
    {
        try {
            $id       = filter_var($this->request->param('id_project'), FILTER_SANITIZE_NUMBER_INT );
            $password = filter_var($this->request->param('password'), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
            $name     = CatUtils::validateProjectName($this->request->param('name') );

            if($name === false){
                throw new InvalidArgumentException( $this->request->param('name') . " is not a valid project name", -3 );
            }

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

            $name = Utils::sanitizeName($name);

            if ( empty($name) ) {
                $code = 400;
                $this->response->status()->setCode( $code );
                $this->response->json( [
                        'error' => 'Missing required parameters [`name`]'
                ] );
                exit();
            }

            $this->validator->validate();

            ( new ProjectAccessValidator( $this, $this->validator->getProject() ) )->validate();
            $ownerEmail = $this->validator->getProject()->id_customer;

            $this->changeProjectName($id, $password, $name);
            $this->featureSet->filter( 'filterProjectNameModified', $id, $name, $password, $ownerEmail );

            $this->response->status()->setCode(200);
            $this->response->json( [
                'id'   => $id,
                'name' => $name,
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
     * @param $id
     * @param $password
     * @param $name
     * @throws Exception
     */
    private function changeProjectName($id, $password, $name)
    {
        $pStruct = Projects_ProjectDao::findByIdAndPassword( $id, $password );

        if($pStruct === null){
            throw new Exception('Project not found');
        }

        $this->checkUserPermissions($pStruct, $this->getUser());

        $pDao = new Projects_ProjectDao();
        $pDao->changeName( $pStruct, $name );
        $pDao->destroyCacheById( $id );
        $pDao->destroyCacheForProjectData($pStruct->id, $pStruct->password);
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