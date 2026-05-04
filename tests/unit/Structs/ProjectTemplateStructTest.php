<?php

declare(strict_types=1);

namespace unit\Structs;

use Model\Projects\ProjectTemplateStruct;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

class ProjectTemplateStructTest extends TestCase
{
    private function createFullInputObject(): stdClass
    {
        $obj                             = new stdClass();
        $obj->id                         = 42;
        $obj->uid                        = 7;
        $obj->name                       = 'My Template';
        $obj->is_default                 = true;
        $obj->id_team                    = 99;
        $obj->segmentation_rule          = (object) ['name' => 'custom', 'value' => 'abc'];
        $obj->pretranslate_100           = true;
        $obj->pretranslate_101           = false;
        $obj->tm_prioritization          = true;
        $obj->dialect_strict             = false;
        $obj->public_tm_penalty          = 5;
        $obj->get_public_matches         = false;
        $obj->mt                         = (object) ['id' => 1, 'extra' => new stdClass()];
        $obj->tm                         = [(object) ['key' => 'abc', 'name' => 'My TM']];
        $obj->payable_rate_template_id   = 10;
        $obj->qa_model_template_id       = 20;
        $obj->filters_template_id        = 30;
        $obj->xliff_config_template_id   = 40;
        $obj->character_counter_count_tags = true;
        $obj->character_counter_mode     = 'words';
        $obj->subject                    = 'general';
        $obj->subfiltering_handlers      = ['handler1', 'handler2'];
        $obj->source_language            = 'en-US';
        $obj->target_language            = ['it-IT', 'de-DE'];
        $obj->mt_quality_value_in_editor = 80;
        $obj->icu_enabled                = true;

        return $obj;
    }

    // ── Phase 1 — hydrateFromJSON round-trip ─────────────────────

    #[Test]
    public function hydrateFromJsonSetsAllPropertiesFromFullObject(): void
    {
        $struct = new ProjectTemplateStruct();
        $obj    = $this->createFullInputObject();

        $result = $struct->hydrateFromJSON($obj, 7);

        self::assertSame($struct, $result);
        self::assertSame(42, $struct->id);
        self::assertSame(7, $struct->uid);
        self::assertSame('My Template', $struct->name);
        self::assertTrue($struct->is_default);
        self::assertSame(99, $struct->id_team);
        self::assertTrue($struct->pretranslate_100);
        self::assertFalse($struct->pretranslate_101);
        self::assertTrue($struct->tm_prioritization);
        self::assertFalse($struct->dialect_strict);
        self::assertSame(5, $struct->public_tm_penalty);
        self::assertFalse($struct->get_public_matches);
        self::assertSame(10, $struct->payable_rate_template_id);
        self::assertSame(20, $struct->qa_model_template_id);
        self::assertSame(30, $struct->filters_template_id);
        self::assertSame(40, $struct->xliff_config_template_id);
        self::assertTrue($struct->character_counter_count_tags);
        self::assertSame('words', $struct->character_counter_mode);
        self::assertSame('general', $struct->subject);
        self::assertSame('en-US', $struct->source_language);
        self::assertSame(80, $struct->mt_quality_value_in_editor);
        self::assertTrue($struct->icu_enabled);
    }

    #[Test]
    public function hydrateFromJsonStoresJsonEncodedStringsForComplexFields(): void
    {
        $struct = new ProjectTemplateStruct();
        $obj    = $this->createFullInputObject();

        $struct->hydrateFromJSON($obj, 7);

        self::assertIsString($struct->mt);
        self::assertIsString($struct->segmentation_rule);
        self::assertIsString($struct->tm);
        self::assertIsString($struct->subfiltering_handlers);
        self::assertIsString($struct->target_language);
    }

    #[Test]
    public function hydrateFromJsonUsesParameterDefaultsForOptionalFields(): void
    {
        $obj           = $this->createFullInputObject();
        $obj->id       = null;
        $obj->uid      = null;
        $obj->icu_enabled = null;

        unset($obj->is_default, $obj->public_tm_penalty, $obj->icu_enabled);

        $struct = new ProjectTemplateStruct();
        $struct->hydrateFromJSON($obj, 55, 101);

        self::assertSame(101, $struct->id);
        self::assertSame(55, $struct->uid);
        self::assertFalse($struct->is_default);
        self::assertSame(0, $struct->public_tm_penalty);
        self::assertFalse($struct->icu_enabled);
    }

    // ── Phase 2 — json_encode false → null ───────────────────────

    #[Test]
    public function hydrateFromJsonSetsMtToNullWhenJsonEncodeFails(): void
    {
        $struct = new ProjectTemplateStruct();
        $obj    = $this->createFullInputObject();

        // invalid UTF-8 → json_encode returns false
        $obj->mt = "\xB1\x31";

        $struct->hydrateFromJSON($obj, 1);

        self::assertNull($struct->mt, 'mt should be null when json_encode fails, not empty string');
    }

    #[Test]
    public function hydrateFromJsonSetsSubfilteringHandlersToNullWhenJsonEncodeFails(): void
    {
        $struct = new ProjectTemplateStruct();
        $obj    = $this->createFullInputObject();

        $obj->subfiltering_handlers = "\xB1\x31";

        $struct->hydrateFromJSON($obj, 1);

        self::assertNull(
            $struct->subfiltering_handlers,
            'subfiltering_handlers should be null when json_encode fails, not empty string'
        );
    }

    // ── Phase 3 — DateTime safety in jsonSerialize ───────────────

    #[Test]
    public function jsonSerializeFormatsValidDatesAsRfc822(): void
    {
        $struct             = new ProjectTemplateStruct();
        $struct->created_at  = '2024-06-15 14:30:00';
        $struct->modified_at = '2024-06-16 09:00:00';

        $result = $struct->jsonSerialize();

        self::assertIsString($result['created_at']);
        self::assertIsString($result['modified_at']);
        self::assertSame(
            strtotime('2024-06-15 14:30:00'),
            strtotime($result['created_at'])
        );
        self::assertSame(
            strtotime('2024-06-16 09:00:00'),
            strtotime($result['modified_at'])
        );
    }

    #[Test]
    public function jsonSerializeReturnsNullForNullModifiedAt(): void
    {
        $struct              = new ProjectTemplateStruct();
        $struct->created_at  = '2024-06-15 14:30:00';
        $struct->modified_at = null;

        $result = $struct->jsonSerialize();

        self::assertNull(
            $result['modified_at'],
            'modified_at should be null in JSON output when the property is null'
        );
    }

    // ── Phase 4 — Getter return type shapes ──────────────────────

    #[Test]
    public function getTmReturnsDecodedArrayFromValidJson(): void
    {
        $struct     = new ProjectTemplateStruct();
        $struct->tm = json_encode([
            ['key' => 'abc', 'name' => 'TM 1'],
            ['key' => 'def', 'name' => 'TM 2'],
        ]);

        $result = $struct->getTm();

        self::assertIsArray($result);
        self::assertCount(2, $result);
    }

    #[Test]
    public function getTmReturnsEmptyArrayWhenPropertyIsNull(): void
    {
        $struct     = new ProjectTemplateStruct();
        $struct->tm = null;

        self::assertSame([], $struct->getTm());
    }

    #[Test]
    public function getTargetLanguageReturnsListOfStrings(): void
    {
        $struct                  = new ProjectTemplateStruct();
        $struct->target_language = serialize(['it-IT', 'de-DE', 'fr-FR']);

        $result = $struct->getTargetLanguage();

        self::assertSame(['it-IT', 'de-DE', 'fr-FR'], $result);
    }

    #[Test]
    public function getTargetLanguageReturnsEmptyArrayWhenPropertyIsNull(): void
    {
        $struct                  = new ProjectTemplateStruct();
        $struct->target_language = null;

        self::assertSame([], $struct->getTargetLanguage());
    }

    #[Test]
    public function getSubfilteringHandlersReturnsDecodedArray(): void
    {
        $struct                       = new ProjectTemplateStruct();
        $struct->subfiltering_handlers = json_encode(['handler1' => true, 'handler2' => false]);

        $result = $struct->getSubfilteringHandlers();

        self::assertIsArray($result);
        self::assertArrayHasKey('handler1', $result);
    }

    #[Test]
    public function getSubfilteringHandlersReturnsEmptyArrayWhenPropertyIsEmpty(): void
    {
        $struct                        = new ProjectTemplateStruct();
        $struct->subfiltering_handlers = null;

        self::assertSame([], $struct->getSubfilteringHandlers());
    }

    #[Test]
    public function jsonSerializeReturnsCompleteStructure(): void
    {
        $struct = new ProjectTemplateStruct();
        $obj    = $this->createFullInputObject();
        $struct->hydrateFromJSON($obj, 7);
        $struct->created_at  = '2024-01-15 10:00:00';
        $struct->modified_at = '2024-01-16 12:00:00';

        $result = $struct->jsonSerialize();

        $expectedKeys = [
            'id', 'name', 'is_default', 'uid', 'id_team',
            'segmentation_rule', 'mt', 'tm',
            'payable_rate_template_id', 'qa_model_template_id',
            'filters_template_id', 'xliff_config_template_id',
            'get_public_matches', 'public_tm_penalty',
            'pretranslate_100', 'pretranslate_101',
            'tm_prioritization', 'dialect_strict',
            'mt_quality_value_in_editor',
            'character_counter_count_tags', 'character_counter_mode',
            'subject', 'source_language', 'target_language',
            'created_at', 'modified_at', 'icu_enabled',
        ];

        foreach ($expectedKeys as $key) {
            self::assertArrayHasKey($key, $result, "jsonSerialize output missing key: $key");
        }

        self::assertIsObject($result['segmentation_rule']);
        self::assertIsObject($result['mt']);
        self::assertIsArray($result['tm']);
        self::assertIsArray($result['target_language']);
    }
}
