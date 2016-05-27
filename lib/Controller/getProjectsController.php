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
     * @var bool
     */
    private $filter_enabled;

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
                'filter'        => array( 'filter' => FILTER_VALIDATE_BOOLEAN,
                                          'options' => array( FILTER_NULL_ON_FAILURE ) ),
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
        $this->filter_enabled       = (int) $postInput[ 'filter' ];
        $this->search_in_pname      = (string) $postInput[ 'pn' ];
        $this->search_source        = (string) $postInput[ 'source' ];
        $this->search_target        = (string) $postInput[ 'target' ];
        $this->search_status        = (string) $postInput[ 'status' ];
        $this->search_onlycompleted = $postInput[ 'onlycompleted' ];
    }

    public function doAction() {

        $start = ( ( $this->page - 1 ) * $this->step );

        if( empty($_SESSION['cid']) ){
            throw new Exception('User not Logged');
        }

        $projects = ManageUtils::queryProjects( $start, $this->step,
            $this->search_in_pname,
            $this->search_source, $this->search_target, $this->search_status,
            $this->search_onlycompleted, $this->filter_enabled, $this->project_id );

        $projnum = getProjectsNumber( $start, $this->step,
            $this->search_in_pname, $this->search_source,
            $this->search_target, $this->search_status,
            $this->search_onlycompleted, $this->filter_enabled );

        /**
         * pass projects in a filter to find associated reivew_password if needed.
         * Review password may be needed or not depending on the project. Some
         * projects may need a separate review password, others not. Even thought
         * the feature is disable for the given project, the password. Given this
         * recordset is paginated, it may be feasible to seek for a revision password
         * for each of them in a separate query.
         */

        $featureSet = FeatureSet::fromIdCustomer( $_SESSION['cid'] );

        $projects = $featureSet->filter('filter_manage_projects_loaded', $projects);

        $this->result[ 'data' ]     = json_encode( $projects );
        $this->result[ 'page' ]     = $this->page;
        $this->result[ 'pnumber' ]  = $projnum[ 0 ][ 'c' ];
        $this->result[ 'pageStep' ] = $this->step;
    }


    public function cmp( $a, $b ) {
        return strcmp( $a[ "id" ], $b[ "id" ] );
    }

}
