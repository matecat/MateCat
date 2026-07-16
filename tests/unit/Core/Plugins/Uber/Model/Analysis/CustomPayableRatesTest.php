<?php

namespace Matecat\Core\Plugins\Uber\Model\Analysis;

use Features\Uber\Model\Analysis\CustomPayableRates;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;

class CustomPayableRatesTest extends AbstractTest
{
    #[Test]
    public function defaultPayableRatesHasExpectedKeys(): void
    {
        $rates = CustomPayableRates::$DEFAULT_PAYABLE_RATES;

        $this->assertArrayHasKey('NO_MATCH', $rates);
        $this->assertArrayHasKey('ICE', $rates);
        $this->assertArrayHasKey('MT', $rates);
        $this->assertArrayHasKey('REPETITIONS', $rates);
        $this->assertSame(100, $rates['NO_MATCH']);
        $this->assertSame(20, $rates['ICE']);
    }

    #[Test]
    public function getPayableRatesReturnsDefaultForUnknownPair(): void
    {
        $rates = CustomPayableRates::getPayableRates('xx-XX', 'yy-YY');

        $this->assertIsArray($rates);
        $this->assertArrayHasKey('NO_MATCH', $rates);
    }

    #[Test]
    public function getPayableRatesReturnsCustomForEnIt(): void
    {
        $rates = CustomPayableRates::getPayableRates('en-US', 'it-IT');

        $this->assertIsArray($rates);
        $this->assertArrayHasKey('MT', $rates);
        $this->assertSame(62, $rates['MT']);
    }

    #[Test]
    public function getPayableRatesReturnsCustomForEnDe(): void
    {
        $rates = CustomPayableRates::getPayableRates('en-US', 'de-DE');

        $this->assertIsArray($rates);
        $this->assertSame(67, $rates['MT']);
    }

    #[Test]
    public function enGBUsesEnUSRates(): void
    {
        $ratesGB = CustomPayableRates::getPayableRates('en-GB', 'it-IT');
        $ratesUS = CustomPayableRates::getPayableRates('en-US', 'it-IT');

        $this->assertSame($ratesGB, $ratesUS);
    }
}
