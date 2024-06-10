<?php

namespace API\V1;


use API\V2\KleinController;
use API\V2\Validators\ChunkPasswordValidator;
use CatUtils;
use Chunks_ChunkStruct;
use WordCount\WordCountStruct;

class StatsController extends KleinController {

    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk ;

    public function setChunk( Chunks_ChunkStruct $chunk ){
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

        $Validator = ( new ChunkPasswordValidator( $this ) );
        $Controller = $this;
        $Validator->onSuccess( function () use ( $Validator, $Controller ) {
            $Controller->setChunk( $Validator->getChunk() );
        } );

        $this->appendValidator( $Validator );

    }

}