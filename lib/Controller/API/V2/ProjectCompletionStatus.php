<?php

namespace Controller\API\V2;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use Controller\API\Commons\Validators\ProjectValidator;
use Exception;
use Model\Projects\ProjectStruct;
use Plugins\Features\ProjectCompletion\Model\ProjectCompletionStatusModel;

class ProjectCompletionStatus extends KleinController {

    /**
     * @var ProjectStruct
     */
    private ProjectStruct $project;

    public function afterConstruct() {

        $projectValidator = new ProjectValidator( $this );

        if ( $this->request->paramsNamed()[ 'password' ] ) {
            $projectPasswordValidator = new ProjectPasswordValidator( $this );
            $projectPasswordValidator->onSuccess( function () use ( $projectPasswordValidator, $projectValidator ) {
                $this->project = $projectPasswordValidator->getProject();
                $projectValidator->setProject( $this->project );
            } );

            $this->appendValidator( $projectPasswordValidator );
        }

        if ( $this->getUser() ) {
            $projectValidator->setUser( $this->getUser() );
        }

        $projectValidator->setIdProject( $this->request->param( 'id_project' ) );
        $projectValidator->setFeature( 'project_completion' );

        $projectValidator->onSuccess( function () use ( $projectValidator ) {
            $this->project = $projectValidator->getProject();
        } );

        $this->appendValidator( $projectValidator );
    }

    /**
     * @throws Exception
     */
    public function status() {

        $model = new ProjectCompletionStatusModel( $this->project );
        $this->response->json( [
                'project_status' => $model->getStatus()
        ] );
    }

}