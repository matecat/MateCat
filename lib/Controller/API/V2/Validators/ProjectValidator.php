<?php

namespace API\V2\Validators;

use API\V2\Exceptions\AuthenticationError;
use API\V2\Exceptions\NotFoundException;
use API\V2\KleinController;
use ApiKeys_ApiKeyStruct;
use Log;

/**
 * @daprecated this should extend Base
 *
 * Class ProjectValidator
 * @package API\V2\Validators
 */
class ProjectValidator extends Base {

    /**
     * @var ApiKeys_ApiKeyStruct
     */
    private $api_record;
    private $id_project;

    /**
     * @param ApiKeys_ApiKeyStruct $api_record
     *
     * @return $this
     */
    public function setApiRecord( $api_record ) {
        $this->api_record = $api_record;

        return $this;
    }

    /**
     * @param mixed $id_project
     *
     * @return $this
     */
    public function setIdProject( $id_project ) {
        $this->id_project = $id_project;

        return $this;
    }

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

    public function __construct( KleinController $controller ) {}

    /**
     * @return mixed|void
     * @throws AuthenticationError
     * @throws NotFoundException
     */
    protected function _validate() {

        $this->project = \Projects_ProjectDao::findById( $this->id_project );

        if ( $this->project == false ) {
            throw new NotFoundException( "Project Not Found.", 404 );
        }

        if ( !$this->validateFeatureEnabled() ) {
            throw new NotFoundException( "Project Not Found.", 404 );
        }

        if( !$this->inProjectScope() ){
            throw new NotFoundException( "Project Not Found.", 404 );
        }

    }

    private function validateFeatureEnabled() {
        return $this->feature == null || $this->project->isFeatureEnabled( $this->feature );
    }

    /**
     * @return bool
     * @throws AuthenticationError
     */
    private function inProjectScope() {

        if( !$this->api_record ){
            throw new AuthenticationError( "Invalid API key", 401 );
        }

        Log::doJsonLog( $this->api_record->getUser()->email );
        Log::doJsonLog( $this->project->id_customer );

        return $this->api_record->getUser()->email == $this->project->id_customer;
    }
}
