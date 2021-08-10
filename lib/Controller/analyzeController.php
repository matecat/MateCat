<?php

use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;

class analyzeController extends viewController {

    /**
     * External EndPoint for outsource Login Service or for all in one login and Confirm Order
     *
     * If a login service exists, it can return a token authentication on the Success page,
     *
     * That token will be sent back to the review/confirm page on the provider website to grant it logged
     *
     * The success Page must be set in concrete subclass of "OutsourceTo_AbstractProvider"
     *  Ex: "OutsourceTo_Translated"
     *
     *
     * Values from quote result will be posted there anyway.
     *
     * @var string
     */
    protected $_outsource_login_API = '//signin.translated.net/';

    private $pid;
    private $jid;
    private $ppassword;
    private $jpassword;

    /**
     * @var Analysis_AnalysisModel
     */
    public $model;

    /**
     * @var Projects_ProjectStruct
     */
    public $project;

    /**
     * @var Chunks_ChunkStruct
     */
    private $chunk;

    /**
     * @var bool
     */
    private $project_not_found = false;

    protected $analyze_html      = "analyze.html";
    protected $job_analysis_html = "jobAnalysis.html";

    protected $page_type;

    public function __construct() {

        parent::sessionStart();
        parent::__construct( false );

        $filterArgs = [
                'pid'      => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'jid'      => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'password' => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ]
        ];

        $postInput = filter_input_array( INPUT_GET, $filterArgs );

        $this->pid = $postInput[ 'pid' ];
        $this->jid = $postInput[ 'jid' ];
        $pass      = $postInput[ 'password' ];

        $this->project = Projects_ProjectDao::findById( $this->pid, 60 * 60 );

        if ( !empty( $this->jid ) ) {

            // we are looking for a chunk
            $this->chunk = Chunks_ChunkDao::getByIdAndPassword( $this->jid, $pass );

            if ( $this->chunk->status_owner === Constants_JobStatus::STATUS_DELETED ) {
                $this->project_not_found = true;
            }

            parent::makeTemplate( $this->job_analysis_html );
            $this->jpassword = $pass;
            $this->ppassword = $this->project->password;
            $this->page_type = 'job_analysis';

        } else {

            $chunks = ( new Chunks_ChunkDao )->getByProjectID( $this->project->id );

            $notDeleted = array_filter( $chunks, function( $element ){
                return $element->status_owner != Constants_JobStatus::STATUS_DELETED;
            } );

            $this->project_not_found = $this->project->password != $pass || empty( $notDeleted );

            parent::makeTemplate( $this->analyze_html );
            $this->jid       = null;
            $this->jpassword = null;
            $this->ppassword = $pass;
            $this->page_type = 'project_analysis';
        }

        if ( $this->project ) {
            $this->featureSet->loadForProject( $this->project );
        }

    }


    public function doAction() {

        if ( $this->project_not_found ) {
            $this->render404();

            return;
        }

        $this->featureSet->run( 'beginDoAction', $this, [
                'project' => $this->project, 'page_type' => $this->page_type
        ] );

        $this->model = new Analysis_AnalysisModel( $this->project, $this->chunk );
        $this->model->loadData();

        $activity             = new ActivityLogStruct();
        $activity->id_job     = $this->jid;
        $activity->id_project = $this->pid;
        $activity->action     = ActivityLogStruct::ACCESS_ANALYZE_PAGE;
        $activity->ip         = Utils::getRealIpAddr();
        $activity->uid        = $this->user->uid;
        $activity->event_date = date( 'Y-m-d H:i:s' );
        Activity::save( $activity );

    }

    public function setTemplateVars() {

        if ( $this->project_not_found ) {
            parent::makeTemplate( 'project_not_found.html' );
            $this->template->support_mail = INIT::$SUPPORT_MAIL;

            return;
        }

        if ( empty( $this->jid ) ) {
            $this->template->project_password = $this->ppassword;
        } else {
            $this->template->project_password = $this->ppassword;
            $this->template->job_password     = $this->jpassword;
            $this->template->jid              = $this->jid;
        }

        $this->template->outsource_service_login = $this->_outsource_login_API;

        $this->__evalModalBoxForLogin();

        $this->decorator = new AnalyzeDecorator( $this, $this->template );
        $this->decorator->decorate();

        /**
         * This loading is forced for HTML customization purpose only
         * when a plugin in beginDoAction needs to customize the behaviour
         *
         * @see FeatureSet::forceAutoLoadFeatures()
         *
         */
        $this->featureSet->forceAutoLoadFeatures();

        $this->featureSet->appendDecorators(
                'AnalyzeDecorator',
                $this,
                $this->template
        );
    }

    private function __evalModalBoxForLogin() {
        if (
                !$this->isLoggedIn() &&
                isset( $_SESSION[ 'last_created_pid' ] ) && $_SESSION[ 'last_created_pid' ] == $this->project->id
        ) {
            $this->template->showModalBoxLogin = true;
        } else {
            $this->template->showModalBoxLogin = false;
        }
    }

}
