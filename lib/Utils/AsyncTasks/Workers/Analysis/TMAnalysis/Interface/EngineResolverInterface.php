<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface;

use Exception;
use Utils\Engines\AbstractEngine;

interface EngineResolverInterface
{
    /**
     * Resolve an engine instance by its database ID.
     *
     * @param int $id Engine ID from database
     *
     * @return AbstractEngine
     * @throws Exception When engine cannot be found or instantiated
     */
    public function getInstance(int $id): AbstractEngine;
}
