<?php

namespace Controller\API\V2;


use Controller\Abstracts\KleinController;
use Controller\API\Commons\Interfaces\ChunkPasswordValidatorInterface;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Model\Jobs\JobStruct;
use Model\WordCount\WordCountStruct;
use Utils\Tools\CatUtils;

class StatsController extends KleinController implements ChunkPasswordValidatorInterface {

    protected int    $id_job;
    protected string $jobPassword;

    /**
     * @param int $id_job
     *
     * @return $this
     */
    public function setIdJob( int $id_job ): static {
        $this->id_job = $id_job;

        return $this;
    }

    /**
     * @param string $jobPassword
     *
     * @return $this
     */
    public function setJobPassword( string $jobPassword ): static {
        $this->jobPassword = $jobPassword;

        return $this;
    }

    /**
     * @var ?JobStruct
     */
    protected ?JobStruct $chunk = null;

    public function setChunk( JobStruct $chunk ): void {
        $this->chunk = $chunk;
    }

    /**
     * @return void
     */
    public function stats(): void {
        $wStruct                          = WordCountStruct::loadFromJob( $this->chunk );
        $job_stats                        = CatUtils::getFastStatsForJob( $wStruct );
        $job_stats[ 'analysis_complete' ] = $this->chunk->getProject()->analysisComplete();

        $this->response->json( $job_stats );
    }

    protected function afterConstruct(): void {

        $Validator  = ( new ChunkPasswordValidator( $this ) );
        $Controller = $this;
        $Validator->onSuccess( function () use ( $Validator, $Controller ) {
            $Controller->setChunk( $Validator->getChunk() );
        } );

        $this->appendValidator( $Validator );
        $this->appendValidator( new LoginValidator( $this ) );

    }

}