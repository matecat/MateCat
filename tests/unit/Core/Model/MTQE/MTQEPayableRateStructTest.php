<?php

namespace Matecat\Core\Model\MTQE;

use DomainException;
use Matecat\TestHelpers\AbstractTest;
use Model\MTQE\PayableRate\DTO\MTQEPayableRateBreakdowns;
use Model\MTQE\PayableRate\MTQEPayableRateStruct;

class MTQEPayableRateStructTest extends AbstractTest
{
    public function testHydrateFromJSONMinimal(): void
    {
        $struct = new MTQEPayableRateStruct();
        $result = $struct->hydrateFromJSON(json_encode([
            'name' => 'Test Rate',
            'uid' => 42,
        ]));

        $this->assertSame('Test Rate', $result->name);
        $this->assertSame(42, $result->uid);
        $this->assertSame($struct, $result);
    }

    public function testHydrateFromJSONFull(): void
    {
        $struct = new MTQEPayableRateStruct();
        $result = $struct->hydrateFromJSON(json_encode([
            'name' => 'Full Rate',
            'uid' => 10,
            'id' => 99,
            'version' => 3,
            'created_at' => '2025-01-01 00:00:00',
            'deleted_at' => '2025-06-01 00:00:00',
            'modified_at' => '2025-03-01 00:00:00',
            'breakdowns' => ['breakdowns' => ['ice' => 5, 'tm_100' => 10]],
        ]));

        $this->assertSame(99, $result->id);
        $this->assertSame(3, $result->version);
        $this->assertSame('2025-01-01 00:00:00', $result->created_at);
        $this->assertSame('2025-06-01 00:00:00', $result->deleted_at);
        $this->assertSame('2025-03-01 00:00:00', $result->modified_at);
        $this->assertInstanceOf(MTQEPayableRateBreakdowns::class, $result->breakdowns);
    }

    public function testHydrateFromJSONWithUidFallback(): void
    {
        $struct = new MTQEPayableRateStruct();
        $result = $struct->hydrateFromJSON(json_encode(['name' => 'X']), 77);

        $this->assertSame(77, $result->uid);
    }

    public function testHydrateFromJSONThrowsOnMissingName(): void
    {
        $this->expectException(DomainException::class);
        (new MTQEPayableRateStruct())->hydrateFromJSON(json_encode(['uid' => 1]));
    }

    public function testHydrateFromJSONThrowsOnMissingUid(): void
    {
        $this->expectException(DomainException::class);
        (new MTQEPayableRateStruct())->hydrateFromJSON(json_encode(['name' => 'X']));
    }

    public function testHydrateBreakdownsFromJsonString(): void
    {
        $struct = new MTQEPayableRateStruct();
        $struct->hydrateBreakdownsFromJson(json_encode(['breakdowns' => ['ice' => 7]]));

        $this->assertInstanceOf(MTQEPayableRateBreakdowns::class, $struct->breakdowns);
    }

    public function testHydrateBreakdownsFromDataArrayWithoutNestedKey(): void
    {
        $struct = new MTQEPayableRateStruct();
        $struct->hydrateBreakdownsFromDataArray(['ice' => 5]);

        $this->assertInstanceOf(MTQEPayableRateBreakdowns::class, $struct->breakdowns);
        $this->assertSame(0, $struct->breakdowns->ice);
    }

    public function testHydrateBreakdownsFromDataArrayWithNestedKey(): void
    {
        $struct = new MTQEPayableRateStruct();
        $struct->hydrateBreakdownsFromDataArray(['breakdowns' => ['ice' => 15]]);

        $this->assertSame(15, $struct->breakdowns->ice);
    }

    public function testHydrateFromJSONWithStringBreakdowns(): void
    {
        $struct = new MTQEPayableRateStruct();
        $struct->hydrateFromJSON(json_encode([
            'name' => 'R',
            'uid' => 1,
            'breakdowns' => json_encode(['breakdowns' => ['ice' => 3]]),
        ]));

        $this->assertInstanceOf(MTQEPayableRateBreakdowns::class, $struct->breakdowns);
    }

    public function testJsonSerializeReturnsArray(): void
    {
        $struct = new MTQEPayableRateStruct();
        $result = $struct->jsonSerialize();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
    }

    public function testToStringReturnsJsonString(): void
    {
        $struct = new MTQEPayableRateStruct();
        $str = (string)$struct;

        $this->assertIsString($str);
        $this->assertNotEmpty($str);
        $decoded = json_decode($str, true);
        $this->assertIsArray($decoded);
    }
}
