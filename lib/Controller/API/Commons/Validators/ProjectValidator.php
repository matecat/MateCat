<?php

namespace Controller\API\Commons\Validators;

use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Exceptions\NotFoundException;
use Exception;
use Model\FeaturesBase\FeatureSet;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Users\UserStruct;
use ReflectionException;
use RuntimeException;
use TypeError;

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
     * @throws TypeError
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
    private ?string $feature = null;

    /**
     * @param ProjectStruct $project
     */
    public function setProject(ProjectStruct $project): void
    {
        $this->project = $project;
    }

    /**
     * @throws RuntimeException
     */
    public function getProject(): ProjectStruct
    {
        if ($this->project === null) {
            throw new RuntimeException('validate() must be called before getProject()');
        }
        return $this->project;
    }

    public function setFeature(string $feature): void
    {
        $this->feature = $feature;
    }

    /**
     * @throws AuthenticationError
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws Exception
     */
    protected function _validate(): void
    {
        if (!$this->project) {
            $this->project = (new ProjectDao($this->controller->getDatabase()))->findById($this->id_project);
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

    /**
     * @throws Exception
     */
    private function validateFeatureEnabled(): bool
    {
        if ($this->feature === null || $this->project === null) {
            return true;
        }

        return FeatureSet::forProject($this->project, $this->controller->getDatabase())->hasFeature($this->feature);
    }

    /**
     * @return bool
     * @throws AuthenticationError
     * @throws RuntimeException
     */
    private function inProjectScope(): bool
    {
        if (!$this->user) {
            throw new AuthenticationError("Invalid API key", 401);
        }

        if ($this->project === null) {
            throw new RuntimeException('project must be set before calling inProjectScope()');
        }

        return $this->user->email == $this->project->id_customer;
    }
}
