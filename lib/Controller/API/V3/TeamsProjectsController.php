<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 13/02/2017
 * Time: 10:06
 */

namespace API\V3;


use API\V2\Exceptions\NotFoundException;
use API\V2\Json\Project;
use API\V2\KleinController;
use API\V2\Validators\LoginValidator;
use API\V2\Validators\TeamAccessValidator;
use INIT;
use Projects_ProjectDao;
use Teams\TeamStruct;

class TeamsProjectsController extends KleinController {

    protected $project;

    /** @var TeamStruct */
    protected $team;

    protected function afterConstruct() {
        parent::afterConstruct();
        $this->appendValidator( new LoginValidator( $this ) );
        $this->appendValidator( new TeamAccessValidator( $this ) );
    }

    /**
     * @throws NotFoundException
     * @throws \Exceptions\NotFoundException
     * @throws \Exception
     */
    public function getPaginated() {

        $id_team = $this->request->param( 'id_team' );
        $page    = $this->request->param( 'page' ) ? $this->request->param( 'page' ) : 1;
        $step    = $this->request->param( 'step' ) ? $this->request->param( 'step' ) : 20;
        $search  = $this->request->param( 'search' );

        $filter = [
                'limit'  => $step,
                'offset' => $this->getOffset( $page, $step ),
        ];

        if ( $search ) {
            $filter[ 'search' ] = $search;
        }

        $this->featureSet->loadFromUserEmail( $this->user->email );
        $projectsList = Projects_ProjectDao::findByTeamId( $id_team, $filter, 0 );

        $projectsList = ( new Project( $projectsList ) )->render();

        $totals      = \Projects_ProjectDao::getTotalCountByTeamId( $id_team, $filter, 60 * 5 );
        $total_pages = $this->getTotalPages( $step, $totals );

        if ( $totals == 0 ) {
            $this->response->status()->setCode( 204 );
            $this->response->json( [
                    '_links'   => $this->_getPaginationLinks( $page, $totals, $step, $search ),
                    'projects' => []
            ] );
            exit();
        }

        if ( $page > $total_pages ) {
            throw new NotFoundException( $page . " too high, maximum value is " . $total_pages, 404 );
        }

        $this->response->json( [
                '_links'   => $this->_getPaginationLinks( $page, $totals, $step, $search ),
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
    private function _getPaginationLinks( $page, $totals, $step = 20, $search = [] ) {

        $url = parse_url( $_SERVER[ 'REQUEST_URI' ] );

        $links = [
                "base"        => INIT::$HTTPHOST,
                "self"        => $_SERVER[ 'REQUEST_URI' ],
                "page"        => (int)$page,
                "step"        => (int)$step,
                "totals"      => (int)$totals,
                "total_pages" => $total_pages = $this->getTotalPages( $step, $totals ),
        ];

        $last_part_of_url = ( $step != 20 ? "&step=" . $step : null ) . ( isset( $search[ 'name' ] ) ? "&search[name]=" . $search[ 'name' ] : null ) . (
                isset( $search[ 'id' ] ) ? "&search[id]=" . $search[ 'id' ] : null );

        if ( $page < $total_pages ) {
            $links[ 'next' ] = $url[ 'path' ] . "?page=" . ( $page + 1 ) . $last_part_of_url;
        }

        if ( $page > 1 ) {
            $links[ 'prev' ] = $url[ 'path' ] . "?page=" . ( $page - 1 ) . $last_part_of_url;
        }

        return $links;
    }

    /**
     * @param int $page
     * @param int $step
     *
     * @return int
     */
    private function getOffset( $page, $step ) {

        if ( $page === 1 ) {
            return 0;
        }

        return $step * ( $page - 1 );
    }

    /**
     * @param int $step
     * @param int $totals
     *
     * @return int
     */
    private function getTotalPages( $step, $totals ) {
        return (int)ceil( (int)$totals / (int)$step );
    }

    /**
     * @param $team
     */
    public function setTeam( $team ) {
        $this->team = $team;
    }
}