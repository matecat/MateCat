<?php

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class FinalizationCrashSafeTest extends AbstractTest
{
    #[Test]
    public function test_tryToCloseProject_releases_lock_and_requeues_project_on_finalization_failure(): void
    {
        $workerPath = realpath(__DIR__ . '/../../../../lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysisWorker.php');
        $this->assertNotFalse($workerPath);

        $source = file_get_contents($workerPath);
        $this->assertNotFalse($source);

        $tryToCloseProjectPos = strpos($source, 'protected function _tryToCloseProject');
        $this->assertNotFalse($tryToCloseProjectPos, 'Expected _tryToCloseProject() method.');

        $catchPos = strpos($source, 'catch (\Throwable $e)', $tryToCloseProjectPos);
        $this->assertNotFalse($catchPos, 'Expected catch (\\Throwable $e) in _tryToCloseProject().');

        $methodTail = substr($source, $catchPos, 2500);

        $this->assertStringContainsString(
            '->rollback()',
            $methodTail,
            'Expected rollback in _tryToCloseProject() failure catch block.'
        );

        $this->assertStringContainsString(
            '->del(RedisKeys::PROJECT_ENDING_SEMAPHORE',
            $methodTail,
            'Expected semaphore release in _tryToCloseProject() failure catch block.'
        );

        $this->assertStringContainsString(
            '->rpush($this->_myContext->redis_key, $_project_id)',
            $methodTail,
            'Expected project requeue in _tryToCloseProject() failure catch block.'
        );
    }
}
