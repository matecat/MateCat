<?php

namespace API\V1;


use API\Commons\KleinController;
use API\Commons\Validators\ChunkPasswordValidator;
use API\V2\Validators\LoginValidator;
use CatUtils;
use Jobs_JobStruct;
use WordCount\WordCountStruct;

class StatsController extends KleinController {

    /**
     * @var Jobs_JobStruct
     */
    protected $chunk ;

    public function setChunk( Jobs_JobStruct $chunk ){
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
        $this->appendValidator( new LoginValidator( $this ) );

    }

}