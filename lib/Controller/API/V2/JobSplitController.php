<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 12/02/2018
 * Time: 15:46
 */

namespace API\V2;

use API\V2\Exceptions\NotFoundException;
use API\V2\Exceptions\UnprocessableException;
use API\V2\Validators\ProjectPasswordValidator;
use Exception;
use Jobs_JobStruct;
use ProjectManager;
use Projects_MetadataDao;
use Projects_ProjectStruct;


class JobSplitController extends KleinController {

    private $job;

    /**
     * @var Projects_ProjectStruct
     */
    private $project_struct;

    /**
     * @var ProjectManager
     */
    private $pManager;

    protected function afterConstruct() {

        $projectValidator = ( new ProjectPasswordValidator( $this ) );

        $projectValidator->onSuccess( function () use ( $projectValidator ) {
            $this->project_struct = $projectValidator->getProject();
            $this->job            = \Jobs_JobDao::getById( $this->request->id_job )[ 0 ];
            $this->filterJobsById( $this->project_struct->getJobs() );
        } );
        $this->appendValidator( $projectValidator );
    }

    /**
     * @throws UnprocessableException
     */
    public function check() {
        try {
            $pStruct = $this->getSplitData();
            $this->response->json( [ 'data' => $pStruct[ 'split_result' ] ] );
        } catch ( Exception $e ) {
            throw new UnprocessableException( $e->getMessage(), $e->getCode(), $e );
        }
    }

    /**
     * @throws UnprocessableException
     * @throws Exception
     */
    public function apply() {

        try {
            $pStruct = $this->getSplitData();
        } catch ( Exception $e ) {
            throw new UnprocessableException( $e->getMessage(), $e->getCode(), $e );
        }

        $this->pManager->applySplit( $pStruct );
        $this->response->json( [ 'data' => $pStruct[ 'split_result' ] ] );
    }

    /**
     * @throws Exception
     */
    private function getSplitData() {
        $this->pManager = new ProjectManager();
        $this->pManager->setProjectAndReLoadFeatures( $this->project_struct );

        $count_type = ($this->request->param("split_raw_words") == true) ? Projects_MetadataDao::SPLIT_RAW_WORD_TYPE : Projects_MetadataDao::SPLIT_EQUIVALENT_WORD_TYPE;

        $pStruct = $this->pManager->getProjectStructure();

        $pStruct[ 'job_to_split' ]      = $this->job->id;
        $pStruct[ 'job_to_split_pass' ] = $this->job->password;

        $this->pManager->getSplitData( $pStruct, $this->request->num_split, $this->request->split_values, $count_type );

        return $pStruct;
    }

    protected function filterJobsById( array $jobList ) {

        $found      = false;
        $jid        = $this->job->id;
        $jobToMerge = array_filter( $jobList, function ( Jobs_JobStruct $jobStruct ) use ( &$found, $jid ) {
            return $jobStruct->id == $jid and !$jobStruct->wasDeleted(); // exclude every deleted job from merge
        } );

        if ( empty( $jobToMerge ) ) {
            throw new NotFoundException( "Access denied", -10 );
        }

        return $jobToMerge;
    }

}