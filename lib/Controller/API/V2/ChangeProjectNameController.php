<?php

namespace Controller\API\V2;

use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\ProjectAccessValidator;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use Exception;
use InvalidArgumentException;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Teams\MembershipDao;
use Model\Users\UserStruct;
use Throwable;
use Utils\Tools\CatUtils;

class ChangeProjectNameController extends JobsController {
    /**
     * @var ProjectPasswordValidator
     */
    private $validator;

    protected function afterConstruct() {
        $this->validator = new ProjectPasswordValidator( $this );
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * @throws Throwable
     */
    public function changeName() {

        $id       = filter_var( $this->request->param( 'id_project' ), FILTER_SANITIZE_NUMBER_INT );
        $password = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $name     = filter_var( $this->request->param( 'name' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );

        if (
                empty( $id ) or
                empty( $password )
        ) {
            throw new InvalidArgumentException( 'Missing required parameters [`id `, `password`]' );
        }

        $name = CatUtils::sanitizeOrFallbackProjectName( $name ?? '' );

        $this->validator->validate();

        ( new ProjectAccessValidator( $this, $this->validator->getProject() ) )->validate();
        $ownerEmail = $this->validator->getProject()->id_customer;

        $this->changeProjectName( $id, $password, $name );
        $this->featureSet->filter( 'filterProjectNameModified', $id, $name, $password, $ownerEmail );

        $this->response->status()->setCode( 200 );
        $this->response->json( [
                'id'   => $id,
                'name' => $name,
        ] );

    }

    /**
     * @param $id
     * @param $password
     * @param $name
     *
     * @throws Exception
     */
    private function changeProjectName( $id, $password, $name ) {
        $pStruct = ProjectDao::findByIdAndPassword( $id, $password );

        if ( $pStruct === null ) {
            throw new Exception( 'Project not found' );
        }

        $this->checkUserPermissions( $pStruct, $this->getUser() );

        $pDao = new ProjectDao();
        $pDao->changeName( $pStruct, $name );
        $pDao->destroyCacheById( $id );
        $pDao->destroyCacheForProjectData( $pStruct->id, $pStruct->password );
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