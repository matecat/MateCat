<?php

use Jobs\JobStatsStruct;
use LexiQA\LexiQADecorator;

class CatDecorator extends \AbstractDecorator {

    /**
     * @var catController
     */
    protected $controller;

    /**
     * @var PHPTALWithAppend
     */
    protected $template;

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
        $this->template->pageTitle                        = $this->_buildPageTitle();
        $this->template->revisionNumber                   = $this->controller->getRevisionNumber();
        $this->template->isReview                         = $this->controller->getRevisionNumber() > 0  ;

        $this->template->header_quality_report_item_class = '';

        $this->template->header_main_button_enabled = true;
        $this->template->header_main_button_label   = $this->getHeaderMainButtonLabelNew();
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

        $this->assignCatDecorator();

        $this->setQualityReportHref();

        $this->template->searchable_statuses = $this->searchableStatuses();
        $this->template->project_type        = null;

        $this->template->remoteFilesInJob = array();

        if ( $this->isGDriveProject ) {
            $files = array_map(function( $item ) {
                return $item->toArray( [ 'id' ] );
            }, RemoteFiles_RemoteFileDao::getByJobId( $this->job->id ) );

            $this->template->remoteFilesInJob = $files ;
        }

        $this->template->support_mail = INIT::$SUPPORT_MAIL ;
        $this->template->showReplaceOptionsInSearch = true ;

        $this->template->languages_array = json_encode(  $this->lang_handler->getEnabledLanguages( 'en' ) ) ;
        //languages for tm panel
        $this->template->languages_array_obj = $this->lang_handler->getEnabledLanguages( 'en' ) ;
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
                [
                    Constants_TranslationStatus::STATUS_APPROVED,
                ]
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

    private function getHeaderMainButtonLabelNew() {
        $label = '';

        if ( $this->jobStatsStruct->isDownloadable() ) {
            if($this->isGDriveProject) {
                $label = 'Open in Google Drive';
            } else {
                $label = 'Download Translation';
            }
        } else {
            if($this->isGDriveProject) {
                $label = 'Preview in Google Drive';
            } else {
                $label = 'Draft';
            }
        }

        return $label;
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
        $isSourceRTL = $lang_handler->isRTL( $this->controller->source_code );
        $isTargetRTL = $lang_handler->isRTL( $this->controller->target_code );
        $this->template->isTargetRTL = $isTargetRTL;
        $this->template->isSourceRTL = $isSourceRTL;
        $this->template->source_rtl = $isSourceRTL ? ' rtl-source' : '';
        $this->template->target_rtl = $isTargetRTL ? ' rtl-target' : '';


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

    /**
     * @return string
     */
    protected function _buildPageTitle() {
        if ( $this->controller->getRevisionNumber() && $this->controller->getRevisionNumber() > 1 ) {
            $pageTitle = 'Revise ' . $this->controller->getRevisionNumber() . ' - ' ;
        }
        elseif ( $this->controller->getRevisionNumber() ) {
            $pageTitle = 'Revise - ' ;
        }
        else {
            $pageTitle = 'Translate - ' ;
        }
        return $pageTitle . $this->controller->getProject()->name . ' - ' .
                $this->controller->getChunk()->id ;
    }

}
