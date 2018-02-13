<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 12/02/2018
 * Time: 15:46
 */

namespace API\V2;

use API\V2\Exceptions\NotFoundException;
use API\V2\Validators\ProjectPasswordValidator;
use Jobs_JobStruct;
use ProjectManager;


class JobSplitController extends KleinController {

    /**
     * @var ProjectPasswordValidator
     */
    private $validator;

    private $job;

    private $project_struct;

    private $pManager;

    public function check() {
        $pStruct = $this->getSplitData();
        $this->response->json( [ 'data' => $pStruct[ 'split_result' ] ] );
    }

    public function apply() {
        $pStruct = $this->getSplitData();
        $this->pManager->applySplit( $pStruct );
        $this->response->json( [ 'data' => $pStruct[ 'split_result' ] ] );
    }

    private function getSplitData() {
        $this->project_struct = \Projects_ProjectDao::findByIdAndPassword( $this->request->id_project, $this->request->password, 60 * 60 );

        $this->pManager = new ProjectManager();
        $this->pManager->setProjectAndReLoadFeatures( $this->validator->getProject() );

        $pStruct = $this->pManager->getProjectStructure();
        $jobs    = $this->validator->getProject()->getJobs();
        $this->checkSplitAccess( $jobs );

        $pStruct[ 'job_to_split' ]      = $this->job->id;
        $pStruct[ 'job_to_split_pass' ] = $this->request->job_password;

        $this->pManager->getSplitData( $pStruct, $this->request->num_split, $this->request->split_values );

        return $pStruct;
    }

    protected function validateRequest() {
        $this->validator->validate();
        // TODO: additional validation to be included in a ProjectAndJob Validation object

        $this->job = \Jobs_JobDao::getById( $this->request->id_job )[ 0 ];

        if ( !$this->job || $this->job->id_project != $this->validator->getProject()->id ) {
            throw new \Exceptions_RecordNotFound();
        }
    }

    protected function afterConstruct() {
        $this->validator = new Validators\ProjectPasswordValidator( $this );
    }

    /**
     * @param Jobs_JobStruct[] $jobList
     *
     * @return Jobs_JobStruct[]
     * @throws NotFoundException
     */
    protected function checkSplitAccess( array $jobList ) {

        $jobToSplit = $this->filterJobsById( $jobList );

        if ( array_shift( $jobToSplit )->password != $this->request->job_password ) {
            throw new Exception( "Wrong Password. Access denied", -10 );
        }

        $this->project_struct->getFeatures()->run( 'checkSplitAccess', $jobList );
    }

    protected function filterJobsById( array $jobList ) {

        $found      = false;
        $jid        = $this->job->id;
        $jobToMerge = array_filter( $jobList, function ( Jobs_JobStruct $jobStruct ) use ( &$found, $jid ) {
            return $jobStruct->id == $jid;
        } );

        if ( empty( $jobToMerge ) ) {
            throw new NotFoundException( "Access denied", -10 );
        }

        return $jobToMerge;
    }

}