<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 26/03/2018
 * Time: 12:35
 */
namespace API\V2;

use API\V2\Exceptions\NotFoundException;
use API\V2\Validators\JobPasswordValidator;
use Jobs_JobStruct;
use Projects_ProjectStruct;
use Translations\BulkStatusChangeModel;


class JobStatusController extends KleinController {

    /**
     * @var Jobs_JobStruct
     */
    private $job;

    /**
     * @var Projects_ProjectStruct
     */
    private $project;

    protected function afterConstruct() {

        $jobValidator = new JobPasswordValidator( $this ) ;
        $jobValidator->onSuccess( function () use ( $jobValidator ) {
            $this->job     = $jobValidator->getJob();
            $this->project = $this->job->getProject();
        } );

        $this->appendValidator( $jobValidator );
    }

    public function changeSegmentsStatus() {
        $segments_id = filter_var( $this->request->segments_id, FILTER_VALIDATE_INT, FILTER_FORCE_ARRAY );

        $bulk_status_change = new BulkStatusChangeModel( $this->job, $segments_id ) ;
        if ( $bulk_status_change->anyChangeableSegmentStatus() ) {
            try {
                $bulk_status_change->changeStatusTo( $this->request->status ) ;

                $this->response->json( [
                        'data'                 => true,
                        'unchangeble_segments' => $bulk_status_change->getUnchangebleSegments(),
                        'stats'                => $bulk_status_change->getStats()
                ] );
            }
            catch(\Exception $e){
                $this->response->json( [
                        'data'                 => true,
                        'unchangeble_segments' => $bulk_status_change->getUnchangebleSegments()
                ] );
                return;
            }
        }

    }
}