<?php

namespace Matecat\Core\Model\TranslationsSplit;

use Model\TranslationsSplit\SegmentSplitStruct;
use Model\TranslationsSplit\SplitDAO;
use PHPUnit\Framework\Attributes\Group;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;

/**
 * Real-SQL coverage for SplitDAO (plan dao-realsql-90.md, Wave 6 / T15).
 *
 * Every public SQL method is called DIRECTLY and asserted on real returned data (DoD b):
 *   read, atomicUpdate, sanitize.
 *
 * SplitDAO touches only segment_translations_splits (assignable composite PK, no FK). Single
 * per-test connection for DAO + builder + cleanup (C-2); no wrapping transaction (C-1);
 * whole-table residue gate (A-1). Ids assigned >= 1.9e9 by the builder for the seed rows;
 * atomicUpdate rows are tracked via trackExisting so the residue gate returns to baseline.
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class SplitDAORealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private const array TABLE_DEPS = ['segment_translations_splits'];

    private SplitDAO $dao;

    /** Assignable ids above the seed band (M-2) for the composite PK of this table. */
    private int $idSegment;
    private int $idJob;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertDbWriteGuard();
        $this->startRealSql(self::TABLE_DEPS);

        $this->dao = new SplitDAO($this->realSqlDb());
        $this->assertDaoUsesTestConnection($this->dao);

        $this->idSegment = $this->fixtures->nextAssignableId();
        $this->idJob = $this->fixtures->nextAssignableId();
    }

    protected function tearDown(): void
    {
        $this->finishRealSql();
        parent::tearDown();
    }

    public function testReadReturnsDecodedChunkLengths(): void
    {
        $this->fixtures->makeSegmentTranslationsSplit($this->idSegment, $this->idJob, [
            'source_chunk_lengths' => '[3,4]',
            'target_chunk_lengths' => '{"len":[3,4],"statuses":["DRAFT","DRAFT"]}',
        ]);

        $obj = new SegmentSplitStruct();
        $obj->id_segment = $this->idSegment;
        $obj->id_job = $this->idJob;

        $rows = $this->dao->read($obj);

        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertInstanceOf(SegmentSplitStruct::class, $row);
        $this->assertSame($this->idSegment, (int)$row->id_segment);
        $this->assertSame($this->idJob, (int)$row->id_job);
        // _buildResult json_decodes the stored strings back to arrays
        $this->assertSame([3, 4], $row->source_chunk_lengths);
        $this->assertSame(['len' => [3, 4], 'statuses' => ['DRAFT', 'DRAFT']], $row->target_chunk_lengths);
    }

    public function testReadReturnsEmptyWhenAbsent(): void
    {
        $obj = new SegmentSplitStruct();
        $obj->id_segment = $this->idSegment;
        $obj->id_job = $this->idJob;

        $this->assertSame([], $this->dao->read($obj));
    }

    public function testSanitizeJsonEncodesArrayChunkLengths(): void
    {
        $obj = new SegmentSplitStruct();
        $obj->id_segment = $this->idSegment;
        $obj->id_job = $this->idJob;
        $obj->source_chunk_lengths = [5, 6];
        $obj->target_chunk_lengths = ['len' => [5, 6], 'statuses' => ['DRAFT', 'DRAFT']];

        $sanitized = $this->dao->sanitize($obj);

        $this->assertSame('[5,6]', $sanitized->source_chunk_lengths);
        $this->assertSame('{"len":[5,6],"statuses":["DRAFT","DRAFT"]}', $sanitized->target_chunk_lengths);
    }

    public function testAtomicUpdateInsertsNewRow(): void
    {
        $obj = new SegmentSplitStruct();
        $obj->id_segment = $this->idSegment;
        $obj->id_job = $this->idJob;
        $obj->source_chunk_lengths = [2, 8];
        $obj->target_chunk_lengths = ['len' => [2, 8], 'statuses' => ['DRAFT', 'TRANSLATED']];

        $result = $this->dao->atomicUpdate($obj);
        // DAO INSERTs through its own SQL: register for residue-gate cleanup.
        $this->fixtures->trackExisting('segment_translations_splits', [
            'id_segment' => $this->idSegment,
            'id_job'     => $this->idJob,
        ]);

        $this->assertInstanceOf(SegmentSplitStruct::class, $result);

        // round-trip read confirms the row persisted with the encoded payload
        $readBack = new SegmentSplitStruct();
        $readBack->id_segment = $this->idSegment;
        $readBack->id_job = $this->idJob;
        $rows = $this->dao->read($readBack);
        $this->assertCount(1, $rows);
        $this->assertSame([2, 8], $rows[0]->source_chunk_lengths);
        $this->assertSame(['len' => [2, 8], 'statuses' => ['DRAFT', 'TRANSLATED']], $rows[0]->target_chunk_lengths);
    }

    public function testAtomicUpdateReturnsNullWhenNoRowAffected(): void
    {
        // seed a row, then upsert the IDENTICAL payload: MySQL reports 0 affected rows for an
        // ON DUPLICATE KEY UPDATE that changes nothing, so atomicUpdate() returns null.
        $this->fixtures->makeSegmentTranslationsSplit($this->idSegment, $this->idJob, [
            'source_chunk_lengths' => '[5,7]',
            'target_chunk_lengths' => '{"len":[5,7],"statuses":["DRAFT","DRAFT"]}',
        ]);

        $obj = new SegmentSplitStruct();
        $obj->id_segment = $this->idSegment;
        $obj->id_job = $this->idJob;
        $obj->source_chunk_lengths = [5, 7];
        $obj->target_chunk_lengths = ['len' => [5, 7], 'statuses' => ['DRAFT', 'DRAFT']];

        $this->assertNull($this->dao->atomicUpdate($obj));
    }

    public function testAtomicUpdateUpdatesExistingRowOnDuplicateKey(): void
    {
        // seed a row, then upsert the SAME composite key with new chunk lengths
        $this->fixtures->makeSegmentTranslationsSplit($this->idSegment, $this->idJob, [
            'source_chunk_lengths' => '[1,1]',
            'target_chunk_lengths' => '{"len":[1,1],"statuses":["DRAFT","DRAFT"]}',
        ]);

        $obj = new SegmentSplitStruct();
        $obj->id_segment = $this->idSegment;
        $obj->id_job = $this->idJob;
        $obj->source_chunk_lengths = [9, 9];
        $obj->target_chunk_lengths = ['len' => [9, 9], 'statuses' => ['APPROVED', 'APPROVED']];

        $result = $this->dao->atomicUpdate($obj);
        $this->assertInstanceOf(SegmentSplitStruct::class, $result);

        // exactly one row still exists for the key, with updated content
        $readBack = new SegmentSplitStruct();
        $readBack->id_segment = $this->idSegment;
        $readBack->id_job = $this->idJob;
        $rows = $this->dao->read($readBack);
        $this->assertCount(1, $rows);
        $this->assertSame([9, 9], $rows[0]->source_chunk_lengths);
    }
}
