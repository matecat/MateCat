<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/09/16
 * Time: 15:11
 */

namespace Controller\API\V2;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use Exception;
use Model\FeaturesBase\Hook\Event\Filter\ProjectUrlsEvent;
use Model\Projects\ProjectDao;
use View\API\V2\Json\ProjectUrls;

class UrlsController extends KleinController
{

    /**
     * @var ProjectPasswordValidator
     */
    private ProjectPasswordValidator $validator;

    /**
     * @throws Exception
     */
    public function urls(): void
    {
        $project = $this->validator->getProject() ?? throw new Exception('Project not found');

        $this->featureSet->loadForProject($project);

        $jobCheck = 0;
        foreach ($project->getJobs() as $job) {
            if (!$job->isDeleted()) {
                $jobCheck++;
            }
        }

        if ($jobCheck === 0) {
            $this->response->status()->setCode(404);
            $this->response->json([
                'errors' => [
                    'code' => 0,
                    'message' => 'No project found.'
                ]
            ]);
            return;
        }

        $projectData = (new ProjectDao($this->db()))->setCacheTTL(60 * 60)->getProjectData($project->id ?? throw new Exception('Project id is null'));

        $formatted = new ProjectUrls($projectData);

        $projectUrlsEvent = new ProjectUrlsEvent($formatted);
        $this->featureSet->dispatch($projectUrlsEvent);
        $formatted = $projectUrlsEvent->getFormatted();

        $this->response->json(['urls' => $formatted->render()]);
    }

    protected function validateRequest(): void
    {
        $this->validator->validate();
    }

    protected function registerValidators(): void
    {
        $this->validator = new ProjectPasswordValidator($this);
        $this->appendValidator(new LoginValidator($this));
    }

}
