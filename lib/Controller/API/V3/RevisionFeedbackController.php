<?php

namespace API\V3;

use API\V2\KleinController;
use API\V2\Validators\ChunkPasswordValidator;
use Chunks_ChunkStruct;
use Revise_FeedbackDAO;
use Revise_FeedbackStruct;

class RevisionFeedbackController extends KleinController {

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
        $feedbackStruct = new Revise_FeedbackStruct();
        $feedbackStruct->id_job = $this->request->param( 'id_job' );
        $feedbackStruct->password = $this->request->param( 'password' );
        $feedbackStruct->revision_number = $this->request->param( 'revision_number' );
        $feedbackStruct->feedback = $this->request->param( 'feedback' );

        $rows = (new Revise_FeedbackDAO())->insertOrUpdate($feedbackStruct);
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

