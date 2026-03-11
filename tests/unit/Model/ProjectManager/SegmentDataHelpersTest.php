<?php

namespace unit\Model\ProjectManager;

use ArrayObject;
use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use Model\Segments\SegmentMetadataStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;

/**
 * Unit tests for pure-data helper methods in ProjectManager:
 *
 *  - _cleanSegmentsMetadata()
 *  - __setSegmentIdForNotes()
 *  - __setSegmentIdForContexts()
 *  - _saveSegmentMetadata()
 */
class SegmentDataHelpersTest extends AbstractTest
{
    private TestableProjectManager $pm;

    protected function setUp(): void
    {
        $this->pm = new TestableProjectManager();
        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $this->createStub(FeatureSet::class),
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );
    }

    // ──────────────────────────────────────────────────────────────
    // _cleanSegmentsMetadata()
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function cleanSegmentsMetadataKeepsShowInCattoolSegments(): void
    {
        $ps = $this->pm->getTestProjectStructure();
        $ps['segments_metadata'] = new ArrayObject([
            ['id' => 1, 'show_in_cattool' => 1, 'meta_key' => 'a'],
            ['id' => 2, 'show_in_cattool' => 1, 'meta_key' => 'b'],
        ]);

        $this->pm->callCleanSegmentsMetadata();

        self::assertCount(2, $ps['segments_metadata']);
    }

    #[Test]
    public function cleanSegmentsMetadataRemovesHiddenSegments(): void
    {
        $ps = $this->pm->getTestProjectStructure();
        $ps['segments_metadata'] = new ArrayObject([
            ['id' => 1, 'show_in_cattool' => 1],
            ['id' => 2, 'show_in_cattool' => 0],
            ['id' => 3, 'show_in_cattool' => 1],
        ]);

        $this->pm->callCleanSegmentsMetadata();

        $result = $ps['segments_metadata']->getArrayCopy();
        self::assertCount(2, $result);
        // array_filter preserves keys, so check values
        $ids = array_column($result, 'id');
        self::assertSame([1, 3], $ids);
    }

    #[Test]
    public function cleanSegmentsMetadataRemovesAllWhenNoneVisible(): void
    {
        $ps = $this->pm->getTestProjectStructure();
        $ps['segments_metadata'] = new ArrayObject([
            ['id' => 1, 'show_in_cattool' => 0],
            ['id' => 2, 'show_in_cattool' => 0],
        ]);

        $this->pm->callCleanSegmentsMetadata();

        self::assertCount(0, $ps['segments_metadata']);
    }

    #[Test]
    public function cleanSegmentsMetadataHandlesEmptyArrayObject(): void
    {
        $ps = $this->pm->getTestProjectStructure();
        $ps['segments_metadata'] = new ArrayObject([]);

        $this->pm->callCleanSegmentsMetadata();

        self::assertCount(0, $ps['segments_metadata']);
    }

    #[Test]
    public function cleanSegmentsMetadataUsesLooseComparisonForShowInCattool(): void
    {
        // show_in_cattool == 1 uses loose comparison, so "1" (string) should also pass
        $ps = $this->pm->getTestProjectStructure();
        $ps['segments_metadata'] = new ArrayObject([
            ['id' => 1, 'show_in_cattool' => '1'],
            ['id' => 2, 'show_in_cattool' => true],
            ['id' => 3, 'show_in_cattool' => '0'],
            ['id' => 4, 'show_in_cattool' => false],
        ]);

        $this->pm->callCleanSegmentsMetadata();

        $result = $ps['segments_metadata']->getArrayCopy();
        $ids = array_column($result, 'id');
        self::assertSame([1, 2], $ids);
    }

    // ──────────────────────────────────────────────────────────────
    // __setSegmentIdForNotes()
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function setSegmentIdForNotesAddsToSegmentIdsWhenJsonIsEmpty(): void
    {
        $ps = $this->pm->getTestProjectStructure();
        $ps['notes'] = new ArrayObject([
            'unit-1' => [
                'json' => [],
                'segment_ids' => [],
                'json_segment_ids' => [],
            ],
        ]);

        $this->pm->callSetSegmentIdForNotes([
            'internal_id' => 'unit-1',
            'id' => 42,
        ]);

        self::assertSame([42], $ps['notes']['unit-1']['segment_ids']);
        self::assertEmpty($ps['notes']['unit-1']['json_segment_ids']);
    }

    #[Test]
    public function setSegmentIdForNotesAddsToJsonSegmentIdsWhenJsonHasEntries(): void
    {
        $ps = $this->pm->getTestProjectStructure();
        $ps['notes'] = new ArrayObject([
            'unit-1' => [
                'json' => ['some note data'],
                'segment_ids' => [],
                'json_segment_ids' => [],
            ],
        ]);

        $this->pm->callSetSegmentIdForNotes([
            'internal_id' => 'unit-1',
            'id' => 99,
        ]);

        self::assertSame([99], $ps['notes']['unit-1']['json_segment_ids']);
        self::assertEmpty($ps['notes']['unit-1']['segment_ids']);
    }

    #[Test]
    public function setSegmentIdForNotesAppendsMultipleIds(): void
    {
        $ps = $this->pm->getTestProjectStructure();
        $ps['notes'] = new ArrayObject([
            'unit-1' => [
                'json' => [],
                'segment_ids' => [10],
                'json_segment_ids' => [],
            ],
        ]);

        $this->pm->callSetSegmentIdForNotes(['internal_id' => 'unit-1', 'id' => 20]);
        $this->pm->callSetSegmentIdForNotes(['internal_id' => 'unit-1', 'id' => 30]);

        self::assertSame([10, 20, 30], $ps['notes']['unit-1']['segment_ids']);
    }

    #[Test]
    public function setSegmentIdForNotesDoesNothingWhenInternalIdNotFound(): void
    {
        $ps = $this->pm->getTestProjectStructure();
        $ps['notes'] = new ArrayObject([
            'unit-1' => [
                'json' => [],
                'segment_ids' => [],
                'json_segment_ids' => [],
            ],
        ]);

        // Different internal_id — no match
        $this->pm->callSetSegmentIdForNotes([
            'internal_id' => 'unit-999',
            'id' => 42,
        ]);

        self::assertEmpty($ps['notes']['unit-1']['segment_ids']);
        self::assertEmpty($ps['notes']['unit-1']['json_segment_ids']);
    }

    #[Test]
    public function setSegmentIdForNotesHandlesMultipleInternalIds(): void
    {
        $ps = $this->pm->getTestProjectStructure();
        $ps['notes'] = new ArrayObject([
            'unit-A' => [
                'json' => ['note'],
                'segment_ids' => [],
                'json_segment_ids' => [],
            ],
            'unit-B' => [
                'json' => [],
                'segment_ids' => [],
                'json_segment_ids' => [],
            ],
        ]);

        $this->pm->callSetSegmentIdForNotes(['internal_id' => 'unit-A', 'id' => 1]);
        $this->pm->callSetSegmentIdForNotes(['internal_id' => 'unit-B', 'id' => 2]);

        self::assertSame([1], $ps['notes']['unit-A']['json_segment_ids']);
        self::assertSame([2], $ps['notes']['unit-B']['segment_ids']);
    }

    // ──────────────────────────────────────────────────────────────
    // __setSegmentIdForContexts()
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function setSegmentIdForContextsAddsIdWhenInternalIdExists(): void
    {
        $ps = $this->pm->getTestProjectStructure();
        $ps['context-group'] = new ArrayObject([
            'unit-1' => [
                'context_json_segment_ids' => [],
            ],
        ]);

        $this->pm->callSetSegmentIdForContexts([
            'internal_id' => 'unit-1',
            'id' => 55,
        ]);

        self::assertSame([55], $ps['context-group']['unit-1']['context_json_segment_ids']);
    }

    #[Test]
    public function setSegmentIdForContextsAppendsMultipleIds(): void
    {
        $ps = $this->pm->getTestProjectStructure();
        $ps['context-group'] = new ArrayObject([
            'unit-1' => [
                'context_json_segment_ids' => [10],
            ],
        ]);

        $this->pm->callSetSegmentIdForContexts(['internal_id' => 'unit-1', 'id' => 20]);
        $this->pm->callSetSegmentIdForContexts(['internal_id' => 'unit-1', 'id' => 30]);

        self::assertSame([10, 20, 30], $ps['context-group']['unit-1']['context_json_segment_ids']);
    }

    #[Test]
    public function setSegmentIdForContextsDoesNothingWhenInternalIdNotFound(): void
    {
        $ps = $this->pm->getTestProjectStructure();
        $ps['context-group'] = new ArrayObject([
            'unit-1' => [
                'context_json_segment_ids' => [],
            ],
        ]);

        $this->pm->callSetSegmentIdForContexts([
            'internal_id' => 'unit-999',
            'id' => 42,
        ]);

        self::assertEmpty($ps['context-group']['unit-1']['context_json_segment_ids']);
    }

    #[Test]
    public function setSegmentIdForContextsHandlesMultipleInternalIds(): void
    {
        $ps = $this->pm->getTestProjectStructure();
        $ps['context-group'] = new ArrayObject([
            'unit-A' => ['context_json_segment_ids' => []],
            'unit-B' => ['context_json_segment_ids' => []],
        ]);

        $this->pm->callSetSegmentIdForContexts(['internal_id' => 'unit-A', 'id' => 1]);
        $this->pm->callSetSegmentIdForContexts(['internal_id' => 'unit-B', 'id' => 2]);

        self::assertSame([1], $ps['context-group']['unit-A']['context_json_segment_ids']);
        self::assertSame([2], $ps['context-group']['unit-B']['context_json_segment_ids']);
    }

    // ──────────────────────────────────────────────────────────────
    // _saveSegmentMetadata()
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function saveSegmentMetadataPersistsWhenKeyAndValueAreSet(): void
    {
        $meta = new SegmentMetadataStruct();
        $meta->meta_key = 'char_count';
        $meta->meta_value = '42';

        $this->pm->callSaveSegmentMetadata(100, $meta);

        $persisted = $this->pm->getPersistedSegmentMetadata();
        self::assertCount(1, $persisted);
        self::assertEquals(100, $persisted[0]->id_segment);
        self::assertSame('char_count', $persisted[0]->meta_key);
        self::assertSame('42', $persisted[0]->meta_value);
    }

    #[Test]
    public function saveSegmentMetadataDoesNotPersistWhenNull(): void
    {
        $this->pm->callSaveSegmentMetadata(100, null);

        self::assertEmpty($this->pm->getPersistedSegmentMetadata());
    }

    #[Test]
    public function saveSegmentMetadataDoesNotPersistWhenMetaKeyIsEmpty(): void
    {
        $meta = new SegmentMetadataStruct();
        $meta->meta_key = '';
        $meta->meta_value = '42';

        $this->pm->callSaveSegmentMetadata(100, $meta);

        self::assertEmpty($this->pm->getPersistedSegmentMetadata());
    }

    #[Test]
    public function saveSegmentMetadataDoesNotPersistWhenMetaValueIsEmpty(): void
    {
        $meta = new SegmentMetadataStruct();
        $meta->meta_key = 'char_count';
        $meta->meta_value = '';

        $this->pm->callSaveSegmentMetadata(100, $meta);

        self::assertEmpty($this->pm->getPersistedSegmentMetadata());
    }

    #[Test]
    public function saveSegmentMetadataDoesNotPersistWhenMetaKeyIsNull(): void
    {
        $meta = new SegmentMetadataStruct();
        $meta->meta_value = '42';
        // meta_key is not set (null by default from struct)

        $this->pm->callSaveSegmentMetadata(100, $meta);

        self::assertEmpty($this->pm->getPersistedSegmentMetadata());
    }

    #[Test]
    public function saveSegmentMetadataDoesNotPersistWhenMetaValueIsNull(): void
    {
        $meta = new SegmentMetadataStruct();
        $meta->meta_key = 'char_count';
        // meta_value is not set (null by default from struct)

        $this->pm->callSaveSegmentMetadata(100, $meta);

        self::assertEmpty($this->pm->getPersistedSegmentMetadata());
    }

    #[Test]
    public function saveSegmentMetadataSetsIdSegmentBeforePersisting(): void
    {
        $meta = new SegmentMetadataStruct();
        $meta->meta_key = 'type';
        $meta->meta_value = 'heading';
        $meta->id_segment = 999; // pre-set value should be overwritten

        $this->pm->callSaveSegmentMetadata(200, $meta);

        $persisted = $this->pm->getPersistedSegmentMetadata();
        self::assertCount(1, $persisted);
        self::assertEquals(200, $persisted[0]->id_segment);
    }

    #[Test]
    public function saveSegmentMetadataMultipleCallsAccumulate(): void
    {
        $meta1 = new SegmentMetadataStruct();
        $meta1->meta_key = 'key1';
        $meta1->meta_value = 'val1';

        $meta2 = new SegmentMetadataStruct();
        $meta2->meta_key = 'key2';
        $meta2->meta_value = 'val2';

        $this->pm->callSaveSegmentMetadata(1, $meta1);
        $this->pm->callSaveSegmentMetadata(2, $meta2);

        self::assertCount(2, $this->pm->getPersistedSegmentMetadata());
    }
}
