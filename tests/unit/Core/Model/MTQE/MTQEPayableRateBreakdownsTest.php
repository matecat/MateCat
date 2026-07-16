<?php

namespace Matecat\Core\Model\MTQE;

use Matecat\TestHelpers\AbstractTest;
use Model\MTQE\PayableRate\DTO\MTQEPayableRateBreakdowns;

class MTQEPayableRateBreakdownsTest extends AbstractTest
{
    public function testDefaults(): void
    {
        $b = new MTQEPayableRateBreakdowns();

        $this->assertSame(0, $b->ice);
        $this->assertSame(0, $b->tm_100);
        $this->assertSame(0, $b->tm_100_public);
        $this->assertSame(9, $b->repetitions);
        $this->assertSame(0, $b->ice_mt);
        $this->assertSame(9, $b->top_quality_mt);
        $this->assertSame(27, $b->higher_quality_mt);
        $this->assertSame(75, $b->standard_quality_mt);
    }

    public function testJsonSerialize(): void
    {
        $b = new MTQEPayableRateBreakdowns();
        $arr = $b->jsonSerialize();

        $this->assertIsArray($arr);
        $this->assertArrayHasKey('ice', $arr);
    }

    public function testToString(): void
    {
        $b = new MTQEPayableRateBreakdowns();
        $str = (string)$b;

        $this->assertJson($str);
        $decoded = json_decode($str, true);
        $this->assertSame(0, $decoded['ice']);
    }

    public function testHydrateFromConstructor(): void
    {
        $b = new MTQEPayableRateBreakdowns(['ice' => 15, 'tm_100' => 20]);

        $this->assertSame(15, $b->ice);
        $this->assertSame(20, $b->tm_100);
    }
}
