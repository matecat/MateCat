<?php

namespace API\V3;

use API\V2\BaseChunkController;
use API\V2\Exceptions\NotFoundException;
use API\V2\Validators\ChunkPasswordValidator;
use Chunks_ChunkStruct;
use Revise\FeedbackDAO;
use Revise\FeedbackStruct;

class RevisionFeedbackController extends BaseChunkController {

    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk;

    /**
     * @param Chunks_ChunkStruct $chunk
     *
     * @return $this
     */
    public function setChunk( $chunk ) {
        $this->chunk = $chunk;

        return $this;
    }

    public function feedback() {

        // insert or update feedback
        $feedbackStruct = new FeedbackStruct();
        $feedbackStruct->id_job = $this->request->param( 'id_job' );
        $feedbackStruct->password = $this->request->param( 'password' );
        $feedbackStruct->revision_number = $this->request->param( 'revision_number' );
        $feedbackStruct->feedback = $this->request->param( 'feedback' );

        // check if job exists and it is not deleted
        $job = $this->getJob( $feedbackStruct->id_job, $feedbackStruct->password );

        if ( null === $job ) {
            throw new NotFoundException( 'Job not found.' );
        }

        $this->chunk = $job;
        $this->return404IfTheJobWasDeleted();

        $rows = (new FeedbackDAO())->insertOrUpdate($feedbackStruct);
        $status = ($rows > 0) ? 'ok' : 'ko';

        $this->response->json( [
                'status' => $status
        ] );
    }

    protected function afterConstruct() {
        $validator = new ChunkPasswordValidator( $this ) ;
        $controller = $this;
        $validator->onSuccess( function () use ( $validator, $controller ) {
            $controller->setChunk( $validator->getChunk() );
        } );

        $this->appendValidator( $validator );
    }
}

