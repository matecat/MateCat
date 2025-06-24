<?php

namespace API\App;

use Constants_JobStatus;
use Constants_Teams;
use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Exceptions\NotFoundException;
use InvalidArgumentException;
use ManageUtils;
use ReflectionException;
use Teams\MembershipDao;
use Teams\MembershipStruct;
use Teams\TeamStruct;
use Users_UserStruct;

class GetProjectsController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * @throws ReflectionException
     */
    public function fetch(): void {

        $this->featureSet->loadFromUserEmail( $this->user->email );
        $request = $this->validateTheRequest();

        $page            = $request[ 'page' ];
        $start           = $request[ 'start' ];
        $step            = $request[ 'step' ];
        $search_status   = $request[ 'search_status' ];
        $project_id      = $request[ 'project_id' ];
        $search_in_pname = $request[ 'search_in_pname' ];
        $source          = $request[ 'source' ];
        $target          = $request[ 'target' ];
        $status          = $request[ 'status' ];
        $only_completed  = $request[ 'onlycompleted' ] ?? null;
        $id_team         = $request[ 'id_team' ];
        $id_assignee     = $request[ 'id_assignee' ];
        $no_assignee     = $request[ 'no_assignee' ];

        $team = $this->filterTeam( $id_team );

        if ( $team->type == Constants_Teams::PERSONAL ) {
            $assignee = $this->user;
            $team     = null;
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

        $this->response->json( [
                'data'     => $projects,
                'page'     => $page,
                'pnumber'  => $projnum[ 0 ][ 'c' ],
                'pageStep' => $step,
        ] );

    }

    /**
     * @return array
     * @throws Exception
     */
    private function validateTheRequest(): array {
        $page            = filter_var( $this->request->param( 'page' ), FILTER_SANITIZE_NUMBER_INT );
        $step            = filter_var( $this->request->param( 'step' ), FILTER_SANITIZE_NUMBER_INT );
        $project_id      = filter_var( $this->request->param( 'project' ), FILTER_SANITIZE_NUMBER_INT );
        $search_in_pname = filter_var( $this->request->param( 'pn' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        $source          = filter_var( $this->request->param( 'source' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW ] );
        $target          = filter_var( $this->request->param( 'target' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW ] );
        $status          = filter_var( $this->request->param( 'status' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW ] );
        $only_completed  = filter_var( $this->request->param( 'onlycompleted' ), FILTER_VALIDATE_BOOLEAN, [ 'flags' => FILTER_NULL_ON_FAILURE ] );
        $id_team         = filter_var( $this->request->param( 'id_team' ), FILTER_SANITIZE_NUMBER_INT );
        $id_assignee     = filter_var( $this->request->param( 'id_assignee' ), FILTER_SANITIZE_NUMBER_INT );
        $no_assignee     = filter_var( $this->request->param( 'no_assignee' ), FILTER_VALIDATE_BOOLEAN );

        $search_status = ( !empty( $status ) and Constants_JobStatus::isAllowedStatus( $status ) ) ? $status : Constants_JobStatus::STATUS_ACTIVE;
        $page          = ( !empty( $page ) ) ? (int)$page : 1;
        $step          = ( !empty( $step ) ) ? (int)$step : 10;
        $start         = ( $page - 1 ) * $step;

        if ( empty( $id_team ) ) {
            throw new InvalidArgumentException( "No id team provided", -1 );
        }

        return [
                'page'            => $page,
                'start'           => $start,
                'step'            => $step,
                'search_status'   => $search_status,
                'project_id'      => ( !empty( $project_id ) ) ? $project_id : null,
                'search_in_pname' => ( !empty( $search_in_pname ) ) ? $search_in_pname : null,
                'source'          => ( !empty( $source ) ) ? $source : null,
                'target'          => ( !empty( $target ) ) ? $target : null,
                'status'          => ( !empty( $status ) ) ? $status : null,
                'only_completed'  => $only_completed,
                'id_team'         => $id_team,
                'id_assignee'     => ( !empty( $id_assignee ) ) ? $id_assignee : null,
                'no_assignee'     => $no_assignee,
        ];
    }

    /**
     * @param TeamStruct $team
     * @param            $id_assignee
     *
     * @return Users_UserStruct|null
     * @throws Exception
     */
    private function filterAssignee( TeamStruct $team, $id_assignee ): ?Users_UserStruct {
        if ( is_null( $id_assignee ) ) {
            return null;
        }

        $dao         = new MembershipDao();
        $memberships = $dao->setCacheTTL( 60 * 60 * 24 )->getMemberListByTeamId( $team->id );

        /**
         * @var $users MembershipStruct[]
         */
        $users = array_values( array_filter( $memberships, function ( MembershipStruct $membership ) use ( $id_assignee ) {
            return $membership->getUser()->uid == $id_assignee;
        } ) );

        if ( empty( $users ) ) {
            throw new NotFoundException( 'Assignee not found in team' );
        }

        return $users[ 0 ]->getUser();
    }

    /**
     * @param $id_team
     *
     * @return TeamStruct|null
     * @throws Exception
     */
    private function filterTeam( $id_team ): ?TeamStruct {
        $dao  = new MembershipDao();
        $team = $dao->findTeamByIdAndUser( $id_team, $this->user );

        if ( !$team ) {
            throw  new NotFoundException( 'Team not found in user memberships', 404 );
        }

        return $team;
    }
}