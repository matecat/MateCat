<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service;

use Exception;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\EngineResolverInterface;
use Utils\Engines\AbstractEngine;
use Utils\Engines\EnginesFactory;
use Model\DataAccess\IDatabase;

class DefaultEngineResolver implements EngineResolverInterface
{
    /**
     * @throws Exception
     */
    public function __construct(private readonly IDatabase $database)
    {
    }

    public function getInstance(int $id): AbstractEngine
    {
        return EnginesFactory::getInstance($id, $this->database, AbstractEngine::class);
    }
}
