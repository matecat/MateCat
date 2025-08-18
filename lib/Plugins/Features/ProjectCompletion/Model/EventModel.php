<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/05/2017
 * Time: 12:01
 */

namespace Plugins\Features\ProjectCompletion\Model ;

use Controller\Features\ProjectCompletion\CompletionEventStruct;
use Exception;
use Model\ChunksCompletion\ChunkCompletionEventDao;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectDao;


class EventModel {

    /**
     * @var CompletionEventStruct
     */
    protected $eventStruct ;
    /**
     * @var \Model\Jobs\JobStruct
     */
    protected $chunk ;
    protected $chunkCompletionEventId ;



    public function __construct( JobStruct $chunk, CompletionEventStruct $eventStruct ) {
        $this->eventStruct = $eventStruct ;
        $this->chunk = $chunk ;
    }

    public function save() {
        $this->_checkStatusIsValid();

        $this->chunkCompletionEventId = ChunkCompletionEventDao::createFromChunk(
                $this->chunk, $this->eventStruct
        );

        $featureSet = new FeatureSet() ;
        $featureSet->loadForProject( ProjectDao::findById($this->chunk->id_project ) );
        $featureSet->run('project_completion_event_saved', $this->chunk, $this->eventStruct, $this->chunkCompletionEventId );
    }

    public function getChunkCompletionEventId() {
        return $this->chunkCompletionEventId ;
    }

    private function _checkStatusIsValid() {
        $dao = new ChunkCompletionEventDao();
        $current_phase = $dao->currentPhase( $this->chunk );

        if (
                (  $this->eventStruct->is_review && $current_phase != ChunkCompletionEventDao::REVISE ) ||
                ( !$this->eventStruct->is_review && $current_phase != ChunkCompletionEventDao::TRANSLATE )
        ) {
            throw new Exception('Cannot save event, current status mismatch.') ;
        }
    }
}