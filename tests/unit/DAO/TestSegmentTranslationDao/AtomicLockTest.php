<?php

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class AtomicLockTest extends AbstractTest
{
    #[Test]
    public function test_tm_analysis_worker_uses_atomic_set_nx_ex_for_project_locks(): void
    {
        $workerPath = realpath(__DIR__ . '/../../../../lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysisWorker.php');
        $this->assertNotFalse($workerPath);

        $source = file_get_contents($workerPath);
        $this->assertNotFalse($source);

        $this->assertSame(
            0,
            preg_match('/\bsetnx\s*\(/', $source),
            'Expected no setnx() calls in TMAnalysisWorker.php'
        );

        $this->assertSame(
            2,
            preg_match_all(
                '/->set\s*\(\s*RedisKeys::PROJECT_(?:INIT|ENDING)_SEMAPHORE\s*\.\s*\$[A-Za-z_][A-Za-z0-9_]*\s*,\s*1\s*,\s*[\"\']EX[\"\']\s*,\s*86400\s*,\s*[\"\']NX[\"\']/s',
                $source
            ),
            'Expected both project semaphore lock acquisitions to use atomic set(..., \"EX\", 86400, \"NX\").'
        );
    }
}
