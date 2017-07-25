<?php

namespace API\V2 ;

use API\V2\Validators\ProjectPasswordValidator;
use Features ;
use API\V2\Validators\ProjectValidator;
use Features\ProjectCompletion\Model\ProjectCompletionStatusModel;

class ProjectCompletionStatus extends KleinController {

    /**
     * @var ProjectValidator
     */
    private $validator ;

    protected function validateRequest() {

        if ( $this->request->paramsNamed()['password'] ) {
            $this->validator = new ProjectPasswordValidator( $this ) ;
        }
        else {
            $this->validator = new ProjectValidator(
                    $this->api_record,
                    $this->request->id_project
            );
            $this->validator->setFeature( 'project_completion' );
        }

        $valid = $this->validator->validate();

        if (! $valid) {
            $this->response->code(404);
            $this->response->json(
                array('error' => 'This project does not exist')
            );
        }
    }

    public function status() {
        // TODO: wrap everything inside a JSON formatter class
        $model = new ProjectCompletionStatusModel( $this->validator->getProject() ) ;

        $this->response->json( array(
            'project_status' => $model->getStatus()
        ) ) ;
    }

}
