<?php

namespace API\V2\Validators;

class ProjectValidator {

    private $api_record;
    private $id_project;
    /**
     * @var \Projects_ProjectStruct
     */
    private $project;
    private $feature;

    public function getProject() {
        return $this->project;
    }

    public function setFeature( $feature ) {
        $this->feature = $feature;
    }

    public function __construct( $api_record, $id_project ) {
        $this->api_record = $api_record;
        $this->id_project = $id_project;
    }

    public function validate() {
        $this->project = \Projects_ProjectDao::findById( $this->id_project );

        if ( $this->project == false ) {
            return false;
        }

        if ( !$this->validateFeatureEnabled() ) {
            return false;
        }

        return $this->inProjectScope();
    }

    private function validateFeatureEnabled() {
        return $this->feature == null || $this->project->isFeatureEnabled( $this->feature );
    }

    private function inProjectScope() {

        \Log::doLog( $this->api_record->getUser()->email );
        \Log::doLog( $this->project->id_customer );

        return $this->api_record->getUser()->email == $this->project->id_customer;
    }
}
