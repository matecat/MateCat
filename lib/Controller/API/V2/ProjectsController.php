<?php

namespace Controller\API\V2;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\ProjectAccessTokenValidator;
use Controller\API\Commons\Validators\ProjectAccessValidator;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use Exception;
use Model\Exceptions\NotFoundException;
use Model\Jobs\JobDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Translations\SegmentTranslationDao;
use ReflectionException;
use Throwable;
use Utils\Constants\JobStatus;
use Utils\Tools\Utils;
use View\API\V2\Json\Project;

/**
 * This controller can be called as Anonymous, but only if you already know the id and the password
 *
 * Class ProjectsController
 * @package API\V2
 */
class ProjectsController extends KleinController
{

    /**
     * @var ProjectStruct
     */
    private ProjectStruct $project;

    /**
     * @return void
     * @throws ReflectionException
     * @throws Exception
     */
    public function get(): void
    {
        $formatted = new Project();
        $formatted->setUser($this->user);
        if (!empty($this->api_key)) {
            $formatted->setCalledFromApi(true);
        }

        $this->featureSet->loadForProject($this->project);
        $projectOutputFields = $formatted->renderItem($this->project);
        $this->response->json(['project' => $projectOutputFields]);
    }

    /**
     * @throws ReflectionException
     */
    public function setDueDate(): void
    {
        $this->updateDueDate();
    }

    /**
     * @throws ReflectionException
     */
    public function updateDueDate(): void
    {
        if (
            array_key_exists("due_date", $this->params)
            &&
            is_numeric($this->params['due_date'])
            &&
            $this->params['due_date'] > time()
        ) {
            $due_date = Utils::mysqlTimestamp($this->params['due_date']);
            $project_dao = new ProjectDao;
            $project_dao->updateField($this->project, "due_date", $due_date);
        }

        $formatted = new Project();

        //$this->response->json( $this->project->toArray() );
        $this->response->json(['project' => $formatted->renderItem($this->project)]);
    }

    /**
     * @throws ReflectionException
     */
    public function deleteDueDate(): void
    {
        $project_dao = new ProjectDao;
        $project_dao->updateField($this->project, "due_date", null);

        $formatted = new Project();
        $this->response->json(['project' => $formatted->renderItem($this->project)]);
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function cancel(): void
    {
        $this->changeStatus(JobStatus::STATUS_CANCELLED);
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function archive(): void
    {
        $this->changeStatus(JobStatus::STATUS_ARCHIVED);
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function active(): void
    {
        $this->changeStatus(JobStatus::STATUS_ACTIVE);
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function delete(): void
    {
        $this->changeStatus(JobStatus::STATUS_DELETED);
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    protected function changeStatus($status): void
    {
        (new ProjectAccessValidator($this, $this->project))->validate();

        $chunks = $this->project->getJobs();

        foreach ($chunks as $chunk) {
            // update a job only if it is NOT deleted
            if (!$chunk->isDeleted()) {
                JobDao::updateJobStatus($chunk, $status);

                $lastSegmentsList = SegmentTranslationDao::getMaxSegmentIdsFromJob($chunk);
                SegmentTranslationDao::updateLastTranslationDateByIdList($lastSegmentsList, Utils::mysqlTimestamp(time()));
            }
        }

        $this->response->json(['code' => 1, 'data' => "OK", 'status' => $status]);
    }

    /**
     * Handles the initialization process after object construction by setting up
     * project validation, error handling, and appending necessary validators.
     *
     * This method performs the following steps:
     * - Creates a `ProjectPasswordValidator` instance to validate the project password.
     * - Defines success and failure callbacks for the password validator:
     *   - On success, retrieves and assigns the validated project to the `$project` property.
     *   - On failure, checks if the exception is a `NotFoundException` and attempts to validate
     *     the project using a `ProjectAccessTokenValidator`. If validation succeeds, assigns
     *     the validated project to the `$project` property. Otherwise, rethrows the exception.
     * - Appends a `LoginValidator` and the `ProjectPasswordValidator` to the list of validators.
     *
     * @return void
     * @throws Throwable If the project is not found and no valid access token is provided.
     * @throws Exception For other validation failures or unexpected errors.
     */
    protected function afterConstruct(): void
    {
        // Initialize the project password validator.
        $projectValidator = (new ProjectPasswordValidator($this));

        // Define the success callback for the password validator.
        $projectValidator->onSuccess(function () use ($projectValidator) {
            $this->project = $projectValidator->getProject();
        })
            // Define the failure callback for the password validator.
            ->onFailure(function (Throwable $exception) {
                if ($exception instanceof NotFoundException && !empty($this->request->param('project_access_token'))) {
                    // If the project is not found, attempt validation using an access token.
                    $projectByTokenValidator = new ProjectAccessTokenValidator($this);
                    $projectByTokenValidator->onSuccess(function () use ($projectByTokenValidator) {
                        $this->project = $projectByTokenValidator->getProject();
                    })->validate();
                } else {
                    // Rethrow the exception for other validation failures.
                    throw $exception;
                }
            });

        // Append the login validator to the list of validators.
        $this->appendValidator(new LoginValidator($this));

        // Append the project password validator to the list of validators.
        $this->appendValidator($projectValidator);
    }

}