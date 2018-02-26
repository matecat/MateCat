<?php

use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;
use \Analysis\Status;

class analyzeOldController extends viewController {

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
    protected $model;

    /**
     * @var Projects_ProjectStruct
     */
    public $project ;

    /**
     * @var Chunks_ChunkStruct
     */
    private $chunk ;

    /**
     * @var bool
     */
    private $project_not_found = false;

    protected $analyze_html = "analyze_old.html";
    protected $job_analysis_html = "jobAnalysis_old.html" ;

    protected $page_type ;

    public function __construct() {

        parent::sessionStart();
        parent::__construct( false );

        $filterArgs = array(
                'pid'      => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'jid'      => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'password' => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                )
        );

        $postInput = filter_input_array( INPUT_GET, $filterArgs );

        $this->pid = $postInput[ 'pid' ];
        $this->jid = $postInput[ 'jid' ];
        $pass      = $postInput[ 'password' ];

        $this->project = Projects_ProjectDao::findById( $this->pid, 60 * 60 );

        if ( !empty( $this->jid ) ) {
            // we are looking for a chunk
            $this->chunk = Chunks_ChunkDao::getByIdAndPassword($this->jid, $pass);

            parent::makeTemplate( $this->job_analysis_html  );
            $this->jpassword = $pass;
            $this->ppassword = $this->project->password;
            $this->page_type = 'job_analysis';

        } else {
            $this->project_not_found = $this->project->password != $pass;

            parent::makeTemplate( $this->analyze_html  );
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

        $this->featureSet->run('beginDoAction', $this, array(
                'project' => $this->project,  'page_type' => $this->page_type
        ));

        if ( !$this->project ) {
            $this->render404() ;
            return ;
        }

        $this->model = new Analysis_AnalysisModel( $this->project, $this->chunk );
        $this->model->loadData();

        $activity             = new ActivityLogStruct();
        $activity->id_job     = $this->jid;
        $activity->id_project = $this->pid;
        $activity->action     = ActivityLogStruct::ACCESS_ANALYZE_PAGE;
        $activity->ip         = Utils::getRealIpAddr();
        $activity->uid        = $this->logged_user->uid;
        $activity->event_date = date( 'Y-m-d H:i:s' );
        Activity::save( $activity );
        
    }

    public function setTemplateVars() {

        if( $this->project_not_found ){
            parent::makeTemplate( 'project_not_found.html' );
            $this->template->support_mail    = INIT::$SUPPORT_MAIL;
            return;
        }

        $this->template->jobs                       = $this->model->jobs;
        $this->template->json_jobs                  = json_encode($this->model->jobs);
        $this->template->fast_analysis_wc           = $this->model->fast_analysis_wc;
        $this->template->fast_analysis_wc_print     = $this->model->fast_analysis_wc_print;
        $this->template->tm_analysis_wc             = $this->model->tm_analysis_wc;
        $this->template->tm_analysis_wc_print       = $this->model->tm_analysis_wc_print;
        $this->template->standard_analysis_wc       = $this->model->standard_analysis_wc;
        $this->template->standard_analysis_wc_print = $this->model->standard_analysis_wc_print;
        $this->template->total_raw_word_count       = $this->model->total_raw_word_count;
        $this->template->total_raw_word_count_print = $this->model->total_raw_word_count_print;
        $this->template->pname                      = $this->model->pname;
        $this->template->pid                        = $this->model->pid;

        if ( empty( $this->jid ) ) {
            $this->template->project_password = $this->ppassword;
        } else {
            $this->template->project_password = $this->ppassword;
            $this->template->job_password =     $this->jpassword;
            $this->template->jid              = $this->jid;
        }

        $this->template->tm_wc_time                 = $this->model->tm_wc_time;
        $this->template->fast_wc_time               = $this->model->fast_wc_time;
        $this->template->tm_wc_unit                 = $this->model->tm_wc_unit;
        $this->template->fast_wc_unit               = $this->model->fast_wc_unit;
        $this->template->standard_wc_unit           = $this->model->standard_wc_unit;
        $this->template->raw_wc_time                = $this->model->raw_wc_time;
        $this->template->standard_wc_time           = $this->model->standard_wc_time;
        $this->template->raw_wc_unit                = $this->model->raw_wc_unit;
        $this->template->project_status             = $this->model->project_status;
        $this->template->num_segments               = $this->model->num_segments;
        $this->template->num_segments_analyzed      = $this->model->num_segments_analyzed;

        $this->template->logged_user                = ($this->logged_user !== false ) ? $this->logged_user->shortName() : "";
        $this->template->extended_user              = ($this->logged_user !== false ) ? trim( $this->logged_user->fullName() ) : "";
        $this->template->build_number               = INIT::$BUILD_NUMBER;
        $this->template->enable_outsource           = $this->featureSet->filter('filter_enable_outsource', INIT::$ENABLE_OUTSOURCE);

        $this->template->outsource_service_login    = $this->_outsource_login_API ;

        $this->template->support_mail    = INIT::$SUPPORT_MAIL;

        $langDomains = Langs_LanguageDomains::getInstance();
        $this->subject = $langDomains::getDisplayDomain($this->model->subject);  // subject is null !!??!?!?!
        $this->template->subject                    = $this->model->subject;

        //first two letter of code lang
        $project_data = $this->model->getProjectData()[ 0 ];

        $this->template->isCJK = false;

        if ( array_key_exists( explode( "-" , $project_data[ 'source' ] )[0], CatUtils::$cjk ) ) {
            $this->template->isCJK = true;
        }

        $this->template->isLoggedIn = $this->isLoggedIn();

        $this->__evalModalBoxForLogin();

        //url to which to send data in case of login
        $client                       = OauthClient::getInstance()->getClient();

        //perform check on running daemons and send a mail randomly
        $misconfiguration = Status::thereIsAMisconfiguration();
        if ( $misconfiguration && mt_rand( 1, 3 ) == 1 ) {
            $msg = "<strong>The analysis daemons seem not to be running despite server configuration.</strong><br />Change the application configuration or start analysis daemons.";
            Utils::sendErrMailReport( $msg, "Matecat Misconfiguration" );
        }

        $this->template->daemon_misconfiguration = var_export( $misconfiguration, true );

    }

    private function __evalModalBoxForLogin() {
        if (
            !$this->isLoggedIn() &&
            isset( $_SESSION[ 'last_created_pid' ] ) && $_SESSION['last_created_pid'] == $this->project->id
        ) {
            $this->template->showModalBoxLogin = true;
        } else {
            $this->template->showModalBoxLogin = false;
        }
    }

}
