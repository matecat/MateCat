<?php

namespace API\V2;

use API\Commons\KleinController;
use Jobs_JobDao;
use Jobs_JobStruct;
use LQA\ChunkReviewDao;
use ReflectionException;

abstract class BaseChunkController extends KleinController {

    /**
     * @var Jobs_JobStruct
     */
    protected Jobs_JobStruct $chunk;

    /**
     * @param $id_job
     * @param $password
     *
     * @return Jobs_JobStruct
     * @throws ReflectionException
     */
    protected function getJob( $id_job, $password ): Jobs_JobStruct {

        $job = Jobs_JobDao::getByIdAndPassword( $id_job, $password );

        if ( null === $job ) {
            $chunkReview = ChunkReviewDao::findByReviewPasswordAndJobId( $password, $id_job );
            if ( $chunkReview ) {
                $job = $chunkReview->getChunk();
            }
        }

        return $job;
    }

    /**
     * Return 404 if chunk was deleted
     */
    protected function return404IfTheJobWasDeleted() {
        if ( $this->chunk->isDeleted() ) {
            $this->response->status()->setCode( 404 );
            $this->response->json( [
                    'errors' => [
                            'code'    => 0,
                            'message' => 'No job found.'
                    ]
            ] );
            exit();
        }
    }
}