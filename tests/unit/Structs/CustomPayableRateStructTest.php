<?php

use Model\PayableRates\CustomPayableRateStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class CustomPayableRateStructTypeSafetyTest extends AbstractTest
{
    #[Test]
    public function getBreakdownsArray_decodes_string_breakdowns(): void
    {
        $struct = new CustomPayableRateStruct();
        $struct->breakdowns = json_encode([
            'default' => ['NO_MATCH' => 100, 'ICE_MT' => 50, 'MT' => 80],
            'en-US' => ['it-IT' => ['NO_MATCH' => 100, 'ICE_MT' => 50, 'MT' => 80]],
        ]);

        $result = $struct->getBreakdownsArray();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('default', $result);
    }

    #[Test]
    public function getBreakdownsArray_returns_array_breakdowns_unchanged(): void
    {
        $struct = new CustomPayableRateStruct();
        $struct->breakdowns = [
            'default' => ['NO_MATCH' => 100, 'ICE_MT' => 50, 'MT' => 80],
        ];

        $result = $struct->getBreakdownsArray();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('default', $result);
    }

    #[Test]
    public function getBreakdownsArray_adds_ICE_MT_for_backward_compatibility(): void
    {
        $struct = new CustomPayableRateStruct();
        $struct->breakdowns = [
            'default' => ['NO_MATCH' => 100, 'MT' => 80],
            'en-US' => ['it-IT' => ['NO_MATCH' => 100, 'MT' => 80]],
        ];

        $result = $struct->getBreakdownsArray();

        $this->assertSame(80, $result['en-US']['it-IT']['ICE_MT']);
    }

    #[Test]
    public function breakdownsToJson_returns_valid_json_string(): void
    {
        $struct = new CustomPayableRateStruct();
        $struct->breakdowns = ['default' => ['NO_MATCH' => 100]];

        $result = $struct->breakdownsToJson();

        $this->assertIsString($result);
        $this->assertNotFalse(json_decode($result, true));
    }
}
