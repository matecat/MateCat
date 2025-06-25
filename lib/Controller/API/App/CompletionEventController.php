<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 31/05/2017
 * Time: 14:57
 */


namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Database;
use Exception;
use Jobs_JobStruct;
use Model\ChunksCompletion\ChunkCompletionEventDao;
use Model\ChunksCompletion\ChunkCompletionEventStruct;

class CompletionEventController extends KleinController {

    /**
     * @var Jobs_JobStruct
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
     * @param Jobs_JobStruct $chunk
     *
     * @return $this
     */
    public function setChunk( $chunk ) {
        $this->chunk = $chunk;

        return $this;
    }

    /**
     * @var ChunkCompletionEventStruct
     */
    protected $event;

    /**
     * @param ChunkCompletionEventStruct $event
     */
    public function setEvent( ChunkCompletionEventStruct $event ) {
        $this->event = $event;
    }

    /**
     * @throws Exception
     */
    protected function afterConstruct() {

        $Controller = $this;
        $Validator  = new ChunkPasswordValidator( $this );
        $Validator->onSuccess( function () use ( $Controller, $Validator ) {

            $event = ( new ChunkCompletionEventDao() )->getByIdAndChunk( $Controller->getParams()[ 'id_event' ], $Validator->getChunk() );

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
     * @throws Exception
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
     * @throws Exception
     */
    private function __performUndo() {

        Database::obtain()->begin();

        /**
         * This method means to allow project_completion to work alone, the undo feature belongs to AbstractRevisionFeature
         */
        $this->featureSet->filter( 'alter_chunk_review_struct', $this->event );

        ( new ChunkCompletionEventDao() )->deleteEvent( $this->event );
        Database::obtain()->commit();

    }

}