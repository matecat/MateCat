<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 08/04/16
 * Time: 23:55
 */

namespace API\V2;
use API\V2\Validators\ProjectPasswordValidator;
use ProjectManager ;


class JobMergeController extends ProtectedKleinController {

    /**
     * @var ProjectPasswordValidator
     */
    private $validator;

    private $job ;

    public function merge() {
        $pManager = new ProjectManager();
        $pManager->setProjectIdAndLoadProject( $this->validator->getProject()->id );

        $pStruct = $pManager->getProjectStructure();
        $pStruct['id_customer'] = $this->validator->getProject()->id_customer ;

        $pStruct[ 'job_to_merge' ] = $this->job->id;
        $pManager->mergeALL( $pStruct );

        $this->response->code(200);

    }

    protected function validateRequest() {
        $this->validator->validate();
        // TODO: additional validation to be included in a ProjectAndJob Validation object

        $this->job = \Jobs_JobDao::getById( $this->request->id_job );

        if ( !$this->job || $this->job->id_project != $this->validator->getProject()->id ) {
            throw new \Exceptions_RecordNotFound();
        }
    }

    protected function afterConstruct() {
        $this->validator = new Validators\ProjectPasswordValidator( $this->request );
    }
}