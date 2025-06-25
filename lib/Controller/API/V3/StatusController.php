<?php

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use Exception;
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
     * @throws Exception
     */
    public function index() {

        $_project_data  = Projects_ProjectDao::getProjectAndJobData( $this->request->param( 'id_project' ) );
        $analysisStatus = new Status( $_project_data, $this->featureSet, $this->user );
        $result = $analysisStatus->fetchData()->getResult();

        // return 404 if there are no chunks
        // (or they were deleted)
        $chunksCount = 0;
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
