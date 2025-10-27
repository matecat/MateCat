<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 31/05/2017
 * Time: 14:57
 */


namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Interfaces\ChunkPasswordValidatorInterface;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Exception;
use Model\ChunksCompletion\ChunkCompletionEventDao;
use Model\ChunksCompletion\ChunkCompletionEventStruct;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;

class CompletionEventController extends KleinController implements ChunkPasswordValidatorInterface {

    protected int    $id_job;
    protected string $jobPassword;

    /**
     * @var JobStruct
     */
    protected JobStruct $chunk;

    /**
     * @var ProjectStruct
     */
    protected ProjectStruct $project;

    /**
     * @param ProjectStruct $project
     */
    public function setProject( ProjectStruct $project ): void {
        $this->project = $project;
    }

    /**
     * @param JobStruct $chunk
     *
     * @return $this
     */
    public function setChunk( JobStruct $chunk ): static {
        $this->chunk = $chunk;

        return $this;
    }

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
     * @param string $password
     *
     * @return $this
     */
    public function setJobPassword( string $password ): static {
        $this->jobPassword = $password;

        return $this;
    }



    /**
     * @var ChunkCompletionEventStruct
     */
    protected ChunkCompletionEventStruct $event;

    /**
     * @param ChunkCompletionEventStruct $event
     */
    public function setEvent( ChunkCompletionEventStruct $event ): void {
        $this->event = $event;
    }

    /**
     * @throws Exception
     */
    protected function afterConstruct() {

        $Validator  = new ChunkPasswordValidator( $this );
        $Validator->onSuccess( function () use ( $Validator ) {

            $event = ( new ChunkCompletionEventDao() )->getByIdAndChunk( $this->getParams()[ 'id_event' ], $Validator->getChunk() );

            if ( !$event ) {
                throw new NotFoundException( "Event Not Found.", 404 );
            }

            $this->setChunk( $Validator->getChunk() );

            $project = $this->chunk->getProject( 60 * 60 );
            $this->setProject( $project );
            $this->setEvent( $event );
            $this->featureSet->loadForProject( $project );

        } );

        $this->appendValidator( $Validator );

    }

    /**
     * @throws Exception
     */
    public function delete(): void {

        $undoable = true;

        $undoable = $this->featureSet->filter( 'filterIsChunkCompletionUndoable', $undoable, $this->project, $this->chunk );

        if ( $undoable ) {
            $this->__performUndo();
            $this->response->code( 200 );
            $this->response->send();
        } else {
            $this->response->code( 400 );
        }

    }

    /**
     * @throws Exception
     */
    private function __performUndo(): void {

        Database::obtain()->begin();

        /**
         * This method means to allow project_completion to work alone, the undo feature belongs to AbstractRevisionFeature
         */
        $this->featureSet->filter( 'alter_chunk_review_struct', $this->event );

        ( new ChunkCompletionEventDao() )->deleteEvent( $this->event );
        Database::obtain()->commit();

    }

}