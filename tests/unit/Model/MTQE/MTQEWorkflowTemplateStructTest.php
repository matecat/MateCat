<?php

namespace unit\Model\MTQE;

use DomainException;
use Model\MTQE\Templates\DTO\MTQEWorkflowParams;
use Model\MTQE\Templates\MTQEWorkflowTemplateStruct;
use TestHelpers\AbstractTest;

class MTQEWorkflowTemplateStructTest extends AbstractTest
{
    public function testHydrateFromJSONMinimal(): void
    {
        $struct = new MTQEWorkflowTemplateStruct();
        $result = $struct->hydrateFromJSON(json_encode([
            'name' => 'Test Workflow',
            'uid' => 42,
        ]));

        $this->assertSame('Test Workflow', $result->name);
        $this->assertSame(42, $result->uid);
        $this->assertSame($struct, $result);
    }

    public function testHydrateFromJSONFull(): void
    {
        $struct = new MTQEWorkflowTemplateStruct();
        $result = $struct->hydrateFromJSON(json_encode([
            'name' => 'Full Workflow',
            'uid' => 10,
            'id' => 88,
            'created_at' => '2025-01-01 00:00:00',
            'deleted_at' => '2025-06-01 00:00:00',
            'modified_at' => '2025-03-01 00:00:00',
            'params' => ['params' => ['analysis_ignore_100' => true]],
        ]));

        $this->assertSame(88, $result->id);
        $this->assertSame('2025-01-01 00:00:00', $result->created_at);
        $this->assertSame('2025-06-01 00:00:00', $result->deleted_at);
        $this->assertSame('2025-03-01 00:00:00', $result->modified_at);
        $this->assertInstanceOf(MTQEWorkflowParams::class, $result->params);
    }

    public function testHydrateFromJSONWithUidFallback(): void
    {
        $struct = new MTQEWorkflowTemplateStruct();
        $result = $struct->hydrateFromJSON(json_encode(['name' => 'X']), 77);

        $this->assertSame(77, $result->uid);
    }

    public function testHydrateFromJSONThrowsOnMissingName(): void
    {
        $this->expectException(DomainException::class);
        (new MTQEWorkflowTemplateStruct())->hydrateFromJSON(json_encode(['uid' => 1]));
    }

    public function testHydrateFromJSONThrowsOnMissingUid(): void
    {
        $this->expectException(DomainException::class);
        (new MTQEWorkflowTemplateStruct())->hydrateFromJSON(json_encode(['name' => 'X']));
    }

    public function testHydrateParamsFromJsonString(): void
    {
        $struct = new MTQEWorkflowTemplateStruct();
        $struct->hydrateParamsFromJson(json_encode(['params' => ['analysis_ignore_100' => true]]));

        $this->assertInstanceOf(MTQEWorkflowParams::class, $struct->params);
    }

    public function testHydrateParamsFromDataArrayWithoutNestedKey(): void
    {
        $struct = new MTQEWorkflowTemplateStruct();
        $struct->hydrateParamsFromDataArray(['analysis_ignore_100' => true]);

        $this->assertInstanceOf(MTQEWorkflowParams::class, $struct->params);
        $this->assertFalse($struct->params->analysis_ignore_100);
    }

    public function testHydrateParamsFromDataArrayWithNestedKey(): void
    {
        $struct = new MTQEWorkflowTemplateStruct();
        $struct->hydrateParamsFromDataArray(['params' => ['analysis_ignore_100' => true]]);

        $this->assertTrue($struct->params->analysis_ignore_100);
    }

    public function testHydrateFromJSONWithStringParams(): void
    {
        $struct = new MTQEWorkflowTemplateStruct();
        $struct->hydrateFromJSON(json_encode([
            'name' => 'R',
            'uid' => 1,
            'params' => json_encode(['params' => ['lock_best_quality_mt' => true]]),
        ]));

        $this->assertInstanceOf(MTQEWorkflowParams::class, $struct->params);
    }

    public function testJsonSerializeReturnsArray(): void
    {
        $struct = new MTQEWorkflowTemplateStruct();
        $result = $struct->jsonSerialize();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
    }

    public function testToStringReturnsJsonString(): void
    {
        $struct = new MTQEWorkflowTemplateStruct();
        $str = (string)$struct;

        $this->assertIsString($str);
        $this->assertNotEmpty($str);
        $decoded = json_decode($str, true);
        $this->assertIsArray($decoded);
    }
}
