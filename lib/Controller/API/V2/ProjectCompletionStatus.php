<?php

namespace Controller\API\V2;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use Controller\API\Commons\Validators\ProjectValidator;
use Exception;
use Model\FeaturesBase\FeatureSet;
use Model\Projects\ProjectStruct;
use Plugins\Features\ProjectCompletion\Model\ProjectCompletionStatusModel;
use RuntimeException;

class ProjectCompletionStatus extends KleinController
{

    private ?ProjectStruct $project = null;

    protected function registerValidators(): void
    {
        $projectValidator = new ProjectValidator($this);

        $password = filter_var($this->request->param('password'), FILTER_DEFAULT);
        if (is_string($password) && $password !== '') {
            $projectPasswordValidator = new ProjectPasswordValidator($this);
            $projectPasswordValidator->onSuccess(function () use ($projectPasswordValidator, $projectValidator) {
                $project = $projectPasswordValidator->getProject() ?? throw new RuntimeException('Project not found');
                $this->project = $project;
                $projectValidator->setProject($project);
            });

            $this->appendValidator($projectPasswordValidator);
        }

        $projectValidator->setUser($this->getUser());

        $projectValidator->setIdProject($this->request->param('id_project'));
        $projectValidator->setFeature('project_completion');

        $projectValidator->onSuccess(function () use ($projectValidator) {
            $this->project = $projectValidator->getProject();
        });

        $this->appendValidator($projectValidator);
    }

    /**
     * @throws Exception
     */
    public function status(): void
    {
        $model = new ProjectCompletionStatusModel(
            $this->project ?? throw new RuntimeException('Project not found'),
            new FeatureSet(null, $this->getDatabase()),
        );
        $this->response->json([
            'project_status' => $model->getStatus()
        ]);
    }

}