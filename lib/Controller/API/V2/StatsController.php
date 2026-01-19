<?php

namespace Controller\API\V2;


use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Model\Jobs\JobStruct;
use Model\WordCount\WordCountStruct;
use Utils\Tools\CatUtils;

class StatsController extends KleinController
{

    /**
     * @var ?JobStruct
     */
    protected ?JobStruct $chunk = null;

    /**
     * @return void
     */
    public function stats(): void
    {
        $wStruct = WordCountStruct::loadFromJob($this->chunk);
        $job_stats = CatUtils::getFastStatsForJob($wStruct);
        $job_stats['analysis_complete'] = $this->chunk->getProject()->analysisComplete();

        $this->response->json($job_stats);
    }

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
        $Validator = (new ChunkPasswordValidator($this));
        $Validator->onSuccess(function () use ($Validator) {
            $this->chunk = $Validator->getChunk();
        });

        $this->appendValidator($Validator);
    }

}