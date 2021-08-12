<?php

namespace API\V2;

use Chunks_ChunkStruct;
use LQA\ChunkReviewDao;

abstract class BaseChunkController extends KleinController {

    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk;

    /**
     * @param $id_job
     * @param $password
     *
     * @return \Chunks_ChunkStruct|\DataAccess_IDaoStruct|\Jobs_JobStruct
     */
    protected function getJob( $id_job, $password ) {

        $job = \Jobs_JobDao::getByIdAndPassword( $id_job, $password );

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
        if($this->chunk->wasDeleted()){
            $this->response->status()->setCode( 404 );
            $this->response->json( [
                    'errors' => [
                            'code' => 0,
                            'message' => 'No job found.'
                    ]
            ] );
            exit();
        }
    }
}