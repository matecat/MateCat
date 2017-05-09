<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/05/2017
 * Time: 12:01
 */

namespace Features\ProjectCompletion\Model ;

use Chunks_ChunkCompletionEventDao;
use FeatureSet ;
use Projects_ProjectDao ;


class EventModel {

    protected $params ;
    protected $chunk ;
    protected $chunkCompletionEventId ;

    public function __construct( $chunk, $params ) {
        $this->params = $params ;
        $this->chunk = $chunk ;
    }

    public function save() {
        $this->chunkCompletionEventId = Chunks_ChunkCompletionEventDao::createFromChunk(
                $this->chunk, $this->params
        );

        $featureSet = new FeatureSet() ;
        $featureSet->loadForProject( Projects_ProjectDao::findById($this->chunk->id_project ) );
        $featureSet->run('project_completion_event_saved', $this->chunk, $this->params, $this->chunkCompletionEventId );
    }

}