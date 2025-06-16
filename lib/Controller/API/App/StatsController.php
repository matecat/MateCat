<?php

namespace API\App;

use AbstractControllers\KleinController;
use API\Commons\Validators\ChunkPasswordValidator;
use API\Commons\Validators\LoginValidator;
use CatUtils;
use Jobs_JobStruct;
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
     * @var Jobs_JobStruct
     */
    protected $chunk;

    public function setChunk( Jobs_JobStruct $chunk ) {
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
        $this->appendValidator( new LoginValidator( $this ) );
    }

}
