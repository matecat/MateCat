<?php

class CatDecorator {

  private $controller ;
  private $template ;
  private $job ;
  private $review_type ;

  public function  __construct( catController $controller, PHPTAL $template ) {

    $this->controller = $controller ;
    $this->template = $template ;
    $this->job = $this->controller->getJob() ;
    $this->job_stats = $this->controller->getJobStats();
  }

  public function decorate() {
      $this->template->isReview = $this->controller->isRevision();
      $this->template->header_quality_report_item_class = '' ;
      $this->template->review_password = $this->controller->getReviewPassword() ;

      $this->template->header_main_button_enabled = true ;
      $this->template->header_main_button_class = 'downloadtr-button ';
      $this->template->header_main_button_label = $this->getHeaderMainButtonLabel();
      $this->template->header_main_button_id = 'downloadProject';


      $this->template->status_labels = json_encode( $this->getStatusLabels() );

      if ( $this->controller->isRevision() ) {
          $this->decorateForRevision();
      }
      else {
          $this->decorateForTranslate();
      }

      $this->template->searchable_statuses = $this->searchableStatuses();
      $this->template->project_type = null;

      Features::appendDecorators(
          $this->job->getProject()->id_customer,
          'CatDecorator',
          $this->controller,
          $this->template,
          array('project' => $this->job->getProject())
      );
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

        return array_map(function($item) {
            return (object) array( 'value' => $item, 'label' => $item );
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
      if ( $this->isDownloadable() ) {
          return 'DOWNLOAD TRANSLATION';
      } else {
          return 'PREVIEW';
      }
  }

  private function downloadStatus() {
        return $this->job_stats['DOWNLOAD_STATUS'] ;
  }

  private function isDownloadable() {
      return (
        $this->job_stats['TODO_FORMATTED'] == 0 &&
        $this->job_stats['ANALYSIS_COMPLETE']
      );
  }

  private function decorateForRevision() {
      $this->template->footer_show_revise_link = false;
      $this->template->footer_show_translate_link = true;
      $this->template->review_class = 'review' ;
      $this->template->review_type = 'simple';

      // TODO: move this logic in javascript QualityReportButton component
      if ( $this->controller->getQaOverall() == 'fail' ||
          $this->controller->getQaOverall() == 'poor' ) {
          $this->template->header_quality_report_item_class = 'hide' ;
      }

      $this->setQualityReportHref();

  }

  private function decorateForTranslate() {
      $this->template->footer_show_revise_link = true;
      $this->template->footer_show_translate_link = false;
      $this->template->review_class = '';
      $this->template->review_type = 'simple';

  }

    private function setQualityReportHref() {
        $this->template->quality_report_href =
            "{$this->template->basepath}revise-summary/{$this->job->id}-{$this->job->password}";
    }
}
