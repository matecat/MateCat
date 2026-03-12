<?php

namespace unit\Model\ProjectCreation;

use ArrayObject;
use Matecat\SubFiltering\MateCatFilter;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\ProjectCreation\ProjectManagerModel;
use Model\Segments\SegmentDao;
use Model\Segments\SegmentMetadataStruct;
use Model\Segments\SegmentOriginalDataStruct;
use Model\Segments\SegmentStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;

/**
 * Unit tests for SegmentStorageService::storeSegments().
 *
 * These tests exercise the core segment storage logic including:
 *  - sequence ID reservation and min/max tracking
 *  - segment ID assignment to structs
 *  - original data processing and persistence
 *  - metadata saving
 *  - analysis metadata building
 *  - bulk insert via SegmentDao
 *  - notes and context-group linking
 *  - translation offset linking
 *  - segments_metadata accumulation across files
 */
#[AllowMockObjectsWithoutExpectations]
class SegmentStorageServiceTest extends AbstractTest
{
    private TestableSegmentStorageService $service;
    private Database&MockObject $dbHandler;
    private FeatureSet&MockObject $features;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dbHandler = $this->createMock(Database::class);
        $this->features  = $this->createMock(FeatureSet::class);
        $logger          = $this->createStub(MatecatLogger::class);
        $filter          = $this->createStub(MateCatFilter::class);
        $pmModel         = $this->createStub(ProjectManagerModel::class);

        $this->service = new TestableSegmentStorageService(
            $this->dbHandler,
            $this->features,
            $logger,
            $filter,
            $pmModel,
        );

        // Inject a stub SegmentDao so createList() doesn't hit the DB
        $segmentDaoStub = $this->createStub(SegmentDao::class);
        $this->service->setSegmentDao($segmentDaoStub);
    }

    // ── Helper methods ──────────────────────────────────────────────

    /**
     * Create a minimal SegmentStruct for testing.
     */
    private function makeSegment(int $idFile, string $internalId, string $text = 'Hello', float $rawWordCount = 1.0, bool $showInCattool = true): SegmentStruct
    {
        $seg = new SegmentStruct();
        $seg->id_file       = $idFile;
        $seg->internal_id   = $internalId;
        $seg->segment       = $text;
        $seg->segment_hash  = md5($text);
        $seg->raw_word_count = $rawWordCount;
        $seg->show_in_cattool = $showInCattool;
        $seg->xliff_mrk_id  = null;

        return $seg;
    }

    /**
     * Create a basic project structure ArrayObject with all keys needed by storeSegments.
     */
    private function makeProjectStructure(int $fid, array $segments, array $originalData = [], array $metaData = []): ArrayObject
    {
        return new ArrayObject([
            'segments'              => new ArrayObject([$fid => new ArrayObject($segments)]),
            'segments-original-data' => new ArrayObject(array_key_exists($fid, $originalData) ? $originalData : [$fid => $originalData]),
            'segments-meta-data'    => new ArrayObject(array_key_exists($fid, $metaData) ? $metaData : [$fid => $metaData]),
            'file_segments_count'   => new ArrayObject(),
            'segments_metadata'     => new ArrayObject([]),
            'notes'                 => new ArrayObject(),
            'translations'          => new ArrayObject(),
            'context-group'         => new ArrayObject(),
        ]);
    }

    /**
     * Stub dbHandler to return a sequence of IDs starting from $start.
     */
    private function stubSequence(int $count, int $start = 100): void
    {
        $ids = range($start, $start + $count - 1);
        $this->dbHandler->method('nextSequence')
            ->with(Database::SEQ_ID_SEGMENT, $count)
            ->willReturn($ids);
    }

    /**
     * Make features->filter return its second argument by default (pass-through).
     */
    private function stubFeaturesPassThrough(): void
    {
        $this->features->method('filter')
            ->willReturnCallback(function (string $name, mixed $arg1) {
                return $arg1;
            });
    }

    // ── Empty segments (early return) ───────────────────────────────

    #[Test]
    public function storeSegmentsReturnsEarlyWhenSegmentsArrayIsEmpty(): void
    {
        $fid = 1;
        $ps = $this->makeProjectStructure($fid, []);

        // dbHandler should never be called
        $this->dbHandler->expects(self::never())->method('nextSequence');

        $this->service->storeSegments($fid, $ps);

        self::assertEmpty($this->service->getMinMaxSegmentsId());
        self::assertCount(0, $ps['segments_metadata']);
    }

    // ── Sequence ID reservation ─────────────────────────────────────

    #[Test]
    public function storeSegmentsReservesCorrectNumberOfSequenceIds(): void
    {
        $fid = 1;
        $segments = [
            $this->makeSegment($fid, 'u1', 'Seg one'),
            $this->makeSegment($fid, 'u2', 'Seg two'),
            $this->makeSegment($fid, 'u3', 'Seg three'),
        ];

        $this->dbHandler->expects(self::once())
            ->method('nextSequence')
            ->with(Database::SEQ_ID_SEGMENT, 3)
            ->willReturn([100, 101, 102]);

        $this->stubFeaturesPassThrough();

        $ps = $this->makeProjectStructure($fid, $segments);
        $this->service->storeSegments($fid, $ps);

        self::assertSame(100, $this->service->getMinMaxSegmentsId()['job_first_segment']);
        self::assertSame(102, $this->service->getMinMaxSegmentsId()['job_last_segment']);
    }

    // ── Min/max segment ID tracking ─────────────────────────────────

    #[Test]
    public function storeSegmentsFirstCallSetsJobFirstSegment(): void
    {
        $fid = 1;
        $this->stubSequence(1, 50);
        $this->stubFeaturesPassThrough();

        $ps = $this->makeProjectStructure($fid, [$this->makeSegment($fid, 'u1')]);
        $this->service->storeSegments($fid, $ps);

        self::assertSame(50, $this->service->getMinMaxSegmentsId()['job_first_segment']);
        self::assertSame(50, $this->service->getMinMaxSegmentsId()['job_last_segment']);
    }

    #[Test]
    public function storeSegmentsSecondCallUpdatesJobLastSegmentOnly(): void
    {
        $fid1 = 1;
        $fid2 = 2;

        // First file: IDs 10-12
        $this->dbHandler->expects(self::exactly(2))
            ->method('nextSequence')
            ->willReturnOnConsecutiveCalls([10, 11, 12], [20, 21]);

        $this->stubFeaturesPassThrough();

        $ps1 = $this->makeProjectStructure($fid1, [
            $this->makeSegment($fid1, 'u1'),
            $this->makeSegment($fid1, 'u2'),
            $this->makeSegment($fid1, 'u3'),
        ]);

        // Add second file's segments to the same structure
        $ps1['segments'][$fid2] = new ArrayObject([
            $this->makeSegment($fid2, 'u4'),
            $this->makeSegment($fid2, 'u5'),
        ]);
        $ps1['segments-original-data'][$fid2] = [];
        $ps1['segments-meta-data'][$fid2] = [];

        $this->service->storeSegments($fid1, $ps1);
        $this->service->storeSegments($fid2, $ps1);

        // First segment stays from first call, last segment from second call
        self::assertSame(10, $this->service->getMinMaxSegmentsId()['job_first_segment']);
        self::assertSame(21, $this->service->getMinMaxSegmentsId()['job_last_segment']);
    }

    // ── Segment ID assignment ───────────────────────────────────────

    #[Test]
    public function storeSegmentsAssignsSequenceIdsToSegmentStructs(): void
    {
        $fid = 1;
        $seg1 = $this->makeSegment($fid, 'u1', 'Hello');
        $seg2 = $this->makeSegment($fid, 'u2', 'World');

        $this->stubSequence(2, 200);
        $this->stubFeaturesPassThrough();

        $ps = $this->makeProjectStructure($fid, [$seg1, $seg2]);
        $this->service->storeSegments($fid, $ps);

        // After storeSegments, the segments ArrayObject is cleared,
        // but the IDs were set on the original structs which we still hold references to
        self::assertSame(200, $seg1->id);
        self::assertSame(201, $seg2->id);
    }

    // ── Original data processing ────────────────────────────────────

    #[Test]
    public function storeSegmentsProcessesOriginalDataMaps(): void
    {
        $fid = 1;
        $seg = $this->makeSegment($fid, 'u1', '<g id="1">Hello</g>');

        $origData = new SegmentOriginalDataStruct();
        $origData->setMap(['ph' => '<x/>']);

        $this->stubSequence(1, 300);

        // sanitizeOriginalDataMap returns the map, correctTagErrors returns modified segment
        $this->features->method('filter')
            ->willReturnCallback(function (string $name, mixed $arg1) {
                if ($name === 'sanitizeOriginalDataMap') {
                    return $arg1; // pass-through
                }
                if ($name === 'correctTagErrors') {
                    return 'corrected-segment'; // simulate correction
                }
                return $arg1;
            });

        $ps = $this->makeProjectStructure($fid, [$seg], [$fid => [0 => $origData]]);
        $this->service->storeSegments($fid, $ps);

        // Verify original data was persisted
        $records = $this->service->getInsertedOriginalDataRecords();
        self::assertCount(1, $records);
        self::assertSame(300, $records[0]['id_segment']);
        self::assertSame(['ph' => '<x/>'], $records[0]['map']);

        // Verify segment text was corrected
        self::assertSame('corrected-segment', $seg->segment);
    }

    #[Test]
    public function storeSegmentsSkipsOriginalDataWhenMapIsEmpty(): void
    {
        $fid = 1;
        $seg = $this->makeSegment($fid, 'u1');

        // No original data at all for this position
        $this->stubSequence(1, 400);
        $this->stubFeaturesPassThrough();

        $ps = $this->makeProjectStructure($fid, [$seg]);
        $this->service->storeSegments($fid, $ps);

        self::assertEmpty($this->service->getInsertedOriginalDataRecords());
    }

    // ── Metadata saving ─────────────────────────────────────────────

    #[Test]
    public function storeSegmentsSavesMetadataWhenPresent(): void
    {
        $fid = 1;
        $seg = $this->makeSegment($fid, 'u1');

        $meta = new SegmentMetadataStruct();
        $meta->meta_key = 'char_count';
        $meta->meta_value = '42';

        $this->stubSequence(1, 500);
        $this->stubFeaturesPassThrough();

        $ps = $this->makeProjectStructure($fid, [$seg], [], [$fid => [0 => $meta]]);
        $this->service->storeSegments($fid, $ps);

        $persisted = $this->service->getPersistedSegmentMetadata();
        self::assertCount(1, $persisted);
        self::assertEquals(500, $persisted[0]->id_segment);
        self::assertSame('char_count', $persisted[0]->meta_key);
    }

    #[Test]
    public function storeSegmentsSkipsMetadataWhenNotSet(): void
    {
        $fid = 1;
        $seg = $this->makeSegment($fid, 'u1');

        $this->stubSequence(1, 600);
        $this->stubFeaturesPassThrough();

        $ps = $this->makeProjectStructure($fid, [$seg]);
        $this->service->storeSegments($fid, $ps);

        self::assertEmpty($this->service->getPersistedSegmentMetadata());
    }

    // ── File segments count ─────────────────────────────────────────

    #[Test]
    public function storeSegmentsIncrementsFileSegmentsCount(): void
    {
        $fid = 1;
        $this->stubSequence(3, 700);
        $this->stubFeaturesPassThrough();

        $ps = $this->makeProjectStructure($fid, [
            $this->makeSegment($fid, 'u1'),
            $this->makeSegment($fid, 'u2'),
            $this->makeSegment($fid, 'u3'),
        ]);

        $this->service->storeSegments($fid, $ps);

        self::assertSame(3, $ps['file_segments_count'][$fid]);
    }

    // ── Analysis metadata building ──────────────────────────────────

    #[Test]
    public function storeSegmentsBuildsMetadataArrayWithCorrectFields(): void
    {
        $fid = 7;
        $seg = $this->makeSegment($fid, 'unit-42', 'Test segment', 5.0, true);

        $this->stubSequence(1, 800);
        $this->stubFeaturesPassThrough();

        $ps = $this->makeProjectStructure($fid, [$seg]);
        $this->service->storeSegments($fid, $ps);

        $metadata = $ps['segments_metadata']->getArrayCopy();
        self::assertCount(1, $metadata);

        $entry = $metadata[0];
        self::assertSame(800, $entry['id']);
        self::assertStringContainsString('unit-42', $entry['internal_id']);
        self::assertSame('Test segment', $entry['segment']);
        self::assertSame(md5('Test segment'), $entry['segment_hash']);
        self::assertEquals(5.0, $entry['raw_word_count']);
        self::assertNull($entry['xliff_mrk_id']);
        self::assertTrue($entry['show_in_cattool']);
        self::assertNull($entry['additional_params']);
        self::assertSame(7, $entry['file_id']);
    }

    #[Test]
    public function storeSegmentsCallsAppendFieldToAnalysisObjectFilter(): void
    {
        $fid = 1;
        $seg = $this->makeSegment($fid, 'u1');

        $this->stubSequence(1, 900);

        // Simulate a plugin adding a field
        $this->features->method('filter')
            ->willReturnCallback(function (string $name, mixed $arg1) {
                if ($name === 'appendFieldToAnalysisObject') {
                    $arg1['custom_field'] = 'custom_value';
                    return $arg1;
                }
                return $arg1;
            });

        $ps = $this->makeProjectStructure($fid, [$seg]);
        $this->service->storeSegments($fid, $ps);

        $metadata = $ps['segments_metadata']->getArrayCopy();
        self::assertSame('custom_value', $metadata[0]['custom_field']);
    }

    // ── Memory cleanup ──────────────────────────────────────────────

    #[Test]
    public function storeSegmentsClearsSegmentsArrayAfterInsert(): void
    {
        $fid = 1;
        $this->stubSequence(2, 1000);
        $this->stubFeaturesPassThrough();

        $ps = $this->makeProjectStructure($fid, [
            $this->makeSegment($fid, 'u1'),
            $this->makeSegment($fid, 'u2'),
        ]);

        self::assertCount(2, $ps['segments'][$fid]);

        $this->service->storeSegments($fid, $ps);

        self::assertCount(0, $ps['segments'][$fid]);
    }

    // ── Notes linking ───────────────────────────────────────────────

    #[Test]
    public function storeSegmentsLinksSegmentIdsToNotes(): void
    {
        $fid = 1;
        $seg = $this->makeSegment($fid, 'u1');

        $this->stubSequence(1, 1100);
        $this->stubFeaturesPassThrough();

        $ps = $this->makeProjectStructure($fid, [$seg]);
        // Internal ID will be sanitized as "$fid|u1"
        $sanitizedId = "$fid|u1";

        $ps['notes'] = new ArrayObject([
            $sanitizedId => [
                'json' => [],
                'segment_ids' => [],
                'json_segment_ids' => [],
            ],
        ]);

        $this->service->storeSegments($fid, $ps);

        self::assertSame([1100], $ps['notes'][$sanitizedId]['segment_ids']);
    }

    #[Test]
    public function storeSegmentsLinksSegmentIdsToJsonNotes(): void
    {
        $fid = 1;
        $seg = $this->makeSegment($fid, 'u1');

        $this->stubSequence(1, 1200);
        $this->stubFeaturesPassThrough();

        $ps = $this->makeProjectStructure($fid, [$seg]);
        $sanitizedId = "$fid|u1";

        $ps['notes'] = new ArrayObject([
            $sanitizedId => [
                'json' => ['some json note'],
                'segment_ids' => [],
                'json_segment_ids' => [],
            ],
        ]);

        $this->service->storeSegments($fid, $ps);

        self::assertSame([1200], $ps['notes'][$sanitizedId]['json_segment_ids']);
        self::assertEmpty($ps['notes'][$sanitizedId]['segment_ids']);
    }

    // ── Context-group linking ───────────────────────────────────────

    #[Test]
    public function storeSegmentsLinksSegmentIdsToContextGroups(): void
    {
        $fid = 1;
        $seg = $this->makeSegment($fid, 'u1');

        $this->stubSequence(1, 1300);
        $this->stubFeaturesPassThrough();

        $ps = $this->makeProjectStructure($fid, [$seg]);
        $sanitizedId = "$fid|u1";

        $ps['context-group'] = new ArrayObject([
            $sanitizedId => [
                'context_json_segment_ids' => [],
            ],
        ]);

        $this->service->storeSegments($fid, $ps);

        self::assertSame([1300], $ps['context-group'][$sanitizedId]['context_json_segment_ids']);
    }

    // ── Translation linking ─────────────────────────────────────────

    #[Test]
    public function storeSegmentsLinksSegmentIdToTranslationEntries(): void
    {
        $fid = 1;
        $seg = $this->makeSegment($fid, 'u1', 'Hello world');

        $this->stubSequence(1, 1400);
        $this->stubFeaturesPassThrough();

        $ps = $this->makeProjectStructure($fid, [$seg]);
        $sanitizedId = "$fid|u1";

        // Translation structure: translations[internal_id][counter] = ArrayObject([0 => id, 1 => internal_id, 2 => target, 3 => hash, 4 => trans_unit, 5 => file_id, 6 => position])
        $translationRow = new ArrayObject([null, null, 'Ciao mondo', null, '<trans-unit/>', null, null]);
        $ps['translations'] = new ArrayObject([
            $sanitizedId => new ArrayObject([
                0 => $translationRow,
            ]),
        ]);

        $this->service->storeSegments($fid, $ps);

        // offset 0 = segment id
        self::assertSame(1400, $translationRow[0]);
        // offset 1 = internal_id
        self::assertSame($sanitizedId, $translationRow[1]);
        // offset 3 = segment_hash
        self::assertSame(md5('Hello world'), $translationRow[3]);
        // offset 5 = file_id
        self::assertSame($fid, $translationRow[5]);
    }

    #[Test]
    public function storeSegmentsSkipsTranslationWhenCounterDoesNotExist(): void
    {
        $fid = 1;
        $seg = $this->makeSegment($fid, 'u1');
        $seg->xliff_mrk_id = '5'; // mrk id 5 but no translation at position 5

        $this->stubSequence(1, 1500);
        $this->stubFeaturesPassThrough();

        $ps = $this->makeProjectStructure($fid, [$seg]);
        $sanitizedId = "$fid|u1";

        // Translation exists for this internal_id but not for mrk position 5
        $translationRow = new ArrayObject([null, null, 'Target', null, '<tu/>', null, null]);
        $ps['translations'] = new ArrayObject([
            $sanitizedId => new ArrayObject([
                0 => $translationRow, // position 0, not 5
            ]),
        ]);

        $this->service->storeSegments($fid, $ps);

        // Translation should NOT be linked — offset 0 should remain null
        self::assertNull($translationRow[0]);
    }

    // ── Segments metadata accumulation across files ──────────────────

    #[Test]
    public function storeSegmentsAccumulatesMetadataAcrossMultipleFiles(): void
    {
        $fid1 = 1;
        $fid2 = 2;

        $this->dbHandler->method('nextSequence')
            ->willReturnOnConsecutiveCalls([100, 101], [200]);

        $this->stubFeaturesPassThrough();

        $ps = new ArrayObject([
            'segments'              => new ArrayObject([
                $fid1 => new ArrayObject([
                    $this->makeSegment($fid1, 'u1'),
                    $this->makeSegment($fid1, 'u2'),
                ]),
                $fid2 => new ArrayObject([
                    $this->makeSegment($fid2, 'u3'),
                ]),
            ]),
            'segments-original-data' => new ArrayObject([$fid1 => [], $fid2 => []]),
            'segments-meta-data'    => new ArrayObject([$fid1 => [], $fid2 => []]),
            'file_segments_count'   => new ArrayObject(),
            'segments_metadata'     => new ArrayObject([]),
            'notes'                 => new ArrayObject(),
            'translations'          => new ArrayObject(),
            'context-group'         => new ArrayObject(),
        ]);

        $this->service->storeSegments($fid1, $ps);
        self::assertCount(2, $ps['segments_metadata']);

        $this->service->storeSegments($fid2, $ps);
        self::assertCount(3, $ps['segments_metadata']);

        // Verify IDs are correct
        $ids = array_column($ps['segments_metadata']->getArrayCopy(), 'id');
        self::assertSame([100, 101, 200], $ids);
    }

    // ── Translation with mrk_id ─────────────────────────────────────

    #[Test]
    public function storeSegmentsUsesMrkIdForTranslationCounter(): void
    {
        $fid = 1;
        $seg = $this->makeSegment($fid, 'u1', 'Hello');
        $seg->xliff_mrk_id = '2';

        $this->stubSequence(1, 1600);
        $this->stubFeaturesPassThrough();

        $ps = $this->makeProjectStructure($fid, [$seg]);
        $sanitizedId = "$fid|u1";

        // Translation at mrk position 2
        $translationRow = new ArrayObject([null, null, 'Ciao', null, '<tu/>', null, null]);
        $ps['translations'] = new ArrayObject([
            $sanitizedId => new ArrayObject([
                '2' => $translationRow,
            ]),
        ]);

        $this->service->storeSegments($fid, $ps);

        self::assertSame(1600, $translationRow[0]);
        self::assertSame($sanitizedId, $translationRow[1]);
    }

    // ── Multiple segments same internal_id with positional counter ──

    #[Test]
    public function storeSegmentsIncrementsPositionalCounterWhenNoMrkId(): void
    {
        $fid = 1;
        $seg1 = $this->makeSegment($fid, 'u1', 'Part one');
        $seg2 = $this->makeSegment($fid, 'u1', 'Part two');

        $this->stubSequence(2, 1700);
        $this->stubFeaturesPassThrough();

        $ps = $this->makeProjectStructure($fid, [$seg1, $seg2]);
        $sanitizedId = "$fid|u1";

        $row0 = new ArrayObject([null, null, 'Parte uno', null, '<tu/>', null, null]);
        $row1 = new ArrayObject([null, null, 'Parte due', null, '<tu/>', null, null]);

        $ps['translations'] = new ArrayObject([
            $sanitizedId => new ArrayObject([
                0 => $row0,
                1 => $row1,
            ]),
        ]);

        $this->service->storeSegments($fid, $ps);

        self::assertSame(1700, $row0[0]);
        self::assertSame(1701, $row1[0]);
    }

    // ── No notes/translations: skip linking loop ────────────────────

    #[Test]
    public function storeSegmentsSkipsLinkingWhenNotesAndTranslationsAreEmpty(): void
    {
        $fid = 1;
        $this->stubSequence(1, 1800);
        $this->stubFeaturesPassThrough();

        $ps = $this->makeProjectStructure($fid, [$this->makeSegment($fid, 'u1')]);
        // Ensure both are truly empty
        $ps['notes'] = new ArrayObject();
        $ps['translations'] = new ArrayObject();

        $this->service->storeSegments($fid, $ps);

        // Should complete without error, metadata should still be built
        self::assertCount(1, $ps['segments_metadata']);
    }

    // ── cleanSegmentsMetadata (on service directly) ─────────────────

    #[Test]
    public function cleanSegmentsMetadataRemovesNonCattoolSegments(): void
    {
        $ps = new ArrayObject([
            'segments_metadata' => new ArrayObject([
                ['id' => 1, 'show_in_cattool' => 1],
                ['id' => 2, 'show_in_cattool' => 0],
                ['id' => 3, 'show_in_cattool' => 1],
            ]),
        ]);

        $this->service->cleanSegmentsMetadata($ps);

        $result = $ps['segments_metadata']->getArrayCopy();
        self::assertCount(2, $result);
        $ids = array_column($result, 'id');
        self::assertSame([1, 3], $ids);
    }

    // ── getMinMaxSegmentsId starts empty ─────────────────────────────

    #[Test]
    public function getMinMaxSegmentsIdStartsEmpty(): void
    {
        self::assertEmpty($this->service->getMinMaxSegmentsId());
    }
}
