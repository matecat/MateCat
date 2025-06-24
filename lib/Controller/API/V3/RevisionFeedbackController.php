<?php

namespace API\V3;
use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Jobs_JobStruct;
use Revise\FeedbackDAO;
use Revise\FeedbackStruct;

class RevisionFeedbackController extends KleinController {
    use ChunkNotFoundHandlerTrait;
    /**
     * @param Jobs_JobStruct $chunk
     *
     * @return $this
     */
    public function setChunk( $chunk ) {
        $this->chunk = $chunk;

        return $this;
    }

    public function feedback() {

        // insert or update feedback
        $feedbackStruct                  = new FeedbackStruct();
        $feedbackStruct->id_job          = $this->request->param( 'id_job' );
        $feedbackStruct->password        = $this->request->param( 'password' );
        $feedbackStruct->revision_number = $this->request->param( 'revision_number' );
        $feedbackStruct->feedback        = $this->request->param( 'feedback' );

        // check if job exists and it is not deleted
        $job = $this->getJob( $feedbackStruct->id_job, $feedbackStruct->password );

        if ( null === $job ) {
            throw new NotFoundException( 'Job not found.' );
        }

        $this->chunk = $job;
        $this->return404IfTheJobWasDeleted();

        $rows   = ( new FeedbackDAO() )->insertOrUpdate( $feedbackStruct );
        $status = ( $rows > 0 ) ? 'ok' : 'ko';

        $this->response->json( [
                'status' => $status
        ] );
    }

    protected function afterConstruct() {
        $validator  = new ChunkPasswordValidator( $this );
        $controller = $this;
        $validator->onSuccess( function () use ( $validator, $controller ) {
            $controller->setChunk( $validator->getChunk() );
        } );

        $this->appendValidator( $validator );
        $this->appendValidator( new LoginValidator( $this ) );
    }
}

