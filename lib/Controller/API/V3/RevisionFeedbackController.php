<?php

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Interfaces\ChunkPasswordValidatorInterface;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Model\Jobs\JobStruct;
use Model\ReviseFeedback\FeedbackDAO;
use Model\ReviseFeedback\FeedbackStruct;
use ReflectionException;

class RevisionFeedbackController extends KleinController implements ChunkPasswordValidatorInterface {
    use ChunkNotFoundHandlerTrait;

    protected int    $id_job;
    protected string $jobPassword;

    /**
     * @param int $id_job
     *
     * @return $this
     */
    public function setIdJob( int $id_job ): static {
        $this->id_job = $id_job;

        return $this;
    }

    /**
     * @param string $jobPassword
     *
     * @return $this
     */
    public function setJobPassword( string $jobPassword ): static {
        $this->jobPassword = $jobPassword;

        return $this;
    }


    /**
     * @param JobStruct $chunk
     *
     * @return $this
     */
    public function setChunk( JobStruct $chunk ): RevisionFeedbackController {
        $this->chunk = $chunk;

        return $this;
    }

    /**
     * @throws ReflectionException
     * @throws NotFoundException
     */
    public function feedback(): void {

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

    protected function afterConstruct(): void {
        $validator  = new ChunkPasswordValidator( $this );
        $controller = $this;
        $validator->onSuccess( function () use ( $validator, $controller ) {
            $controller->setChunk( $validator->getChunk() );
        } );

        $this->appendValidator( $validator );
        $this->appendValidator( new LoginValidator( $this ) );
    }
}

