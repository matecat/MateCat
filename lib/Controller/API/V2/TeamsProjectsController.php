<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 13/02/2017
 * Time: 10:06
 */

namespace API\V2;


use API\V2\Json\Project;
use API\V2\Validators\LoginValidator;
use API\V2\Validators\ProjectExistsInTeamValidator;
use API\V2\Validators\TeamAccessValidator;
use API\V2\Validators\TeamProjectValidator;
use FeatureSet;
use Projects\ProjectModel;

class TeamsProjectsController extends KleinController {

    protected $project;

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

    public function getAll(){

        $this->featureSet->loadFromUserEmail( $this->getUser()->email ) ;

        $projectsList = \Projects_ProjectDao::findByTeamId( $this->params[ 'id_team' ], 60 );

        $projectsList     = ( new Project( $projectsList ) )->render();
        $projectsList = $this->filterProjectsWithUserFeatures( $projectsList ) ;
        $projectsList = $this->filterProjectsWithProjectFeatures( $projectsList ) ;
        $this->response->json( array( 'projects' => $projectsList ) );

    }

    private function filterProjectsWithUserFeatures( $projects ) {
        $projects = $this->featureSet->filter('filter_manage_projects_loaded', $projects);
        return $projects ;
    }

    private function filterProjectsWithProjectFeatures( $projects ) {
        foreach( $projects as $key => $project ) {
            $features = new FeatureSet() ;
            $features->loadFromString( $project['features'] );
            $projects[ $key ] = $features->filter('filter_manage_single_project', $project );
        }
        return $projects ;
    }

}