<?php

namespace Matecat\Core\View\Analysis;

use Matecat\TestHelpers\AbstractTest;
use Model\Analysis\Constants\StandardMatchTypeNamesConstants;
use PHPUnit\Framework\Attributes\Test;
use View\API\App\Json\Analysis\AnalysisJobSummary;
use View\API\App\Json\Analysis\AnalysisMatch;

class AnalysisJobSummaryTest extends AbstractTest
{
    #[Test]
    public function constructPopulatesMatchesFromConstants(): void
    {
        $summary = new AnalysisJobSummary(new StandardMatchTypeNamesConstants());
        $match = $summary->getMatch('tm_100');

        $this->assertInstanceOf(AnalysisMatch::class, $match);
        $this->assertSame('tm_100', $match->name());
    }

    #[Test]
    public function jsonSerializeReturnsListOfMatches(): void
    {
        $summary = new AnalysisJobSummary(new StandardMatchTypeNamesConstants());
        $serialized = $summary->jsonSerialize();

        $this->assertIsArray($serialized);
        $this->assertNotEmpty($serialized);
        $this->assertContainsOnlyInstancesOf(AnalysisMatch::class, $serialized);
        $this->assertSame(array_values($serialized), $serialized);
    }
}
