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
        $status      = strtoupper( $this->request->status );
        if ( in_array( $status, [ \Constants_TranslationStatus::STATUS_TRANSLATED, \Constants_TranslationStatus::STATUS_APPROVED ] ) ) {
            $unchangeble_segments = \Translations_SegmentTranslationDao::getUnchangebleStatus( $segments_id, $status );

            $segments_id = array_diff( $segments_id, $unchangeble_segments );

            if ( !empty( $segments_id ) ) {
                try{
                    $stats = \Translations_SegmentTranslationDao::changeStatusBySegmentsIds( $this->job, $segments_id, $status );
                }
                catch(\Exception $e){
                    $this->response->json( [ 'data' => true, 'unchangeble_segments' => $segments_id] );
                    return;
                }
            }

            $this->response->json( [ 'data' => true, 'unchangeble_segments' => $unchangeble_segments, 'stats' => $stats ] );
        }
    }

}