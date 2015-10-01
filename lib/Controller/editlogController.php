<?php

/**
 * Description of catController
 *
 * @author antonio
 */
class editlogController extends viewController {

    private $jid = "";
    private $project_status = "";
    private $thisUrl;

    private $job_archived = false;
    private $job_owner_email;
    private $jobData;
    private $job_stats;
    private $data;
    private $languageStatsData;

    //number of percentage point under which the post editing effor evaluation is still accepted;
    const PEE_THRESHOLD = 1;

    public function __construct() {
        parent::__construct();
        parent::makeTemplate( "editlog.html" );

        $filterArgs = array(
                'jid'      => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'password' => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
        );

        $__postInput = filter_input_array( INPUT_GET, $filterArgs );

        $this->jid      = $__postInput[ "jid" ];
        $this->password = $__postInput[ "password" ];
        $this->thisUrl  = $_SERVER[ 'REQUEST_URI' ];

    }

    public function doAction() {

        $this->generateAuthURL();

        $model = new EditLog_EditLogModel();
        $model -> setJid($this->jid);
        $model -> setPassword( $this->password );

        $model -> controllerDoAction();

        $this->job_stats = $model->getJobStats();
        $this->stats = $model->getStats();
        $this->data = $model->getData();

    }

    public function setTemplateVars() {

        $this->template->job_archived = ( $this->job_archived ) ? INIT::JOB_ARCHIVABILITY_THRESHOLD : '';
        $this->template->owner_email  = $this->job_owner_email;

        $this->template->jid         = $this->jid;
        $this->template->password    = $this->password;
        $this->template->data        = $this->data;
        $this->template->stats       = $this->stats;
        $this->template->pname       = $this->data[ 0 ][ 'pname' ];
        $this->template->source_code = $this->data[ 0 ][ 'source_lang' ];
        $this->template->target_code = $this->data[ 0 ][ 'target_lang' ];

        /*
         * Evaluate TTE and set it in the template
         * NOTICE:  TTE is assigned to a variable because it's needed in future evaluations.
         *          Otherwise it wouldn't be accessible
         */
        $__overallTTE = round(
            $this->languageStatsData->total_time_to_edit / (1000 * $this->languageStatsData->total_wordcount ),
            2
        );
        $this->template->overall_tte = $__overallTTE;

        /*
        * Evaluate PEE and set it in the template
        * NOTICE:  PEE is assigned to a variable because it's needed in future evaluations.
        *          Otherwise it wouldn't be accessible
        */
        $__overallPEE = round(
            $this->languageStatsData->total_postediting_effort / ( $this->languageStatsData->job_count ),
            2
        );
        $this->template->overall_pee = $__overallPEE;

        $this->template->pee_slow  = false;
        $this->template->tte_fast  = false;

        if($this->stats['total-tte-seconds'] < $__overallTTE ) {
            $this->template->tte_fast = true;
        }

        if($this->stats['avg-pee'] + self::PEE_THRESHOLD < $__overallPEE ){
            $this->template->pee_slow  = true;
        }

        $this->job_stats[ 'STATUS_BAR_NO_DISPLAY' ] = ( $this->project_status[ 'status_analysis' ] == Constants_ProjectStatus::STATUS_DONE ? '' : 'display:none;' );
        $this->job_stats[ 'ANALYSIS_COMPLETE' ]     = ( $this->project_status[ 'status_analysis' ] == Constants_ProjectStatus::STATUS_DONE ? true : false );
        $this->template->job_stats                  = $this->job_stats;

        $this->template->showDQF = ( INIT::$DQF_ENABLED && !empty( $this->jobData[ 'dqf_key' ] ) );

        $this->template->build_number  = INIT::$BUILD_NUMBER;
        $this->template->extended_user = trim( $this->logged_user[ 'first_name' ] . " " . $this->logged_user[ 'last_name' ] );
        $this->template->logged_user   = $this->logged_user[ 'short' ];
        $this->template->incomingUrl   = '/login?incomingUrl=' . $this->thisUrl;
        $this->template->authURL       = $this->authURL;

        $this->template->jobOwnerIsMe        = ( $this->logged_user[ 'email' ] == $this->jobData['owner'] );
    }

}


