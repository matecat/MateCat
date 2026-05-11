<?php

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class ProjectWordCountNumAnalyzedTest extends AbstractTest
{
    #[Test]
    public function test_project_word_count_query_derives_num_analyzed_from_tm_analysis_status(): void
    {
        $traitPath = realpath(__DIR__ . '/../../../../lib/Utils/AsyncTasks/Workers/Traits/ProjectWordCount.php');
        $this->assertNotFalse($traitPath);

        $source = file_get_contents($traitPath);
        $this->assertNotFalse($source);

        $this->assertStringContainsString(
            "SUM(IF(st.tm_analysis_status = 'DONE', 1, 0)) AS num_analyzed",
            $source
        );
        $this->assertStringNotContainsString('0 AS num_analyzed', $source);
    }
}
