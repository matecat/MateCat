<?php

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class InitRaceWaitTest extends AbstractTest
{
    #[Test]
    public function test_tm_analysis_worker_waits_for_project_totals_when_init_lock_is_lost(): void
    {
        $workerPath = realpath(__DIR__ . '/../../../../lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysisWorker.php');
        $this->assertNotFalse($workerPath);

        $source = file_get_contents($workerPath);
        $this->assertNotFalse($source);

        $this->assertSame(
            1,
            preg_match(
                '/function\s+_initializeTMAnalysis\s*\([^)]*\)\s*:\s*void\s*\{[\s\S]*usleep\(\$sleepMs\s*\*\s*1000\);[\s\S]*\}\s*\n\s*\/\*\*/',
                $source
            ),
            'Expected usleep($sleepMs * 1000) inside _initializeTMAnalysis()'
        );

        $this->assertStringContainsString('$maxWaitMs = 5000;', $source);
        $this->assertStringContainsString('$sleepMs = min($sleepMs * 2, 500);', $source);
        $this->assertStringContainsString('timed out waiting for PROJECT_TOT_SEGMENTS', $source);
    }
}
