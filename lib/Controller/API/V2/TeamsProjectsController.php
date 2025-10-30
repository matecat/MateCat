<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 13/02/2017
 * Time: 10:06
 */

namespace Controller\API\V2;


use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\ProjectExistsInTeamValidator;
use Controller\API\Commons\Validators\TeamAccessValidator;
use Controller\API\Commons\Validators\TeamProjectValidator;
use Exception;
use Model\Exceptions\ValidationError;
use Model\Projects\ManageModel;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectModel;
use Model\Projects\ProjectStruct;
use Model\Teams\TeamStruct;
use ReflectionException;
use View\API\V2\Json\Project;

class TeamsProjectsController extends KleinController {

    /**
     * @var ProjectStruct
     */
    protected ProjectStruct $project;

    /** @var TeamStruct */
    protected TeamStruct $team;

    /**
     * @throws AuthorizationError
     * @throws ReflectionException
     * @throws ValidationError
     * @throws Exception
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
        $formatted->setUser( $this->user );

        $this->refreshClientSessionIfNotApi();

        $this->response->json( [ 'project' => $formatted->renderItem( $updatedStruct ) ] );

    }

    protected function afterConstruct(): void {
        parent::afterConstruct();
        $this->appendValidator( new LoginValidator( $this ) );
        $this->appendValidator( new TeamAccessValidator( $this ) );
    }

    /**
     * @return $this
     * @throws ReflectionException
     */
    protected function _appendSingleProjectTeamValidators(): TeamsProjectsController {
        $this->project = ProjectDao::findById( $this->request->param( 'id_project' ) ); //check login and auth before request the project info
        $this->appendValidator( ( new TeamProjectValidator( $this ) )->setProject( $this->project ) );
        $this->appendValidator( ( new ProjectExistsInTeamValidator( $this ) )->setProject( $this->project ) );

        return $this;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function get() {
        $this->_appendSingleProjectTeamValidators()->validateRequest();
        $formatted = new Project();
        $this->response->json( [ 'project' => $formatted->renderItem( $this->project ) ] );
    }

    /**
     * @throws \Model\Exceptions\NotFoundException
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

        $projects = ManageModel::getProjects( $this->user, $start, $step,
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

    public function setTeam( $team ) {
        $this->team = $team;
    }

}