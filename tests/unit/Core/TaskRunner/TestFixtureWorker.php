<?php

namespace Utils\AsyncTasks\Workers;

use Utils\TaskRunner\Commons\AbstractElement;
use Utils\TaskRunner\Commons\AbstractWorker;

/**
 * @internal Test-only worker in an allowed namespace for Executor instantiation tests.
 */
class TestFixtureWorker extends AbstractWorker
{
    public bool $processCalled = false;
    public ?AbstractElement $lastElement = null;

    public function process(AbstractElement $queueElement): void
    {
        $this->processCalled = true;
        $this->lastElement = $queueElement;
    }

    public function getLogMsg(): array|string
    {
        return 'fixture worker log';
    }
}
