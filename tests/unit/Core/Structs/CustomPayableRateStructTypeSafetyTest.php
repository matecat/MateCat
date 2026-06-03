<?php


namespace Matecat\Core\Structs;

use DomainException;
use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\PayableRates\CustomPayableRateStruct;
use PHPUnit\Framework\Attributes\Test;

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

    #[Test]
    public function hydrateFromJSON_sets_name_and_breakdowns(): void
    {
        $struct = new CustomPayableRateStruct();
        $json = json_encode([
            'payable_rate_template_name' => 'Test Rate',
            'breakdowns' => [
                'default' => ['NO_MATCH' => 100, 'ICE_MT' => 0, 'MT' => 80],
            ],
        ]);

        $result = $struct->hydrateFromJSON($json);

        $this->assertSame('Test Rate', $result->name);
        $this->assertSame($struct, $result);
    }

    #[Test]
    public function hydrateFromJSON_with_version(): void
    {
        $struct = new CustomPayableRateStruct();
        $json = json_encode([
            'payable_rate_template_name' => 'V2',
            'version' => 3,
            'breakdowns' => [
                'default' => ['NO_MATCH' => 100, 'ICE_MT' => 0, 'MT' => 80],
            ],
        ]);

        $struct->hydrateFromJSON($json);

        $this->assertSame(3, $struct->version);
    }

    #[Test]
    public function hydrateFromJSON_throws_on_missing_fields(): void
    {
        $this->expectException(Exception::class);
        (new CustomPayableRateStruct())->hydrateFromJSON(json_encode(['foo' => 'bar']));
    }

    #[Test]
    public function validateBreakdowns_throws_on_missing_default(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('`default` node is MANDATORY');

        (new CustomPayableRateStruct())->hydrateFromJSON(json_encode([
            'payable_rate_template_name' => 'Bad',
            'breakdowns' => ['en-US' => ['it-IT' => ['NO_MATCH' => 100]]],
        ]));
    }

    #[Test]
    public function validateBreakdowns_throws_on_invalid_source_language(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('is not a supported language');

        (new CustomPayableRateStruct())->hydrateFromJSON(json_encode([
            'payable_rate_template_name' => 'Bad',
            'breakdowns' => [
                'default' => ['NO_MATCH' => 100],
                'xx-INVALID' => ['it-IT' => ['NO_MATCH' => 100]],
            ],
        ]));
    }

    #[Test]
    public function validateBreakdowns_throws_on_invalid_target_language(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('is not a supported language');

        (new CustomPayableRateStruct())->hydrateFromJSON(json_encode([
            'payable_rate_template_name' => 'Bad',
            'breakdowns' => [
                'default' => ['NO_MATCH' => 100],
                'en-US' => ['xx-INVALID' => ['NO_MATCH' => 100]],
            ],
        ]));
    }

    #[Test]
    public function validateBreakdowns_throws_on_too_large(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('too large');

        $huge = ['default' => ['NO_MATCH' => 100]];
        for ($i = 0; $i < 500; $i++) {
            $huge["en-US"]["target-lang-$i"] = ['NO_MATCH' => 100, 'ICE_MT' => 0, 'MT' => 80, 'REPETITIONS' => 50, 'ICE' => 0, 'INTERNAL' => 60, '100%_PUBLIC' => 30, '100%' => 30, '95%-99%' => 40, '85%-94%' => 50, '75%-84%' => 60, 'NEW' => 100];
        }

        (new CustomPayableRateStruct())->hydrateFromJSON(json_encode([
            'payable_rate_template_name' => 'Huge',
            'breakdowns' => $huge,
        ]));
    }

    #[Test]
    public function jsonSerialize_returns_expected_keys(): void
    {
        $struct = new CustomPayableRateStruct();
        $struct->id = 1;
        $struct->uid = 42;
        $struct->version = 1;
        $struct->name = 'Test';
        $struct->breakdowns = ['default' => ['NO_MATCH' => 100]];
        $struct->created_at = '2025-01-01 00:00:00';

        $result = $struct->jsonSerialize();

        $this->assertSame(1, $result['id']);
        $this->assertSame(42, $result['uid']);
        $this->assertSame('Test', $result['payable_rate_template_name']);
        $this->assertArrayHasKey('breakdowns', $result);
        $this->assertArrayHasKey('createdAt', $result);
    }

    #[Test]
    public function getPayableRates_returns_resolved_rates(): void
    {
        $struct = new CustomPayableRateStruct();
        $struct->breakdowns = [
            'default' => ['NO_MATCH' => 100, 'ICE_MT' => 0, 'MT' => 80, 'REPETITIONS' => 50, 'ICE' => 0, 'INTERNAL' => 60, '100%_PUBLIC' => 30, '100%' => 30, '95%-99%' => 40, '85%-94%' => 50, '75%-84%' => 60, 'NEW' => 100],
            'en-US' => ['it-IT' => ['NO_MATCH' => 90, 'ICE_MT' => 5, 'MT' => 70, 'REPETITIONS' => 40, 'ICE' => 5, 'INTERNAL' => 50, '100%_PUBLIC' => 20, '100%' => 20, '95%-99%' => 30, '85%-94%' => 40, '75%-84%' => 50, 'NEW' => 90]],
        ];

        $rates = $struct->getPayableRates('en-US', 'it-IT');

        $this->assertIsArray($rates);
        $this->assertSame(90, $rates['NO_MATCH']);
    }

    #[Test]
    public function getPayableRates_throws_on_invalid_source(): void
    {
        $this->expectException(DomainException::class);

        $struct = new CustomPayableRateStruct();
        $struct->breakdowns = ['default' => ['NO_MATCH' => 100]];
        $struct->getPayableRates('xx-INVALID', 'it-IT');
    }
}
