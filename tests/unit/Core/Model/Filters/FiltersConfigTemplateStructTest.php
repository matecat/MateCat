<?php

namespace Matecat\Core\Model\Filters;

use DomainException;
use Matecat\TestHelpers\AbstractTest;
use Model\Filters\DTO\Dita;
use Model\Filters\DTO\Json;
use Model\Filters\DTO\MSExcel;
use Model\Filters\DTO\MSPowerpoint;
use Model\Filters\DTO\MSWord;
use Model\Filters\DTO\Xml;
use Model\Filters\DTO\Yaml;
use Model\Filters\FiltersConfigTemplateStruct;
use PHPUnit\Framework\Attributes\Test;

class FiltersConfigTemplateStructTest extends AbstractTest
{
    #[Test]
    public function constructor_sets_defaults(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $this->assertNull($struct->id);
        $this->assertNull($struct->name);
        $this->assertNull($struct->uid);
        $this->assertNull($struct->yaml);
        $this->assertNull($struct->xml);
        $this->assertNull($struct->json);
        $this->assertNull($struct->ms_word);
        $this->assertNull($struct->ms_excel);
        $this->assertNull($struct->ms_powerpoint);
        $this->assertNull($struct->dita);
    }

    #[Test]
    public function getters_and_setters_for_all_dtos(): void
    {
        $struct = new FiltersConfigTemplateStruct();

        $yaml = new Yaml();
        $struct->setYaml($yaml);
        $this->assertSame($yaml, $struct->getYaml());

        $xml = new Xml();
        $struct->setXml($xml);
        $this->assertSame($xml, $struct->getXml());

        $json = new Json();
        $struct->setJson($json);
        $this->assertSame($json, $struct->getJson());

        $word = new MSWord();
        $struct->setMsWord($word);
        $this->assertSame($word, $struct->getMsWord());

        $excel = new MSExcel();
        $struct->setMsExcel($excel);
        $this->assertSame($excel, $struct->getMsExcel());

        $ppt = new MSPowerpoint();
        $struct->setMsPowerpoint($ppt);
        $this->assertSame($ppt, $struct->getMsPowerpoint());

        $dita = new Dita();
        $struct->setDita($dita);
        $this->assertSame($dita, $struct->getDita());
    }

    #[Test]
    public function setters_accept_null(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->setJson(new Json());
        $struct->setJson(null);
        $this->assertNull($struct->getJson());
    }

    #[Test]
    public function hydrateDtoFromArray_hydrates_all_dto_types(): void
    {
        $struct = new TestableFiltersConfigTemplateStruct();

        $struct->callHydrateDtoFromArray(Json::class, []);
        $this->assertInstanceOf(Json::class, $struct->getJson());

        $struct->callHydrateDtoFromArray(Xml::class, []);
        $this->assertInstanceOf(Xml::class, $struct->getXml());

        $struct->callHydrateDtoFromArray(Yaml::class, []);
        $this->assertInstanceOf(Yaml::class, $struct->getYaml());

        $struct->callHydrateDtoFromArray(MSExcel::class, []);
        $this->assertInstanceOf(MSExcel::class, $struct->getMsExcel());

        $struct->callHydrateDtoFromArray(MSWord::class, []);
        $this->assertInstanceOf(MSWord::class, $struct->getMsWord());

        $struct->callHydrateDtoFromArray(MSPowerpoint::class, []);
        $this->assertInstanceOf(MSPowerpoint::class, $struct->getMsPowerpoint());

        $struct->callHydrateDtoFromArray(Dita::class, []);
        $this->assertInstanceOf(Dita::class, $struct->getDita());
    }

    #[Test]
    public function hydrateAllDto_from_arrays(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->hydrateAllDto([
            'json' => [],
            'xml'  => [],
            'yaml' => [],
            'ms_excel' => [],
            'ms_word' => [],
            'ms_powerpoint' => [],
            'dita' => [],
        ]);

        $this->assertInstanceOf(Json::class, $struct->getJson());
        $this->assertInstanceOf(Xml::class, $struct->getXml());
        $this->assertInstanceOf(Yaml::class, $struct->getYaml());
        $this->assertInstanceOf(MSExcel::class, $struct->getMsExcel());
        $this->assertInstanceOf(MSWord::class, $struct->getMsWord());
        $this->assertInstanceOf(MSPowerpoint::class, $struct->getMsPowerpoint());
        $this->assertInstanceOf(Dita::class, $struct->getDita());
    }

    #[Test]
    public function hydrateAllDto_from_json_strings(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->hydrateAllDto([
            'json' => '{}',
            'xml'  => '{}',
            'yaml' => '{}',
            'ms_excel' => '{}',
            'ms_word' => '{}',
            'ms_powerpoint' => '{}',
            'dita' => '{}',
        ]);

        $this->assertInstanceOf(Json::class, $struct->getJson());
        $this->assertInstanceOf(Xml::class, $struct->getXml());
        $this->assertInstanceOf(Yaml::class, $struct->getYaml());
        $this->assertInstanceOf(MSExcel::class, $struct->getMsExcel());
        $this->assertInstanceOf(MSWord::class, $struct->getMsWord());
        $this->assertInstanceOf(MSPowerpoint::class, $struct->getMsPowerpoint());
        $this->assertInstanceOf(Dita::class, $struct->getDita());
    }

    #[Test]
    public function hydrateAllDto_skips_unset_keys(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->hydrateAllDto([]);

        $this->assertNull($struct->getJson());
        $this->assertNull($struct->getXml());
    }

    #[Test]
    public function hydrateFromJSON_sets_basic_fields(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $input = json_encode([
            'name' => 'my-template',
            'uid'  => 42,
            'id'   => 10,
            'created_at' => '2026-01-01',
            'modified_at' => '2026-01-02',
            'deleted_at' => '2026-01-03',
        ]);

        $result = $struct->hydrateFromJSON($input);

        $this->assertSame($struct, $result);
        $this->assertSame('my-template', $struct->name);
        $this->assertSame(42, $struct->uid);
        $this->assertSame(10, $struct->id);
        $this->assertSame('2026-01-01', $struct->created_at);
        $this->assertSame('2026-01-02', $struct->modified_at);
        $this->assertSame('2026-01-03', $struct->deleted_at);
    }

    #[Test]
    public function hydrateFromJSON_uses_uid_param_fallback(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $input = json_encode(['name' => 'test']);

        $struct->hydrateFromJSON($input, 99);
        $this->assertSame(99, $struct->uid);
    }

    #[Test]
    public function hydrateFromJSON_sets_default_dtos(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $input = json_encode(['name' => 'test', 'uid' => 1]);

        $struct->hydrateFromJSON($input);

        $this->assertInstanceOf(Json::class, $struct->getJson());
        $this->assertInstanceOf(Xml::class, $struct->getXml());
        $this->assertInstanceOf(Yaml::class, $struct->getYaml());
        $this->assertInstanceOf(MSExcel::class, $struct->getMsExcel());
        $this->assertInstanceOf(MSWord::class, $struct->getMsWord());
        $this->assertInstanceOf(MSPowerpoint::class, $struct->getMsPowerpoint());
        $this->assertInstanceOf(Dita::class, $struct->getDita());
    }

    #[Test]
    public function hydrateFromJSON_throws_when_name_missing(): void
    {
        $struct = new FiltersConfigTemplateStruct();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Invalid data provided');
        $struct->hydrateFromJSON('{"uid": 1}');
    }

    #[Test]
    public function hydrateFromJSON_throws_when_uid_missing(): void
    {
        $struct = new FiltersConfigTemplateStruct();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Invalid user id');
        $struct->hydrateFromJSON('{"name": "test"}');
    }

    #[Test]
    public function jsonSerialize_returns_expected_shape(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->hydrateFromJSON(json_encode([
            'name' => 'test',
            'uid'  => 1,
        ]));

        $result = $struct->jsonSerialize();

        $this->assertSame('test', $result['name']);
        $this->assertSame(1, $result['uid']);
        $this->assertArrayHasKey('json', $result);
        $this->assertArrayHasKey('xml', $result);
        $this->assertArrayHasKey('yaml', $result);
        $this->assertArrayHasKey('ms_word', $result);
        $this->assertArrayHasKey('ms_excel', $result);
        $this->assertArrayHasKey('ms_powerpoint', $result);
        $this->assertArrayHasKey('dita', $result);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertArrayHasKey('modified_at', $result);
    }

    #[Test]
    public function hydrateAllDto_hydrates_json_inner_content_type(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->hydrateAllDto([
            'json' => ['inner_content_type' => 'text/html'],
        ]);

        $json = $struct->getJson();
        $this->assertInstanceOf(Json::class, $json);
        $this->assertSame('text/html', $json->jsonSerialize()['inner_content_type']);
    }

    #[Test]
    public function hydrateAllDto_hydrates_json_inner_content_type_from_json_string(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->hydrateAllDto([
            'json' => '{"inner_content_type":"text/html"}',
        ]);

        $json = $struct->getJson();
        $this->assertInstanceOf(Json::class, $json);
        $this->assertSame('text/html', $json->jsonSerialize()['inner_content_type']);
    }

    /**
     * Omitting the parameter is how the caller asks for the extracted text to be treated as plain text.
     */
    #[Test]
    public function hydrateAllDto_json_inner_content_type_defaults_to_null(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->hydrateAllDto([
            'json' => [],
        ]);

        $json = $struct->getJson();
        $this->assertInstanceOf(Json::class, $json);
        $this->assertNull($json->jsonSerialize()['inner_content_type']);
    }

    /**
     * An explicit null is allowed by the JSON schema and is skipped by the isset() guard in
     * Json::fromArray(), leaving the plain text default in place.
     */
    #[Test]
    public function hydrateAllDto_json_accepts_explicit_null_inner_content_type(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->hydrateAllDto([
            'json' => ['inner_content_type' => null],
        ]);

        $json = $struct->getJson();
        $this->assertInstanceOf(Json::class, $json);
        $this->assertNull($json->jsonSerialize()['inner_content_type']);
    }

    #[Test]
    public function hydrateAllDto_throws_on_invalid_json_inner_content_type(): void
    {
        $struct = new FiltersConfigTemplateStruct();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('JSON Inner content type not valid');
        $struct->hydrateAllDto([
            'json' => ['inner_content_type' => 'text/plain'],
        ]);
    }

    /**
     * text/html is the only value the JSON filter accepts, unlike the wider YAML allow-list.
     */
    #[Test]
    public function hydrateAllDto_throws_on_yaml_only_json_inner_content_type(): void
    {
        $struct = new FiltersConfigTemplateStruct();

        $this->expectException(DomainException::class);
        $struct->hydrateAllDto([
            'json' => ['inner_content_type' => 'text/markdown'],
        ]);
    }

    #[Test]
    public function hydrateFromJSON_hydrates_json_inner_content_type(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->hydrateFromJSON(json_encode([
            'name' => 'test',
            'uid'  => 1,
            'json' => [
                'extract_arrays'     => true,
                'inner_content_type' => 'text/html',
            ],
        ]));

        $json = $struct->getJson();
        $this->assertInstanceOf(Json::class, $json);
        $this->assertSame('text/html', $json->jsonSerialize()['inner_content_type']);
    }

    #[Test]
    public function jsonSerialize_exposes_json_inner_content_type(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->hydrateFromJSON(json_encode([
            'name' => 'test',
            'uid'  => 1,
            'json' => ['inner_content_type' => 'text/html'],
            'yaml' => ['inner_content_type' => 'text/markdown'],
        ]));

        $serialized = json_decode(json_encode($struct->jsonSerialize()), true);

        $this->assertSame('text/html', $serialized['json']['inner_content_type']);
        $this->assertSame('text/markdown', $serialized['yaml']['inner_content_type']);
    }
}

class TestableFiltersConfigTemplateStruct extends FiltersConfigTemplateStruct
{
    /**
     * @param class-string<\Model\Filters\DTO\IDto> $dtoClass
     * @param array<string, mixed> $data
     */
    public function callHydrateDtoFromArray(string $dtoClass, array $data): void
    {
        $this->hydrateDtoFromArray($dtoClass, $data);
    }
}
