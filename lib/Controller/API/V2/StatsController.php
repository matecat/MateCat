<?php

namespace Controller\API\V2;


use CatUtils;
use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Model\Jobs\JobStruct;
use WordCount\WordCountStruct;

class StatsController extends KleinController {

    /**
     * @var ?\Model\Jobs\JobStruct
     */
    protected ?JobStruct $chunk = null;

    public function setChunk( JobStruct $chunk ) {
        $this->chunk = $chunk;
    }

    /**
     * @return void
     */
    public function stats() {
        $wStruct                          = WordCountStruct::loadFromJob( $this->chunk );
        $job_stats                        = CatUtils::getFastStatsForJob( $wStruct );
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
        $this->appendValidator( new LoginValidator( $this ) );

    }

}