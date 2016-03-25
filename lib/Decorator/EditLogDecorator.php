<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 01/10/15
 * Time: 13.09
 */
class EditLogDecorator extends AbstractDecorator {

    public function decorate() {
        /**
         * @var $controller editlogController
         */
        $controller = $this->controller;

        /**
         * @var $model EditLog_EditLogModel
         */
        $model = $controller->getModel();

        $this->template->job_archived = ( $model->isJobArchived() ) ? INIT::JOB_ARCHIVABILITY_THRESHOLD : '';
        $this->template->owner_email  = $model->getJobOwnerEmail();
        $this->template->emptyJob     = $model->isJobEmpty();
        /**
         * @var $data EditLog_EditLogSegmentClientStruct[]
         */
        $data           = $model->getData();
        $pagination     = $model->getPagination();
        $stats          = $model->getStats();
        $job_stats      = $model->getJobStats();
        $project_status = $model->getProjectStatus();
        $jobData        = $model->getJobData();

        $this->template->pid         = $model->getProjectId();
        $this->template->jid         = $model->getJid();
        $this->template->password    = $model->getPassword();
        $this->template->data        = $data;
        $this->template->stats       = $stats;
        $this->template->pagination  = $pagination;
        $this->template->pname       = $data[ 0 ][ 'proj_name' ];
        $this->template->source_code = $data[ 0 ][ 'job_source' ];
        $this->template->target_code = $data[ 0 ][ 'job_target' ];

        $this->template->overall_tte = $model->evaluateOverallTTE();
        $this->template->overall_pee = $model->evaluateOverallPEE();
        //FIXME: temporarily disabled
        $this->template->pee_slow    = false;// $model->isPEEslow();
        $this->template->tte_fast    = $model->isTTEfast();

        $job_stats[ 'STATUS_BAR_NO_DISPLAY' ] = ( $project_status[ 'status_analysis' ] == Constants_ProjectStatus::STATUS_DONE ? '' : 'display:none;' );
        $job_stats[ 'ANALYSIS_COMPLETE' ]     = ( $project_status[ 'status_analysis' ] == Constants_ProjectStatus::STATUS_DONE ? true : false );
        $this->template->job_stats            = $job_stats;

        $this->template->showDQF = ( INIT::$DQF_ENABLED && !empty( $jobData[ 'dqf_key' ] ) );

        $loggedUser = $controller->getLoggedUser();

        $this->template->extended_user = "";
        $this->template->logged_user   = "";
        $this->template->jobOwnerIsMe  = false;

        if ( !empty( $loggedUser ) ) {
            $this->template->extended_user = trim( $loggedUser->fullName() );
            $this->template->logged_user   = $loggedUser->shortName();
            $this->template->jobOwnerIsMe  = ( $loggedUser->email == $jobData[ 'owner' ] );
        }

        $this->template->build_number = INIT::$BUILD_NUMBER;
        $this->template->incomingUrl  = '/login?incomingUrl=' . $controller->getThisUrl();
        $this->template->authURL      = $controller->getAuthUrl();


    }
}