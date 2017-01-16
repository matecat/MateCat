<?php
include_once INIT::$UTILS_ROOT . "/manage.class.php";

/**
 * Description of manageController
 *
 * @author andrea
 */
class getProjectsController extends ajaxController {

    /**
     * @var Langs_Languages
     */
    private $lang_handler;

    /**
     * @var int
     */
    private $page;

    /**
     * @var int
     */
    private $step;

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
    private $search_status;

    /**
     * @var bool
     */
    private $search_onlycompleted;

    /**
     * @var int
     */
    private $notAllCancelled = 0;

    public function __construct() {

        //SESSION ENABLED
        parent::sessionStart();
        parent::__construct();

        $filterArgs = array(
                'page'          => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'step'          => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'project'       => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'pn'            => array( 'filter'  => FILTER_SANITIZE_STRING,
                                          'flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW
                ),
                'source'        => array( 'filter'  => FILTER_SANITIZE_STRING,
                                          'flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW
                ),
                'target'        => array( 'filter'  => FILTER_SANITIZE_STRING,
                                          'flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW
                ),
                'status'        => array( 'filter'  => FILTER_SANITIZE_STRING,
                                          'flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW
                ),
                'onlycompleted' => array( 'filter' => FILTER_VALIDATE_BOOLEAN,
                                          'options' => array( FILTER_NULL_ON_FAILURE )
                )
        );

        $postInput = filter_input_array( INPUT_POST, $filterArgs );

        // assigning default values
        if ( is_null( $postInput[ 'page' ] ) || empty( $postInput[ 'page' ] ) ) {
            $postInput[ 'page' ] = 1;
        }
        if ( is_null( $postInput[ 'step' ] ) || empty( $postInput[ 'step' ] ) ) {
            $postInput[ 'step' ] = 25;
        }

        if ( is_null( $postInput[ 'status' ] ) || empty( $postInput[ 'status' ] ) ) {
            $postInput[ 'status' ] = Constants_JobStatus::STATUS_ACTIVE;
        }

        $this->lang_handler = Langs_Languages::getInstance();
        $this->page                 = (int) $postInput[ 'page' ];
        $this->step                 = (int) $postInput[ 'step' ];
        $this->project_id           = $postInput[ 'project' ];
        $this->search_in_pname      = (string) $postInput[ 'pn' ];
        $this->search_source        = (string) $postInput[ 'source' ];
        $this->search_target        = (string) $postInput[ 'target' ];
        $this->search_status        = (string) $postInput[ 'status' ];
        $this->search_onlycompleted = $postInput[ 'onlycompleted' ];
    }

    public function doAction() {
        $this->checkLogin( FALSE ) ;

        if (! $this->userIsLogged ) {
            throw new Exception('User not Logged');
        }

        $team = Users_UserDao::findDefaultTeam( $this->logged_user );

        $start = ( ( $this->page - 1 ) * $this->step );

        $projects = ManageUtils::queryProjects( $this->logged_user, $start, $this->step,
            $this->search_in_pname,
            $this->search_source, $this->search_target, $this->search_status,
            $this->search_onlycompleted, $this->project_id,
            $team
        );

        $projnum = getProjectsNumber( $this->logged_user,
            $this->search_in_pname, $this->search_source,
            $this->search_target, $this->search_status,
            $this->search_onlycompleted, $team );


        $projects = $this->filterProjectsWithUserFeatures( $projects ) ;

        $projects = $this->filterProjectsWithProjectFeatures( $projects ) ;

        $this->result[ 'data' ]     = json_encode( $projects );
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

}
