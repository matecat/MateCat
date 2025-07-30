<?php

namespace API\Commons\Validators;

use API\Commons\Exceptions\AuthenticationError;
use API\Commons\Exceptions\NotFoundException;
use Log;
use Projects_ProjectDao;
use Projects_ProjectStruct;
use ReflectionException;
use Users_UserStruct;

/**
 * @daprecated this should extend Base
 *
 * Class ProjectValidator
 * @package    API\V2\Validators
 */
class ProjectValidator extends Base {

    /**
     * @var ?Users_UserStruct
     */
    private ?Users_UserStruct $user = null;

    /**
     * @var int
     */
    private int $id_project;

    /**
     * @param Users_UserStruct $user
     *
     * @return $this
     */
    public function setUser( Users_UserStruct $user ): ProjectValidator {
        $this->user = $user;

        return $this;
    }

    /**
     * @param mixed $id_project
     *
     * @return $this
     */
    public function setIdProject( $id_project ): ProjectValidator {
        $this->id_project = $id_project;

        return $this;
    }

    /**
     * @var ?Projects_ProjectStruct
     */
    private ?Projects_ProjectStruct $project = null;
    private ?string                $feature = null;

    /**
     * @param Projects_ProjectStruct $project
     */
    public function setProject( Projects_ProjectStruct $project ) {
        $this->project = $project;
    }

    public function getProject(): Projects_ProjectStruct {
        return $this->project;
    }

    public function setFeature( $feature ) {
        $this->feature = $feature;
    }

    /**
     * @return mixed|void
     * @throws AuthenticationError
     * @throws NotFoundException
     * @throws ReflectionException
     */
    protected function _validate(): void {

        if ( !$this->project ) {
            $this->project = Projects_ProjectDao::findById( $this->id_project );
        }

        if ( empty( $this->project ) ) {
            throw new NotFoundException( "Project not found.", 404 );
        }

        if ( !$this->validateFeatureEnabled() ) {
            throw new NotFoundException( "Feature not enabled on this project.", 404 );
        }

        if ( !$this->inProjectScope() ) {
            throw new NotFoundException( "You are not allowed to access to this project", 403 );
        }

    }

    private function validateFeatureEnabled(): bool {
        return $this->feature == null || $this->project->isFeatureEnabled( $this->feature );
    }

    /**
     * @return bool
     * @throws AuthenticationError
     */
    private function inProjectScope(): bool {

        if ( !$this->user ) {
            throw new AuthenticationError( "Invalid API key", 401 );
        }

        Log::doJsonLog( $this->user->email );
        Log::doJsonLog( $this->project->id_customer );

        return $this->user->email == $this->project->id_customer;
    }
}
