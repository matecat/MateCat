<?php

namespace API\V2 ;

use API\V2\Validators\ProjectPasswordValidator;
use API\V2\Validators\ProjectValidator;
use Features\ProjectCompletion\Model\ProjectCompletionStatusModel;

class ProjectCompletionStatus extends KleinController {

    /**
     * @var ProjectValidator
     */
    private $validator ;

    public function afterConstruct() {

        if ( $this->request->paramsNamed()[ 'password' ] ) {
            $validator = new ProjectPasswordValidator( $this );
        } else {
            $validator = new ProjectValidator( $this );
            $validator->setApiRecord( $this->api_record );
            $validator->setIdProject( $this->request->id_project );
            $validator->setFeature( 'project_completion' );
        }

        $this->appendValidator( $validator );

    }

    public function status() {
        // TODO: wrap everything inside a JSON formatter class
        $model = new ProjectCompletionStatusModel( $this->validator->getProject() ) ;

        $this->response->json( array(
            'project_status' => $model->getStatus()
        ) ) ;
    }

}
