<?php

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class ProjectWordCountNumAnalyzedTest extends AbstractTest
{
    #[Test]
    public function test_project_word_count_query_counts_done_and_skipped_as_analyzed(): void
    {
        $traitPath = realpath(__DIR__ . '/../../../../lib/Utils/AsyncTasks/Workers/Traits/ProjectWordCount.php');
        $this->assertNotFalse($traitPath);

        $source = file_get_contents($traitPath);
        $this->assertNotFalse($source);

        $this->assertStringContainsString(
            "SUM(IF(st.tm_analysis_status IN ('DONE', 'SKIPPED'), 1, 0)) AS num_analyzed",
            $source,
            'num_analyzed must count both DONE and SKIPPED segments so completion is not blocked by pre-translated segments.'
        );
        $this->assertStringNotContainsString('0 AS num_analyzed', $source);
    }
}
