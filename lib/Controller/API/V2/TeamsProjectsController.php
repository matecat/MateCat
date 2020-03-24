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
use ManageUtils;
use Projects\ProjectModel;
use Teams\TeamStruct;

class TeamsProjectsController extends KleinController {

    protected $project;

    /** @var TeamStruct */
    protected $team;

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
    protected function _appendSingleProjectTeamValidators() {
        $this->project = \Projects_ProjectDao::findById( $this->request->id_project ); //check login and auth before request the project info
        $this->appendValidator( ( new TeamProjectValidator( $this ) )->setProject( $this->project ) );
        $this->appendValidator( ( new ProjectExistsInTeamValidator( $this ) )->setProject( $this->project ) );

        return $this;
    }

    public function get() {
        $this->_appendSingleProjectTeamValidators()->validateRequest();
        $formatted = new Project();
        $this->response->json( [ 'project' => $formatted->renderItem( $this->project ) ] );
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

        if ( empty( $projects ) ) {
            throw new NotFoundException( "Project not found", 404 );
        }

        $this->response->json( [ 'projects' => $projects ] );
    }

    /**
     * @throws NotFoundException
     * @throws \Exceptions\NotFoundException
     * @throws \Exception
     */
    public function getAll() {

        $id_team = $this->request->param( 'id_team' );
        $page    = $this->request->param( 'page' ) ? $this->request->param( 'page' ) : 1;
        $step    = $this->request->param( 'step' ) ? $this->request->param( 'step' ) : 20;

        $this->featureSet->loadFromUserEmail( $this->user->email );
        $projectsList = \Projects_ProjectDao::findByTeamId( $id_team, $step, $this->getOffset($page, $step) ,60 );

        $projectsList = ( new Project( $projectsList ) )->render();

        $totals      = \Projects_ProjectDao::getTotalCountByTeamId( $id_team, 0 );
        $total_pages = $this->getTotalPages( $step, $totals );

        if ( $page > $total_pages ) {
            throw new NotFoundException( $page . " too high, maximum value is " . $total_pages, 404 );
        }

        $this->response->json( [
                '_links'   => $this->_getPaginationLinks( $page, $totals, $step ),
                'projects' => $projectsList
        ] );
    }

    /**
     * @param int $page
     * @param int $totals
     * @param int $step
     *
     * @return array
     */
    private function _getPaginationLinks( $page, $totals, $step = 20 ) {

        $url = parse_url( $_SERVER[ 'REQUEST_URI' ] );

        $links = [
                "base"        => \INIT::$HTTPHOST,
                "self"        => $_SERVER[ 'REQUEST_URI' ],
                "page"        => (int)$page,
                "step"        => (int)$step,
                "totals"      => (int)$totals,
                "total_pages" => $total_pages = $this->getTotalPages( $step, $totals ),
        ];

        if ( $page < $total_pages ) {
            $links[ 'next' ] = $url[ 'path' ] . "?page=" . ( $page + 1 ) . ( $step != 20 ? "&step=" . $step : null );
        }

        if ( $page > 1 ) {
            $links[ 'prev' ] = $url[ 'path' ] . "?page=" . ( $page - 1 ) . ( $step != 20 ? "&step=" . $step : null );
        }

        return $links;
    }

    /**
     * @param int $page
     * @param int $step
     *
     * @return int
     */
    private function getOffset($page, $step){

        if($page === 1){
            return 0;
        }

        return $step * ($page-1);
    }

    /**
     * @param int $step
     * @param int $totals
     *
     * @return int
     */
    private function getTotalPages( $step, $totals ) {
        return (int)$total_pages = ceil( (int)$totals / (int)$step );
    }

    public function setTeam( $team ) {
        $this->team = $team;
    }

}