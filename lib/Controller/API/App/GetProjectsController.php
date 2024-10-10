<?php

namespace API\App;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use Constants_JobStatus;
use Constants_Teams;
use Exception;
use Exceptions\NotFoundException;
use ManageUtils;
use Teams\MembershipDao;
use Teams\MembershipStruct;
use Teams\TeamStruct;
use Users_UserStruct;

class GetProjectsController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function fetch()
    {
        $page = filter_var( $this->request->param( 'page' ), FILTER_SANITIZE_NUMBER_INT );
        $step = filter_var( $this->request->param( 'step' ), FILTER_SANITIZE_NUMBER_INT );
        $project_id = filter_var( $this->request->param( 'project' ), FILTER_SANITIZE_NUMBER_INT );
        $search_in_pname = filter_var( $this->request->param( 'pn' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        $source = filter_var( $this->request->param( 'source' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW ] );
        $target = filter_var( $this->request->param( 'target' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW ] );
        $status = filter_var( $this->request->param( 'status' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW ] );
        $only_completed = filter_var( $this->request->param( 'onlycompleted' ), FILTER_VALIDATE_BOOLEAN, [ 'flags' => FILTER_NULL_ON_FAILURE ] );
        $id_team = filter_var( $this->request->param( 'id_team' ), FILTER_SANITIZE_NUMBER_INT );
        $id_assignee = filter_var( $this->request->param( 'id_assignee' ), FILTER_SANITIZE_NUMBER_INT );
        $no_assignee = filter_var( $this->request->param( 'no_assignee' ), FILTER_VALIDATE_BOOLEAN );

        $search_status = (!empty( $status ) and Constants_JobStatus::isAllowedStatus( $status)) ? $status : Constants_JobStatus::STATUS_ACTIVE;
        $page = (!empty( $page )) ? (int)$page : 1;
        $step = (!empty( $step )) ? (int)$step : 10;
        $start  = ( $page - 1 ) * $step;

        $this->featureSet->loadFromUserEmail( $this->user->email ) ;

        try {
            $team = $this->filterTeam($id_team);
        } catch( NotFoundException $e ){
            throw new $e;
        }

        if( $team->type == Constants_Teams::PERSONAL ){
            $assignee = $this->user;
            $team = null;
        } else {
            $assignee = $this->filterAssignee( $team, $id_assignee );
        }

        $projects = ManageUtils::getProjects(
            $this->user,
            $start,
            $step,
            $search_in_pname,
            $source,
            $target,
            $search_status,
            $only_completed,
            $project_id,
            $team,
            $assignee,
            $no_assignee
        );

        $projnum = ManageUtils::getProjectsNumber(
            $this->user,
            $search_in_pname,
            $source,
            $target,
            $search_status,
            $only_completed,
            $team,
            $assignee,
            $no_assignee
        );

        return $this->response->json([
            'data' => $projects,
            'page' => $page,
            'pnumber' => $projnum[ 0 ][ 'c' ],
            'pageStep' => $step,
        ]);
    }

    /**
     * @param TeamStruct $team
     * @param $id_assignee
     *
     * @return Users_UserStruct|null
     * @throws \ReflectionException
     */
    private function filterAssignee( TeamStruct $team, $id_assignee ) {

        if ( is_null( $id_assignee ) ) {
            return null;
        }

        $dao         = new MembershipDao();
        $memberships = $dao->setCacheTTL( 60 * 60 * 24 )->getMemberListByTeamId( $team->id );

        /**
         * @var $users \Teams\MembershipStruct[]
         */
        $users = array_values( array_filter( $memberships, function ( MembershipStruct $membership ) use ( $id_assignee ) {
            return $membership->getUser()->uid == $id_assignee;
        } ) );

        if ( empty( $users ) ) {
            throw new Exception( 'Assignee not found in team' );
        }

        return $users[ 0 ]->getUser();
    }

    /**
     * @param $id_team
     *
     * @return TeamStruct|null
     * @throws NotFoundException
     * @throws \ReflectionException
     */
    private function filterTeam($id_team)
    {
        $dao = new MembershipDao() ;
        $team = $dao->findTeamByIdAndUser($id_team, $this->user);

        if ( !$team ) {
            throw  new NotFoundException( 'Team not found in user memberships', 404 ) ;
        }

        return $team ;
    }
}