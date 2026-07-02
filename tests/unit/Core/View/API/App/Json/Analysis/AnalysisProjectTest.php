<?php

namespace Matecat\Core\View\API\App\Json\Analysis;

use Matecat\TestHelpers\AbstractTest;
use Model\Analysis\Constants\StandardMatchTypeNamesConstants;
use PHPUnit\Framework\Attributes\CoversClass;
use View\API\App\Json\Analysis\AnalysisJob;
use View\API\App\Json\Analysis\AnalysisProject;
use View\API\App\Json\Analysis\AnalysisProjectSummary;

#[CoversClass(AnalysisProject::class)]
class AnalysisProjectTest extends AbstractTest
{
    private StandardMatchTypeNamesConstants $constants;
    private AnalysisProjectSummary $summary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->constants = new StandardMatchTypeNamesConstants();
        $this->summary   = new AnalysisProjectSummary(0, 100, 'ANALYZING');
    }

    private function makeProject(string $name = 'Test Project', string $status = 'ACTIVE'): AnalysisProject
    {
        return new AnalysisProject(
            $name,
            $status,
            '2024-01-01',
            'general',
            $this->summary,
            $this->constants
        );
    }

    public function testGetStatusReturnsStatus(): void
    {
        $project = $this->makeProject('P', 'DONE');

        $this->assertSame('DONE', $project->getStatus());
    }

    public function testSetStatus(): void
    {
        $project = $this->makeProject();
        $project->setStatus('NEW');

        $this->assertSame('NEW', $project->getStatus());
    }

    public function testGetNameReturnsName(): void
    {
        $project = $this->makeProject('My Project');

        $this->assertSame('My Project', $project->getName());
    }

    public function testGetCreateDateReturnsDate(): void
    {
        $project = $this->makeProject();

        $this->assertSame('2024-01-01', $project->getCreateDate());
    }

    public function testSetAndGetAnalyzeLink(): void
    {
        $project = $this->makeProject();
        $project->setAnalyzeLink('http://example.com/analyze');

        $this->assertSame('http://example.com/analyze', $project->getAnalyzeLink());
    }

    public function testGetSummaryReturnsSummary(): void
    {
        $project = $this->makeProject();

        $this->assertSame($this->summary, $project->getSummary());
    }

    public function testSetJobAndHasJob(): void
    {
        $project = $this->makeProject();
        $job     = $this->createStub(AnalysisJob::class);
        $job->method('getId')->willReturn(1);

        $project->setJob($job);

        $this->assertTrue($project->hasJob(1));
        $this->assertFalse($project->hasJob(2));
    }

    public function testGetJob(): void
    {
        $project = $this->makeProject();
        $job     = $this->createStub(AnalysisJob::class);
        $job->method('getId')->willReturn(1);

        $project->setJob($job);

        $this->assertSame($job, $project->getJob(1));
    }

    public function testGetJobs(): void
    {
        $project = $this->makeProject();
        $job     = $this->createStub(AnalysisJob::class);
        $job->method('getId')->willReturn(1);

        $project->setJob($job);

        $this->assertCount(1, $project->getJobs());
    }

    public function testSetJobReturnsSelf(): void
    {
        $project = $this->makeProject();
        $job     = $this->createStub(AnalysisJob::class);
        $job->method('getId')->willReturn(1);

        $this->assertSame($project, $project->setJob($job));
    }

    public function testJsonSerializeReturnsExpectedKeys(): void
    {
        $project = $this->makeProject('Proj', 'ACTIVE');
        $project->setAnalyzeLink('http://example.com');
        $result  = $project->jsonSerialize();

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('create_date', $result);
        $this->assertArrayHasKey('subject', $result);
        $this->assertArrayHasKey('workflow_type', $result);
        $this->assertArrayHasKey('jobs', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('analyze_url', $result);
        $this->assertSame('Proj', $result['name']);
        $this->assertSame('ACTIVE', $result['status']);
    }
}
