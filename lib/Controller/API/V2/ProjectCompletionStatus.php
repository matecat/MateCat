<?php

namespace API\V2 ;

use AbstractControllers\KleinController;
use API\Commons\Validators\ProjectPasswordValidator;
use API\Commons\Validators\ProjectValidator;
use Features\ProjectCompletion\Model\ProjectCompletionStatusModel;
use Projects_ProjectStruct;

class ProjectCompletionStatus extends KleinController {

    /**
     * @var Projects_ProjectStruct
     */
    private $project ;

    public function afterConstruct() {

        $projectValidator = new ProjectValidator( $this );

        if ( $this->request->paramsNamed()[ 'password' ] ) {
            $projectPasswordValidator = new ProjectPasswordValidator( $this );
            $projectPasswordValidator->onSuccess( function () use ( $projectPasswordValidator, $projectValidator ) {
                $this->project = $projectPasswordValidator->getProject();
                $projectValidator->setProject($this->project);
            } );

            $this->appendValidator( $projectPasswordValidator );
        }

        if($this->getUser()){
            $projectValidator->setUser( $this->getUser() );
        }

        $projectValidator->setIdProject( $this->request->id_project );
        $projectValidator->setFeature( 'project_completion' );

        $projectValidator->onSuccess( function () use ( $projectValidator ) {
            $this->project = $projectValidator->getProject();
        } );

        $this->appendValidator( $projectValidator );
    }

    public function status() {

        $model = new ProjectCompletionStatusModel( $this->project ) ;
        $this->response->json( [
            'project_status' => $model->getStatus()
        ] ) ;
    }

}