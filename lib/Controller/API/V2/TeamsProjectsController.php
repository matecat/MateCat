<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 13/02/2017
 * Time: 10:06
 */

namespace API\V2;


use AbstractControllers\KleinController;
use API\Commons\Exceptions\AuthorizationError;
use API\Commons\Exceptions\NotFoundException;
use API\Commons\Validators\LoginValidator;
use API\Commons\Validators\ProjectExistsInTeamValidator;
use API\Commons\Validators\TeamAccessValidator;
use API\Commons\Validators\TeamProjectValidator;
use API\V2\Json\Project;
use Exception;
use Exceptions\ValidationError;
use ManageUtils;
use Projects\ProjectModel;
use Projects_ProjectDao;
use Projects_ProjectStruct;
use ReflectionException;
use Teams\TeamStruct;

class TeamsProjectsController extends KleinController {

    protected $project;

    /** @var TeamStruct */
    protected TeamStruct $team;

    /**
     * @throws AuthorizationError
     * @throws ReflectionException
     * @throws ValidationError
     */
    public function update() {

        $this->_appendSingleProjectTeamValidators()->validateRequest();

        $acceptedFields = [ 'id_assignee', 'name', 'id_team' ];

        $projectModel = new ProjectModel( $this->project );
        $projectModel->setUser( $this->user );

        foreach ( $acceptedFields as $field ) {
            if ( array_key_exists( $field, $this->params ) ) {
                $projectModel->prepareUpdate( $field, $this->params[ $field ] );
            }
        }

        $updatedStruct = $projectModel->update();
        $formatted     = new Project();

        $this->refreshClientSessionIfNotApi();

        $this->response->json( [ 'project' => $formatted->renderItem( $updatedStruct ) ] );

    }

    protected function afterConstruct() {
        parent::afterConstruct();
        $this->appendValidator( new LoginValidator( $this ) );
        $this->appendValidator( new TeamAccessValidator( $this ) );
    }

    /**
     * @return $this
     */
    protected function _appendSingleProjectTeamValidators(): TeamsProjectsController {
        $this->project = Projects_ProjectDao::findById( $this->request->param( 'id_project' ) ); //check login and auth before request the project info
        $this->appendValidator( ( new TeamProjectValidator( $this ) )->setProject( $this->project ) );
        $this->appendValidator( ( new ProjectExistsInTeamValidator( $this ) )->setProject( $this->project ) );

        return $this;
    }

    /**
     * @throws ReflectionException
     */
    public function get() {
        $this->_appendSingleProjectTeamValidators()->validateRequest();
        $formatted = new Project();
        $this->response->json( [ 'project' => $formatted->renderItem( $this->project ) ] );
    }

    /**
     * @throws \Exceptions\NotFoundException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function getByName() {
        $start                 = 0;
        $step                  = 25;
        $search_in_pname       = $this->request->param( 'project_name' );
        $search_source         = null;
        $search_target         = null;
        $search_status         = "active";
        $search_only_completed = null;
        $project_id            = null;
        $assignee              = null;
        $no_assignee           = null;

        $projects = ManageUtils::getProjects( $this->user, $start, $step,
                $search_in_pname,
                $search_source, $search_target, $search_status,
                $search_only_completed, $project_id,
                $this->team, $assignee,
                $no_assignee );

        if ( empty( $projects ) ) {
            throw new NotFoundException( "Project not found", 404 );
        }

        $this->response->json( [ 'projects' => $projects ] );
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function getAll() {

        $this->featureSet->loadFromUserEmail( $this->user->email );

        /** @var Projects_ProjectStruct[] $projectsList */
        $projectsList = Projects_ProjectDao::findByTeamId( $this->params[ 'id_team' ], [], 60 );

        $projectsList = ( new Project( $projectsList ) )->render();
        $this->response->json( [ 'projects' => $projectsList ] );

    }

    public function setTeam( $team ) {
        $this->team = $team;
    }

}