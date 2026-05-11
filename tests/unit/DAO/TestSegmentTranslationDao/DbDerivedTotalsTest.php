<?php

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class DbDerivedTotalsTest extends AbstractTest
{
    #[Test]
    public function test_tryToCloseProject_persists_project_totals_from_db_rollup_instead_of_redis(): void
    {
        $workerPath = realpath(__DIR__ . '/../../../../lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysisWorker.php');
        $this->assertNotFalse($workerPath);

        $source = file_get_contents($workerPath);
        $this->assertNotFalse($source);

        $projectUpdatePos = strpos($source, 'ProjectDao::updateFields(');
        $this->assertNotFalse($projectUpdatePos, 'Expected ProjectDao::updateFields() in _tryToCloseProject().');

        $projectUpdateBlock = substr($source, $projectUpdatePos, 800);

        $this->assertStringContainsString("'tm_analysis_wc' => \$rollup['eq_wc']", $projectUpdateBlock);
        $this->assertStringContainsString("'standard_analysis_wc' => \$rollup['st_wc']", $projectUpdateBlock);
        $this->assertStringNotContainsString("'tm_analysis_wc' => \$project_totals['eq_wc']", $projectUpdateBlock);
        $this->assertStringNotContainsString("'standard_analysis_wc' => \$project_totals['st_wc']", $projectUpdateBlock);
    }

    #[Test]
    public function test_tryToCloseProject_distributes_job_standard_wc_from_db_rollup(): void
    {
        $workerPath = realpath(__DIR__ . '/../../../../lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysisWorker.php');
        $this->assertNotFalse($workerPath);

        $source = file_get_contents($workerPath);
        $this->assertNotFalse($source);

        $jobUpdatePos = strpos($source, 'JobDao::updateFields([');
        $this->assertNotFalse($jobUpdatePos, 'Expected JobDao::updateFields() in _tryToCloseProject().');

        $jobUpdateBlock = substr($source, $jobUpdatePos, 300);

        $this->assertStringContainsString("round(\$rollup['st_wc'] / \$numberOfJobs)", $jobUpdateBlock);
        $this->assertStringNotContainsString("round(\$project_totals['st_wc'] / \$numberOfJobs)", $jobUpdateBlock);
    }
}
