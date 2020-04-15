<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 13/02/2017
 * Time: 10:06
 */

namespace API\V2;


use API\V2\Exceptions\NotFoundException;
use API\V2\Json\Project;
use API\V2\Validators\LoginValidator;
use API\V2\Validators\ProjectExistsInTeamValidator;
use API\V2\Validators\TeamAccessValidator;
use API\V2\Validators\TeamProjectValidator;
use FeatureSet;
use ManageUtils;
use Projects\ProjectModel;
use Teams\TeamStruct;

class TeamsProjectsController extends KleinController {

    protected $project;

    /** @var TeamStruct */
    protected $team;

    public function update() {

        $this->_appendSingleProjectTeamValidators()->validateRequest();

        $acceptedFields = array( 'id_assignee', 'name', 'id_team' );

        $projectModel   = new ProjectModel( $this->project );
        $projectModel->setUser( $this->user ) ;

        foreach ( $acceptedFields as $field ) {
            if ( array_key_exists($field, $this->params ) ) {
                $projectModel->prepareUpdate( $field, $this->params[ $field ] );
            }
        }

        $updatedStruct = $projectModel->update();
        $formatted     = new Project();
        $this->response->json( array( 'project' => $formatted->renderItem( $updatedStruct ) ) );

    }

    protected function afterConstruct() {
        parent::afterConstruct();
        $this->appendValidator( new LoginValidator( $this ) );
        $this->appendValidator( new TeamAccessValidator( $this ) );
    }

    /**
     * @return $this
     */
    protected function _appendSingleProjectTeamValidators(){
        $this->project = \Projects_ProjectDao::findById( $this->request->id_project ); //check login and auth before request the project info
        $this->appendValidator( ( new TeamProjectValidator( $this ) )->setProject( $this->project ) );
        $this->appendValidator( ( new ProjectExistsInTeamValidator( $this ) )->setProject( $this->project ) );
        return $this;
    }

    public function get(){
        $this->_appendSingleProjectTeamValidators()->validateRequest();
        $formatted     = new Project();
        $this->response->json( array( 'project' => $formatted->renderItem( $this->project ) ) );
    }

    public function getByName() {
        $start                 = 0;
        $step                  = 25;
        $search_in_pname       = $this->request->project_name;
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

        if( empty( $projects ) ){
            throw new NotFoundException( "Project not found", 404 );
        }

        $this->response->json( [ 'projects' => $projects ] );
    }

    public function getAll(){

        $this->featureSet->loadFromUserEmail( $this->user->email ) ;

        $projectsList = \Projects_ProjectDao::findByTeamId( $this->params[ 'id_team' ], [], 60 );

        $projectsList     = ( new Project( $projectsList ) )->render();
        $this->response->json( array( 'projects' => $projectsList ) );

    }

    public function setTeam($team){
        $this->team = $team;
    }

}