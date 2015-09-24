<?php

class CatDecorator {

  private $controller ;
  private $template ;
  private $job ;
  private $project_completion_feature_enabled ;

  public function  __construct( $controller, $template ) {
    $this->controller = $controller ;
    $this->template = $template ;
    $this->job = $this->controller->getJob() ;

    $this->project_completion_feature_enabled =
      $this->job->isFeatureEnabled( Features::PROJECT_COMPLETION );

    $this->template->header = new HeaderDecorator( $controller ) ;

  }

  public function decorate() {
    $this->projectCompletionFeature();

    // TODO: add future presentation logic here
  }

  private function projectCompletionFeature() {
    $this->template->projectCompletionFeature = $this->project_completion_feature_enabled ;
  }

}
