<?php

namespace Matecat\Core\View\API\App\Json\Analysis;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\CoversClass;
use Utils\Constants\ProjectStatus;
use View\API\App\Json\Analysis\AnalysisProjectSummary;

#[CoversClass(AnalysisProjectSummary::class)]
class AnalysisProjectSummaryTest extends AbstractTest
{
    private function makeSummary(
        int    $inQueueBefore = 0,
        int    $totalSegments = 100,
        string $status = ProjectStatus::STATUS_NEW
    ): AnalysisProjectSummary {
        return new AnalysisProjectSummary($inQueueBefore, $totalSegments, $status);
    }

    public function testJsonSerializeReturnsExpectedKeys(): void
    {
        $summary = $this->makeSummary();
        $result  = $summary->jsonSerialize();

        $this->assertArrayHasKey('in_queue_before', $result);
        $this->assertArrayHasKey('total_segments', $result);
        $this->assertArrayHasKey('segments_analyzed', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('total_raw', $result);
        $this->assertArrayHasKey('total_industry', $result);
        $this->assertArrayHasKey('total_equivalent', $result);
        $this->assertArrayHasKey('discount', $result);
    }

    public function testJsonSerializeInitialValues(): void
    {
        $summary = $this->makeSummary(5, 200, ProjectStatus::STATUS_NEW);
        $result  = $summary->jsonSerialize();

        $this->assertSame(5, $result['in_queue_before']);
        $this->assertSame(200, $result['total_segments']);
        $this->assertSame(0, $result['segments_analyzed']);
        $this->assertSame(ProjectStatus::STATUS_NEW, $result['status']);
        $this->assertSame(0, $result['total_raw']);
        $this->assertSame(0.0, $result['discount']);
    }

    public function testIncrementAnalyzed(): void
    {
        $summary = $this->makeSummary();
        $summary->incrementAnalyzed();
        $summary->incrementAnalyzed();

        $this->assertSame(2, $summary->getSegmentsAnalyzed());
    }

    public function testIncrementEquivalent(): void
    {
        $summary = $this->makeSummary();
        $summary->incrementEquivalent(1.5);
        $summary->incrementEquivalent(2.5);

        $result = $summary->jsonSerialize();
        $this->assertSame(4.0, $result['total_equivalent']);
    }

    public function testIncrementRaw(): void
    {
        $summary = $this->makeSummary();
        $summary->incrementRaw(10);
        $summary->incrementRaw(20);

        $result = $summary->jsonSerialize();
        $this->assertSame(30, $result['total_raw']);
    }

    public function testIncrementIndustry(): void
    {
        $summary = $this->makeSummary();
        $summary->incrementIndustry(5.0);
        $summary->incrementIndustry(3.0);

        $result = $summary->jsonSerialize();
        $this->assertSame(8.0, $result['total_industry']);
    }

    public function testGetDiscountZeroWhenRawIsZero(): void
    {
        $summary = $this->makeSummary();
        $this->assertSame(0.0, $summary->getDiscount());
    }

    public function testGetDiscountCalculation(): void
    {
        $summary = $this->makeSummary();
        $summary->incrementRaw(100);
        $summary->incrementEquivalent(80.0);

        // discount = round(((100 - round(80)) / 100) * 100) = round(20) = 20
        $this->assertSame(20.0, $summary->getDiscount());
    }

    public function testSetAndGetTotalFastAnalysis(): void
    {
        $summary = $this->makeSummary();
        $result  = $summary->setTotalFastAnalysis(42);

        $this->assertSame($summary, $result);
        $this->assertSame(42, $summary->getTotalFastAnalysis());
    }

    public function testGetTotalSegments(): void
    {
        $summary = $this->makeSummary(0, 500);
        $this->assertSame(500, $summary->getTotalSegments());
    }

    public function testTotalIndustryIsMaxOfIndustryAndEquivalent(): void
    {
        $summary = $this->makeSummary();
        $summary->incrementEquivalent(100.0);
        $summary->incrementIndustry(50.0);

        $result = $summary->jsonSerialize();
        // max(round(50), round(100)) = 100
        $this->assertSame(100.0, $result['total_industry']);
    }
}
