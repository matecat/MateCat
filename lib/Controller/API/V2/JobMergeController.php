<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 08/04/16
 * Time: 23:55
 */

namespace API\V2;
use Analysis\AnalysisDao;
use API\V2\Exceptions\NotFoundException;
use API\V2\Validators\ProjectPasswordValidator;
use Jobs_JobStruct;
use ProjectManager;


class JobMergeController extends KleinController {

    /**
     * @var ProjectPasswordValidator
     */
    private $validator;

    private $job ;

    public function merge() {

        $pManager = new ProjectManager();
        $pManager->setProjectAndReLoadFeatures( $this->validator->getProject() );

        $pStruct = $pManager->getProjectStructure();
        $pStruct['id_customer'] = $this->validator->getProject()->id_customer ;
        $pStruct[ 'job_to_merge' ] = $this->job->id;

        $jobStructs = $this->checkMergeAccess( $this->validator->getProject()->getJobs() );

        $pManager->mergeALL( $pStruct, $jobStructs );

        $this->response->code(200);
        $this->response->json( [ 'success' => true ] );
    }

    protected function validateRequest() {
        $this->validator->validate();
        // TODO: additional validation to be included in a ProjectAndJob Validation object

        $this->job = \Jobs_JobDao::getById( $this->request->id_job )[0];

        if ( !$this->job || $this->job->id_project != $this->validator->getProject()->id || $this->job->wasDeleted() ) {
            throw new \Exceptions\NotFoundException();
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
    protected function checkMergeAccess( array $jobList ) {

        $jid   = $this->job->id;
        $jobToMerge = array_filter( $jobList, function ( Jobs_JobStruct $jobStruct ) use ( $jid ) {
            return $jobStruct->id == $jid and !$jobStruct->wasDeleted(); // exclude deleted jobs
        } );

        if ( empty( $jobToMerge ) ) {
            throw new NotFoundException( "Access denied", -10 );
        }

        return $jobToMerge;

    }

}