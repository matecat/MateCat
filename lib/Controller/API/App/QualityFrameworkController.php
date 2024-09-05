<?php

namespace API\App;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use Exceptions\NotFoundException;
use LQA\ModelDao;
use Projects_ProjectStruct;
use QAModelTemplate\QAModelTemplateDao;

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
            $project = ( new \Projects_ProjectDao() )->findByIdAndPassword( $idProject, $password );
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

        $job = \CatUtils::getJobFromIdAndAnyPassword( $idJob, $password );

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
     * @param Projects_ProjectStruct $projectStruct
     *
     * @return array
     */
    private function renderQualityFramework( Projects_ProjectStruct $projectStruct ) {
        $idQaModel = $projectStruct->id_qa_model;
        $qaModel   = ModelDao::findById( $idQaModel );

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