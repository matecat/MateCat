<?php
use Exceptions\NotFoundError;
use Teams\MembershipDao;
use Teams\MembershipStruct;
use \API\V2\Json\Error;


/**
 * Description of manageController
 *
 * @author andrea
 */
class getProjectsController extends ajaxController {

    /**
     * @var int
     */
    private $page = 1;

    /**
     * @var int
     */
    private $step = 10;

    /**
     * @var bool
     */
    private $project_id;

    /**
     * @var string|bool
     */
    private $search_in_pname;

    /**
     * @var string|bool
     */
    private $search_source;

    /**
     * @var string|bool
     */
    private $search_target;

    /**
     * @var string
     */
    private $search_status = Constants_JobStatus::STATUS_ACTIVE;

    /**
     * @var bool
     */
    private $search_only_completed;

    /**
     * @var int
     */
    private $start;

    private $id_team ;
    private $id_assignee ;

    private $no_assignee ;

    public function __construct() {

        //SESSION ENABLED
        parent::__construct();
        parent::checkLogin();

        $filterArgs = [
                'page'          => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'step'          => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'project'       => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'pn'            => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW
                ],
                'source'        => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW
                ],
                'target'        => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW
                ],
                'status'        => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW
                ],
                'onlycompleted' => [
                        'filter'  => FILTER_VALIDATE_BOOLEAN,
                        'options' => [ FILTER_NULL_ON_FAILURE ]
                ],
                'id_team' => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'id_assignee' => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],

                'no_assignee' => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],

        ];

        $postInput = filter_input_array( INPUT_POST, $filterArgs );

        ( !empty( $postInput[ 'status' ] ) && Constants_JobStatus::isAllowedStatus( $postInput[ 'status' ] ) ? $this->search_status = $postInput[ 'status' ] : null );
        ( !empty( $postInput[ 'page' ] ) ? $this->page = (int)$postInput[ 'page' ] : null );
        ( !empty( $postInput[ 'step' ] ) ? $this->step = (int)$postInput[ 'step' ] : null );

        $this->start                 = ( $this->page - 1 ) * $this->step;
        $this->project_id            = $postInput[ 'project' ];
        $this->search_in_pname       = $postInput[ 'pn' ];
        $this->search_source         = $postInput[ 'source' ];
        $this->search_target         = $postInput[ 'target' ];
        $this->id_team               = $postInput[ 'id_team' ];
        $this->id_assignee           = $postInput[ 'id_assignee' ];

        $this->no_assignee           = $postInput[ 'no_assignee' ];

        $this->search_only_completed = $postInput[ 'onlycompleted' ];

    }

    public function doAction() {

        if ( !$this->userIsLogged ) {
            $this->result = ( new Error( [ new Exception( 'User not Logged', 401 ) ] ) )->render();
            return;
        }

        $this->featureSet->loadFromUserEmail( $this->logged_user->email ) ;

        try {
            $team = $this->filterTeam();
        } catch( NotFoundError $e ){
            $this->result = ( new Error( [ $e ] ) )->render();
            return;
        }

        if( $team->type == Constants_Teams::PERSONAL ){
            $assignee = $this->logged_user;
            $team = null;
        } else {
            $assignee = $this->filterAssignee( $team );
        }

        $projects = ManageUtils::queryProjects( $this->logged_user, $this->start, $this->step,
            $this->search_in_pname,
            $this->search_source, $this->search_target, $this->search_status,
            $this->search_only_completed, $this->project_id,
            $team, $assignee,
            $this->no_assignee
        );

        $projnum = getProjectsNumber( $this->logged_user,
            $this->search_in_pname, $this->search_source,
            $this->search_target, $this->search_status,
            $this->search_only_completed,
            $team, $assignee,
            $this->no_assignee
            );

        $projects = $this->filterProjectsWithUserFeatures( $projects ) ;

        $projects = $this->filterProjectsWithProjectFeatures( $projects ) ;

        $this->result[ 'data' ]     = $projects;
        $this->result[ 'page' ]     = $this->page;
        $this->result[ 'pnumber' ]  = $projnum[ 0 ][ 'c' ];
        $this->result[ 'pageStep' ] = $this->step;
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

    /**
     * @param $team
     *
     * @return Users_UserStruct
     * @throws Exception
     */

    private function filterAssignee( $team ) {

        if ( is_null( $this->id_assignee ) ) {
            return null;
        }

        $dao         = new MembershipDao();
        $memberships = $dao->setCacheTTL( 60 * 60 * 24 )->getMemberListByTeamId( $team->id );
        $id_assignee = $this->id_assignee;
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

    private function filterTeam() {
        $dao = new MembershipDao() ;
        $team = $dao->findTeamByIdAndUser($this->id_team, $this->logged_user ) ;
        if ( !$team ) {
            throw  new NotFoundError( 'Team not found in user memberships', 404 ) ;
        }
        else {
            return $team ;
        }
    }
}
