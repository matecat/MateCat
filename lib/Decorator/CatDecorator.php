<?php

class CatDecorator {

  private $controller ;
  private $template ;
  private $job ;
  private $project_completion_feature_enabled ;
  private $review_type ;

  public function  __construct( catController $controller, PHPTAL $template ) {

    $this->controller = $controller ;
    $this->template = $template ;
    $this->job = $this->controller->getJob() ;
  }

  public function decorate() {
      $this->template->isReview = $this->controller->isRevision();
      $this->template->header_quality_report_item_class = '' ;
      $this->template->header_output_quality_report = false;

      if ( $this->controller->isRevision() ) {
          $this->decorateForRevision();
      }
      else {
          $this->decorateForTranslated();
      }

      $this->decorateHeader();

      Features::appendDecorators(
          $this->job->getProject()->id_customer,
          'CatDecorator',
          $this->controller,
          $this->template
      );
  }

  private function decorateForRevision() {
      $this->template->footer_show_revise_link = false;
      $this->template->footer_show_translate_link = true;
      $this->template->review_class = 'review' ;
      $this->template->review_type = 'simple';

      $this->template->header_output_quality_report = true;
      if ( $this->template->overall_quality_class == 'fail' ||
          $this->template->overall_quality_class == 'poor' ) {
          $this->template->header_quality_report_item_class = 'hide' ;
      }

  }

  private function decorateForTranslated() {
      $this->template->footer_show_revise_link = true;
      $this->template->footer_show_translate_link = false;
      $this->template->review_class = '';
      $this->template->review_type = 'simple';

  }

  /**
   * @deprecated doesn't sound like a good idea to work this way, because of the possible
   * issues with plugins trying to override template properties.
   */

  private function decorateHeader() {
      $this->template->header = new HeaderDecorator( $this->controller ) ;
  }

}
