<?php

namespace Matecat\Core\View\API\App\Json\Analysis;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use View\API\App\Json\Analysis\AnalysisJob;

#[CoversClass(AnalysisJob::class)]
class AnalysisJobTest extends AbstractTest
{
    /**
     * @throws \Exception
     */
    private function makeJob(int $id = 1, string $source = 'en-US', string $target = 'it-IT'): AnalysisJob
    {
        return new AnalysisJob($id, $source, $target);
    }

    public function testConstructorSetsId(): void
    {
        $job = $this->makeJob(42);
        $this->assertSame(42, $job->getId());
    }

    public function testConstructorSetsSourceAndTarget(): void
    {
        $job = $this->makeJob(1, 'en-US', 'fr-FR');
        $this->assertSame('en-US', $job->getSource());
        $this->assertSame('fr-FR', $job->getTarget());
    }

    public function testGetLangPair(): void
    {
        $job = $this->makeJob(1, 'en-US', 'it-IT');
        $this->assertSame('en-US|it-IT', $job->getLangPair());
    }

    public function testJsonSerializeReturnsExpectedKeys(): void
    {
        $job    = $this->makeJob();
        $result = $job->jsonSerialize();

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('source', $result);
        $this->assertArrayHasKey('source_name', $result);
        $this->assertArrayHasKey('target', $result);
        $this->assertArrayHasKey('target_name', $result);
        $this->assertArrayHasKey('chunks', $result);
        $this->assertArrayHasKey('total_raw', $result);
        $this->assertArrayHasKey('total_equivalent', $result);
        $this->assertArrayHasKey('total_industry', $result);
        $this->assertArrayHasKey('outsource_available', $result);
        $this->assertArrayHasKey('payable_rates', $result);
        $this->assertArrayHasKey('count_unit', $result);
    }

    public function testJsonSerializeInitialValues(): void
    {
        $job    = $this->makeJob(7, 'en-US', 'it-IT');
        $result = $job->jsonSerialize();

        $this->assertSame(7, $result['id']);
        $this->assertSame('en-US', $result['source']);
        $this->assertSame('it-IT', $result['target']);
        $this->assertSame(0, $result['total_raw']);
        $this->assertSame(0.0, $result['total_equivalent']);
        $this->assertSame(0.0, $result['total_industry']);
        $this->assertTrue($result['outsource_available']);
        $this->assertSame([], $result['chunks']);
    }

    public function testCountUnitWordsForNonCjkLanguage(): void
    {
        $job    = $this->makeJob(1, 'en-US', 'it-IT');
        $result = $job->jsonSerialize();

        $this->assertSame('words', $result['count_unit']);
    }

    public function testCountUnitCharactersForCjkLanguage(): void
    {
        $job    = $this->makeJob(1, 'zh-CN', 'en-US');
        $result = $job->jsonSerialize();

        $this->assertSame('characters', $result['count_unit']);
    }

    public function testIncrementRaw(): void
    {
        $job = $this->makeJob();
        $job->incrementRaw(50);
        $job->incrementRaw(25);

        $result = $job->jsonSerialize();
        $this->assertSame(75, $result['total_raw']);
    }

    public function testIncrementEquivalent(): void
    {
        $job = $this->makeJob();
        $job->incrementEquivalent(10.5);
        $job->incrementEquivalent(4.5);

        $result = $job->jsonSerialize();
        $this->assertSame(15.0, $result['total_equivalent']);
    }

    public function testIncrementIndustry(): void
    {
        $job = $this->makeJob();
        $job->incrementIndustry(3.0);
        $job->incrementIndustry(7.0);

        $result = $job->jsonSerialize();
        $this->assertSame(10.0, $result['total_industry']);
    }

    public function testSetOutsourceAvailable(): void
    {
        $job    = $this->makeJob();
        $result = $job->setOutsourceAvailable(false);

        $this->assertSame($job, $result);
        $this->assertFalse($job->jsonSerialize()['outsource_available']);
    }

    public function testSetPayableRates(): void
    {
        $job   = $this->makeJob();
        $rates = new stdClass();
        $rates->per_word = 0.15;

        $result = $job->setPayableRates($rates);
        $this->assertSame($job, $result);
        $this->assertSame($rates, $job->jsonSerialize()['payable_rates']);
    }

    public function testHasChunkReturnsFalseWhenNoChunks(): void
    {
        $job = $this->makeJob();
        $this->assertFalse($job->hasChunk('abc123'));
    }

    public function testGetChunksReturnsEmptyInitially(): void
    {
        $job = $this->makeJob();
        $this->assertSame([], $job->getChunks());
    }

    public function testTotalIndustryIsMaxOfIndustryAndEquivalent(): void
    {
        $job = $this->makeJob();
        $job->incrementEquivalent(100.0);
        $job->incrementIndustry(50.0);

        $result = $job->jsonSerialize();
        // max(round(50), round(100)) = 100
        $this->assertSame(100.0, $result['total_industry']);
    }
}
