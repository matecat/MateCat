<?php

declare(strict_types=1);

namespace Matecat\Core\DAO\TestSegmentNoteDAO;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Segments\SegmentNoteDao;
use Model\Segments\SegmentNoteStruct;
use PDO;
use PDOStatement;
use Utils\Registry\AppConfig;

class SegmentNoteDaoTest extends AbstractTest
{
    private \PHPUnit\Framework\MockObject\Stub&IDatabase $dbStub;
    private \PHPUnit\Framework\MockObject\Stub&PDO $pdoStub;
    private \PHPUnit\Framework\MockObject\Stub&PDOStatement $stmtStub;

    protected function setUp(): void
    {
        parent::setUp();

        AppConfig::$SKIP_SQL_CACHE = true;

        [$this->dbStub, $this->pdoStub, $this->stmtStub] = $this->createDatabaseMock();
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();

        AppConfig::$SKIP_SQL_CACHE = false;

        parent::tearDown();
    }

    private function makeSegmentNoteStruct(array $overrides = []): SegmentNoteStruct
    {
        $note = new SegmentNoteStruct();
        $note->id = $overrides['id'] ?? 1;
        $note->id_segment = $overrides['id_segment'] ?? 100;
        $note->internal_id = $overrides['internal_id'] ?? null;
        $note->note = $overrides['note'] ?? 'A test note';
        $note->json = $overrides['json'] ?? null;

        return $note;
    }


    // ── getBySegmentId ──────────────────────────────────────────────────────

    public function testGetBySegmentIdReturnsArrayOfStructs(): void
    {
        $note = $this->makeSegmentNoteStruct(['id_segment' => 42]);

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$note]);

        $dao = new SegmentNoteDao($this->dbStub);
        $result = $dao->getBySegmentId(42);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(SegmentNoteStruct::class, $result[0]);
        $this->assertSame(42, $result[0]->id_segment);
    }

    public function testGetBySegmentIdReturnsEmptyArrayWhenNoNotes(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new SegmentNoteDao($this->dbStub);
        $result = $dao->getBySegmentId(999);

        $this->assertSame([], $result);
    }

    public function testGetBySegmentIdRespectsCustomTtl(): void
    {
        $note = $this->makeSegmentNoteStruct(['id_segment' => 10]);

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$note]);

        $dao = new SegmentNoteDao($this->dbStub);
        $result = $dao->getBySegmentId(10, 3600);

        $this->assertCount(1, $result);
    }


    // ── getBySegmentIds ─────────────────────────────────────────────────────

    public function testGetBySegmentIdsReturnsStructsForMultipleIds(): void
    {
        $note1 = $this->makeSegmentNoteStruct(['id' => 1, 'id_segment' => 10]);
        $note2 = $this->makeSegmentNoteStruct(['id' => 2, 'id_segment' => 11]);

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$note1, $note2]);

        $dao = new SegmentNoteDao($this->dbStub);
        $result = $dao->getBySegmentIds([10, 11]);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(SegmentNoteStruct::class, $result[0]);
    }

    public function testGetBySegmentIdsReturnsEmptyArrayWhenNoNotes(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new SegmentNoteDao($this->dbStub);
        $result = $dao->getBySegmentIds([100, 200, 300]);

        $this->assertSame([], $result);
    }

    public function testGetBySegmentIdsReturnsReindexedArray(): void
    {
        $note1 = $this->makeSegmentNoteStruct(['id' => 5, 'id_segment' => 50]);
        $note2 = $this->makeSegmentNoteStruct(['id' => 6, 'id_segment' => 51]);

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$note1, $note2]);

        $dao = new SegmentNoteDao($this->dbStub);
        $result = $dao->getBySegmentIds([50, 51]);

        $this->assertArrayHasKey(0, $result);
        $this->assertArrayHasKey(1, $result);
    }


    // ── getAggregatedBySegmentIdInInterval ──────────────────────────────────

    public function testGetAggregatedBySegmentIdInIntervalReturnsGroupedArray(): void
    {
        $flatRows = [
            ['id_segment' => 100, 'id' => 1, 'note' => 'First note'],
            ['id_segment' => 101, 'id' => 2, 'note' => 'Second note'],
        ];

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn($flatRows);

        $dao = new SegmentNoteDao($this->dbStub);
        $result = $dao->getAggregatedBySegmentIdInInterval(100, 101);

        $this->assertIsArray($result);
        $this->assertArrayHasKey(100, $result);
        $this->assertArrayHasKey(101, $result);
        $this->assertSame('First note', $result[100][0]['note']);
    }

    public function testGetAggregatedBySegmentIdInIntervalReturnsEmptyWhenNoNotes(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new SegmentNoteDao($this->dbStub);
        $result = $dao->getAggregatedBySegmentIdInInterval(500, 600);

        $this->assertSame([], $result);
    }


    // ── getAllAggregatedBySegmentIdInInterval ───────────────────────────────

    public function testGetAllAggregatedBySegmentIdInIntervalReturnsGroupedArrayWithJson(): void
    {
        $flatRows = [
            ['id_segment' => 200, 'id' => 3, 'note' => null, 'json' => '{"key":"val"}'],
            ['id_segment' => 201, 'id' => 4, 'note' => 'Plain note', 'json' => null],
        ];

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn($flatRows);

        $dao = new SegmentNoteDao($this->dbStub);
        $result = $dao->getAllAggregatedBySegmentIdInInterval(200, 201);

        $this->assertIsArray($result);
        $this->assertArrayHasKey(200, $result);
        $this->assertArrayHasKey(201, $result);
        $this->assertSame('{"key":"val"}', $result[200][0]['json']);
    }

    public function testGetAllAggregatedBySegmentIdInIntervalReturnsEmptyWhenNoNotes(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new SegmentNoteDao($this->dbStub);
        $result = $dao->getAllAggregatedBySegmentIdInInterval(700, 800);

        $this->assertSame([], $result);
    }


    // ── getJsonNotesByRange ─────────────────────────────────────────────────

    public function testGetJsonNotesByRangeReturnsArrayOfStructs(): void
    {
        $note = $this->makeSegmentNoteStruct(['id_segment' => 300, 'note' => null, 'json' => '{"tag":"value"}']);

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$note]);

        $dao = new SegmentNoteDao($this->dbStub);
        $result = $dao->getJsonNotesByRange(300, 350);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(SegmentNoteStruct::class, $result[0]);
    }

    public function testGetJsonNotesByRangeReturnsEmptyArrayWhenNoJsonNotes(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new SegmentNoteDao($this->dbStub);
        $result = $dao->getJsonNotesByRange(400, 500);

        $this->assertSame([], $result);
    }

    public function testGetJsonNotesByRangeRespectsCustomTtl(): void
    {
        $note = $this->makeSegmentNoteStruct(['id_segment' => 310, 'json' => '{}']);

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$note]);

        $dao = new SegmentNoteDao($this->dbStub);
        $result = $dao->getJsonNotesByRange(310, 320, 7200);

        $this->assertCount(1, $result);
    }
}
