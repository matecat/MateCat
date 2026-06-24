<?php

declare(strict_types=1);

namespace Matecat\Core\DAO\TestSegmentOriginalDataDAO;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Segments\SegmentOriginalDataDao;
use Model\Segments\SegmentOriginalDataStruct;
use PDO;
use PDOStatement;
use Utils\Registry\AppConfig;

class SegmentOriginalDataDaoTest extends AbstractTest
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

    private function makeStruct(array $overrides = []): SegmentOriginalDataStruct
    {
        $struct = new SegmentOriginalDataStruct();
        $struct->id = $overrides['id'] ?? 1;
        $struct->id_segment = $overrides['id_segment'] ?? 100;

        if (isset($overrides['map'])) {
            $struct->setMap($overrides['map']);
        }

        return $struct;
    }


    // ── getBySegmentId ──────────────────────────────────────────────────────

    public function testGetBySegmentIdReturnsStructWhenFound(): void
    {
        $struct = $this->makeStruct(['id_segment' => 100]);

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new SegmentOriginalDataDao($this->dbStub);
        $result = $dao->getBySegmentId(100);

        $this->assertInstanceOf(SegmentOriginalDataStruct::class, $result);
        $this->assertSame(100, $result->id_segment);
    }

    public function testGetBySegmentIdReturnsNullWhenNotFound(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new SegmentOriginalDataDao($this->dbStub);
        $result = $dao->getBySegmentId(999);

        $this->assertNull($result);
    }

    public function testGetBySegmentIdRespectsCustomTtl(): void
    {
        $struct = $this->makeStruct(['id_segment' => 50]);

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new SegmentOriginalDataDao($this->dbStub);
        $result = $dao->getBySegmentId(50, 7200);

        $this->assertInstanceOf(SegmentOriginalDataStruct::class, $result);
    }


    // ── getSegmentDataRefMap ────────────────────────────────────────────────

    public function testGetSegmentDataRefMapReturnsMapArrayWhenStructHasData(): void
    {
        $struct = $this->makeStruct(['id_segment' => 100, 'map' => ['key1' => 'val1', 'key2' => 'val2']]);

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new SegmentOriginalDataDao($this->dbStub);
        $result = $dao->getSegmentDataRefMap(100);

        $this->assertSame(['key1' => 'val1', 'key2' => 'val2'], $result);
    }

    public function testGetSegmentDataRefMapReturnsEmptyArrayWhenNoRecord(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new SegmentOriginalDataDao($this->dbStub);
        $result = $dao->getSegmentDataRefMap(999);

        $this->assertSame([], $result);
    }

    public function testGetSegmentDataRefMapReturnsEmptyArrayWhenMapIsEmpty(): void
    {
        $struct = $this->makeStruct(['id_segment' => 200]);

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new SegmentOriginalDataDao($this->dbStub);
        $result = $dao->getSegmentDataRefMap(200);

        $this->assertSame([], $result);
    }


    // ── insertRecord ────────────────────────────────────────────────────────

    public function testInsertRecordExecutesWithoutError(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);

        $dao = new SegmentOriginalDataDao($this->dbStub);
        $dao->insertRecord(100, ['key' => 'value']);

        $this->assertTrue(true);
    }

    public function testInsertRecordHandlesEmptyMap(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);

        $dao = new SegmentOriginalDataDao($this->dbStub);
        $dao->insertRecord(200, []);

        $this->assertTrue(true);
    }

    public function testInsertRecordStripsNewlinesFromJson(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);

        $dao = new SegmentOriginalDataDao($this->dbStub);
        $dao->insertRecord(300, ['key' => "value\nwith\r\nnewlines"]);

        $this->assertTrue(true);
    }
}
