<?php

namespace API\App;

use API\V2\KleinController;
use API\V2\Validators\ChunkPasswordValidator;
use CatUtils;
use Chunks_ChunkStruct;
use WordCount\WordCountStruct;

/**
 * Class StatsController
 *
 * This class was copied from API\V1 instead of extended so to favour a complete refactoring.
 *
 * @package API\App
 */
class StatsController extends KleinController {

    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk;

    public function setChunk( Chunks_ChunkStruct $chunk ) {
        $this->chunk = $chunk;
    }

    /**
     * @return void
     */
    public function stats() {

        $wStruct = WordCountStruct::loadFromJob( $this->chunk );
        $job_stats   = CatUtils::getFastStatsForJob( $wStruct );
        $job_stats[ 'analysis_complete' ] = $this->chunk->getProject()->analysisComplete();

        $this->response->json( $job_stats );
    }

    protected function afterConstruct() {
        $Validator  = ( new ChunkPasswordValidator( $this ) );
        $Controller = $this;
        $Validator->onSuccess( function () use ( $Validator, $Controller ) {
            $Controller->setChunk( $Validator->getChunk() );
        } );

        $this->appendValidator( $Validator );
    }

}
