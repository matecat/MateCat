<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 31/05/2017
 * Time: 14:57
 */


namespace Features\ProjectCompletion\Controller;

use API\V2\Validators\ChunkPasswordValidator;
use BaseKleinViewController;
use Chunks_ChunkCompletionEventDao;
use Chunks_ChunkCompletionEventStruct;
use Chunks_ChunkStruct;
use Database;
use Exception;

class CompletionEventController extends BaseKleinViewController {

    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk;

    /**
     * @var \Projects_ProjectStruct
     */
    protected $project;

    /**
     * @param \Projects_ProjectStruct $project
     */
    public function setProject( \Projects_ProjectStruct $project ){
        $this->project = $project;
    }

    /**
     * @param Chunks_ChunkStruct $chunk
     *
     * @return $this
     */
    public function setChunk( $chunk ) {
        $this->chunk = $chunk;

        return $this;
    }

    /**
     * @var Chunks_ChunkCompletionEventStruct
     */
    protected $event;

    /**
     * @param Chunks_ChunkCompletionEventStruct $event
     */
    public function setEvent( Chunks_ChunkCompletionEventStruct $event ) {
        $this->event = $event;
    }

    /**
     * @throws Exception
     */
    protected function afterConstruct() {

        $Controller = $this;
        $Validator  = new ChunkPasswordValidator( $this );
        $Validator->onSuccess( function () use ( $Controller, $Validator ) {

            $event = ( new Chunks_ChunkCompletionEventDao() )->getByIdAndChunk( $Controller->getParams()[ 'id_event' ], $Validator->getChunk() );

            if ( !$event ) {
                throw new \Exceptions\NotFoundException( "Event Not Found.", 404 );
            }

            $Controller->setChunk( $Validator->getChunk() );

            $project = $this->chunk->getProject( 60 * 60 );
            $Controller->setProject( $project );
            $Controller->setEvent( $event );
            $Controller->featureSet->loadForProject( $project );

        } );

        $this->appendValidator( $Validator );

    }

    /**
     * @throws \Exceptions\NotFoundException
     * @throws \API\V2\Exceptions\AuthenticationError
     * @throws \Exceptions\ValidationError
     */
    public function delete() {

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
     * @throws \Exceptions\NotFoundException
     * @throws \API\V2\Exceptions\AuthenticationError
     * @throws \Exceptions\ValidationError
     */
    private function __performUndo() {

        Database::obtain()->begin();

        /**
         * This method means to allow project_completion to work alone, the undo feature belongs to AbstractRevisionFeature
         */
        $this->featureSet->filter( 'alter_chunk_review_struct', $this->event );

        ( new Chunks_ChunkCompletionEventDao() )->deleteEvent( $this->event );
        Database::obtain()->commit();

    }

}