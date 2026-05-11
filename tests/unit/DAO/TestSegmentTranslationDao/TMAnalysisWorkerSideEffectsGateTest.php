<?php

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class TMAnalysisWorkerSideEffectsGateTest extends AbstractTest
{
    #[Test]
    public function test_updateRecord_returns_early_when_analysis_row_is_already_done(): void
    {
        $workerPath = realpath(__DIR__ . '/../../../../lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysisWorker.php');
        $this->assertNotFalse($workerPath);

        $source = file_get_contents($workerPath);
        $this->assertNotFalse($source);

        $guardPos = strpos($source, 'if ($updateRes === 0)');
        $this->assertNotFalse($guardPos, 'Expected strict guard for already-DONE rows.');

        $incrementPos = strpos($source, '$this->_incrementAnalyzedCount');
        $this->assertNotFalse($incrementPos, 'Expected analyzed counter side-effect call.');

        $betweenGuardAndIncrement = substr($source, $guardPos, $incrementPos - $guardPos);

        $this->assertStringContainsString(
            'not updated (already DONE/SKIPPED or missing), skipping side-effects',
            $betweenGuardAndIncrement,
            'Expected explicit idempotency log message before early return.'
        );
        $this->assertStringContainsString(
            'return;',
            $betweenGuardAndIncrement,
            'Expected early return before Redis side-effects when updateRes is zero.'
        );
    }
}
