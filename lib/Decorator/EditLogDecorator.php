<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 01/10/15
 * Time: 13.09
 */
class EditLogDecorator {
    private $controller;
    private $template;

    public function __construct( editlogController $controller, PHPTAL $template ) {
        $this->controller = $controller;
        $this->template   = $template;
    }

    public function decorate() {
        /**
         * @var $model EditLog_EditLogModel
         */
        $model = $this->controller->getModel();

        $this->template->job_archived = ( $model->isJobArchived() ) ? INIT::JOB_ARCHIVABILITY_THRESHOLD : '';
        $this->template->owner_email  = $model->getJobOwnerEmail();
        $this->template->emptyJob = $model->isJobEmpty();
        /**
         * @var $data EditLog_EditLogSegmentClientStruct[]
         */
        $data           = $model->getData();
        $pagination     = $model->getPagination();
        $stats          = $model->getStats();
        $job_stats      = $model->getJobStats();
        $project_status = $model->getProjectStatus();
        $jobData        = $model->getJobData();

        $this->template->jid         = $model->getJid();
        $this->template->password    = $model->getPassword();
        $this->template->data        = $data;
        $this->template->stats       = $stats;
        $this->template->pagination  = $pagination;
        $this->template->pname       = $data[ 0 ][ 'pname' ];
        $this->template->source_code = $data[ 0 ][ 'job_source' ];
        $this->template->target_code = $data[ 0 ][ 'job_target' ];

        $this->template->overall_tte = $model->evaluateOverallTTE();
        $this->template->overall_pee = $model->evaluateOverallPEE();
        $this->template->pee_slow    = $model->isPEEslow();
        $this->template->tte_fast    = $model->isTTEfast();

        $job_stats[ 'STATUS_BAR_NO_DISPLAY' ] = ( $project_status[ 'status_analysis' ] == Constants_ProjectStatus::STATUS_DONE ? '' : 'display:none;' );
        $job_stats[ 'ANALYSIS_COMPLETE' ]     = ( $project_status[ 'status_analysis' ] == Constants_ProjectStatus::STATUS_DONE ? true : false );
        $this->template->job_stats            = $job_stats;

        $this->template->showDQF = ( INIT::$DQF_ENABLED && !empty( $jobData[ 'dqf_key' ] ) );

        $loggedUser = $this->controller->getLoggedUser();

        $this->template->build_number  = INIT::$BUILD_NUMBER;
        $this->template->extended_user = trim( $loggedUser->fullName() );
        $this->template->logged_user   = $loggedUser->shortName();
        $this->template->incomingUrl   = '/login?incomingUrl=' . $this->controller->getThisUrl();
        $this->template->authURL       = $this->controller->getAuthUrl();

        $this->template->jobOwnerIsMe = ( $loggedUser->email == $jobData[ 'owner' ] );

    }
}