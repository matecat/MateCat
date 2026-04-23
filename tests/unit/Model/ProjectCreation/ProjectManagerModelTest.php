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

    private int $originalBatchSleep;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalBatchSleep = ProjectManagerModel::$batchSleepMicroseconds;
        ProjectManagerModel::$batchSleepMicroseconds = 0;
    }

    protected function tearDown(): void
    {
        ProjectManagerModel::$batchSleepMicroseconds = $this->originalBatchSleep;
        parent::tearDown();
    }

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

    // ══════════════════════════════════════════════════════════════════
    //  deleteProject tests
    // ══════════════════════════════════════════════════════════════════

    /**
     * Creates a model whose mock PDO returns pre-configured rows for
     * the jobs SELECT and the files SELECT used by deleteProject().
     *
     * @param list<array{id: int, job_first_segment: int, job_last_segment: int}> $jobRows
     * @param list<int> $fileIds
     */
    private function createModelForDelete(array $jobRows = [], array $fileIds = []): ProjectManagerModel
    {
        $this->preparedQueries = [];
        $this->executedValues  = [];

        $lastQuery = '';

        $mockStmt = $this->createStub(PDOStatement::class);
        $mockStmt->method('execute')->willReturnCallback(
            function (array $values): bool {
                $this->executedValues[] = $values;
                return true;
            }
        );
        $mockStmt->method('fetchAll')->willReturnCallback(
            function () use (&$lastQuery, $jobRows, $fileIds): array {
                if (str_contains($lastQuery, 'FROM jobs')) {
                    return $jobRows;
                }
                if (str_contains($lastQuery, 'FROM files')) {
                    return $fileIds;
                }
                return [];
            }
        );

        $mockPdo = $this->createStub(PDO::class);
        $mockPdo->method('prepare')->willReturnCallback(
            function (string $query) use ($mockStmt, &$lastQuery): PDOStatement {
                $lastQuery = $query;
                $this->preparedQueries[] = $query;
                return $mockStmt;
            }
        );

        $mockDb = $this->createStub(IDatabase::class);
        $mockDb->method('getConnection')->willReturn($mockPdo);

        $mockLogger = $this->createStub(MatecatLogger::class);

        return new ProjectManagerModel($mockDb, $mockLogger);
    }

    /** Extracts the table name from a DELETE or SELECT query for ordering assertions. */
    private function extractTableSequence(): array
    {
        $tables = [];
        foreach ($this->preparedQueries as $query) {
            if (preg_match('/(?:DELETE FROM|FROM)\s+(\w+)/i', $query, $m)) {
                $tables[] = $m[1];
            }
        }
        return $tables;
    }

    public function testDeleteProjectDeletesAllTablesInCorrectOrder(): void
    {
        $model = $this->createModelForDelete(
            jobRows: [['id' => 10, 'job_first_segment' => 100, 'job_last_segment' => 200]],
            fileIds: [5],
        );

        $model->deleteProject(42);

        $tables = $this->extractTableSequence();

        // Expected order: jobs(SELECT), per-job deletes, segment-scoped deletes,
        // files(SELECT), file-scoped deletes, project-scoped deletes, root deletes
        $expected = [
            'jobs',                          // SELECT jobs
            'comments',                      // per-job
            'qa_chunk_reviews',
            'segment_translation_events',
            'segment_translation_versions',
            'segment_translations',          // batched (1 batch for 101 segments)
            'files_job',
            'job_metadata',
            'segment_metadata',              // segment-scoped (batched)
            'segment_notes',                 // batched
            'segment_original_data',         // batched
            'segments',                      // batched
            'files',                         // SELECT files
            'files_parts',                   // per-file
            'files',                         // DELETE files
            'file_references',               // by id_project
            'file_metadata',
            'context_groups',                // by id_project
            'project_metadata',
            'projects',
            'jobs',                          // DELETE jobs
        ];

        self::assertSame($expected, $tables);
    }

    public function testDeleteProjectPassesCorrectParametersForJobScopedDeletes(): void
    {
        $model = $this->createModelForDelete(
            jobRows: [['id' => 10, 'job_first_segment' => 100, 'job_last_segment' => 200]],
            fileIds: [],
        );

        $model->deleteProject(42);

        // executedValues[0] = jobs SELECT params
        self::assertSame(['id_project' => 42], $this->executedValues[0]);

        // executedValues[1..4] = comments, qa_chunk_reviews, segment_translation_events, segment_translation_versions
        for ($i = 1; $i <= 4; $i++) {
            self::assertSame(['id_job' => 10], $this->executedValues[$i], "Job-scoped delete at index $i");
        }

        // executedValues[5] = segment_translations batch (100-200 in one batch since 101 < 200)
        self::assertSame(
            ['id_job' => 10, 'start' => 100, 'end' => 200],
            $this->executedValues[5],
            'segment_translations batch params'
        );

        // executedValues[6] = files_job, executedValues[7] = job_metadata
        self::assertSame(['id_job' => 10], $this->executedValues[6]);
        self::assertSame(['id_job' => 10], $this->executedValues[7]);
    }

    public function testDeleteProjectPassesCorrectParametersForSegmentScopedDeletes(): void
    {
        $model = $this->createModelForDelete(
            jobRows: [['id' => 10, 'job_first_segment' => 100, 'job_last_segment' => 200]],
            fileIds: [],
        );

        $model->deleteProject(42);

        // After job-scoped (indices 0-7), segment-scoped start at index 8
        // segment_metadata, segment_notes, segment_original_data, segments
        for ($i = 8; $i <= 11; $i++) {
            self::assertSame(
                ['start' => 100, 'end' => 200],
                $this->executedValues[$i],
                "Segment-scoped delete at index $i"
            );
        }
    }

    public function testDeleteProjectWithNoJobsDeletesProjectAndFileLevelOnly(): void
    {
        $model = $this->createModelForDelete(
            jobRows: [],
            fileIds: [5, 8],
        );

        $model->deleteProject(42);

        $tables = $this->extractTableSequence();

        // No job-scoped or segment-scoped deletes
        $expected = [
            'jobs',                          // SELECT jobs (returns empty)
            'files',                         // SELECT files
            'files_parts',                   // file 5
            'files_parts',                   // file 8
            'files',                         // DELETE files
            'file_references',
            'file_metadata',
            'context_groups',
            'project_metadata',
            'projects',
            // no DELETE jobs (empty list)
        ];

        self::assertSame($expected, $tables);
    }

    public function testDeleteProjectBatchesLargeSegmentRanges(): void
    {
        $model = $this->createModelForDelete(
            jobRows: [['id' => 10, 'job_first_segment' => 1, 'job_last_segment' => 500]],
            fileIds: [],
        );

        $model->deleteProject(42, batchSize: 200);

        // segment_translations should produce 3 batches: 1-200, 201-400, 401-500
        $stIndices = $this->queryIndicesForTable('segment_translations');
        self::assertCount(3, $stIndices, 'segment_translations should have 3 batches');

        self::assertSame(
            ['id_job' => 10, 'start' => 1, 'end' => 200],
            $this->executedValues[$stIndices[0]]
        );
        self::assertSame(
            ['id_job' => 10, 'start' => 201, 'end' => 400],
            $this->executedValues[$stIndices[1]]
        );
        self::assertSame(
            ['id_job' => 10, 'start' => 401, 'end' => 500],
            $this->executedValues[$stIndices[2]]
        );

        // All segment-scoped tables should also produce 3 batches each
        foreach (['segment_metadata', 'segment_notes', 'segment_original_data'] as $table) {
            $indices = $this->queryIndicesForTable($table);
            $deleteIndices = array_values(array_filter($indices, function (int $i): bool {
                return str_starts_with(trim($this->preparedQueries[$i]), 'DELETE');
            }));
            self::assertCount(3, $deleteIndices, "$table should have 3 batches");

            self::assertSame(['start' => 1, 'end' => 200], $this->executedValues[$deleteIndices[0]]);
            self::assertSame(['start' => 201, 'end' => 400], $this->executedValues[$deleteIndices[1]]);
            self::assertSame(['start' => 401, 'end' => 500], $this->executedValues[$deleteIndices[2]]);
        }

        // segments should also produce 3 batches
        $segIndices = $this->queryIndicesForTable('segments');
        $segDeleteIndices = array_values(array_filter($segIndices, function (int $i): bool {
            return str_starts_with(trim($this->preparedQueries[$i]), 'DELETE');
        }));
        self::assertCount(3, $segDeleteIndices, 'segments should have 3 batches');

        self::assertSame(['start' => 1, 'end' => 200], $this->executedValues[$segDeleteIndices[0]]);
        self::assertSame(['start' => 201, 'end' => 400], $this->executedValues[$segDeleteIndices[1]]);
        self::assertSame(['start' => 401, 'end' => 500], $this->executedValues[$segDeleteIndices[2]]);
    }

    public function testDeleteProjectWithMultipleJobsUsesPerJobSegmentRange(): void
    {
        $model = $this->createModelForDelete(
            jobRows: [
                ['id' => 10, 'job_first_segment' => 100, 'job_last_segment' => 300],
                ['id' => 11, 'job_first_segment' => 301, 'job_last_segment' => 500],
            ],
            fileIds: [],
        );

        $model->deleteProject(42);

        // Segment-scoped deletes now iterate per-job, so each table appears twice
        // (once per job). With batchSize=200:
        //   Job 10: range 100-300 → batch1=100-299, batch2=300-300
        //   Job 11: range 301-500 → batch1=301-500
        foreach (['segment_metadata', 'segment_notes', 'segment_original_data'] as $table) {
            $indices = $this->queryIndicesForTable($table);
            $deleteIndices = array_values(array_filter($indices, function (int $i): bool {
                return str_starts_with(trim($this->preparedQueries[$i]), 'DELETE');
            }));
            self::assertCount(3, $deleteIndices, "$table should have 3 batches (2 for job 10, 1 for job 11)");

            // Job 10: 100-299, 300-300
            self::assertSame(
                ['start' => 100, 'end' => 299],
                $this->executedValues[$deleteIndices[0]],
                "$table job 10 batch 1"
            );
            self::assertSame(
                ['start' => 300, 'end' => 300],
                $this->executedValues[$deleteIndices[1]],
                "$table job 10 batch 2"
            );

            // Job 11: 301-500
            self::assertSame(
                ['start' => 301, 'end' => 500],
                $this->executedValues[$deleteIndices[2]],
                "$table job 11 batch 1"
            );
        }
    }

    public function testDeleteProjectWithNoFilesSkipsFilePartsDelete(): void
    {
        $model = $this->createModelForDelete(
            jobRows: [['id' => 10, 'job_first_segment' => 1, 'job_last_segment' => 50]],
            fileIds: [],
        );

        $model->deleteProject(42);

        $fpIndices = $this->queryIndicesForTable('files_parts');
        self::assertEmpty($fpIndices, 'No files_parts DELETE when project has no files');
    }

    public function testDeleteProjectWithNonContiguousJobsDoesNotSpanGap(): void
    {
        // Jobs with a gap: 100-200 and 500-600.  Segments 201-499 belong to other projects.
        $model = $this->createModelForDelete(
            jobRows: [
                ['id' => 10, 'job_first_segment' => 100, 'job_last_segment' => 200],
                ['id' => 11, 'job_first_segment' => 500, 'job_last_segment' => 600],
            ],
            fileIds: [],
        );

        $model->deleteProject(42);

        // Verify segment_metadata has exactly 2 batches — one per job, no merged super-range
        $indices = $this->queryIndicesForTable('segment_metadata');
        $deleteIndices = array_values(array_filter($indices, function (int $i): bool {
            return str_starts_with(trim($this->preparedQueries[$i]), 'DELETE');
        }));
        self::assertCount(2, $deleteIndices, 'segment_metadata should have 2 batches (one per job)');

        // Job 10: 100-200
        self::assertSame(['start' => 100, 'end' => 200], $this->executedValues[$deleteIndices[0]]);
        // Job 11: 500-600
        self::assertSame(['start' => 500, 'end' => 600], $this->executedValues[$deleteIndices[1]]);

        // Crucially, no batch should cover the gap 201-499
        foreach ($deleteIndices as $idx) {
            $params = $this->executedValues[$idx];
            self::assertFalse(
                $params['start'] <= 201 && $params['end'] >= 499,
                'Segment delete must not span the gap between non-contiguous jobs'
            );
        }
    }

    public function testDeleteProjectThrowsOnInvalidBatchSize(): void
    {
        $model = $this->createModelForDelete(
            jobRows: [['id' => 10, 'job_first_segment' => 1, 'job_last_segment' => 50]],
            fileIds: [],
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('batchSize must be >= 1, got 0');

        $model->deleteProject(42, batchSize: 0);
    }

    public function testDeleteProjectThrowsOnNegativeBatchSize(): void
    {
        $model = $this->createModelForDelete(
            jobRows: [],
            fileIds: [],
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('batchSize must be >= 1, got -5');

        $model->deleteProject(42, batchSize: -5);
    }

    public function testDeleteProjectDeletesContextGroupsByProject(): void
    {
        $model = $this->createModelForDelete(
            jobRows: [['id' => 10, 'job_first_segment' => 1, 'job_last_segment' => 50]],
            fileIds: [],
        );

        $model->deleteProject(42);

        // context_groups should be deleted via WHERE id_project (Phase 3), not BETWEEN segment range
        $indices = $this->queryIndicesForTable('context_groups');
        self::assertCount(1, $indices, 'context_groups should appear exactly once');

        $query = $this->preparedQueries[$indices[0]];
        self::assertStringContainsString('id_project', $query, 'context_groups must be deleted by id_project');
        self::assertStringNotContainsString('BETWEEN', $query, 'context_groups must NOT use BETWEEN segment range');

        self::assertSame(['id_project' => 42], $this->executedValues[$indices[0]]);
    }

    public function testDeleteProjectDeletesFileReferences(): void
    {
        $model = $this->createModelForDelete(
            jobRows: [['id' => 10, 'job_first_segment' => 1, 'job_last_segment' => 50]],
            fileIds: [5],
        );

        $model->deleteProject(42);

        $indices = $this->queryIndicesForTable('file_references');
        self::assertCount(1, $indices, 'file_references should appear exactly once');

        $query = $this->preparedQueries[$indices[0]];
        self::assertStringContainsString('DELETE FROM file_references', $query);
        self::assertSame(['id_project' => 42], $this->executedValues[$indices[0]]);
    }
}
