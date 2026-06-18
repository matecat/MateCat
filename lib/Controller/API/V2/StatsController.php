<?php

namespace Controller\API\V2;


use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use DivisionByZeroError;
use Exception;
use Model\Jobs\JobStruct;
use Model\WordCount\WordCountStruct;
use RuntimeException;
use TypeError;
use Utils\Tools\CatUtils;

class StatsController extends KleinController
{

    /**
     * @var ?JobStruct
     */
    protected ?JobStruct $chunk = null;

    /**
     * @return void
     * @throws Exception
     * @throws TypeError
     * @throws DivisionByZeroError
     */
    public function stats(): void
    {
        $chunk = $this->chunk ?? throw new RuntimeException('Chunk not found');
        $wStruct = WordCountStruct::loadFromJob($chunk);
        $job_stats = (new CatUtils())->getFastStatsForJob($wStruct);
        $job_stats['analysis_complete'] = $chunk->getProject()->analysisComplete();

        $this->response->json($job_stats);
    }

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
        $Validator = (new ChunkPasswordValidator($this));
        $Validator->onSuccess(function () use ($Validator) {
            $this->chunk = $Validator->getChunk();
        });

        $this->appendValidator($Validator);
    }

}