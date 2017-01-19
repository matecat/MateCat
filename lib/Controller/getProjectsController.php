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
    private $search_status = Constants_JobStatus::STATUS_ACTIVE;

    /**
     * @var bool
     */
    private $search_only_completed;

    /**
     * @var int
     */
    private $start;

    public function __construct() {

        //SESSION ENABLED
        parent::__construct();
        parent::checkLogin();

        $filterArgs = [
                'page'          => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'step'          => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'project'       => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'filter'        => [
                        'filter'  => FILTER_VALIDATE_BOOLEAN,
                        'options' => [ FILTER_NULL_ON_FAILURE ]
                ],
                'pn'            => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW
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
                ]
        ];

        $postInput = filter_input_array( INPUT_POST, $filterArgs );

        ( !empty( $postInput[ 'status' ] ) ? $this->search_status = $postInput[ 'status' ] : null );
        ( !empty( $postInput[ 'page' ] ) ? $this->page = (int)$postInput[ 'page' ] : null );
        ( !empty( $postInput[ 'step' ] ) ? $this->step = (int)$postInput[ 'step' ] : null );

        $this->start                 = ( $this->page - 1 ) * $this->step;
        $this->project_id            = $postInput[ 'project' ];
        $this->filter_enabled        = (bool)$postInput[ 'filter' ];
        $this->search_in_pname       = $postInput[ 'pn' ];
        $this->search_source         = $postInput[ 'source' ];
        $this->search_target         = $postInput[ 'target' ];

        $this->search_only_completed = $postInput[ 'onlycompleted' ];

    }

    public function doAction() {

        if( !$this->userIsLogged ){
            throw new Exception('User not Logged');
        }

        $projects = ManageUtils::queryProjects( $this->start, $this->step,
            $this->search_in_pname,
            $this->search_source, $this->search_target, $this->search_status,
            $this->search_only_completed, $this->filter_enabled, $this->project_id );

        $projnum = getProjectsNumber( $this->start, $this->step,
            $this->search_in_pname, $this->search_source,
            $this->search_target, $this->search_status,
            $this->search_only_completed, $this->filter_enabled );

        /**
         * pass projects in a filter to find associated reivew_password if needed.
         * Review password may be needed or not depending on the project. Some
         * projects may need a separate review password, others not. Even thought
         * the feature is disable for the given project, the password. Given this
         * recordset is paginated, it may be feasible to seek for a revision password
         * for each of them in a separate query.
         */

        $featureSet = FeatureSet::fromIdCustomer( $this->userMail );

        $projects = $featureSet->filter('filter_manage_projects_loaded', $projects);

        $this->result[ 'data' ]     = $projects;
        $this->result[ 'page' ]     = $this->page;
        $this->result[ 'pnumber' ]  = $projnum[ 0 ][ 'c' ];
        $this->result[ 'pageStep' ] = $this->step;
    }

}
