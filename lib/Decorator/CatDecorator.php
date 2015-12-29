<?php

class CatDecorator {

  private $controller ;
  private $template ;
  private $job ;
  private $project_completion_feature_enabled ;

  public function  __construct( catController $controller, PHPTAL $template ) {

    $this->controller = $controller ;
    $this->template = $template ;
    $this->job = $this->controller->getJob() ;

    $this->project_completion_feature_enabled =
      $this->job->isFeatureEnabled( Features::PROJECT_COMPLETION );

  }

  public function decorate() {
    $this->decorateMainView();

    $this->decorateHeader();
    $this->projectCompletionFeature();
    // TODO: add future presentation logic here
  }

  private function projectCompletionFeature() {
    $this->template->projectCompletionFeature = $this->project_completion_feature_enabled ;
  }

  private function decorateMainView() {
      $this->reviewSettings();
  }

  private function decorateHeader() {
      $this->template->header = new HeaderDecorator( $this->controller ) ;
  }

  private function reviewSettings() {
      $this->template->isReview = $this->controller->isRevision();
      $this->template->reviewClass = (
          $this->controller->isRevision() ?
          ' review' :
          '' );

      $review_type =
          $this->job->isFeatureEnabled( Features::REVIEW_IMPROVED ) ?
          'improved' :
          'simple' ;

      $this->template->reviewType = $review_type ;
      $this->template->review_improved = ( $review_type == 'improved' );

      if ( $review_type == 'improved' ) {
          $project = $this->job->getProject();
          $model = $project->getLqaModel() ;

          $this->template->lqa_categories = $model->getSerializedCategories();
      }
  }


}
