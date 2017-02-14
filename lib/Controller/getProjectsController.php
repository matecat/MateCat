<?php
use Organizations\OrganizationDao;

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

    private $id_organization ;
    private $id_assignee ;
    private $id_workspace ;

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
                'id_organization' => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'id_assignee' => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'id_workspace' => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
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
        $this->id_organization       = $postInput[ 'id_organization' ];
        $this->id_assignee           = $postInput[ 'id_assignee' ];
        $this->id_workspace          = $postInput[ 'id_workspace' ];

        $this->search_only_completed = $postInput[ 'onlycompleted' ];

    }

    public function doAction() {

        if( !$this->userIsLogged ){
            throw new Exception('User not Logged');
        }

        $organization = $this->filterOrganization();
        $workspace = $this->filterWorkspace($organization);
        $assignee = $this->filterAssignee($organization);

        $projects = ManageUtils::queryProjects( $this->logged_user, $this->start, $this->step,
            $this->search_in_pname,
            $this->search_source, $this->search_target, $this->search_status,
            $this->search_only_completed, $this->project_id,
            $organization, $workspace, $assignee
        );

        $projnum = getProjectsNumber( $this->logged_user,
            $this->search_in_pname, $this->search_source,
            $this->search_target, $this->search_status,
            $this->search_only_completed, $organization );

        $projects = $this->filterProjectsWithUserFeatures( $projects ) ;

        $projects = $this->filterProjectsWithProjectFeatures( $projects ) ;

        $this->result[ 'data' ]     = $projects;
        $this->result[ 'page' ]     = $this->page;
        $this->result[ 'pnumber' ]  = $projnum[ 0 ][ 'c' ];
        $this->result[ 'pageStep' ] = $this->step;
    }

    private function filterProjectsWithUserFeatures( $projects ) {
        $featureSet = new FeatureSet() ;
        $featureSet->loadFromUserEmail( $this->logged_user->email ) ;
        $projects = $featureSet->filter('filter_manage_projects_loaded', $projects);
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
     * @param $organization
     * @return Users_UserStruct
     * @throws Exception
     */

    private function filterAssignee($organization) {
        if ( is_null($this->id_assignee ) ) return ;

        $dao = new \Organizations\MembershipDao();
        $memberships = $dao->getMemberListByOrganizationId($organization);
        $id_assignee = $this->id_assignee ;

        $users = array_filter($memberships, function( \Organizations\MembershipStruct $membership ) use ( $id_assignee ) {
            return $membership->getUser()->uid == $id_assignee ;
        } );

        if ( empty( $users ) ) {
            throw new Exception('Assignee not found in organization') ;
        }

        return $users[0] ;
    }

    /**
     * @param $organization
     * @return \Organizations\WorkspaceStruct
     * @throws Exception
     */
    private function filterWorkspace($organization) {
        if ( is_null($this->id_organization ) ) return ;

        $dao = new \Organizations\WorkspaceDao() ;
        $workspaces = $dao->getByOrganizationId($organization->id) ;
        $id_workspace = $this->id_workspace ;
        $wp = array_filter($workspaces, function($workspace) use ( $id_workspace ) {
            return $id_workspace == $workspace->id ;
        });

        if ( empty( $wp ) ) {
            throw new Exception('Workspace not found in organization') ;
        }

        return $wp[0];
    }

    private function filterOrganization() {
        $dao = new \Organizations\MembershipDao() ;
        $organization = $dao->findOrganizationByIdAndUser($this->id_organization, $this->logged_user ) ;
        if ( !$organization ) {
            throw  new Exception('Organization not found in user memberships') ;
        }
        else {
            return $organization ;
        }
    }
}
