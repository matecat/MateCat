<?php

class CatDecorator {

  private $controller ;
  private $template ;
  private $job ;
  private $project_completion_feature_enabled ;
  private $header ;

  public function  __construct( $controller, $template ) {
    $this->controller = $controller ;
    $this->template = $template ;
    $this->job = $this->controller->getJob();  ;

    $this->project_completion_feature_enabled =
      $this->job->isFeatureEnabled( Features::PROJECT_COMPLETION );

    $this->template->header = new HeaderDecorator( $controller ) ;
  }

  public function decorate() {
    $this->projectCompletionFeature();
    $this->headerButtonLabel();
  }

  public function isDownloadable() {

  }

  private function projectCompletionFeature() {
    $this->template->projectCompletionFeature = $this->project_completion_feature_enabled ;
  }

  private function headerButtonLabel() {
    if ( $this->project_completion_feature_enabled ) {

    }
    else {

    }

  }

  private function evalHeader() {

  }
}
