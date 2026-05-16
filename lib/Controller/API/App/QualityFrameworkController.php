<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Model\Exceptions\NotFoundException;
use Model\LQA\ModelDao;
use Model\LQA\QAModelTemplate\QAModelTemplateDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use PDOException;
use ReflectionException;
use TypeError;

class QualityFrameworkController extends KleinController
{


    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * Render a QF from project credentials
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws TypeError
     * @throws Exception
     * @throws PDOException
     */
    public function project(): void
    {
        $idProject = $this->request->param('id_project');
        $password = $this->request->param('password');
        $project = (new ProjectDao())->findByIdAndPassword($idProject, $password);

        $this->response->json($this->renderQualityFramework($project));
    }

    /**
     * @param ProjectStruct $projectStruct
     *
     * @return array<string, mixed>
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws TypeError
     * @throws Exception
     * @throws PDOException
     */
    private function renderQualityFramework(ProjectStruct $projectStruct): array
    {
        $idQaModel = $projectStruct->id_qa_model;

        if ($idQaModel === null) {
            throw new NotFoundException('QAModel not found');
        }

        $qaModel = ModelDao::findById($idQaModel);

        if ($qaModel === null) {
            throw new NotFoundException('QAModel not found');
        }

        $json = $qaModel->getDecodedModel();
        $json['template_model'] = null;

        if ($qaModel->qa_model_template_id) {
            $uid = $this->getUser()->uid ?? throw new TypeError('User not authenticated');
            $parentTemplate = QAModelTemplateDao::get(['id' => $qaModel->qa_model_template_id, 'uid' => $uid]);

            if ($parentTemplate === null) {
                return $json;
            }

            $json['template_model'] = $parentTemplate->getDecodedModel()['model'];
        }

        return $json;
    }
}
