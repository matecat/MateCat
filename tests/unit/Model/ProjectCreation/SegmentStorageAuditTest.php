<?php

namespace unit\Model\ProjectCreation;

use Matecat\SubFiltering\MateCatFilter;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\ProjectCreation\ProjectManagerModel;
use Model\ProjectCreation\ProjectStructure;
use Model\Segments\SegmentDao;
use Model\Segments\SegmentStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;

/**
 * Audit tests for SegmentStorageService after migrating from ArrayObject to plain arrays.
 *
 * Each test group verifies that a specific bug (C14–C22) found during the audit
 * is fixed: the code must work correctly when ProjectStructure pipeline properties
 * (segments, translations, notes, context_group, segments_metadata) are plain PHP
 * arrays instead of ArrayObject instances.
 *
 * @see \Model\ProjectCreation\SegmentStorageService
 */
#[AllowMockObjectsWithoutExpectations]
class SegmentStorageAuditTest extends AbstractTest
{
    private TestableSegmentStorageService $service;
    private Database&MockObject $dbHandler;
    private FeatureSet&MockObject $features;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dbHandler = $this->createMock(Database::class);
        $this->features  = $this->createMock(FeatureSet::class);
        $logger = $this->createStub(MatecatLogger::class);
        $filter = $this->createStub(MateCatFilter::class);
        $pmModel = $this->createStub(ProjectManagerModel::class);

        $this->service = new TestableSegmentStorageService(
            $this->dbHandler, $this->features, $logger, $filter, $pmModel,
        );
        $segmentDaoStub = $this->createStub(SegmentDao::class);
        $this->service->setSegmentDao($segmentDaoStub);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Create a minimal SegmentStruct for testing.
     */
    private function makeSegment(
        int    $idFile,
        string $internalId,
        string $text = 'Hello',
        float  $rawWordCount = 1.0,
        bool   $showInCattool = true,
        ?string $xliffMrkId = null,
    ): SegmentStruct {
        $seg = new SegmentStruct();
        $seg->id_file         = $idFile;
        $seg->internal_id     = $internalId;
        $seg->segment         = $text;
        $seg->segment_hash    = md5($text);
        $seg->raw_word_count  = $rawWordCount;
        $seg->show_in_cattool = $showInCattool;
        $seg->xliff_mrk_id   = $xliffMrkId;

        return $seg;
    }

    /**
     * Create a ProjectStructure using PLAIN ARRAYS (not ArrayObject) for all
     * pipeline properties.  This is the post-fix state where ArrayObject has
     * been replaced by plain arrays.
     */
    private function makePlainArrayProjectStructure(
        int   $fid,
        array $segments,
        array $originalData = [],
        array $metaData = [],
    ): ProjectStructure {
        return new ProjectStructure([
            'segments'               => [$fid => $segments],
            'segments_original_data' => array_key_exists($fid, $originalData) ? $originalData : [$fid => $originalData],
            'segments_meta_data'     => array_key_exists($fid, $metaData) ? $metaData : [$fid => $metaData],
            'file_segments_count'    => [],
            'segments_metadata'      => [],
            'notes'                  => [],
            'translations'           => [],
            'context_group'          => [],
        ]);
    }

    /**
     * Stub dbHandler->nextSequence to return sequential IDs starting from $start.
     */
    private function stubSequence(int $count, int $start = 100): void
    {
        $ids = range($start, $start + $count - 1);
        $this->dbHandler->method('nextSequence')
            ->with(Database::SEQ_ID_SEGMENT, $count)
            ->willReturn($ids);
    }

    /**
     * Make features->filter return its second argument (pass-through).
     */
    private function stubFeaturesPassThrough(): void
    {
        $this->features->method('filter')
            ->willReturnCallback(function (string $name, mixed $arg1) {
                return $arg1;
            });
    }

    /**
     * Invoke a private method on the service via reflection.
     */
    private function invokePrivateMethod(string $methodName, array $args): mixed
    {
        $ref    = new \ReflectionClass($this->service);
        $method = $ref->getMethod($methodName);

        return $method->invoke($this->service, ...$args);
    }

    // ══════════════════════════════════════════════════════════════════
    // C14 + C15: segments[$fid] used as plain array
    //
    // C14: Line 170 — `$projectStructure->segments[$fid]->getArrayCopy()`
    //   After fix: `$projectStructure->segments[$fid]` (already a plain array)
    //
    // C15: Line 173 — `$projectStructure->segments[$fid]->exchangeArray([])`
    //   After fix: `$projectStructure->segments[$fid] = []`
    // ══════════════════════════════════════════════════════════════════

    #[Test]
    public function c14_storeSegmentsWorksWithPlainArraySegments(): void
    {
        $fid = 1;
        $seg1 = $this->makeSegment($fid, 'u1', 'Alpha');
        $seg2 = $this->makeSegment($fid, 'u2', 'Beta');

        $this->stubSequence(2, 100);
        $this->stubFeaturesPassThrough();

        $ps = $this->makePlainArrayProjectStructure($fid, [$seg1, $seg2]);

        // Precondition: segments[$fid] is a plain array, NOT an ArrayObject
        self::assertIsArray($ps->segments[$fid]);
        self::assertCount(2, $ps->segments[$fid]);

        // storeSegments must work without calling getArrayCopy() on segments[$fid]
        $this->service->storeSegments($fid, $ps);

        // Segment IDs assigned correctly
        self::assertSame(100, $seg1->id);
        self::assertSame(101, $seg2->id);

        // segments_metadata was built (bulk insert happened from plain array)
        self::assertCount(2, $ps->segments_metadata);
    }

    #[Test]
    public function c15_storeSegmentsClearsPlainArraySegmentsAfterInsert(): void
    {
        $fid = 1;
        $seg = $this->makeSegment($fid, 'u1');

        $this->stubSequence(1, 200);
        $this->stubFeaturesPassThrough();

        $ps = $this->makePlainArrayProjectStructure($fid, [$seg]);

        $this->service->storeSegments($fid, $ps);

        // After fix, segments[$fid] = [] replaces exchangeArray([])
        self::assertCount(0, $ps->segments[$fid]);
    }

    // ══════════════════════════════════════════════════════════════════
    // C16: translations->offsetExists($internal_id)
    //
    // Line 191 — After fix: isset($projectStructure->translations[$row['internal_id']])
    // ══════════════════════════════════════════════════════════════════

    #[Test]
    public function c16_translationLinkingWorksWithPlainArrayTranslations(): void
    {
        $fid = 1;
        $seg = $this->makeSegment($fid, 'u1', 'Hello');

        $this->stubSequence(1, 300);
        $this->stubFeaturesPassThrough();

        $sanitizedId = "$fid|u1";

        $ps = $this->makePlainArrayProjectStructure($fid, [$seg]);
        // Plain array translations — no ArrayObject at top level
        $ps->translations = [
            $sanitizedId => [
                0 => [null, null, 'Ciao', null, '<trans-unit/>', null, null],
            ],
        ];

        // Must not call offsetExists() on a plain array
        $this->service->storeSegments($fid, $ps);

        // Translation was linked: offset 0 = segment_id
        self::assertSame(300, $ps->translations[$sanitizedId][0][0]);
    }

    #[Test]
    public function c16_translationLinkingSkipsWhenInternalIdNotInPlainArray(): void
    {
        $fid = 1;
        $seg = $this->makeSegment($fid, 'u1', 'Hello');

        $this->stubSequence(1, 310);
        $this->stubFeaturesPassThrough();

        $ps = $this->makePlainArrayProjectStructure($fid, [$seg]);
        // translations has entries but NOT for this segment's internal_id
        $ps->translations = [
            'other-id' => [
                0 => [null, null, 'Target', null, '<tu/>', null, null],
            ],
        ];

        // Must complete without error (isset check instead of offsetExists)
        $this->service->storeSegments($fid, $ps);

        // The unrelated translation should remain untouched
        self::assertNull($ps->translations['other-id'][0][0]);
    }

    // ══════════════════════════════════════════════════════════════════
    // C17: translations[$internal_id]->offsetExists($counter)
    //
    // Line 214 — Nested child. After fix:
    //   isset($projectStructure->translations[$row['internal_id']][$short_var_counter])
    // ══════════════════════════════════════════════════════════════════

    #[Test]
    public function c17_translationLinkingSkipsWhenMrkCounterMissingInPlainArray(): void
    {
        $fid = 1;
        $seg = $this->makeSegment($fid, 'u1', 'Hello', xliffMrkId: '5');

        $this->stubSequence(1, 400);
        $this->stubFeaturesPassThrough();

        $sanitizedId = "$fid|u1";

        $ps = $this->makePlainArrayProjectStructure($fid, [$seg]);
        // Translation exists for the internal_id but NOT for mrk position 5
        $ps->translations = [
            $sanitizedId => [
                0 => [null, null, 'Target', null, '<tu/>', null, null],
            ],
        ];

        // Must not call offsetExists() on a plain array — uses isset() instead
        $this->service->storeSegments($fid, $ps);

        // mrk 5 doesn't exist, so offset 0 of translation[0] should stay null
        self::assertNull($ps->translations[$sanitizedId][0][0]);
    }

    #[Test]
    public function c17_translationLinkingMatchesMrkCounterInPlainArray(): void
    {
        $fid = 1;
        $seg = $this->makeSegment($fid, 'u1', 'Hello', xliffMrkId: '2');

        $this->stubSequence(1, 410);
        $this->stubFeaturesPassThrough();

        $sanitizedId = "$fid|u1";

        $ps = $this->makePlainArrayProjectStructure($fid, [$seg]);
        // Translation at mrk position 2
        $ps->translations = [
            $sanitizedId => [
                '2' => [null, null, 'Ciao', null, '<tu/>', null, null],
            ],
        ];

        $this->service->storeSegments($fid, $ps);

        // Should link because mrk 2 exists
        self::assertSame(410, $ps->translations[$sanitizedId]['2'][0]);
    }

    // ══════════════════════════════════════════════════════════════════
    // C18: translations[$id][$c]->offsetSet(0, ...) — deeply nested grandchild
    //
    // Lines 218-228 — After fix: plain array assignment
    //   $projectStructure->translations[$id][$c][0] = $row['id']
    //   $projectStructure->translations[$id][$c][1] = $row['internal_id']
    //   $projectStructure->translations[$id][$c][3] = $row['segment_hash']
    //   $projectStructure->translations[$id][$c][5] = $row['file_id']
    // ══════════════════════════════════════════════════════════════════

    #[Test]
    public function c18_translationOffsetSetWorksWithPlainArrayGrandchild(): void
    {
        $fid = 1;
        $seg = $this->makeSegment($fid, 'u1', 'Hello world');

        $this->stubSequence(1, 500);
        $this->stubFeaturesPassThrough();

        $sanitizedId = "$fid|u1";

        $ps = $this->makePlainArrayProjectStructure($fid, [$seg]);
        // All 3 levels are plain arrays (no ArrayObject anywhere)
        $ps->translations = [
            $sanitizedId => [           // level 1: internal_id
                0 => [                  // level 2: mrk counter
                    null,               // 0: segment_id  (to be filled)
                    null,               // 1: internal_id  (to be filled)
                    'Ciao mondo',       // 2: target translation
                    null,               // 3: segment_hash (to be filled)
                    '<trans-unit/>',    // 4: trans-unit
                    null,               // 5: file_id      (to be filled)
                    null,               // 6: mrk position
                ],
            ],
        ];

        $this->service->storeSegments($fid, $ps);

        $translationRow = $ps->translations[$sanitizedId][0];

        // Verify all 4 offsets were set correctly via plain array assignment
        self::assertSame(500, $translationRow[0], 'offset 0 = segment_id');
        self::assertSame($sanitizedId, $translationRow[1], 'offset 1 = internal_id');
        self::assertSame(md5('Hello world'), $translationRow[3], 'offset 3 = segment_hash');
        self::assertSame($fid, $translationRow[5], 'offset 5 = file_id');

        // Verify untouched offsets are preserved
        self::assertSame('Ciao mondo', $translationRow[2], 'offset 2 = target translation unchanged');
        self::assertSame('<trans-unit/>', $translationRow[4], 'offset 4 = trans-unit unchanged');
    }

    #[Test]
    public function c18_translationOffsetSetWorksWithMultipleMrkSegments(): void
    {
        $fid = 1;
        $seg1 = $this->makeSegment($fid, 'u1', 'Part one');
        $seg2 = $this->makeSegment($fid, 'u1', 'Part two');
        // No xliff_mrk_id → positional counter increments: 0, 1

        $this->stubSequence(2, 600);
        $this->stubFeaturesPassThrough();

        $sanitizedId = "$fid|u1";

        $ps = $this->makePlainArrayProjectStructure($fid, [$seg1, $seg2]);
        $ps->translations = [
            $sanitizedId => [
                0 => [null, null, 'Parte uno', null, '<tu/>', null, null],
                1 => [null, null, 'Parte due', null, '<tu/>', null, null],
            ],
        ];

        $this->service->storeSegments($fid, $ps);

        // First segment (counter=0)
        self::assertSame(600, $ps->translations[$sanitizedId][0][0]);
        self::assertSame($sanitizedId, $ps->translations[$sanitizedId][0][1]);
        self::assertSame(md5('Part one'), $ps->translations[$sanitizedId][0][3]);
        self::assertSame($fid, $ps->translations[$sanitizedId][0][5]);

        // Second segment (counter=1)
        self::assertSame(601, $ps->translations[$sanitizedId][1][0]);
        self::assertSame($sanitizedId, $ps->translations[$sanitizedId][1][1]);
        self::assertSame(md5('Part two'), $ps->translations[$sanitizedId][1][3]);
        self::assertSame($fid, $ps->translations[$sanitizedId][1][5]);
    }

    // ══════════════════════════════════════════════════════════════════
    // C19: segments_metadata->exchangeArray(array_merge(->getArrayCopy(), ...))
    //
    // Lines 245-246 — After fix:
    //   $projectStructure->segments_metadata = array_merge(
    //       $projectStructure->segments_metadata, $segments_metadata
    //   )
    // ══════════════════════════════════════════════════════════════════

    #[Test]
    public function c19_segmentsMetadataAccumulatesAcrossFilesWithPlainArrays(): void
    {
        $fid1 = 1;
        $fid2 = 2;

        $this->dbHandler->method('nextSequence')
            ->willReturnOnConsecutiveCalls([100, 101], [200]);
        $this->stubFeaturesPassThrough();

        $ps = new ProjectStructure([
            'segments'               => [
                $fid1 => [
                    $this->makeSegment($fid1, 'u1'),
                    $this->makeSegment($fid1, 'u2'),
                ],
                $fid2 => [
                    $this->makeSegment($fid2, 'u3'),
                ],
            ],
            'segments_original_data' => [$fid1 => [], $fid2 => []],
            'segments_meta_data'     => [$fid1 => [], $fid2 => []],
            'file_segments_count'    => [],
            'segments_metadata'      => [],          // plain array
            'notes'                  => [],
            'translations'           => [],
            'context_group'          => [],
        ]);

        // First file — metadata should contain 2 entries
        $this->service->storeSegments($fid1, $ps);
        self::assertIsArray($ps->segments_metadata);
        self::assertCount(2, $ps->segments_metadata);

        // Second file — metadata should accumulate to 3 entries
        $this->service->storeSegments($fid2, $ps);
        self::assertCount(3, $ps->segments_metadata);

        // Verify IDs are correct
        $ids = array_column($ps->segments_metadata, 'id');
        self::assertSame([100, 101, 200], $ids);
    }

    #[Test]
    public function c19_segmentsMetadataStartingWithExistingEntriesAccumulates(): void
    {
        $fid = 1;
        $this->stubSequence(1, 300);
        $this->stubFeaturesPassThrough();

        $ps = new ProjectStructure([
            'segments'               => [$fid => [$this->makeSegment($fid, 'u1')]],
            'segments_original_data' => [$fid => []],
            'segments_meta_data'     => [$fid => []],
            'file_segments_count'    => [],
            'segments_metadata'      => [
                ['id' => 99, 'internal_id' => 'pre-existing', 'segment' => 'Pre', 'segment_hash' => md5('Pre'),
                 'raw_word_count' => 1.0, 'xliff_mrk_id' => null, 'show_in_cattool' => true,
                 'additional_params' => null, 'file_id' => 0],
            ],
            'notes'                  => [],
            'translations'           => [],
            'context_group'          => [],
        ]);

        $this->service->storeSegments($fid, $ps);

        // Pre-existing + newly added
        self::assertCount(2, $ps->segments_metadata);
        self::assertSame(99, $ps->segments_metadata[0]['id']);
        self::assertSame(300, $ps->segments_metadata[1]['id']);
    }

    // ══════════════════════════════════════════════════════════════════
    // C20: segments_metadata->exchangeArray(array_filter(->getArrayCopy(), ...))
    //
    // Lines 258-260 — After fix:
    //   $projectStructure->segments_metadata = array_values(array_filter(
    //       $projectStructure->segments_metadata, fn($v) => $v['show_in_cattool'] == 1
    //   ))
    // ══════════════════════════════════════════════════════════════════

    #[Test]
    public function c20_cleanSegmentsMetadataWorksWithPlainArray(): void
    {
        $ps = new ProjectStructure([
            'segments_metadata' => [
                ['id' => 1, 'show_in_cattool' => 1],
                ['id' => 2, 'show_in_cattool' => 0],
                ['id' => 3, 'show_in_cattool' => 1],
            ],
        ]);

        self::assertIsArray($ps->segments_metadata);

        $this->service->cleanSegmentsMetadata($ps);

        // Must filter correctly without calling getArrayCopy() or exchangeArray()
        self::assertCount(2, $ps->segments_metadata);
        $ids = array_column($ps->segments_metadata, 'id');
        self::assertSame([1, 3], $ids);
    }

    #[Test]
    public function c20_cleanSegmentsMetadataRemovesAllHiddenFromPlainArray(): void
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
    public function c20_cleanSegmentsMetadataHandlesEmptyPlainArray(): void
    {
        $ps = new ProjectStructure([
            'segments_metadata' => [],
        ]);

        $this->service->cleanSegmentsMetadata($ps);

        self::assertCount(0, $ps->segments_metadata);
    }

    #[Test]
    public function c20_cleanSegmentsMetadataReindexesKeysAfterFiltering(): void
    {
        $ps = new ProjectStructure([
            'segments_metadata' => [
                ['id' => 1, 'show_in_cattool' => 0],
                ['id' => 2, 'show_in_cattool' => 1],
                ['id' => 3, 'show_in_cattool' => 0],
                ['id' => 4, 'show_in_cattool' => 1],
            ],
        ]);

        $this->service->cleanSegmentsMetadata($ps);

        // After array_values(), keys should be sequential 0, 1
        self::assertCount(2, $ps->segments_metadata);
        self::assertArrayHasKey(0, $ps->segments_metadata);
        self::assertArrayHasKey(1, $ps->segments_metadata);
        self::assertSame(2, $ps->segments_metadata[0]['id']);
        self::assertSame(4, $ps->segments_metadata[1]['id']);
    }

    // ══════════════════════════════════════════════════════════════════
    // C21: notes->offsetExists($internal_id)
    //
    // Line 449 — After fix: isset($projectStructure->notes[$internal_id])
    // ══════════════════════════════════════════════════════════════════

    #[Test]
    public function c21_setSegmentIdForNotesWorksWithPlainArrayNotes(): void
    {
        $ps = new ProjectStructure([
            'notes' => [
                'unit-1' => [
                    'json'             => [],
                    'segment_ids'      => [],
                    'json_segment_ids' => [],
                ],
            ],
        ]);

        // Must not call offsetExists() — uses isset() on plain array
        $this->invokePrivateMethod('setSegmentIdForNotes', [
            ['internal_id' => 'unit-1', 'id' => 42],
            $ps,
        ]);

        self::assertSame([42], $ps->notes['unit-1']['segment_ids']);
        self::assertEmpty($ps->notes['unit-1']['json_segment_ids']);
    }

    #[Test]
    public function c21_setSegmentIdForNotesAddsToJsonSegmentIdsWithPlainArray(): void
    {
        $ps = new ProjectStructure([
            'notes' => [
                'unit-1' => [
                    'json'             => ['some json note'],
                    'segment_ids'      => [],
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

    #[Test]
    public function c21_setSegmentIdForNotesDoesNothingWhenIdMissingFromPlainArray(): void
    {
        $ps = new ProjectStructure([
            'notes' => [
                'unit-1' => [
                    'json'             => [],
                    'segment_ids'      => [],
                    'json_segment_ids' => [],
                ],
            ],
        ]);

        $this->invokePrivateMethod('setSegmentIdForNotes', [
            ['internal_id' => 'unit-999', 'id' => 42],
            $ps,
        ]);

        self::assertEmpty($ps->notes['unit-1']['segment_ids']);
        self::assertEmpty($ps->notes['unit-1']['json_segment_ids']);
    }

    #[Test]
    public function c21_notesLinkingViaStoreSegmentsWithPlainArrays(): void
    {
        $fid = 1;
        $seg = $this->makeSegment($fid, 'u1');

        $this->stubSequence(1, 700);
        $this->stubFeaturesPassThrough();

        $sanitizedId = "$fid|u1";

        $ps = $this->makePlainArrayProjectStructure($fid, [$seg]);
        $ps->notes = [
            $sanitizedId => [
                'json'             => [],
                'segment_ids'      => [],
                'json_segment_ids' => [],
            ],
        ];

        $this->service->storeSegments($fid, $ps);

        self::assertSame([700], $ps->notes[$sanitizedId]['segment_ids']);
    }

    // ══════════════════════════════════════════════════════════════════
    // C22: context_group->offsetExists($internal_id)
    //
    // Line 468 — After fix: isset($projectStructure->context_group[$internal_id])
    // ══════════════════════════════════════════════════════════════════

    #[Test]
    public function c22_setSegmentIdForContextsWorksWithPlainArrayContextGroup(): void
    {
        $ps = new ProjectStructure([
            'context_group' => [
                'unit-1' => [
                    'context_json_segment_ids' => [],
                ],
            ],
        ]);

        // Must not call offsetExists() — uses isset() on plain array
        $this->invokePrivateMethod('setSegmentIdForContexts', [
            ['internal_id' => 'unit-1', 'id' => 55],
            $ps,
        ]);

        self::assertSame([55], $ps->context_group['unit-1']['context_json_segment_ids']);
    }

    #[Test]
    public function c22_setSegmentIdForContextsAppendsMultipleIdsWithPlainArray(): void
    {
        $ps = new ProjectStructure([
            'context_group' => [
                'unit-1' => [
                    'context_json_segment_ids' => [10],
                ],
            ],
        ]);

        $this->invokePrivateMethod('setSegmentIdForContexts', [
            ['internal_id' => 'unit-1', 'id' => 20],
            $ps,
        ]);
        $this->invokePrivateMethod('setSegmentIdForContexts', [
            ['internal_id' => 'unit-1', 'id' => 30],
            $ps,
        ]);

        self::assertSame([10, 20, 30], $ps->context_group['unit-1']['context_json_segment_ids']);
    }

    #[Test]
    public function c22_setSegmentIdForContextsDoesNothingWhenIdMissingFromPlainArray(): void
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

    #[Test]
    public function c22_contextGroupLinkingViaStoreSegmentsWithPlainArrays(): void
    {
        $fid = 1;
        $seg = $this->makeSegment($fid, 'u1');

        $this->stubSequence(1, 800);
        $this->stubFeaturesPassThrough();

        $sanitizedId = "$fid|u1";

        $ps = $this->makePlainArrayProjectStructure($fid, [$seg]);
        $ps->context_group = [
            $sanitizedId => [
                'context_json_segment_ids' => [],
            ],
        ];

        $this->service->storeSegments($fid, $ps);

        self::assertSame([800], $ps->context_group[$sanitizedId]['context_json_segment_ids']);
    }

    // ══════════════════════════════════════════════════════════════════
    // Integration: Full storeSegments flow with ALL plain arrays
    //
    // Exercises C14-C22 together in a single realistic scenario.
    // ══════════════════════════════════════════════════════════════════

    #[Test]
    public function allBugsFixedInFullStoreSegmentsFlowWithPlainArrays(): void
    {
        $fid = 1;
        $seg1 = $this->makeSegment($fid, 'u1', 'Hello');
        $seg2 = $this->makeSegment($fid, 'u1', 'World'); // same internal_id, positional counter
        $seg3 = $this->makeSegment($fid, 'u2', 'Foo');

        $this->stubSequence(3, 900);
        $this->stubFeaturesPassThrough();

        $sanitizedU1 = "$fid|u1";
        $sanitizedU2 = "$fid|u2";

        $ps = new ProjectStructure([
            // C14/C15: plain array segments
            'segments'               => [
                $fid => [$seg1, $seg2, $seg3],
            ],
            'segments_original_data' => [$fid => []],
            'segments_meta_data'     => [$fid => []],
            'file_segments_count'    => [],
            // C19: plain array segments_metadata
            'segments_metadata'      => [],
            // C21: plain array notes
            'notes'                  => [
                $sanitizedU1 => [
                    'json'             => ['a note'],
                    'segment_ids'      => [],
                    'json_segment_ids' => [],
                ],
                $sanitizedU2 => [
                    'json'             => [],
                    'segment_ids'      => [],
                    'json_segment_ids' => [],
                ],
            ],
            // C16/C17/C18: plain array translations (3-level nesting)
            'translations'           => [
                $sanitizedU1 => [
                    0 => [null, null, 'Ciao', null, '<tu/>', null, null],
                    1 => [null, null, 'Mondo', null, '<tu/>', null, null],
                ],
                $sanitizedU2 => [
                    0 => [null, null, 'Pippo', null, '<tu/>', null, null],
                ],
            ],
            // C22: plain array context_group
            'context_group'          => [
                $sanitizedU1 => ['context_json_segment_ids' => []],
                $sanitizedU2 => ['context_json_segment_ids' => []],
            ],
        ]);

        // Execute — must not throw any "Call to undefined method" errors
        $this->service->storeSegments($fid, $ps);

        // C14: bulk insert happened from plain array
        // C15: segments cleared
        self::assertCount(0, $ps->segments[$fid]);

        // C19: segments_metadata accumulated
        self::assertCount(3, $ps->segments_metadata);
        $ids = array_column($ps->segments_metadata, 'id');
        self::assertSame([900, 901, 902], $ids);

        // C16/C17/C18: translation offsets set correctly
        // u1, counter 0 → seg 900
        self::assertSame(900, $ps->translations[$sanitizedU1][0][0]);
        self::assertSame($sanitizedU1, $ps->translations[$sanitizedU1][0][1]);
        self::assertSame(md5('Hello'), $ps->translations[$sanitizedU1][0][3]);
        self::assertSame($fid, $ps->translations[$sanitizedU1][0][5]);

        // u1, counter 1 → seg 901
        self::assertSame(901, $ps->translations[$sanitizedU1][1][0]);
        self::assertSame($sanitizedU1, $ps->translations[$sanitizedU1][1][1]);
        self::assertSame(md5('World'), $ps->translations[$sanitizedU1][1][3]);
        self::assertSame($fid, $ps->translations[$sanitizedU1][1][5]);

        // u2, counter 0 → seg 902
        self::assertSame(902, $ps->translations[$sanitizedU2][0][0]);
        self::assertSame($sanitizedU2, $ps->translations[$sanitizedU2][0][1]);
        self::assertSame(md5('Foo'), $ps->translations[$sanitizedU2][0][3]);
        self::assertSame($fid, $ps->translations[$sanitizedU2][0][5]);

        // C21: notes linked
        self::assertSame([900, 901], $ps->notes[$sanitizedU1]['json_segment_ids']);
        self::assertEmpty($ps->notes[$sanitizedU1]['segment_ids']);
        self::assertSame([902], $ps->notes[$sanitizedU2]['segment_ids']);

        // C22: context_group linked
        self::assertSame([900, 901], $ps->context_group[$sanitizedU1]['context_json_segment_ids']);
        self::assertSame([902], $ps->context_group[$sanitizedU2]['context_json_segment_ids']);
    }
}
