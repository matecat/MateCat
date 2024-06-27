<?php

namespace API\V3;

use API\App\Json\Analysis\AnalysisProject;
use API\V2\Exceptions\AuthenticationError;
use API\V2\Exceptions\NotFoundException;
use API\V2\KleinController;
use API\V2\Validators\LoginValidator;
use API\V2\Validators\ProjectPasswordValidator;
use Constants_JobStatus;
use Exceptions\ValidationError;
use Model\Analysis\Status;
use Projects_ProjectDao;

class StatusController extends KleinController {

    /**
     * Validation callbacks
     */
    public function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
        $this->appendValidator( new ProjectPasswordValidator( $this ) );
    }

    /**
     * @throws NotFoundException
     * @throws AuthenticationError
     * @throws \Exceptions\NotFoundException
     * @throws ValidationError
     */
    public function index() {

        $_project_data  = Projects_ProjectDao::getProjectAndJobData( $this->request->param( 'id_project' ) );
        $analysisStatus = new Status( $_project_data, $this->featureSet, $this->user );
        /**
         * @var AnalysisProject $result
         */
        $result = $analysisStatus->fetchData()->getResult();

        // return 404 if there are no chunks
        // (or they were deleted)
        $chunksCount         = 0;
        if ( !empty( $result->getJobs() ) ) {
            foreach ( $result->getJobs() as $j ) {
                foreach ( $j->getChunks() as $chunk ) {
                    if ( $chunk->getChunkStruct()->status_owner !== Constants_JobStatus::STATUS_DELETED ) {
                        $chunksCount++;
                    }
                }
            }
        }

        if ( $chunksCount === 0 ) {
            throw new NotFoundException( "The project doesn't have any jobs." );
        }

        $this->response->json( $result );

    }

}
