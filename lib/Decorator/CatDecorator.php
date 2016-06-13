<?php

class CatDecorator {

    private $controller;

    /**
     * @var PHPTALWithAppend
     */
    private $template;

    /**
     * @var Chunks_ChunkStruct
     */
    private $job;

    private $review_type;

    /**
     * @var JobStatsStruct
     */
    private $jobStatsStruct;

    private $isGDriveProject ;

    public function __construct( catController $controller, PHPTAL $template ) {

        $this->controller     = $controller;
        $this->template       = $template;
        $this->job            = $this->controller->getJob();
        $this->jobStatsStruct = new JobStatsStruct( $this->controller->getJobStats() );

        $this->isGDriveProject = $controller->isCurrentProjectGDrive();
    }

    public function decorate() {
        $this->template->footer_js = array();
        $this->template->css_resources = array();

        $this->template->isReview                         = $this->controller->isRevision();
        $this->template->header_quality_report_item_class = '';
        $this->template->review_password                  = $this->controller->getReviewPassword();

        $this->template->header_main_button_enabled = true;
        $this->template->header_main_button_label   = $this->getHeaderMainButtonLabel();
        $this->template->header_main_button_id      = 'downloadProject';

        if( $this->jobStatsStruct->isCompleted() && $this->jobStatsStruct->isAllApproved() ){
            $this->template->header_main_button_class = 'downloadtr-button approved';
        } elseif( $this->jobStatsStruct->isCompleted() ) {
            $this->template->header_main_button_class = 'downloadtr-button translated';
        } else {
            $this->template->header_main_button_class = 'downloadtr-button draft';
        }

        $this->template->segmentFilterEnabled = false;

        $this->template->status_labels = json_encode( $this->getStatusLabels() );

        if ( $this->controller->isRevision() ) {
            $this->decorateForRevision();
        } else {
            $this->decorateForTranslate();
        }

        $this->template->searchable_statuses = $this->searchableStatuses();
        $this->template->project_type        = null;

        $this->template->remoteFilesInJob = array();

        if ( $this->isGDriveProject ) {
            $files = array_map(function( $item ) {
                return $item->attributes(array('id'));
            }, RemoteFiles_RemoteFileDao::getByJobId( $this->job->id ) );

            $this->template->remoteFilesInJob = $files ;
        }

        $this->template->showReplaceOptionsInSearch = true ;
        
        
        $this->assignOptions(); 
        


    }

    /**
     * @return array
     */
    private function searchableStatuses() {
        $statuses = array_merge(
                Constants_TranslationStatus::$INITIAL_STATUSES,
                Constants_TranslationStatus::$TRANSLATION_STATUSES,
                Constants_TranslationStatus::$REVISION_STATUSES
        );

        return array_map( function ( $item ) {
            return (object)array( 'value' => $item, 'label' => $item );
        }, $statuses );
    }

    private function getStatusLabels() {
        return array(
                Constants_TranslationStatus::STATUS_NEW        => 'New',
                Constants_TranslationStatus::STATUS_DRAFT      => 'Draft',
                Constants_TranslationStatus::STATUS_TRANSLATED => 'Translated',
                Constants_TranslationStatus::STATUS_APPROVED   => 'Approved',
                Constants_TranslationStatus::STATUS_REJECTED   => 'Rejected',
                Constants_TranslationStatus::STATUS_FIXED      => 'Fixed',
                Constants_TranslationStatus::STATUS_REBUTTED   => 'Rebutted'
        );
    }

  private function getHeaderMainButtonLabel() {
      $label = '';

      if ( $this->jobStatsStruct->isDownloadable() ) {
          if($this->isGDriveProject) {
            $label = 'OPEN IN GOOGLE DRIVE';
          } else {
            $label = 'DOWNLOAD TRANSLATION';
          }
      } else {
          if($this->isGDriveProject) {
            $label = 'PREVIEW ON GOOGLE DRIVE';
          } else {
            $label = 'PREVIEW';
          }
      }

      return $label;
  }

    private function decorateForRevision() {
        $this->template->footer_show_revise_link    = false;
        $this->template->footer_show_translate_link = true;
        $this->template->review_class               = 'review';
        $this->template->review_type                = 'simple';

        // TODO: move this logic in javascript QualityReportButton component
        if ( $this->controller->getQaOverall() == 'fail' ||
                $this->controller->getQaOverall() == 'poor'
        ) {
            $this->template->header_quality_report_item_class = 'hide';
        }

        $this->setQualityReportHref();

    }

    private function decorateForTranslate() {
        $this->template->footer_show_revise_link    = true;
        $this->template->footer_show_translate_link = false;
        $this->template->review_class               = '';
        $this->template->review_type                = 'simple';
    }

    private function setQualityReportHref() {
        $this->template->quality_report_href =
                INIT::$BASEURL . "revise-summary/{$this->job->id}-{$this->job->password}";
    }

    private function assignOptions() {
        $chunk_options_model = new ChunkOptionsModel( $this->job ) ; 
        
        $this->template->tag_projection_enabled = $chunk_options_model->isEnabled('speech2text')   ; 
        $this->template->speech2text_enabled = $chunk_options_model->isEnabled( 'speech2text' ) ; 
        $this->template->lxq_enabled = $chunk_options_model->isEnabled( 'lexiqa' ) ; 
    }

}
