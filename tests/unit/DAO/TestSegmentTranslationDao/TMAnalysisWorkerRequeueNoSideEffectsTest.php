<?php

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class TMAnalysisWorkerRequeueNoSideEffectsTest extends AbstractTest
{
    #[Test]
    public function test_getMatches_requeue_exception_does_not_force_set_segment_analyzed_before_rethrow(): void
    {
        $workerPath = realpath(__DIR__ . '/../../../../lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysisWorker.php');
        $this->assertNotFalse($workerPath);

        $source = file_get_contents($workerPath);
        $this->assertNotFalse($source);

        $catchPos = strpos($source, 'catch (ReQueueException $rEx)');
        $this->assertNotFalse($catchPos, 'Expected ReQueueException catch block in _getMatches().');

        $throwPos = strpos($source, 'throw $rEx', $catchPos);
        $this->assertNotFalse($throwPos, 'Expected rethrow in ReQueueException catch block.');

        $requeueCatchBody = substr($source, $catchPos, $throwPos - $catchPos);

        $this->assertStringNotContainsString(
            '$this->_forceSetSegmentAnalyzed($queueElement);',
            $requeueCatchBody,
            'Requeue path must not increment analyzed counters before rethrowing.'
        );
    }
}
