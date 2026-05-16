<?php

namespace unit\Model\Projects;

use DateTime;
use Model\Jobs\JobsMetadataMarshaller;
use Model\Projects\ProjectTemplateStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use TestHelpers\AbstractTest;
use TypeError;

class ProjectTemplateStructTest extends AbstractTest
{
    #[Test]
    public function hydrateFromJSONMapsFieldsAndEncodesStructuredValues(): void
    {
        $input = $this->makeHydrationInput();
        $input->id = 99;
        $input->uid = 77;
        $input->is_default = true;
        $input->public_tm_penalty = 25;
        $input->target_language = ['it-IT', 'fr-FR'];
        $input->mt_quality_value_in_editor = '42';
        $input->icu_enabled = true;

        $struct = new ProjectTemplateStruct();
        $result = $struct->hydrateFromJSON($input, 1, 2);

        $this->assertSame($struct, $result);
        $this->assertSame(99, $struct->id);
        $this->assertSame(77, $struct->uid);
        $this->assertSame('Template Name', $struct->name);
        $this->assertTrue($struct->is_default);
        $this->assertSame(10, $struct->id_team);
        $this->assertSame('{"rules":["split_on_newline"],"version":1}', $struct->segmentation_rule);
        $this->assertTrue($struct->pretranslate_100);
        $this->assertFalse($struct->pretranslate_101);
        $this->assertTrue($struct->tm_prioritization);
        $this->assertFalse($struct->dialect_strict);
        $this->assertTrue($struct->get_public_matches);
        $this->assertSame('{"engine":"modernmt","enabled":true}', $struct->mt);
        $this->assertSame('[{"id":11},{"id":22}]', $struct->tm);
        $this->assertSame(25, $struct->public_tm_penalty);
        $this->assertSame(7, $struct->payable_rate_template_id);
        $this->assertSame(8, $struct->qa_model_template_id);
        $this->assertSame(9, $struct->filters_template_id);
        $this->assertSame(10, $struct->xliff_config_template_id);
        $this->assertTrue($struct->character_counter_count_tags);
        $this->assertSame('source', $struct->character_counter_mode);
        $this->assertSame('medical', $struct->subject);
        $this->assertSame('{"html":{"enabled":true}}', $struct->subfiltering_handlers);
        $this->assertSame('en-US', $struct->source_language);
        $this->assertSame(serialize(['it-IT', 'fr-FR']), $struct->target_language);
        $this->assertSame(42, $struct->mt_quality_value_in_editor);
        $this->assertTrue($struct->icu_enabled);
    }

    #[Test]
    public function hydrateFromJSONUsesFallbacksAndHandlesJsonEncodingFailures(): void
    {
        $input = $this->makeHydrationInput();
        unset($input->id, $input->uid, $input->is_default, $input->public_tm_penalty, $input->icu_enabled);
        $input->segmentation_rule = null;
        $input->tm = [];
        $input->target_language = [];
        $input->mt = NAN;
        $input->subfiltering_handlers = NAN;
        $input->mt_quality_value_in_editor = 0;

        $struct = new ProjectTemplateStruct();
        $struct->hydrateFromJSON($input, 123, 456);

        $this->assertSame(456, $struct->id);
        $this->assertSame(123, $struct->uid);
        $this->assertFalse($struct->is_default);
        $this->assertSame(0, $struct->public_tm_penalty);
        $this->assertNull($struct->segmentation_rule);
        $this->assertNull($struct->tm);
        $this->assertNull($struct->target_language);
        $this->assertNull($struct->mt);
        $this->assertNull($struct->subfiltering_handlers);
        $this->assertNull($struct->mt_quality_value_in_editor);
        $this->assertFalse($struct->icu_enabled);
    }

    #[Test]
    public function getSegmentationRuleAndGetMtDecodeJsonOrReturnEmptyObject(): void
    {
        $struct = new ProjectTemplateStruct();
        $struct->segmentation_rule = '{"rule":"srx"}';
        $struct->mt = '{"provider":"mmt"}';

        $segmentationRule = $struct->getSegmentationRule();
        $mt = $struct->getMt();

        $this->assertInstanceOf(stdClass::class, $segmentationRule);
        $this->assertSame('srx', $segmentationRule->rule);
        $this->assertInstanceOf(stdClass::class, $mt);
        $this->assertSame('mmt', $mt->provider);

        $struct->segmentation_rule = '';
        $struct->mt = null;

        $this->assertEquals(new stdClass(), $struct->getSegmentationRule());
        $this->assertEquals(new stdClass(), $struct->getMt());
    }

    #[Test]
    public function getTmDecodesListOrReturnsEmptyArray(): void
    {
        $struct = new ProjectTemplateStruct();
        $struct->tm = '[{"id":3},{"id":5}]';

        $decodedTm = $struct->getTm();

        $this->assertCount(2, $decodedTm);
        $this->assertSame(3, $decodedTm[0]->id);
        $this->assertSame(5, $decodedTm[1]->id);

        $struct->tm = null;

        $this->assertSame([], $struct->getTm());
    }

    #[Test]
    #[DataProvider('emptyTargetLanguageProvider')]
    public function getTargetLanguageReturnsEmptyArrayWhenFieldIsEmpty(?string $targetLanguage): void
    {
        $struct = new ProjectTemplateStruct();
        $struct->target_language = $targetLanguage;

        $this->assertSame([], $struct->getTargetLanguage());
    }

    public static function emptyTargetLanguageProvider(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'string zero' => ['0'],
        ];
    }

    #[Test]
    public function getTargetLanguageUnserializesStoredList(): void
    {
        $struct = new ProjectTemplateStruct();
        $struct->target_language = serialize(['de-DE', 'es-ES']);

        $this->assertSame(['de-DE', 'es-ES'], $struct->getTargetLanguage());
    }

    #[Test]
    public function getTargetLanguageThrowsTypeErrorWhenUnserializeDoesNotReturnArray(): void
    {
        $struct = new ProjectTemplateStruct();
        $struct->target_language = 'b:0;';

        $this->expectException(TypeError::class);

        $struct->getTargetLanguage();
    }

    #[Test]
    public function getSubfilteringHandlersDecodesToAssociativeArrayOrEmptyArray(): void
    {
        $struct = new ProjectTemplateStruct();
        $struct->subfiltering_handlers = '{"xml":{"mode":"strict"}}';

        $this->assertSame(['xml' => ['mode' => 'strict']], $struct->getSubfilteringHandlers());

        $struct->subfiltering_handlers = null;

        $this->assertSame([], $struct->getSubfilteringHandlers());
    }

    #[Test]
    public function getSubfilteringHandlersReturnsNullForInvalidJsonPayload(): void
    {
        $struct = new ProjectTemplateStruct();
        $struct->subfiltering_handlers = '{invalid-json';

        $this->assertNull($struct->getSubfilteringHandlers());
    }

    #[Test]
    public function jsonSerializeReturnsNormalizedPayloadWithDecodedValuesAndFormattedDates(): void
    {
        $struct = new ProjectTemplateStruct();
        $struct->id = null;
        $struct->name = 'Template A';
        $struct->is_default = true;
        $struct->uid = 55;
        $struct->id_team = 22;
        $struct->segmentation_rule = '{"break":"sentence"}';
        $struct->mt = '{"provider":"mmt"}';
        $struct->tm = '[{"id":1001}]';
        $struct->payable_rate_template_id = 0;
        $struct->qa_model_template_id = 0;
        $struct->filters_template_id = 0;
        $struct->xliff_config_template_id = 0;
        $struct->get_public_matches = true;
        $struct->public_tm_penalty = 0;
        $struct->pretranslate_100 = true;
        $struct->pretranslate_101 = false;
        $struct->tm_prioritization = true;
        $struct->dialect_strict = false;
        $struct->mt_quality_value_in_editor = 88;
        $struct->character_counter_count_tags = true;
        $struct->character_counter_mode = 'target';
        $struct->subject = 'legal';
        $struct->subfiltering_handlers = '{"html":{"enabled":true}}';
        $struct->source_language = 'en-US';
        $struct->target_language = serialize(['it-IT']);
        $struct->created_at = '2026-05-01 10:20:30';
        $struct->modified_at = '2026-05-03 11:22:33';
        $struct->icu_enabled = true;

        $payload = $struct->jsonSerialize();

        $this->assertSame(0, $payload['id']);
        $this->assertSame('Template A', $payload['name']);
        $this->assertTrue($payload['is_default']);
        $this->assertSame(55, $payload['uid']);
        $this->assertSame(22, $payload['id_team']);
        $this->assertSame('sentence', $payload['segmentation_rule']->break);
        $this->assertSame('mmt', $payload['mt']->provider);
        $this->assertCount(1, $payload['tm']);
        $this->assertSame(1001, $payload['tm'][0]->id);
        $this->assertSame(0, $payload['payable_rate_template_id']);
        $this->assertSame(0, $payload['qa_model_template_id']);
        $this->assertSame(0, $payload['filters_template_id']);
        $this->assertSame(0, $payload['xliff_config_template_id']);
        $this->assertSame(0, $payload['public_tm_penalty']);
        $this->assertSame(88, $payload['mt_quality_value_in_editor']);
        $this->assertSame(['html' => ['enabled' => true]], $payload[JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value]);
        $this->assertSame(['it-IT'], $payload['target_language']);
        $this->assertSame((new DateTime('2026-05-01 10:20:30'))->format(DATE_RFC822), $payload['created_at']);
        $this->assertSame((new DateTime('2026-05-03 11:22:33'))->format(DATE_RFC822), $payload['modified_at']);
        $this->assertTrue($payload['icu_enabled']);
    }

    #[Test]
    public function jsonSerializeReturnsNullModifiedAtWhenNotSet(): void
    {
        $struct = new ProjectTemplateStruct();
        $struct->created_at = '2026-05-01 10:20:30';
        $struct->modified_at = null;

        $payload = $struct->jsonSerialize();

        $this->assertNull($payload['modified_at']);
    }

    private function makeHydrationInput(): object
    {
        return (object)[
            'name' => 'Template Name',
            'id_team' => 10,
            'segmentation_rule' => (object)['rules' => ['split_on_newline'], 'version' => 1],
            'pretranslate_100' => true,
            'pretranslate_101' => false,
            'tm_prioritization' => true,
            'dialect_strict' => false,
            'get_public_matches' => true,
            'mt' => (object)['engine' => 'modernmt', 'enabled' => true],
            'tm' => [(object)['id' => 11], (object)['id' => 22]],
            'payable_rate_template_id' => 7,
            'qa_model_template_id' => 8,
            'filters_template_id' => 9,
            'xliff_config_template_id' => 10,
            'character_counter_count_tags' => true,
            'character_counter_mode' => 'source',
            'subject' => 'medical',
            'subfiltering_handlers' => (object)['html' => (object)['enabled' => true]],
            'source_language' => 'en-US',
            'target_language' => ['it-IT'],
            'mt_quality_value_in_editor' => null,
        ];
    }
}
