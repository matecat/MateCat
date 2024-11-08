<?php

namespace API\V3;

use API\App\Json\Analysis\AnalysisProject;
use API\Commons\Exceptions\NotFoundException;
use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use API\Commons\Validators\ProjectPasswordValidator;
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
     * @throws \Exceptions\NotFoundException
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
                    if ( !$chunk->getChunkStruct()->isDeleted() ) {
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
