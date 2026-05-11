<?php

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class ForceSetSegmentAnalyzedSafetyTest extends AbstractTest
{
    #[Test]
    public function test_forceSetSegmentAnalyzed_aborts_side_effects_on_db_failure_and_noop_update(): void
    {
        $workerPath = realpath(__DIR__ . '/../../../../lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysisWorker.php');
        $this->assertNotFalse($workerPath);

        $source = file_get_contents($workerPath);
        $this->assertNotFalse($source);

        $this->assertStringContainsString(
            "\$affectedRows = \$db->update('segment_translations', \$data, \$where);",
            $source,
            'Expected _forceSetSegmentAnalyzed() to capture affected rows from DB update.'
        );

        $catchPos = strpos($source, 'catch (PDOException $e)');
        $this->assertNotFalse($catchPos, 'Expected PDOException catch block in _forceSetSegmentAnalyzed().');

        $zeroGuardPos = strpos($source, 'if ($affectedRows === 0)', $catchPos);
        $this->assertNotFalse($zeroGuardPos, 'Expected strict affectedRows===0 guard before Redis side-effects.');

        $catchBlock = substr($source, $catchPos, $zeroGuardPos - $catchPos);
        $this->assertStringContainsString(
            'return;',
            $catchBlock,
            'Expected early return in catch block to abort Redis side-effects when DB update fails.'
        );

        $incrementPos = strpos($source, '$this->_incrementAnalyzedCount', $zeroGuardPos);
        $this->assertNotFalse($incrementPos, 'Expected analyzed counter side-effect call.');

        $betweenZeroGuardAndIncrement = substr($source, $zeroGuardPos, $incrementPos - $zeroGuardPos);
        $this->assertStringContainsString(
            'already DONE, skipping force-set side-effects.',
            $betweenZeroGuardAndIncrement,
            'Expected explicit idempotency log message before early return on zero affected rows.'
        );
        $this->assertStringContainsString(
            'return;',
            $betweenZeroGuardAndIncrement,
            'Expected early return before Redis side-effects when affectedRows is zero.'
        );
    }
}
