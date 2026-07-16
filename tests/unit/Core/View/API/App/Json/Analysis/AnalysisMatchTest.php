<?php

namespace Matecat\Core\View\API\App\Json\Analysis;

use Matecat\TestHelpers\AbstractTest;
use Model\Analysis\Constants\StandardMatchTypeNamesConstants;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use View\API\App\Json\Analysis\AnalysisMatch;

#[CoversClass(AnalysisMatch::class)]
class AnalysisMatchTest extends AbstractTest
{
    private function makeMatch(string $type = StandardMatchTypeNamesConstants::_NEW): AnalysisMatch
    {
        return AnalysisMatch::forName($type, new StandardMatchTypeNamesConstants());
    }

    public function testForNameCreatesInstance(): void
    {
        $match = $this->makeMatch();

        $this->assertInstanceOf(AnalysisMatch::class, $match);
    }

    public function testForNameThrowsOnInvalidType(): void
    {
        $this->expectException(RuntimeException::class);
        AnalysisMatch::forName('invalid_type', new StandardMatchTypeNamesConstants());
    }

    public function testNameReturnsType(): void
    {
        $match = $this->makeMatch(StandardMatchTypeNamesConstants::_100);

        $this->assertSame(StandardMatchTypeNamesConstants::_100, $match->name());
    }

    public function testSetAndGetRaw(): void
    {
        $match = $this->makeMatch();
        $match->setRaw(50);

        $this->assertSame(50, $match->getRaw());
    }

    public function testSetAndGetEquivalent(): void
    {
        $match = $this->makeMatch();
        $match->setEquivalent(3.14);

        $this->assertSame(3.14, $match->getEquivalent());
    }

    public function testIncrementRaw(): void
    {
        $match = $this->makeMatch();
        $match->setRaw(10);
        $match->incrementRaw(5);

        $this->assertSame(15, $match->getRaw());
    }

    public function testIncrementEquivalent(): void
    {
        $match = $this->makeMatch();
        $match->setEquivalent(1.5);
        $match->incrementEquivalent(0.5);

        $this->assertSame(2.0, $match->getEquivalent());
    }

    public function testJsonSerializeReturnsExpectedKeys(): void
    {
        $match  = $this->makeMatch(StandardMatchTypeNamesConstants::_MT);
        $match->setRaw(100);
        $match->setEquivalent(75.7);
        $result = $match->jsonSerialize();

        $this->assertArrayHasKey('raw', $result);
        $this->assertArrayHasKey('equivalent', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertSame(100, $result['raw']);
        $this->assertEquals(76, $result['equivalent']); // round(75.7) returns float
        $this->assertSame(StandardMatchTypeNamesConstants::_MT, $result['type']);
    }

    public function testSetRawReturnsSelf(): void
    {
        $match = $this->makeMatch();
        $this->assertSame($match, $match->setRaw(1));
    }

    public function testSetEquivalentReturnsSelf(): void
    {
        $match = $this->makeMatch();
        $this->assertSame($match, $match->setEquivalent(1.0));
    }
}
