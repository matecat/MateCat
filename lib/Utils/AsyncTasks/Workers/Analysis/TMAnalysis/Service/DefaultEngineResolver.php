<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service;

use Exception;
use Utils\Engines\AbstractEngine;
use Utils\Engines\EnginesFactory;
use Model\DataAccess\IDatabase;

class DefaultEngineResolver
{
    public function __construct(private readonly IDatabase $database)
    {
    }

    /**
     * Resolve an engine instance by its database ID.
     *
     * @throws Exception When engine cannot be found or instantiated
     */
    public function getInstance(int $id): AbstractEngine
    {
        return EnginesFactory::getInstance($id, $this->database, AbstractEngine::class);
    }
}
