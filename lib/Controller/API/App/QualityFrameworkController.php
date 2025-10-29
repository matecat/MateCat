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

class QualityFrameworkController extends KleinController {


    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * Render a QF from project credentials
     */
    public function project() {

        $idProject = $this->request->param( 'id_project' );
        $password  = $this->request->param( 'password' );

        try {
            $project = ( new ProjectDao() )->findByIdAndPassword( $idProject, $password );
        } catch ( NotFoundException $exception ) {
            $this->response->code( 500 );
            $this->response->json( [
                    'error' => [
                            'message' => $exception->getMessage()
                    ]
            ] );
            exit();
        }

        $this->response->json( $this->renderQualityFramework( $project ) );
        exit();
    }

    /**
     * Render a QF from job credentials
     */
    public function job() {

        $idJob    = $this->request->param( 'id_job' );
        $password = $this->request->param( 'password' );

        $job = \Utils\Tools\CatUtils::getJobFromIdAndAnyPassword( $idJob, $password );

        if ( $job === null ) {
            $this->response->code( 500 );
            $this->response->json( [
                    'error' => [
                            'message' => 'Job not found'
                    ]
            ] );
            exit();
        }

        $this->response->json( $this->renderQualityFramework( $job->getProject() ) );
        exit();
    }

    /**
     * @param ProjectStruct $projectStruct
     *
     * @return array
     * @throws Exception
     */
    private function renderQualityFramework( ProjectStruct $projectStruct ) {
        $idQaModel = $projectStruct->id_qa_model;

        if($idQaModel === null){
            $this->response->code( 500 );
            $this->response->json( [
                    'error' => [
                            'message' => 'QAModel not found'
                    ]
            ] );
            exit();
        }

        $qaModel = ModelDao::findById( $idQaModel );

        if ( $qaModel === null ) {
            $this->response->code( 500 );
            $this->response->json( [
                    'error' => [
                            'message' => 'QAModel not found'
                    ]
            ] );
            exit();
        }

        $json                     = $qaModel->getDecodedModel();
        $json[ 'template_model' ] = null;

        if ( $qaModel->qa_model_template_id ) {

            $parentTemplate = QAModelTemplateDao::get( [ 'id' => $qaModel->qa_model_template_id, 'uid' => $this->getUser()->uid ] );

            if ( $parentTemplate === null ) {
                return $json;
            }

            $json[ 'template_model' ] = $parentTemplate->getDecodedModel()[ 'model' ];

        }

        return $json;
    }
}