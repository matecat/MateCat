<?php


namespace Matecat\Core\Model\CustomizableXliff;

use DomainException;
use Matecat\TestHelpers\AbstractTest;
use Model\Xliff\DTO\XliffRulesModel;
use Model\Xliff\XliffConfigTemplateStruct;
use PHPUnit\Framework\Attributes\Test;

class XliffConfigTemplateStructTest extends AbstractTest
{
    #[Test]
    public function hydrateFromJsonWithMinimalData(): void
    {
        $struct = new XliffConfigTemplateStruct();
        $json = json_encode(['name' => 'test-template', 'uid' => 42]);

        $result = $struct->hydrateFromJSON($json);

        $this->assertSame('test-template', $result->name);
        $this->assertSame(42, $result->uid);
        $this->assertNull($result->rules);
    }

    #[Test]
    public function hydrateFromJsonWithUidFallback(): void
    {
        $struct = new XliffConfigTemplateStruct();
        $json = json_encode(['name' => 'test-template']);

        $result = $struct->hydrateFromJSON($json, 99);

        $this->assertSame(99, $result->uid);
    }

    #[Test]
    public function hydrateFromJsonWithAllFields(): void
    {
        $struct = new XliffConfigTemplateStruct();
        $json = json_encode([
            'name' => 'full-template',
            'uid' => 1,
            'id' => 10,
            'created_at' => '2026-01-01',
            'modified_at' => '2026-01-02',
            'deleted_at' => '2026-01-03',
        ]);

        $result = $struct->hydrateFromJSON($json);

        $this->assertSame(10, $result->id);
        $this->assertSame('2026-01-01', $result->created_at);
        $this->assertSame('2026-01-02', $result->modified_at);
        $this->assertSame('2026-01-03', $result->deleted_at);
    }

    #[Test]
    public function hydrateFromJsonThrowsWithoutName(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(400);

        $struct = new XliffConfigTemplateStruct();
        $struct->hydrateFromJSON(json_encode(['uid' => 1]));
    }

    #[Test]
    public function hydrateFromJsonThrowsWithoutUid(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(400);

        $struct = new XliffConfigTemplateStruct();
        $struct->hydrateFromJSON(json_encode(['name' => 'test']));
    }

    #[Test]
    public function hydrateFromJsonWithRulesAsArray(): void
    {
        $struct = new XliffConfigTemplateStruct();
        $json = json_encode([
            'name' => 'with-rules',
            'uid' => 1,
            'rules' => [
                'xliff12' => [
                    ['states' => ['translated'], 'analysis' => 'pre-translated'],
                ],
                'xliff20' => [],
            ],
        ]);

        $result = $struct->hydrateFromJSON($json);

        $this->assertInstanceOf(XliffRulesModel::class, $result->rules);
    }

    #[Test]
    public function hydrateFromJsonWithRulesAsJsonString(): void
    {
        $struct = new XliffConfigTemplateStruct();
        $rulesJson = json_encode([
            'xliff12' => [
                ['states' => ['final'], 'analysis' => 'pre-translated', 'editor' => 'approved'],
            ],
            'xliff20' => [],
        ]);

        $json = json_encode([
            'name' => 'string-rules',
            'uid' => 1,
            'rules' => $rulesJson,
        ]);

        $result = $struct->hydrateFromJSON($json);

        $this->assertInstanceOf(XliffRulesModel::class, $result->rules);
    }

    #[Test]
    public function jsonSerializeReturnsExpectedStructure(): void
    {
        $struct = new XliffConfigTemplateStruct();
        $struct->id = 5;
        $struct->uid = 10;
        $struct->name = 'serialize-test';

        $serialized = $struct->jsonSerialize();

        $this->assertSame(5, $serialized['id']);
        $this->assertSame(10, $serialized['uid']);
        $this->assertSame('serialize-test', $serialized['name']);
        $this->assertArrayHasKey('rules', $serialized);
        $this->assertArrayHasKey('created_at', $serialized);
        $this->assertArrayHasKey('modified_at', $serialized);
    }
}
