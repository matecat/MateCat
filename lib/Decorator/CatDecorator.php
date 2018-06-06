<?php

use Jobs\JobStatsStruct;
use LexiQA\LexiQADecorator;

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

    private $lang_handler ;

    public function __construct( catController $controller, PHPTAL $template ) {

        $this->controller     = $controller;
        $this->template       = $template;
        $this->job            = $this->controller->getChunk();
        $this->jobStatsStruct = new JobStatsStruct( $this->controller->getJobStats() );

        $this->isGDriveProject = $controller->isCurrentProjectGDrive();

        $this->lang_handler = Langs_Languages::getInstance();
    }

    public function decorate() {
        $this->template->isReview                         = $this->controller->isRevision();
        $this->template->header_quality_report_item_class = '';
        $this->template->review_password                  = $this->controller->getReviewPassword();

        $this->template->header_main_button_enabled = true;
        $this->template->header_main_button_label   = $this->getHeaderMainButtonLabel();
        $this->template->header_main_button_id      = 'downloadProject';

        $this->template->isCJK = false;

        if( $this->jobStatsStruct->isCompleted() && $this->jobStatsStruct->isAllApproved() ){
            $this->template->header_main_button_class = 'downloadtr-button approved';
        } elseif( $this->jobStatsStruct->isCompleted() ) {
            $this->template->header_main_button_class = 'downloadtr-button translated';
        } else {
            $this->template->header_main_button_class = 'downloadtr-button draft';
        }

        $this->template->segmentFilterEnabled = true;

        $this->template->status_labels = json_encode( $this->getStatusLabels() );

        if ( $this->controller->isRevision() ) {
            $this->decorateForRevision();
        } else {
            $this->decorateForTranslate();
        }

        $this->setQualityReportHref();

        $this->template->searchable_statuses = $this->searchableStatuses();
        $this->template->project_type        = null;

        $this->template->remoteFilesInJob = array();

        if ( $this->isGDriveProject ) {
            $files = array_map(function( $item ) {
                return $item->attributes(array('id'));
            }, RemoteFiles_RemoteFileDao::getByJobId( $this->job->id ) );

            $this->template->remoteFilesInJob = $files ;
        }

        $this->template->support_mail = INIT::$SUPPORT_MAIL ;
        $this->template->showReplaceOptionsInSearch = true ;

        $this->template->languages_array = json_encode(  $this->lang_handler->getEnabledLanguages( 'en' ) ) ;

        $this->decorateForCJK();

        $this->assignOptions();

        $this->template->chunk_completion_undoable = true ;
        $this->template->translation_matches_enabled = true ;
        $this->template->allow_link_to_analysis = true ;
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
            $label = 'PREVIEW IN GOOGLE DRIVE';
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

        //show Tag Projection
        $this->template->show_tag_projection = true;

        $this->template->tag_projection_enabled = $chunk_options_model->isEnabled('tag_projection')   ; 
        $this->template->speech2text_enabled = $chunk_options_model->isEnabled( 'speech2text' ) ;

        LexiQADecorator::getInstance( $this->template )->checkJobHasLexiQAEnabled( $chunk_options_model )->decorateViewLexiQA();

        $this->template->segmentation_rule = @$chunk_options_model->project_metadata[ 'segmentation_rule' ];
        $this->template->tag_projection_languages = json_encode( ProjectOptionsSanitizer::$tag_projection_allowed_languages );

    }

    private function decorateForCJK() {

        //check if language belongs to supported right-to-left languages ( decorate the HTML )
        $lang_handler = Langs_Languages::getInstance();
        $this->template->source_rtl = ( $lang_handler->isRTL( $this->controller->source_code ) ) ? ' rtl-source' : '';
        $this->template->target_rtl = ( $lang_handler->isRTL( $this->controller->target_code ) ) ? ' rtl-target' : '';

        //check if it is a composite language, for cjk check that accepts only ISO 639 code
        //check if cjk
        if ( array_key_exists( explode( '-', $this->controller->target_code )[0], CatUtils::$cjk ) ) {
//            $this->template->taglockEnabled = 0;
            $this->template->targetIsCJK = var_export( true, true ); //config.js -> editArea is a CJK lang?
        } else {
            $this->template->targetIsCJK = var_export( false, true );
        }

        //check if it is a composite language, for cjk check that accepts only ISO 639 code
        //check if cjk
        if ( array_key_exists( explode( '-', $this->controller->source_code )[0] , CatUtils::$cjk ) ) {
            $this->template->isCJK = true; // show "Characters" instead of "Words" ( Footer )
        } else {
            $this->template->isCJK = false;
        }

    }

}
