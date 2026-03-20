<?php

namespace unit\Model\ProjectCreation;

use ArrayObject;
use Model\ProjectCreation\ProjectStructure;
use Model\ProjectCreation\TranslationTuple;
use Model\Segments\SegmentMetadataStruct;
use Model\Segments\SegmentOriginalDataStruct;
use Model\Segments\SegmentStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

/**
 * Audit tests for bugs C8–C13 in {@see \Model\ProjectCreation\SegmentExtractor}.
 *
 * SegmentExtractor::extract() calls ArrayObject methods (->offsetSet(),
 * ->offsetExists(), ->append()) on 6 ProjectStructure properties that
 * default to []. Since nothing assigns new ArrayObject() before extract()
 * runs, these calls crash.
 *
 * After applying Option B (plain arrays), all ArrayObject method calls are
 * replaced with plain PHP array syntax. These tests verify that the plain-
 * array replacements produce the same data structures the downstream
 * pipeline expects.
 *
 * @see \Model\ProjectCreation\SegmentExtractor
 * @see \Model\ProjectCreation\ProjectStructure
 */
class SegmentExtractorAuditTest extends AbstractTest
{
    private ProjectStructure $ps;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a fresh ProjectStructure where all Group C pipeline
        // properties keep their default value of [] (NOT new ArrayObject()).
        // This simulates the state before extract() runs, which is exactly
        // where the C8–C13 bugs manifest.
        $this->ps = new ProjectStructure([
            'id_project'      => 999,
            'source_language'  => 'en-US',
            'target_language'  => ['it-IT'],
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // =========================================================================
    // C8: SegmentExtractor.php:123
    //   $projectStructure->segments->offsetSet($fid, new ArrayObject([]))
    //
    // Fix: $projectStructure->segments[$fid] = []
    // =========================================================================

    #[Test]
    public function c8_segments_default_is_plain_array(): void
    {
        $this->assertIsArray($this->ps->segments);
        $this->assertSame([], $this->ps->segments);
    }

    #[Test]
    public function c8_segments_bracket_assignment_works_as_offsetSet_replacement(): void
    {
        $fid = 42;

        // Option B replacement for: $ps->segments->offsetSet($fid, new ArrayObject([]))
        $this->ps->segments[$fid] = [];

        $this->assertArrayHasKey($fid, $this->ps->segments);
        $this->assertIsArray($this->ps->segments[$fid]);
        $this->assertSame([], $this->ps->segments[$fid]);
    }

    #[Test]
    public function c8_segments_nested_append_works_with_bracket_push(): void
    {
        $fid = 42;
        $this->ps->segments[$fid] = [];

        $seg = new SegmentStruct([
            'id'                  => 1,
            'id_file'             => $fid,
            'id_file_part'        => null,
            'internal_id'         => 'tu1',
            'segment'             => 'Hello world',
            'segment_hash'        => md5('Hello world'),
            'raw_word_count'      => 2,
            'show_in_cattool'     => 1,
        ]);

        // Option B replacement for: $ps->segments[$fid]->append($seg)
        $this->ps->segments[$fid][] = $seg;

        $this->assertCount(1, $this->ps->segments[$fid]);
        $this->assertInstanceOf(SegmentStruct::class, $this->ps->segments[$fid][0]);
        $this->assertSame('Hello world', $this->ps->segments[$fid][0]->segment);
    }

    // =========================================================================
    // C9: SegmentExtractor.php:124
    //   $projectStructure->segments_original_data->offsetSet($fid, new ArrayObject([]))
    //
    // Fix: $projectStructure->segments_original_data[$fid] = []
    // =========================================================================

    #[Test]
    public function c9_segments_original_data_default_is_plain_array(): void
    {
        $this->assertIsArray($this->ps->segments_original_data);
        $this->assertSame([], $this->ps->segments_original_data);
    }

    #[Test]
    public function c9_segments_original_data_bracket_assignment_works(): void
    {
        $fid = 42;

        // Option B replacement
        $this->ps->segments_original_data[$fid] = [];

        $this->assertArrayHasKey($fid, $this->ps->segments_original_data);
        $this->assertSame([], $this->ps->segments_original_data[$fid]);
    }

    #[Test]
    public function c9_segments_original_data_nested_append_works(): void
    {
        $fid = 42;
        $this->ps->segments_original_data[$fid] = [];

        $struct = (new SegmentOriginalDataStruct())->setMap(['d1' => '<br/>']);

        // Option B replacement for: $ps->segments_original_data[$fid]->append($struct)
        $this->ps->segments_original_data[$fid][] = $struct;

        $this->assertCount(1, $this->ps->segments_original_data[$fid]);
        $this->assertInstanceOf(SegmentOriginalDataStruct::class, $this->ps->segments_original_data[$fid][0]);
        $this->assertSame(['d1' => '<br/>'], $this->ps->segments_original_data[$fid][0]->getMap());
    }

    // =========================================================================
    // C10: SegmentExtractor.php:125
    //   $projectStructure->segments_meta_data->offsetSet($fid, new ArrayObject([]))
    //
    // Fix: $projectStructure->segments_meta_data[$fid] = []
    // =========================================================================

    #[Test]
    public function c10_segments_meta_data_default_is_plain_array(): void
    {
        $this->assertIsArray($this->ps->segments_meta_data);
        $this->assertSame([], $this->ps->segments_meta_data);
    }

    #[Test]
    public function c10_segments_meta_data_bracket_assignment_works(): void
    {
        $fid = 42;

        // Option B replacement
        $this->ps->segments_meta_data[$fid] = [];

        $this->assertArrayHasKey($fid, $this->ps->segments_meta_data);
        $this->assertSame([], $this->ps->segments_meta_data[$fid]);
    }

    #[Test]
    public function c10_segments_meta_data_nested_append_works(): void
    {
        $fid = 42;
        $this->ps->segments_meta_data[$fid] = [];

        $meta = new SegmentMetadataStruct();
        $meta->meta_key   = 'sizeRestriction';
        $meta->meta_value = '255';

        // Option B replacement for: $ps->segments_meta_data[$fid]->append($meta)
        $this->ps->segments_meta_data[$fid][] = $meta;

        $this->assertCount(1, $this->ps->segments_meta_data[$fid]);
        $this->assertInstanceOf(SegmentMetadataStruct::class, $this->ps->segments_meta_data[$fid][0]);
        $this->assertSame('sizeRestriction', $this->ps->segments_meta_data[$fid][0]->meta_key);
        $this->assertSame('255', $this->ps->segments_meta_data[$fid][0]->meta_value);
    }

    // =========================================================================
    // C11: SegmentExtractor.php:300-301
    //   $projectStructure->translations->offsetExists($trans_unit_reference)
    //   $projectStructure->translations->offsetSet($trans_unit_reference, new ArrayObject())
    //
    // Fix: isset($ps->translations[$ref]) / $ps->translations[$ref] = []
    // =========================================================================

    #[Test]
    public function c11_translations_default_is_plain_array(): void
    {
        $this->assertIsArray($this->ps->translations);
        $this->assertSame([], $this->ps->translations);
    }

    #[Test]
    public function c11_translations_isset_works_as_offsetExists_replacement(): void
    {
        $ref = '42-tu1';

        // Before assignment, the key does not exist
        $this->assertFalse(isset($this->ps->translations[$ref]));

        // Option B replacement for: $ps->translations->offsetSet($ref, new ArrayObject())
        $this->ps->translations[$ref] = [];

        // Option B replacement for: $ps->translations->offsetExists($ref)
        $this->assertTrue(isset($this->ps->translations[$ref]));
    }

    #[Test]
    public function c11_translations_seg_source_nested_structure(): void
    {
        $ref = '42-tu1';
        $mid = '1';

        // Simulate the seg-source path (lines 300-315)
        if (!isset($this->ps->translations[$ref])) {
            $this->ps->translations[$ref] = [];
        }

        // Option B replacement for: $ps->translations[$ref]->offsetSet($mid, new ArrayObject([...]))
        $this->ps->translations[$ref][$mid] = new TranslationTuple(
            'Translated target text',
            'source text',
            1.0,
            0,
        );

        $this->assertArrayHasKey($ref, $this->ps->translations);
        $this->assertArrayHasKey($mid, $this->ps->translations[$ref]);
        $tuple = $this->ps->translations[$ref][$mid];
        $this->assertInstanceOf(TranslationTuple::class, $tuple);
        $this->assertSame('Translated target text', $tuple->target);
        $this->assertSame(0, $tuple->mrkPosition);
    }

    // =========================================================================
    // C12: SegmentExtractor.php:396-397
    //   Same pattern as C11 but in the non-seg-source code path
    //   $projectStructure->translations->offsetExists($trans_unit_reference)
    //   $projectStructure->translations->offsetSet($trans_unit_reference, new ArrayObject())
    //   $projectStructure->translations[$trans_unit_reference]->append(new ArrayObject([...]))
    //
    // Fix: isset() + bracket assignment + []= push
    // =========================================================================

    #[Test]
    public function c12_translations_non_seg_source_path(): void
    {
        $ref = '42-tu2';

        // Simulate the non-seg-source path (lines 396-409)
        if (!isset($this->ps->translations[$ref])) {
            $this->ps->translations[$ref] = [];
        }

        // Option B replacement for: $ps->translations[$ref]->append(new ArrayObject([...]))
        $this->ps->translations[$ref][] = new TranslationTuple(
            'Pre-translated target',
            'source text',
            1.0,
        );

        $this->assertCount(1, $this->ps->translations[$ref]);
        $this->assertSame('Pre-translated target', $this->ps->translations[$ref][0]->target);
    }

    #[Test]
    public function c12_translations_multiple_pre_translations_accumulate(): void
    {
        $ref = '42-tu3';
        $this->ps->translations[$ref] = [];

        // Multiple trans-units with the same reference should accumulate
        $this->ps->translations[$ref][] = new TranslationTuple(
            'First translation',
            'source text',
            1.0,
        );
        $this->ps->translations[$ref][] = new TranslationTuple(
            'Second translation',
            'source text',
            1.0,
        );

        $this->assertCount(2, $this->ps->translations[$ref]);
        $this->assertSame('First translation', $this->ps->translations[$ref][0]->target);
        $this->assertSame('Second translation', $this->ps->translations[$ref][1]->target);
    }

    // =========================================================================
    // C13: SegmentExtractor.php:806-807 — initArrayObject()
    //   private function initArrayObject(string $key, string $id, ProjectStructure $ps)
    //     if (!$ps->$key->offsetExists($id))
    //         $ps->$key->offsetSet($id, new ArrayObject())
    //
    // Fix: if (!array_key_exists($id, $ps->$key)) { $ps->$key[$id] = []; }
    // =========================================================================

    #[Test]
    public function c13_notes_default_is_plain_array(): void
    {
        $this->assertIsArray($this->ps->notes);
        $this->assertSame([], $this->ps->notes);
    }

    #[Test]
    public function c13_context_group_default_is_plain_array(): void
    {
        $this->assertIsArray($this->ps->context_group);
        $this->assertSame([], $this->ps->context_group);
    }

    #[Test]
    public function c13_initArrayObject_replacement_with_array_key_exists(): void
    {
        $key = 'notes';
        $id  = '42-tu1';

        // Verify the key does not exist yet
        $this->assertFalse(array_key_exists($id, $this->ps->$key));

        // Option B replacement for initArrayObject():
        //   if (!$ps->$key->offsetExists($id))
        //     $ps->$key->offsetSet($id, new ArrayObject())
        if (!array_key_exists($id, $this->ps->$key)) {
            $this->ps->{$key}[$id] = [];
        }

        $this->assertTrue(array_key_exists($id, $this->ps->$key));
        $this->assertIsArray($this->ps->{$key}[$id]);
        $this->assertSame([], $this->ps->{$key}[$id]);
    }

    #[Test]
    public function c13_initArrayObject_is_idempotent(): void
    {
        $key = 'context_group';
        $id  = '42-tu5';

        // First call — creates the key
        if (!array_key_exists($id, $this->ps->$key)) {
            $this->ps->{$key}[$id] = [];
        }

        // Add some data
        $this->ps->{$key}[$id]['context_json'] = ['ctx' => 'value'];

        // Second call — should NOT overwrite existing data
        if (!array_key_exists($id, $this->ps->$key)) {
            $this->ps->{$key}[$id] = [];
        }

        $this->assertSame(['ctx' => 'value'], $this->ps->{$key}[$id]['context_json']);
    }

    // =========================================================================
    // Downstream effect: Lines 611, 615, 637
    //   segments_meta_data[$fid]->append()
    //   segments_original_data[$fid]->append()
    //   segments[$fid]->append()
    //
    // These rely on the nested child being an ArrayObject (or array after fix).
    // =========================================================================

    #[Test]
    public function downstream_buildAndAppendSegment_full_structure(): void
    {
        $fid = 42;

        // Step 1: Initialize per-file arrays (C8/C9/C10 fix)
        $this->ps->segments[$fid]               = [];
        $this->ps->segments_original_data[$fid]  = [];
        $this->ps->segments_meta_data[$fid]      = [];

        // Step 2: Simulate buildAndAppendSegment() at lines 611, 615, 637
        $metadataStruct = new SegmentMetadataStruct();
        $metadataStruct->meta_key   = 'sizeRestriction';
        $metadataStruct->meta_value = '128';

        // Line 611: $ps->segments_meta_data[$fid]->append($metadataStruct)
        $this->ps->segments_meta_data[$fid][] = $metadataStruct;

        $origDataStruct = (new SegmentOriginalDataStruct())->setMap(['d1' => '&amp;']);

        // Line 615: $ps->segments_original_data[$fid]->append($segmentOriginalDataStruct)
        $this->ps->segments_original_data[$fid][] = $origDataStruct;

        $segStruct = new SegmentStruct([
            'id'               => 1,
            'id_file'          => $fid,
            'id_file_part'     => 100,
            'internal_id'      => 'tu1',
            'segment'          => 'Test segment',
            'segment_hash'     => md5('Test segment'),
            'raw_word_count'   => 2,
            'show_in_cattool'  => 1,
        ]);

        // Line 637: $ps->segments[$fid]->append($segStruct)
        $this->ps->segments[$fid][] = $segStruct;

        // Verify the structure has the correct shape
        $this->assertCount(1, $this->ps->segments_meta_data[$fid]);
        $this->assertCount(1, $this->ps->segments_original_data[$fid]);
        $this->assertCount(1, $this->ps->segments[$fid]);

        $this->assertInstanceOf(SegmentMetadataStruct::class, $this->ps->segments_meta_data[$fid][0]);
        $this->assertInstanceOf(SegmentOriginalDataStruct::class, $this->ps->segments_original_data[$fid][0]);
        $this->assertInstanceOf(SegmentStruct::class, $this->ps->segments[$fid][0]);

        $this->assertSame('128', $this->ps->segments_meta_data[$fid][0]->meta_value);
        $this->assertSame(['d1' => '&amp;'], $this->ps->segments_original_data[$fid][0]->getMap());
        $this->assertSame('Test segment', $this->ps->segments[$fid][0]->segment);
    }

    #[Test]
    public function downstream_multiple_segments_per_file(): void
    {
        $fid = 7;
        $this->ps->segments[$fid]               = [];
        $this->ps->segments_original_data[$fid]  = [];
        $this->ps->segments_meta_data[$fid]      = [];

        // Simulate 3 segments from the same file (e.g., 3 mrk elements)
        for ($i = 0; $i < 3; $i++) {
            $meta = new SegmentMetadataStruct();
            $meta->meta_key   = 'sizeRestriction';
            $meta->meta_value = (string)(100 + $i);
            $this->ps->segments_meta_data[$fid][] = $meta;

            $this->ps->segments_original_data[$fid][] = (new SegmentOriginalDataStruct())->setMap([]);

            $this->ps->segments[$fid][] = new SegmentStruct([
                'id'               => $i + 1,
                'id_file'          => $fid,
                'internal_id'      => "tu{$i}",
                'segment'          => "Segment {$i}",
                'segment_hash'     => md5("Segment {$i}"),
                'raw_word_count'   => 1,
                'show_in_cattool'  => 1,
            ]);
        }

        $this->assertCount(3, $this->ps->segments[$fid]);
        $this->assertCount(3, $this->ps->segments_original_data[$fid]);
        $this->assertCount(3, $this->ps->segments_meta_data[$fid]);

        // Verify ordering is preserved (indexed 0, 1, 2)
        $this->assertSame('100', $this->ps->segments_meta_data[$fid][0]->meta_value);
        $this->assertSame('101', $this->ps->segments_meta_data[$fid][1]->meta_value);
        $this->assertSame('102', $this->ps->segments_meta_data[$fid][2]->meta_value);
    }

    // =========================================================================
    // Downstream effect: Lines 758-768
    //   Notes structure initialization with nested ->offsetSet() calls
    //
    //   $ps->notes[$internal_id]->offsetExists('entries')
    //   $ps->notes[$internal_id]->offsetSet('from', new ArrayObject())
    //   $ps->notes[$internal_id]['from']->offsetSet('entries', new ArrayObject())
    //   $ps->notes[$internal_id]['from']->offsetSet('json', new ArrayObject())
    //   $ps->notes[$internal_id]->offsetSet('entries', new ArrayObject())
    //   $ps->notes[$internal_id]->offsetSet('json', new ArrayObject())
    //   $ps->notes[$internal_id]->offsetSet('json_segment_ids', new ArrayObject())
    //   $ps->notes[$internal_id]->offsetSet('segment_ids', new ArrayObject())
    // =========================================================================

    #[Test]
    public function downstream_notes_full_structure_initialization(): void
    {
        $internalId = '42-tu1';

        // C13 fix: initArrayObject() replacement
        if (!array_key_exists($internalId, $this->ps->notes)) {
            $this->ps->notes[$internalId] = [];
        }

        // Lines 758-765 fix: replace nested offsetSet / offsetExists with plain array syntax
        if (!isset($this->ps->notes[$internalId]['entries'])) {
            $this->ps->notes[$internalId]['from']             = [];
            $this->ps->notes[$internalId]['from']['entries']   = [];
            $this->ps->notes[$internalId]['from']['json']      = [];
            $this->ps->notes[$internalId]['entries']            = [];
            $this->ps->notes[$internalId]['json']               = [];
            $this->ps->notes[$internalId]['json_segment_ids']   = [];
            $this->ps->notes[$internalId]['segment_ids']        = [];
        }

        // Verify the full notes sub-structure
        $note = $this->ps->notes[$internalId];
        $this->assertIsArray($note);
        $this->assertArrayHasKey('from', $note);
        $this->assertArrayHasKey('entries', $note['from']);
        $this->assertArrayHasKey('json', $note['from']);
        $this->assertArrayHasKey('entries', $note);
        $this->assertArrayHasKey('json', $note);
        $this->assertArrayHasKey('json_segment_ids', $note);
        $this->assertArrayHasKey('segment_ids', $note);

        // All leaf nodes are empty arrays initially
        $this->assertSame([], $note['from']['entries']);
        $this->assertSame([], $note['from']['json']);
        $this->assertSame([], $note['entries']);
        $this->assertSame([], $note['json']);
        $this->assertSame([], $note['json_segment_ids']);
        $this->assertSame([], $note['segment_ids']);
    }

    #[Test]
    public function downstream_notes_append_content_and_from_attribute(): void
    {
        $internalId = '42-tu1';

        // Initialize
        $this->ps->notes[$internalId] = [];
        $this->ps->notes[$internalId]['from']             = ['entries' => [], 'json' => []];
        $this->ps->notes[$internalId]['entries']            = [];
        $this->ps->notes[$internalId]['json']               = [];
        $this->ps->notes[$internalId]['json_segment_ids']   = [];
        $this->ps->notes[$internalId]['segment_ids']        = [];

        // Line 768: $ps->notes[$internal_id][$noteKey]->append($noteContent)
        $this->ps->notes[$internalId]['entries'][] = 'This is a note';

        // Line 772: $ps->notes[$internal_id]['from'][$noteKey]->append($note['from'])
        $this->ps->notes[$internalId]['from']['entries'][] = 'translator';

        // Add a second note
        $this->ps->notes[$internalId]['entries'][] = 'Another note';
        $this->ps->notes[$internalId]['from']['entries'][] = 'reviewer';

        $this->assertCount(2, $this->ps->notes[$internalId]['entries']);
        $this->assertCount(2, $this->ps->notes[$internalId]['from']['entries']);
        $this->assertSame('This is a note', $this->ps->notes[$internalId]['entries'][0]);
        $this->assertSame('Another note', $this->ps->notes[$internalId]['entries'][1]);
        $this->assertSame('translator', $this->ps->notes[$internalId]['from']['entries'][0]);
        $this->assertSame('reviewer', $this->ps->notes[$internalId]['from']['entries'][1]);
    }

    #[Test]
    public function downstream_notes_json_content(): void
    {
        $internalId = '42-tu2';

        // Initialize
        $this->ps->notes[$internalId] = [];
        $this->ps->notes[$internalId]['from']             = ['entries' => [], 'json' => []];
        $this->ps->notes[$internalId]['entries']            = [];
        $this->ps->notes[$internalId]['json']               = [];
        $this->ps->notes[$internalId]['json_segment_ids']   = [];
        $this->ps->notes[$internalId]['segment_ids']        = [];

        // JSON notes — line 768 when $noteKey = 'json'
        $jsonContent = '{"key":"value"}';
        $this->ps->notes[$internalId]['json'][] = $jsonContent;

        // Line 774: no 'from' attribute — append 'NO_FROM'
        $this->ps->notes[$internalId]['from']['json'][] = 'NO_FROM';

        $this->assertCount(1, $this->ps->notes[$internalId]['json']);
        $this->assertSame($jsonContent, $this->ps->notes[$internalId]['json'][0]);
        $this->assertSame('NO_FROM', $this->ps->notes[$internalId]['from']['json'][0]);
    }

    // =========================================================================
    // Downstream effect: context_group structure (lines 789-796)
    //
    //   initArrayObject('context_group', ...)
    //   $ps->context_group[$id]->offsetExists('context_json')
    //   $ps->context_group[$id]->offsetSet('context_json', ...)
    //   $ps->context_group[$id]->offsetSet('context_json_segment_ids', new ArrayObject())
    // =========================================================================

    #[Test]
    public function downstream_context_group_full_structure(): void
    {
        $internalId = '42-tu1';
        $contextGroupData = [
            ['context-type' => 'sourcefile', 'content' => 'test.html'],
        ];

        // C13 fix: initArrayObject replacement
        if (!array_key_exists($internalId, $this->ps->context_group)) {
            $this->ps->context_group[$internalId] = [];
        }

        // Lines 792-794 fix: replace nested offsetExists / offsetSet
        if (!isset($this->ps->context_group[$internalId]['context_json'])) {
            $this->ps->context_group[$internalId]['context_json']             = $contextGroupData;
            $this->ps->context_group[$internalId]['context_json_segment_ids'] = [];
        }

        $this->assertArrayHasKey($internalId, $this->ps->context_group);
        $this->assertSame($contextGroupData, $this->ps->context_group[$internalId]['context_json']);
        $this->assertSame([], $this->ps->context_group[$internalId]['context_json_segment_ids']);
    }

    #[Test]
    public function downstream_context_group_does_not_overwrite_on_second_init(): void
    {
        $internalId = '42-tu1';

        // First initialization
        $this->ps->context_group[$internalId] = [];
        $this->ps->context_group[$internalId]['context_json'] = [['ctx' => 'first']];
        $this->ps->context_group[$internalId]['context_json_segment_ids'] = [];

        // Simulate second call (e.g., second mrk segment in same trans-unit)
        // initArrayObject is idempotent — won't overwrite
        if (!array_key_exists($internalId, $this->ps->context_group)) {
            $this->ps->context_group[$internalId] = [];
        }
        // context_json already exists — skip
        if (!isset($this->ps->context_group[$internalId]['context_json'])) {
            $this->ps->context_group[$internalId]['context_json'] = [['ctx' => 'SHOULD NOT APPEAR']];
            $this->ps->context_group[$internalId]['context_json_segment_ids'] = [];
        }

        // Original data preserved
        $this->assertSame([['ctx' => 'first']], $this->ps->context_group[$internalId]['context_json']);
    }

    // =========================================================================
    // Full pipeline simulation: multi-file extraction
    // Verifies the overall shape is correct across multiple files
    // =========================================================================

    #[Test]
    public function full_pipeline_multi_file_structure(): void
    {
        $files = [1, 2, 3];

        foreach ($files as $fid) {
            // C8/C9/C10 fix: initialize per-file containers
            $this->ps->segments[$fid]               = [];
            $this->ps->segments_original_data[$fid]  = [];
            $this->ps->segments_meta_data[$fid]      = [];

            // Add 2 segments per file
            for ($s = 0; $s < 2; $s++) {
                $this->ps->segments_meta_data[$fid][] = new SegmentMetadataStruct();
                $this->ps->segments_original_data[$fid][] = (new SegmentOriginalDataStruct())->setMap([]);
                $this->ps->segments[$fid][] = new SegmentStruct([
                    'id'               => ($fid * 10) + $s,
                    'id_file'          => $fid,
                    'internal_id'      => "tu{$s}",
                    'segment'          => "File {$fid} Segment {$s}",
                    'segment_hash'     => md5("File {$fid} Segment {$s}"),
                    'raw_word_count'   => 3,
                    'show_in_cattool'  => 1,
                ]);
            }
        }

        // Verify shape: 3 files × 2 segments each
        $this->assertCount(3, $this->ps->segments);
        foreach ($files as $fid) {
            $this->assertArrayHasKey($fid, $this->ps->segments);
            $this->assertCount(2, $this->ps->segments[$fid]);
            $this->assertCount(2, $this->ps->segments_original_data[$fid]);
            $this->assertCount(2, $this->ps->segments_meta_data[$fid]);
        }

        // Cross-file data isolation
        $this->assertSame('File 1 Segment 0', $this->ps->segments[1][0]->segment);
        $this->assertSame('File 3 Segment 1', $this->ps->segments[3][1]->segment);
    }

    // =========================================================================
    // Verifies that ArrayObject instances still work with same syntax
    // (backward compatibility during migration)
    // =========================================================================

    #[Test]
    public function backward_compat_arrayobject_still_accepts_bracket_syntax(): void
    {
        // Demonstrates that code using bracket syntax works regardless of
        // whether the property holds an ArrayObject or a plain array.
        // This ensures the transition is safe.
        $fid = 99;

        // Case A: With ArrayObject (old behavior, from TestableProjectManager)
        $ao = new ArrayObject();
        $ao[$fid] = new ArrayObject();
        $seg = new SegmentStruct([
            'id'               => 1,
            'id_file'          => $fid,
            'internal_id'      => 'tu1',
            'segment'          => 'AO segment',
            'segment_hash'     => md5('AO segment'),
            'raw_word_count'   => 2,
            'show_in_cattool'  => 1,
        ]);
        $ao[$fid][] = $seg;

        // Case B: With plain array (Option B fix)
        $arr = [];
        $arr[$fid] = [];
        $arr[$fid][] = $seg;

        // Both produce the same shape and content
        $this->assertCount(1, $ao[$fid]);
        $this->assertCount(1, $arr[$fid]);
        $this->assertSame($ao[$fid][0]->segment, $arr[$fid][0]->segment);
        $this->assertSame('AO segment', $arr[$fid][0]->segment);
    }

    // =========================================================================
    // Proves that calling ArrayObject methods on [] crashes (the bug itself)
    // =========================================================================

    #[Test]
    public function proof_of_bug_offsetSet_on_plain_array_crashes(): void
    {
        $arr = [];

        // This is exactly what SegmentExtractor.php:123 does when
        // $projectStructure->segments is [] (default).
        // It should produce a fatal Error.
        $this->expectException(\Error::class);

        /** @noinspection PhpUndefinedMethodInspection */
        $arr->offsetSet(42, new ArrayObject([]));
    }

    #[Test]
    public function proof_of_bug_offsetExists_on_plain_array_crashes(): void
    {
        $arr = [];

        // This is exactly what SegmentExtractor.php:300 does when
        // $projectStructure->translations is [] (default).
        $this->expectException(\Error::class);

        /** @noinspection PhpUndefinedMethodInspection */
        $arr->offsetExists('42-tu1');
    }

    #[Test]
    public function proof_of_bug_append_on_plain_array_crashes(): void
    {
        $arr = [];

        // This is what SegmentExtractor.php:637 does when the nested
        // child is a plain array instead of ArrayObject.
        $this->expectException(\Error::class);

        /** @noinspection PhpUndefinedMethodInspection */
        $arr->append(new SegmentStruct([
            'id'               => 1,
            'id_file'          => 42,
            'internal_id'      => 'tu1',
            'segment'          => 'test',
            'segment_hash'     => md5('test'),
            'raw_word_count'   => 1,
            'show_in_cattool'  => 1,
        ]));
    }
}
