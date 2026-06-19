<?php

namespace Matecat\Core\Model\Projects;

use Matecat\TestHelpers\AbstractTest;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;
use Utils\Constants\ProjectStatus;

class ProjectStructTest extends AbstractTest
{
    #[Test]
    public function analysisCompleteReturnsTrueWhenStatusIsDone(): void
    {
        $project = new ProjectStruct();
        $project->status_analysis = ProjectStatus::STATUS_DONE;

        $this->assertTrue($project->analysisComplete());
    }

    #[Test]
    public function analysisCompleteReturnsTrueWhenStatusIsNotToAnalyze(): void
    {
        $project = new ProjectStruct();
        $project->status_analysis = ProjectStatus::STATUS_NOT_TO_ANALYZE;

        $this->assertTrue($project->analysisComplete());
    }

    #[Test]
    public function analysisCompleteReturnsFalseForOtherStatus(): void
    {
        $project = new ProjectStruct();
        $project->status_analysis = ProjectStatus::STATUS_BUSY;

        $this->assertFalse($project->analysisComplete());
    }
}
