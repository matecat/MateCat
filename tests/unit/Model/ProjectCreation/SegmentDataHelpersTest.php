<?php

namespace unit\Model\ProjectCreation;

use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\ProjectCreation\ProjectManagerModel;
use Model\ProjectCreation\ProjectStructure;
use Model\Segments\SegmentMetadataStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionException;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;

/**
 * Unit tests for pure-data helper methods now on SegmentStorageService:
 *
 *  - cleanSegmentsMetadata()
 *  - setSegmentIdForNotes() (private, tested via storeSegments or reflection)
 *  - setSegmentIdForContexts() (private, tested via storeSegments or reflection)
 *  - saveSegmentMetadata() (protected, tested via TestableSegmentStorageService)
 */
class SegmentDataHelpersTest extends AbstractTest
{
    private TestableSegmentStorageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TestableSegmentStorageService(
            $this->createStub(IDatabase::class),
            $this->createStub(FeatureSet::class),
            $this->createStub(MatecatLogger::class),
            $this->createStub(ProjectManagerModel::class),
        );
    }

    // ──────────────────────────────────────────────────────────────
    // cleanSegmentsMetadata()
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function cleanSegmentsMetadataKeepsShowInCattoolSegments(): void
    {
        $ps = new ProjectStructure([
            'segments_metadata' => [
                ['id' => 1, 'show_in_cattool' => 1, 'meta_key' => 'a'],
                ['id' => 2, 'show_in_cattool' => 1, 'meta_key' => 'b'],
            ],
        ]);

        $this->service->cleanSegmentsMetadata($ps);

        self::assertCount(2, $ps->segments_metadata);
    }

    #[Test]
    public function cleanSegmentsMetadataRemovesHiddenSegments(): void
    {
        $ps = new ProjectStructure([
            'segments_metadata' => [
                ['id' => 1, 'show_in_cattool' => 1],
                ['id' => 2, 'show_in_cattool' => 0],
                ['id' => 3, 'show_in_cattool' => 1],
            ],
        ]);

        $this->service->cleanSegmentsMetadata($ps);

        $result = $ps->segments_metadata;
        self::assertCount(2, $result);
        // array_filter preserves keys, so check values
        $ids = array_column($result, 'id');
        self::assertSame([1, 3], $ids);
    }

    #[Test]
    public function cleanSegmentsMetadataRemovesAllWhenNoneVisible(): void
    {
        $ps = new ProjectStructure([
            'segments_metadata' => [
                ['id' => 1, 'show_in_cattool' => 0],
                ['id' => 2, 'show_in_cattool' => 0],
            ],
        ]);

        $this->service->cleanSegmentsMetadata($ps);

        self::assertCount(0, $ps->segments_metadata);
    }

    #[Test]
    public function cleanSegmentsMetadataHandlesEmptyArray(): void
    {
        $ps = new ProjectStructure([
            'segments_metadata' => [],
        ]);

        $this->service->cleanSegmentsMetadata($ps);

        self::assertCount(0, $ps->segments_metadata);
    }

    #[Test]
    public function cleanSegmentsMetadataUsesLooseComparisonForShowInCattool(): void
    {
        // show_in_cattool == 1 uses loose comparison, so "1" (string) should also pass
        $ps = new ProjectStructure([
            'segments_metadata' => [
                ['id' => 1, 'show_in_cattool' => '1'],
                ['id' => 2, 'show_in_cattool' => true],
                ['id' => 3, 'show_in_cattool' => '0'],
                ['id' => 4, 'show_in_cattool' => false],
            ],
        ]);

        $this->service->cleanSegmentsMetadata($ps);

        $result = $ps->segments_metadata;
        $ids = array_column($result, 'id');
        self::assertSame([1, 2], $ids);
    }

    // ──────────────────────────────────────────────────────────────
    // setSegmentIdForNotes() — private, tested via reflection
    // ──────────────────────────────────────────────────────────────

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function setSegmentIdForNotesAddsToSegmentIdsWhenJsonIsEmpty(): void
    {
        $ps = new ProjectStructure([
            'notes' => [
                'unit-1' => [
                    'json' => [],
                    'segment_ids' => [],
                    'json_segment_ids' => [],
                ],
            ],
        ]);

        $this->invokePrivateMethod('setSegmentIdForNotes', [
            ['internal_id' => 'unit-1', 'id' => 42],
            $ps,
        ]);

        self::assertSame([42], $ps->notes['unit-1']['segment_ids']);
        self::assertEmpty($ps->notes['unit-1']['json_segment_ids']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function setSegmentIdForNotesAddsToJsonSegmentIdsWhenJsonHasEntries(): void
    {
        $ps = new ProjectStructure([
            'notes' => [
                'unit-1' => [
                    'json' => ['some note data'],
                    'segment_ids' => [],
                    'json_segment_ids' => [],
                ],
            ],
        ]);

        $this->invokePrivateMethod('setSegmentIdForNotes', [
            ['internal_id' => 'unit-1', 'id' => 99],
            $ps,
        ]);

        self::assertSame([99], $ps->notes['unit-1']['json_segment_ids']);
        self::assertEmpty($ps->notes['unit-1']['segment_ids']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function setSegmentIdForNotesAppendsMultipleIds(): void
    {
        $ps = new ProjectStructure([
            'notes' => [
                'unit-1' => [
                    'json' => [],
                    'segment_ids' => [10],
                    'json_segment_ids' => [],
                ],
            ],
        ]);

        $this->invokePrivateMethod('setSegmentIdForNotes', [['internal_id' => 'unit-1', 'id' => 20], $ps]);
        $this->invokePrivateMethod('setSegmentIdForNotes', [['internal_id' => 'unit-1', 'id' => 30], $ps]);

        self::assertSame([10, 20, 30], $ps->notes['unit-1']['segment_ids']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function setSegmentIdForNotesDoesNothingWhenInternalIdNotFound(): void
    {
        $ps = new ProjectStructure([
            'notes' => [
                'unit-1' => [
                    'json' => [],
                    'segment_ids' => [],
                    'json_segment_ids' => [],
                ],
            ],
        ]);

        // Different internal_id — no match
        $this->invokePrivateMethod('setSegmentIdForNotes', [
            ['internal_id' => 'unit-999', 'id' => 42],
            $ps,
        ]);

        self::assertEmpty($ps->notes['unit-1']['segment_ids']);
        self::assertEmpty($ps->notes['unit-1']['json_segment_ids']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function setSegmentIdForNotesHandlesMultipleInternalIds(): void
    {
        $ps = new ProjectStructure([
            'notes' => [
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
            ],
        ]);

        $this->invokePrivateMethod('setSegmentIdForNotes', [['internal_id' => 'unit-A', 'id' => 1], $ps]);
        $this->invokePrivateMethod('setSegmentIdForNotes', [['internal_id' => 'unit-B', 'id' => 2], $ps]);

        self::assertSame([1], $ps->notes['unit-A']['json_segment_ids']);
        self::assertSame([2], $ps->notes['unit-B']['segment_ids']);
    }

    // ──────────────────────────────────────────────────────────────
    // setSegmentIdForContexts() — private, tested via reflection
    // ──────────────────────────────────────────────────────────────

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function setSegmentIdForContextsAddsIdWhenInternalIdExists(): void
    {
        $ps = new ProjectStructure([
            'context_group' => [
                'unit-1' => [
                    'context_json_segment_ids' => [],
                ],
            ],
        ]);

        $this->invokePrivateMethod('setSegmentIdForContexts', [
            ['internal_id' => 'unit-1', 'id' => 55],
            $ps,
        ]);

        self::assertSame([55], $ps->context_group['unit-1']['context_json_segment_ids']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function setSegmentIdForContextsAppendsMultipleIds(): void
    {
        $ps = new ProjectStructure([
            'context_group' => [
                'unit-1' => [
                    'context_json_segment_ids' => [10],
                ],
            ],
        ]);

        $this->invokePrivateMethod('setSegmentIdForContexts', [['internal_id' => 'unit-1', 'id' => 20], $ps]);
        $this->invokePrivateMethod('setSegmentIdForContexts', [['internal_id' => 'unit-1', 'id' => 30], $ps]);

        self::assertSame([10, 20, 30], $ps->context_group['unit-1']['context_json_segment_ids']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function setSegmentIdForContextsDoesNothingWhenInternalIdNotFound(): void
    {
        $ps = new ProjectStructure([
            'context_group' => [
                'unit-1' => [
                    'context_json_segment_ids' => [],
                ],
            ],
        ]);

        $this->invokePrivateMethod('setSegmentIdForContexts', [
            ['internal_id' => 'unit-999', 'id' => 42],
            $ps,
        ]);

        self::assertEmpty($ps->context_group['unit-1']['context_json_segment_ids']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function setSegmentIdForContextsHandlesMultipleInternalIds(): void
    {
        $ps = new ProjectStructure([
            'context_group' => [
                'unit-A' => ['context_json_segment_ids' => []],
                'unit-B' => ['context_json_segment_ids' => []],
            ],
        ]);

        $this->invokePrivateMethod('setSegmentIdForContexts', [['internal_id' => 'unit-A', 'id' => 1], $ps]);
        $this->invokePrivateMethod('setSegmentIdForContexts', [['internal_id' => 'unit-B', 'id' => 2], $ps]);

        self::assertSame([1], $ps->context_group['unit-A']['context_json_segment_ids']);
        self::assertSame([2], $ps->context_group['unit-B']['context_json_segment_ids']);
    }

    // ──────────────────────────────────────────────────────────────
    // saveSegmentMetadata() — protected, tested via TestableSegmentStorageService
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function saveSegmentMetadataPersistsWhenKeyAndValueAreSet(): void
    {
        $meta = new SegmentMetadataStruct();
        $meta->meta_key = 'char_count';
        $meta->meta_value = '42';

        $this->service->callSaveSegmentMetadata(100, $meta);

        $persisted = $this->service->getPersistedSegmentMetadata();
        self::assertCount(1, $persisted);
        self::assertEquals(100, $persisted[0]->id_segment);
        self::assertSame('char_count', $persisted[0]->meta_key);
        self::assertSame('42', $persisted[0]->meta_value);
    }

    #[Test]
    public function saveSegmentMetadataDoesNotPersistWhenNull(): void
    {
        $this->service->callSaveSegmentMetadata(100);

        self::assertEmpty($this->service->getPersistedSegmentMetadata());
    }

    #[Test]
    public function saveSegmentMetadataDoesNotPersistWhenMetaKeyIsEmpty(): void
    {
        $meta = new SegmentMetadataStruct();
        $meta->meta_key = '';
        $meta->meta_value = '42';

        $this->service->callSaveSegmentMetadata(100, $meta);

        self::assertEmpty($this->service->getPersistedSegmentMetadata());
    }

    #[Test]
    public function saveSegmentMetadataDoesNotPersistWhenMetaValueIsEmpty(): void
    {
        $meta = new SegmentMetadataStruct();
        $meta->meta_key = 'char_count';
        $meta->meta_value = '';

        $this->service->callSaveSegmentMetadata(100, $meta);

        self::assertEmpty($this->service->getPersistedSegmentMetadata());
    }

    #[Test]
    public function saveSegmentMetadataDoesNotPersistWhenMetaKeyIsNull(): void
    {
        $meta = new SegmentMetadataStruct();
        $meta->meta_value = '42';
        // meta_key is not set (null by default from struct)

        $this->service->callSaveSegmentMetadata(100, $meta);

        self::assertEmpty($this->service->getPersistedSegmentMetadata());
    }

    #[Test]
    public function saveSegmentMetadataDoesNotPersistWhenMetaValueIsNull(): void
    {
        $meta = new SegmentMetadataStruct();
        $meta->meta_key = 'char_count';
        // meta_value is not set (null by default from struct)

        $this->service->callSaveSegmentMetadata(100, $meta);

        self::assertEmpty($this->service->getPersistedSegmentMetadata());
    }

    #[Test]
    public function saveSegmentMetadataSetsIdSegmentBeforePersisting(): void
    {
        $meta = new SegmentMetadataStruct();
        $meta->meta_key = 'type';
        $meta->meta_value = 'heading';
        $meta->id_segment = 999; // pre-set value should be overwritten

        $this->service->callSaveSegmentMetadata(200, $meta);

        $persisted = $this->service->getPersistedSegmentMetadata();
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

        $this->service->callSaveSegmentMetadata(1, $meta1);
        $this->service->callSaveSegmentMetadata(2, $meta2);

        self::assertCount(2, $this->service->getPersistedSegmentMetadata());
    }

    // ── Helper ──────────────────────────────────────────────────────

    /**
     * Invoke a private method on the service via reflection.
     *
     * @throws ReflectionException
     */
    private function invokePrivateMethod(string $methodName, array $args): void
    {
        $ref = new ReflectionClass($this->service);
        $method = $ref->getMethod($methodName);

        $method->invoke($this->service, ...$args);
    }
}
