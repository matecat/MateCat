<?php

namespace unit\Model\ProjectCreation;

use ArrayAccess;
use ArrayObject;
use DomainException;
use JsonSerializable;
use Model\ProjectCreation\ProjectStructure;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

/**
 * Safety-net tests for the {@see ProjectStructure} DTO.
 *
 * These tests document the current contract so that the upcoming
 * removal of {@see ArrayAccess} can be verified: any test that
 * exercises array-access syntax (`$ps['key']`) documents a site
 * that must be migrated to arrow syntax (`$ps->key`) first.
 *
 * @covers \Model\ProjectCreation\ProjectStructure
 */
class ProjectStructureTest extends AbstractTest
{
    // ── Construction ──────────────────────────────────────────────

    #[Test]
    public function constructFromArrayPopulatesKnownProperties(): void
    {
        $ps = new ProjectStructure([
            'project_name'    => 'My Project',
            'uid'             => 42,
            'source_language' => 'en-US',
            'pretranslate_100' => 1,
        ]);

        $this->assertSame('My Project', $ps->project_name);
        $this->assertSame(42, $ps->uid);
        $this->assertSame('en-US', $ps->source_language);
        $this->assertSame(1, $ps->pretranslate_100);
    }

    #[Test]
    public function constructFromEmptyArrayKeepsDefaults(): void
    {
        $ps = new ProjectStructure([]);

        $this->assertNull($ps->id_project);
        $this->assertNull($ps->project_name);
        $this->assertSame('translated_user', $ps->id_customer);
        $this->assertSame('', $ps->owner);
        $this->assertFalse($ps->userIsLogged);
        $this->assertSame(0, $ps->pretranslate_100);
        $this->assertSame(1, $ps->pretranslate_101);
        $this->assertSame('general', $ps->job_subject);
        $this->assertSame(['errors' => [], 'data' => []], $ps->result);
        $this->assertSame([
            'job_list'      => [],
            'job_pass'      => [],
            'job_segments'  => [],
            'job_languages' => [],
            'payable_rates' => [],
        ], $ps->array_jobs);
    }

    #[Test]
    public function constructWithUnknownKeyThrowsDomainException(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/Unknown property/');

        new ProjectStructure(['totally_unknown_key' => 'value']);
    }

    #[Test]
    public function defaultConstructionWithNoArguments(): void
    {
        $ps = new ProjectStructure();

        $this->assertNull($ps->id_project);
        $this->assertSame([], $ps->segments);
        $this->assertFalse($ps->create_2_pass_review);
    }

    // ── Arrow-syntax property access ─────────────────────────────

    #[Test]
    public function arrowWriteAndReadKnownProperty(): void
    {
        $ps = new ProjectStructure();
        $ps->project_name = 'Arrow Test';

        $this->assertSame('Arrow Test', $ps->project_name);
    }

    #[Test]
    public function arrowWriteUnknownPropertyThrowsDomainException(): void
    {
        $ps = new ProjectStructure();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/Unknown property/');

        $ps->nonexistent_property = 'boom';
    }

    #[Test]
    public function arrowReadUnknownPropertyThrowsDomainException(): void
    {
        $ps = new ProjectStructure();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/Trying to get an undefined property/');

        $_ = $ps->nonexistent_property;
    }

    // ── Array-access syntax (current behavior, pre-migration) ────

    #[Test]
    public function arrayAccessWriteAndReadKnownProperty(): void
    {
        $ps = new ProjectStructure();
        $ps['project_name'] = 'ArrayAccess Test';

        $this->assertSame('ArrayAccess Test', $ps['project_name']);
        // Verify the value is also accessible via arrow
        $this->assertSame('ArrayAccess Test', $ps->project_name);
    }

    #[Test]
    public function arrayAccessWriteUnknownKeyThrowsDomainException(): void
    {
        $ps = new ProjectStructure();

        $this->expectException(DomainException::class);

        $ps['nonexistent_key'] = 'value';
    }

    #[Test]
    public function arrayAccessReadUnknownKeyThrowsDomainException(): void
    {
        $ps = new ProjectStructure();

        $this->expectException(DomainException::class);

        $_ = $ps['nonexistent_key'];
    }

    #[Test]
    public function arrayAccessAndArrowAreEquivalent(): void
    {
        $ps = new ProjectStructure();

        // Write via arrow, read via array-access
        $ps->uid = 99;
        $this->assertSame(99, $ps['uid']);

        // Write via array-access, read via arrow
        $ps['source_language'] = 'fr-FR';
        $this->assertSame('fr-FR', $ps->source_language);
    }

    // ── isset() / offsetExists ───────────────────────────────────

    #[Test]
    public function issetViaBracketReturnsTrueForDeclaredProperty(): void
    {
        $ps = new ProjectStructure();

        $this->assertTrue(isset($ps['project_name']));
        $this->assertTrue(isset($ps['id_project']));
        $this->assertTrue(isset($ps['result']));
    }

    #[Test]
    public function issetViaBracketReturnsTrueForNullValuedProperty(): void
    {
        $ps = new ProjectStructure();

        // ppassword defaults to null — property_exists() still returns true
        $this->assertNull($ps->ppassword);
        $this->assertTrue(isset($ps['ppassword']));
    }

    #[Test]
    public function issetViaBracketReturnsFalseForUndeclaredProperty(): void
    {
        $ps = new ProjectStructure();

        $this->assertFalse(isset($ps['totally_nonexistent']));
    }

    // ── unset() / offsetUnset ────────────────────────────────────

    #[Test]
    public function unsetViaBracketSetsPropertyToNull(): void
    {
        $ps = new ProjectStructure();
        $ps->project_name = 'ToBeUnset';

        unset($ps['project_name']);

        $this->assertNull($ps->project_name);
    }

    #[Test]
    public function unsetViaBracketOnUndeclaredKeyThrows(): void
    {
        $ps = new ProjectStructure();

        $this->expectException(DomainException::class);

        unset($ps['nonexistent']);
    }

    // ── toArray() ────────────────────────────────────────────────

    #[Test]
    public function toArrayReturnsAllPublicProperties(): void
    {
        $ps = new ProjectStructure([
            'project_name'    => 'Serialize Me',
            'uid'             => 7,
            'source_language' => 'de-DE',
        ]);

        $arr = $ps->toArray();

        $this->assertIsArray($arr);
        $this->assertSame('Serialize Me', $arr['project_name']);
        $this->assertSame(7, $arr['uid']);
        $this->assertSame('de-DE', $arr['source_language']);

        // Default values should also be present
        $this->assertArrayHasKey('result', $arr);
        $this->assertArrayHasKey('array_jobs', $arr);
        $this->assertArrayHasKey('create_2_pass_review', $arr);
    }

    #[Test]
    public function toArrayRoundTrip(): void
    {
        $input = [
            'project_name'    => 'Round Trip',
            'uid'             => 123,
            'source_language' => 'it-IT',
            'target_language' => ['en-US', 'fr-FR'],
            'pretranslate_100' => 1,
            'result'          => ['errors' => [['code' => 400, 'message' => 'bad']], 'data' => []],
            'array_jobs'      => [
                'job_list'      => [1, 2],
                'job_pass'      => ['a', 'b'],
                'job_segments'  => [],
                'job_languages' => ['en'],
                'payable_rates' => [],
            ],
        ];

        $ps  = new ProjectStructure($input);
        $arr = $ps->toArray();
        $ps2 = new ProjectStructure($arr);

        $this->assertSame($ps->project_name, $ps2->project_name);
        $this->assertSame($ps->uid, $ps2->uid);
        $this->assertSame($ps->source_language, $ps2->source_language);
        $this->assertSame($ps->target_language, $ps2->target_language);
        $this->assertSame($ps->pretranslate_100, $ps2->pretranslate_100);
        $this->assertEquals($ps->result, $ps2->result);
        $this->assertEquals($ps->array_jobs, $ps2->array_jobs);
    }

    #[Test]
    public function toArrayWithMaskFiltersProperties(): void
    {
        $ps = new ProjectStructure([
            'project_name'    => 'Masked',
            'uid'             => 5,
            'source_language' => 'en-US',
        ]);

        $arr = $ps->toArray(['project_name', 'uid']);

        $this->assertSame('Masked', $arr['project_name']);
        $this->assertSame(5, $arr['uid']);
        $this->assertArrayNotHasKey('source_language', $arr);
        $this->assertArrayNotHasKey('result', $arr);
    }

    // ── jsonSerialize() ──────────────────────────────────────────

    #[Test]
    public function jsonSerializeMatchesToArray(): void
    {
        $ps = new ProjectStructure([
            'project_name' => 'JSON Test',
            'uid'          => 55,
        ]);

        $this->assertSame($ps->toArray(), $ps->jsonSerialize());
    }

    #[Test]
    public function implementsJsonSerializableInterface(): void
    {
        $ps = new ProjectStructure();

        $this->assertInstanceOf(JsonSerializable::class, $ps);
    }

    #[Test]
    public function jsonEncodeProducesValidJson(): void
    {
        $ps = new ProjectStructure(['project_name' => 'Encode Test']);

        $json = json_encode($ps);

        $this->assertNotFalse($json);
        $decoded = json_decode($json, true);
        $this->assertSame('Encode Test', $decoded['project_name']);
    }

    // ── count() ──────────────────────────────────────────────────

    #[Test]
    public function countReturnsNumberOfPublicProperties(): void
    {
        $ps = new ProjectStructure();

        // 88 public properties declared on ProjectStructure.
        // The protected $cached_results from AbstractDaoObjectStruct is excluded.
        $this->assertSame(88, count($ps));
    }

    // ── Nested array properties ──────────────────────────────────

    #[Test]
    public function nestedWriteViaArrowSyntaxWorks(): void
    {
        $ps = new ProjectStructure();

        // Arrow syntax returns a reference — nested writes work
        $ps->result['errors'][] = ['code' => 1, 'message' => 'test error'];

        $this->assertCount(1, $ps->result['errors']);
        $this->assertSame('test error', $ps->result['errors'][0]['message']);
    }

    #[Test]
    public function nestedWriteViaArrayAccessIsLost(): void
    {
        $ps = new ProjectStructure();

        // Array-access via offsetGet returns by value — nested writes
        // modify a temporary copy and are silently lost.
        // This documents the known limitation (Discovery #19).
        @$ps['result']['errors'][] = ['code' => 1, 'message' => 'lost'];

        $this->assertCount(0, $ps->result['errors'], 'Nested write via array-access should be lost');
    }

    #[Test]
    public function arrayJobsNestedWriteViaArrowSyntax(): void
    {
        $ps = new ProjectStructure();

        $ps->array_jobs['job_list'][] = 100;
        $ps->array_jobs['job_list'][] = 200;
        $ps->array_jobs['job_pass'][] = 'abc123';

        $this->assertSame([100, 200], $ps->array_jobs['job_list']);
        $this->assertSame(['abc123'], $ps->array_jobs['job_pass']);
        // Unmodified sub-arrays remain empty
        $this->assertSame([], $ps->array_jobs['job_segments']);
    }

    #[Test]
    public function resultStructureDirectAssignment(): void
    {
        $ps = new ProjectStructure();

        $ps->result = ['errors' => [['code' => 500]], 'data' => 'OK'];

        $this->assertCount(1, $ps->result['errors']);
        $this->assertSame('OK', $ps->result['data']);
    }

    // ── ArrayObject-typed properties ─────────────────────────────

    #[Test]
    public function segmentsAcceptsArrayObject(): void
    {
        $ps = new ProjectStructure();
        $ao = new ArrayObject([1 => new ArrayObject(['seg' => 'hello'])]);

        $ps->segments = $ao;

        $this->assertInstanceOf(ArrayObject::class, $ps->segments);
        $this->assertSame('hello', $ps->segments[1]['seg']);
    }

    #[Test]
    public function metadataAcceptsArrayObject(): void
    {
        $ps = new ProjectStructure();
        $ao = new ArrayObject(['key' => 'value']);

        $ps->metadata = $ao;

        $this->assertInstanceOf(ArrayObject::class, $ps->metadata);
    }

    // ── getArrayCopy() compatibility ─────────────────────────────

    #[Test]
    public function getArrayCopyReturnsSameAsToArray(): void
    {
        $ps = new ProjectStructure(['project_name' => 'Copy Test']);

        $this->assertSame($ps->toArray(), $ps->getArrayCopy());
    }

    // ── Implements ArrayAccess (pre-migration contract) ──────────

    #[Test]
    public function implementsArrayAccessInterface(): void
    {
        $ps = new ProjectStructure();

        $this->assertInstanceOf(ArrayAccess::class, $ps);
    }

    // ── Edge cases ───────────────────────────────────────────────

    #[Test]
    public function constructWithNullablePropertySetToExplicitNull(): void
    {
        $ps = new ProjectStructure(['id_project' => null, 'ppassword' => null]);

        $this->assertNull($ps->id_project);
        $this->assertNull($ps->ppassword);
    }

    #[Test]
    public function overwriteDefaultValueViaConstruction(): void
    {
        $ps = new ProjectStructure([
            'id_customer' => 'custom_user',
            'job_subject' => 'legal',
            'pretranslate_101' => 0,
        ]);

        $this->assertSame('custom_user', $ps->id_customer);
        $this->assertSame('legal', $ps->job_subject);
        $this->assertSame(0, $ps->pretranslate_101);
    }

    #[Test]
    public function multipleWritesToSamePropertyKeepsLatestValue(): void
    {
        $ps = new ProjectStructure();

        $ps->project_name = 'first';
        $ps->project_name = 'second';
        $ps->project_name = 'third';

        $this->assertSame('third', $ps->project_name);
    }
}
