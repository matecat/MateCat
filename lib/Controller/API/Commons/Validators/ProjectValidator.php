<?php

namespace Controller\API\Commons\Validators;

use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Exceptions\NotFoundException;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Users\UserStruct;
use ReflectionException;

/**
 * @daprecated this should extend Base
 *
 * Class ProjectValidator
 * @package    API\V2\Validators
 */
class ProjectValidator extends Base
{

    /**
     * @var ?UserStruct
     */
    private ?UserStruct $user = null;

    /**
     * @var int
     */
    private int $id_project;

    /**
     * @param UserStruct $user
     *
     * @return $this
     */
    public function setUser(UserStruct $user): ProjectValidator
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @param mixed $id_project
     *
     * @return $this
     */
    public function setIdProject($id_project): ProjectValidator
    {
        $this->id_project = $id_project;

        return $this;
    }

    /**
     * @var ?ProjectStruct
     */
    private ?ProjectStruct $project = null;
    private ?string        $feature = null;

    /**
     * @param ProjectStruct $project
     */
    public function setProject(ProjectStruct $project)
    {
        $this->project = $project;
    }

    public function getProject(): ProjectStruct
    {
        return $this->project;
    }

    public function setFeature($feature)
    {
        $this->feature = $feature;
    }

    /**
     * @return mixed|void
     * @throws AuthenticationError
     * @throws NotFoundException
     * @throws ReflectionException
     */
    protected function _validate(): void
    {
        if (!$this->project) {
            $this->project = ProjectDao::findById($this->id_project);
        }

        if (empty($this->project)) {
            throw new NotFoundException("Project not found.", 404);
        }

        if (!$this->validateFeatureEnabled()) {
            throw new NotFoundException("Feature not enabled on this project.", 404);
        }

        if (!$this->inProjectScope()) {
            throw new NotFoundException("You are not allowed to access to this project", 403);
        }
    }

    private function validateFeatureEnabled(): bool
    {
        return $this->feature == null || $this->project->isFeatureEnabled($this->feature);
    }

    /**
     * @return bool
     * @throws AuthenticationError
     */
    private function inProjectScope(): bool
    {
        if (!$this->user) {
            throw new AuthenticationError("Invalid API key", 401);
        }

        return $this->user->email == $this->project->id_customer;
    }
}
