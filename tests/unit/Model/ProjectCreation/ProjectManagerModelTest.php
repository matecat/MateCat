<?php

namespace unit\Model\ProjectCreation;

use Model\DataAccess\IDatabase;
use Model\ProjectCreation\ProjectManagerModel;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Utils\Logger\MatecatLogger;

class ProjectManagerModelTest extends TestCase
{
    /** @var list<string> Queries passed to PDO::prepare() */
    private array $preparedQueries = [];

    /** @var list<array<int, mixed>> Values passed to PDOStatement::execute() */
    private array $executedValues = [];

    private function createModel(): ProjectManagerModel
    {
        $this->preparedQueries = [];
        $this->executedValues = [];

        $mockStmt = $this->createStub(PDOStatement::class);
        $mockStmt->method('execute')->willReturnCallback(
            function (array $values): bool {
                $this->executedValues[] = $values;
                return true;
            }
        );

        $mockPdo = $this->createStub(PDO::class);
        $mockPdo->method('prepare')->willReturnCallback(
            function (string $query) use ($mockStmt): PDOStatement {
                $this->preparedQueries[] = $query;
                return $mockStmt;
            }
        );

        $mockDb = $this->createStub(IDatabase::class);
        $mockDb->method('getConnection')->willReturn($mockPdo);

        $mockLogger = $this->createStub(MatecatLogger::class);

        return new ProjectManagerModel($mockDb, $mockLogger);
    }

    /**
     * @param array<int, string> $entryAttributes
     * @param array<int, string> $entries
     * @param list<int>          $segmentIds
     * @param array<int, string> $jsonAttributes
     * @param list<string>       $jsonEntries
     * @param list<int>          $jsonSegmentIds
     *
     * @return array<string, mixed>
     */
    private function makeNote(
        array $entryAttributes,
        array $entries,
        array $segmentIds,
        array $jsonAttributes = [],
        array $jsonEntries = [],
        array $jsonSegmentIds = [],
    ): array {
        return [
            'from' => [
                'entries' => $entryAttributes,
                'json' => $jsonAttributes,
            ],
            'entries' => $entries,
            'segment_ids' => $segmentIds,
            'json' => $jsonEntries,
            'json_segment_ids' => $jsonSegmentIds,
        ];
    }

    /** @return list<int> */
    private function queryIndicesForTable(string $tableName): array
    {
        $indices = [];
        foreach ($this->preparedQueries as $i => $query) {
            if (str_contains($query, $tableName)) {
                $indices[] = $i;
            }
        }
        return $indices;
    }

    // ────── Empty input ──────

    public function testEmptyNotesProducesNoInserts(): void
    {
        $model = $this->createModel();

        $model->bulkInsertSegmentNotesAndMetadata([]);

        self::assertEmpty($this->preparedQueries, 'No queries should be prepared for empty notes');
    }

    // ────── Text entries — notes vs metadata ──────

    public function testPlainNoteInsertsIntoSegmentNotesOnly(): void
    {
        $model = $this->createModel();

        $model->bulkInsertSegmentNotesAndMetadata([
            42 => $this->makeNote(
                [0 => 'comment'],      // 'comment' is NOT a metadata key
                [0 => 'note1'],
                [100],
            ),
        ]);

        $noteIndices = $this->queryIndicesForTable('segment_notes');
        self::assertCount(1, $noteIndices, 'Exactly one segment_notes INSERT expected');

        $values = $this->executedValues[$noteIndices[0]];
        self::assertSame(100, $values[0], 'id_segment');
        self::assertSame(42, $values[1], 'internal_id');
        self::assertSame('note1', $values[2], 'note text');
        self::assertNull($values[3], 'json column null for text notes');

        $metaIndices = $this->queryIndicesForTable('segment_metadata');
        self::assertEmpty($metaIndices, 'No segment_metadata INSERT for non-metadata entries');
    }

    public function testMetadataEntryInsertsIntoSegmentMetadataOnly(): void
    {
        $model = $this->createModel();

        $model->bulkInsertSegmentNotesAndMetadata([
            42 => $this->makeNote(
                [0 => 'id_request'],   // metadata key
                [0 => 'REQ-123'],
                [100],
            ),
        ]);

        $metaIndices = $this->queryIndicesForTable('segment_metadata');
        self::assertCount(1, $metaIndices, 'Exactly one segment_metadata INSERT expected');

        $values = $this->executedValues[$metaIndices[0]];
        self::assertSame([100, 'id_request', 'REQ-123'], $values);

        $noteIndices = $this->queryIndicesForTable('segment_notes');
        self::assertEmpty($noteIndices, 'No segment_notes INSERT for metadata entries');
    }

    public function testMixedEntriesAreSplitAcrossBothTables(): void
    {
        $model = $this->createModel();

        $model->bulkInsertSegmentNotesAndMetadata([
            42 => $this->makeNote(
                [0 => 'comment', 1 => 'id_request'],
                [0 => 'note1', 1 => 'REQ-123'],
                [100],
            ),
        ]);

        // Notes table
        $noteIndices = $this->queryIndicesForTable('segment_notes');
        self::assertCount(1, $noteIndices);
        $noteValues = $this->executedValues[$noteIndices[0]];
        self::assertSame([100, 42, 'note1', null], $noteValues);

        // Metadata table
        $metaIndices = $this->queryIndicesForTable('segment_metadata');
        self::assertCount(1, $metaIndices);
        $metaValues = $this->executedValues[$metaIndices[0]];
        self::assertSame([100, 'id_request', 'REQ-123'], $metaValues);
    }

    public function testEntryWithoutAttributeFallsToSegmentNotes(): void
    {
        $model = $this->createModel();

        $model->bulkInsertSegmentNotesAndMetadata([
            42 => $this->makeNote(
                [],                    // no attributes
                [0 => 'standalone'],
                [100],
            ),
        ]);

        $noteIndices = $this->queryIndicesForTable('segment_notes');
        self::assertCount(1, $noteIndices);
        $values = $this->executedValues[$noteIndices[0]];
        self::assertSame([100, 42, 'standalone', null], $values);

        $metaIndices = $this->queryIndicesForTable('segment_metadata');
        self::assertEmpty($metaIndices);
    }

    // ────── JSON entries — notes vs metadata ──────

    public function testJsonMetadataEntryInsertsIntoSegmentMetadata(): void
    {
        $model = $this->createModel();

        $model->bulkInsertSegmentNotesAndMetadata([
            42 => $this->makeNote(
                [], [], [],
                [0 => 'screenshot'],                      // metadata key
                [0 => '{"url":"http://example.com"}'],
                [101],
            ),
        ]);

        $metaIndices = $this->queryIndicesForTable('segment_metadata');
        self::assertCount(1, $metaIndices);
        $values = $this->executedValues[$metaIndices[0]];
        self::assertSame([101, 'screenshot', '{"url":"http://example.com"}'], $values);

        $noteIndices = $this->queryIndicesForTable('segment_notes');
        self::assertEmpty($noteIndices);
    }

    public function testJsonNonMetadataEntryInsertsIntoSegmentNotes(): void
    {
        $model = $this->createModel();

        $model->bulkInsertSegmentNotesAndMetadata([
            42 => $this->makeNote(
                [], [], [],
                [0 => 'custom_field'],                     // NOT a metadata key
                [0 => '{"data":"value"}'],
                [101],
            ),
        ]);

        $noteIndices = $this->queryIndicesForTable('segment_notes');
        self::assertCount(1, $noteIndices);
        $values = $this->executedValues[$noteIndices[0]];
        self::assertSame([101, 42, null, '{"data":"value"}'], $values);

        $metaIndices = $this->queryIndicesForTable('segment_metadata');
        self::assertEmpty($metaIndices);
    }

    public function testJsonEntryWithoutAttributeFallsToSegmentNotes(): void
    {
        $model = $this->createModel();

        $model->bulkInsertSegmentNotesAndMetadata([
            42 => $this->makeNote(
                [], [], [],
                [],                                         // no json attributes
                [0 => '{"data":"value"}'],
                [101],
            ),
        ]);

        $noteIndices = $this->queryIndicesForTable('segment_notes');
        self::assertCount(1, $noteIndices);
        $values = $this->executedValues[$noteIndices[0]];
        self::assertSame([101, 42, null, '{"data":"value"}'], $values);

        $metaIndices = $this->queryIndicesForTable('segment_metadata');
        self::assertEmpty($metaIndices);
    }

    // ────── Multiple segments ──────

    public function testMultipleSegmentsEachReceiveTheSameNote(): void
    {
        $model = $this->createModel();

        $model->bulkInsertSegmentNotesAndMetadata([
            42 => $this->makeNote(
                [0 => 'comment'],
                [0 => 'shared'],
                [100, 101],            // two segments
            ),
        ]);

        $noteIndices = $this->queryIndicesForTable('segment_notes');
        self::assertCount(1, $noteIndices);
        $values = $this->executedValues[$noteIndices[0]];

        // 2 rows × 4 columns = 8 flattened values
        self::assertSame(
            [100, 42, 'shared', null, 101, 42, 'shared', null],
            $values,
        );
    }

    public function testMultipleSegmentsEachReceiveTheSameMetadata(): void
    {
        $model = $this->createModel();

        $model->bulkInsertSegmentNotesAndMetadata([
            42 => $this->makeNote(
                [0 => 'id_request'],
                [0 => 'REQ-123'],
                [100, 101],            // two segments
            ),
        ]);

        $metaIndices = $this->queryIndicesForTable('segment_metadata');
        self::assertCount(1, $metaIndices);
        $values = $this->executedValues[$metaIndices[0]];

        // 2 rows × 3 columns = 6 flattened values
        self::assertSame(
            [100, 'id_request', 'REQ-123', 101, 'id_request', 'REQ-123'],
            $values,
        );
    }

    // ────── All metadata keys ──────

    public function testAllFiveMetadataKeysAreRecognized(): void
    {
        $model = $this->createModel();

        $metadataKeys = ['id_request', 'id_content', 'id_order', 'id_order_group', 'screenshot'];

        $attributes = [];
        $entries = [];
        foreach ($metadataKeys as $i => $key) {
            $attributes[$i] = $key;
            $entries[$i] = "val_$i";
        }

        $model->bulkInsertSegmentNotesAndMetadata([
            1 => $this->makeNote($attributes, $entries, [200]),
        ]);

        $noteIndices = $this->queryIndicesForTable('segment_notes');
        self::assertEmpty($noteIndices, 'All 5 metadata keys should bypass segment_notes');

        $metaIndices = $this->queryIndicesForTable('segment_metadata');
        self::assertCount(1, $metaIndices);
        $values = $this->executedValues[$metaIndices[0]];

        // 5 rows × 3 columns = 15 flattened values
        self::assertCount(15, $values);
        // Spot-check first and last row
        self::assertSame(200, $values[0]);
        self::assertSame('id_request', $values[1]);
        self::assertSame('val_0', $values[2]);
        self::assertSame(200, $values[12]);
        self::assertSame('screenshot', $values[13]);
        self::assertSame('val_4', $values[14]);
    }

    // ────── Mixed text + JSON in one note group ──────

    public function testTextAndJsonEntriesInSameGroupAreBothClassified(): void
    {
        $model = $this->createModel();

        $model->bulkInsertSegmentNotesAndMetadata([
            42 => $this->makeNote(
                [0 => 'comment'],                          // text → note
                [0 => 'note1'],
                [100],
                [0 => 'screenshot'],                       // json → metadata
                [0 => '{"url":"http://img.png"}'],
                [101],
            ),
        ]);

        // Text note goes to segment_notes
        $noteIndices = $this->queryIndicesForTable('segment_notes');
        self::assertCount(1, $noteIndices);
        $noteValues = $this->executedValues[$noteIndices[0]];
        self::assertSame([100, 42, 'note1', null], $noteValues);

        // JSON metadata goes to segment_metadata
        $metaIndices = $this->queryIndicesForTable('segment_metadata');
        self::assertCount(1, $metaIndices);
        $metaValues = $this->executedValues[$metaIndices[0]];
        self::assertSame([101, 'screenshot', '{"url":"http://img.png"}'], $metaValues);
    }
}
