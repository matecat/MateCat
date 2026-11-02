<?php

namespace Controller\Views;

use Controller\Abstracts\BaseKleinViewController;
use Controller\Abstracts\IController;
use Controller\API\Commons\ViewValidators\ViewLoginRedirectValidator;
use Exception;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectDao;
use Utils\Tools\CatUtils;

class ContextReviewController extends BaseKleinViewController implements IController
{

    protected function registerValidators(): void
    {
        $this->appendValidator(new ViewLoginRedirectValidator($this));
    }

    /**
     * The route names its first segment `id_project`, but the cattool links here with the job id and the
     * password it was opened with: the translate password, or the review password of either phase.
     *
     * @throws Exception
     */
    public function renderView(): void
    {
        $request = $this->validateTheRequest();

        if (!is_array($request)) {
            $this->setView('project_not_found.html', [], 404);
            $this->render();
        }

        $job = $this->findJob((int)$request['id_project'], (string)$request['password']);

        if ($job === null) {
            $this->setView('project_not_found.html', [], 404);
            $this->render();
        }

        $this->setView('context_preview.html', [
            'id_project' => $request['id_project'],
            'password' => $request['password'],
        ]);

        // setView() loads the viewer's own features. The page shows the project's content, so it needs the
        // project's plugins in force when it renders: a customer plugin is what adds the CDN its screenshots
        // are served from to the Content-Security-Policy, whoever opens the preview.
        $this->loadProjectFeatures($job);

        $this->render();
    }

    /**
     * @throws Exception
     */
    protected function findJob(int $idJob, string $password): ?JobStruct
    {
        return (new CatUtils($this->getDatabase()))->getJobFromIdAndAnyPassword($idJob, $password);
    }

    /**
     * @throws Exception
     */
    protected function loadProjectFeatures(JobStruct $job): void
    {
        $this->getFeatureSet()->loadForProject($job->getProject(new ProjectDao($this->getDatabase())));
    }

    /**
     * @return array<string, mixed>|false|null
     */
    protected function validateTheRequest(): false|array|null
    {
        $filterArgs = [
            'id_project' => ['filter' => FILTER_SANITIZE_NUMBER_INT],
            'password' => [
                'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
            ]
        ];

        return filter_var_array($this->request->paramsNamed()->all(), $filterArgs);
    }

}
