<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/05/2017
 * Time: 12:01
 */

namespace Features\ProjectCompletion\Model ;

use Chunks_ChunkCompletionEventDao;
use Chunks_ChunkStruct;
use Exception;
use Features\ProjectCompletion\CompletionEventStruct;
use FeatureSet;
use Projects_ProjectDao;


class EventModel {

    /**
     * @var CompletionEventStruct
     */
    protected $eventStruct ;
    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk ;
    protected $chunkCompletionEventId ;



    public function __construct( Chunks_ChunkStruct $chunk, CompletionEventStruct $eventStruct ) {
        $this->eventStruct = $eventStruct ;
        $this->chunk = $chunk ;
    }

    public function save() {
        $this->_checkStatusIsValid();

        $this->chunkCompletionEventId = Chunks_ChunkCompletionEventDao::createFromChunk(
                $this->chunk, $this->eventStruct
        );

        $featureSet = new FeatureSet() ;
        $featureSet->loadForProject( Projects_ProjectDao::findById($this->chunk->id_project ) );
        $featureSet->run('project_completion_event_saved', $this->chunk, $this->eventStruct, $this->chunkCompletionEventId );
    }

    public function getChunkCompletionEventId() {
        return $this->chunkCompletionEventId ;
    }

    private function _checkStatusIsValid() {
        $dao = new Chunks_ChunkCompletionEventDao();
        $current_phase = $dao->currentPhase( $this->chunk );

        if (
                (  $this->eventStruct->is_review && $current_phase != Chunks_ChunkCompletionEventDao::REVISE ) ||
                ( !$this->eventStruct->is_review && $current_phase != Chunks_ChunkCompletionEventDao::TRANSLATE )
        ) {
            throw new Exception('Cannot save event, current status mismatch.') ;
        }
    }
}