<?php

namespace API\V2 ;

class ProjectValidator {

  private $api_record ;
  private $id_project ;
  private $project ;
  private $feature ;

  public function getProject() {
    return $this->project;
  }

  public function setFeature( $feature ) {
    $this->feature = $feature ;
  }

  public function __construct( $api_record, $id_project ) {
    $this->api_record = $api_record ;
    $this->id_project = $id_project ;
  }

  public function validate() {
    $this->project = \Projects_ProjectDao::findById( $this->id_project );

    if ($this->project == false) {
      return false;
    }

    if (! $this->validateFeatureEnabled() ) {
      return false;
    }

    return $this->inProjectScope() ;
  }

  private function validateFeatureEnabled() {
      \Log::doLog( var_export( $this->feature, true ));

    return $this->feature == null ||
      $this->project->getOwnerFeature( $this->feature )  ;
  }

  private function inProjectScope() {

      \Log::doLog ( $this->api_record->getUser()->email );
      \Log::doLog ( $this->project->id_customer );

       return $this->api_record->getUser()->email == $this->project->id_customer ;
  }
}
