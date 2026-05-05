<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\JobPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use Exception;
use InvalidArgumentException;
use Klein\Response;
use Model\Analysis\Status;
use Model\Projects\ProjectDao;

class GetVolumeAnalysisController extends KleinController
{

    /**
     */
    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));

        $this->validateTheRequest();

        if (empty($this->request->param('id_job'))) {
            $this->appendValidator(new ProjectPasswordValidator($this));
        } else {
            $this->appendValidator(new JobPasswordValidator($this));
        }
    }

    /**
     * @throws Exception
     */
    public function analysis(): Response
    {
        $_project_data = ProjectDao::getProjectAndJobData($this->params['id_project']);

        $analysisStatus = new Status($_project_data, $this->featureSet, $this->user);

        return $this->response->json($analysisStatus->fetchData()->getResult());
    }

    /**
     * @return void
     */
    private function validateTheRequest(): void
    {
        if (empty($this->request->param('id_project'))) {
            throw new InvalidArgumentException("No id project provided", -1);
        }

        if (empty($this->request->param('password'))) {
            throw new InvalidArgumentException("No password provided", -2);
        }
    }
}