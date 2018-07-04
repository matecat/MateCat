<?php

namespace API\V2 ;

use API\V2\Validators\ProjectPasswordValidator;
use API\V2\Validators\ProjectValidator;
use Features\ProjectCompletion\Model\ProjectCompletionStatusModel;
use Projects_ProjectStruct;

class ProjectCompletionStatus extends KleinController {

    /**
     * @var Projects_ProjectStruct
     */
    private $project ;

    public function afterConstruct() {

        if ( $this->request->paramsNamed()[ 'password' ] ) {
            $validator = new ProjectPasswordValidator( $this );
        } else {
            $validator = new ProjectValidator( $this );
            $validator->setApiRecord( $this->api_record );
            $validator->setIdProject( $this->request->id_project );
            $validator->setFeature( 'project_completion' );
        }

        $validator->onSuccess( function () use ( $validator ) {
            $this->project = $validator->getProject();
        } );

        $this->appendValidator( $validator );

    }

    public function status() {

        $model = new ProjectCompletionStatusModel( $this->project ) ;
        $this->response->json( [
            'project_status' => $model->getStatus()
        ] ) ;
    }

}
