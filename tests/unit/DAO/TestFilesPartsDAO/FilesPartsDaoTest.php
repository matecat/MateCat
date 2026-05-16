<?php

namespace unit\DAO\TestFilesPartsDAO;

use Model\DataAccess\IDatabase;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Files\FilesPartsDao;
use Model\Files\FilesPartsStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class TestFilesPartsDao extends FilesPartsDao
{
    public array $fetchResult = [];

    protected function _fetchObjectMap(PDOStatement $stmt, string $fetchClass, array $bindParams = [], ?string $keyMap = null): array
    {
        $stmt->execute($bindParams);
        return $this->fetchResult;
    }
}

class FilesPartsDaoTest extends AbstractTest
{
    private function makeStubDb(PDOStatement $stmt): IDatabase
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $db = $this->createStub(IDatabase::class);
        $db->method('getConnection')->willReturn($pdo);

        return $db;
    }

    #[Test]
    public function insert_returns_zero_when_no_row_affected(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(0);

        $dao = new FilesPartsDao($this->makeStubDb($stmt));
        $struct = new FilesPartsStruct();
        $struct->id_file = 1;
        $struct->tag_key = 'key';
        $struct->tag_value = 'value';

        $this->assertSame(0, $dao->insert($struct));
    }

    #[Test]
    public function insert_returns_last_insert_id_when_row_inserted(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(1);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('lastInsertId')->willReturn('42');

        $db = $this->createStub(IDatabase::class);
        $db->method('getConnection')->willReturn($pdo);

        $dao = new FilesPartsDao($db);
        $struct = new FilesPartsStruct();
        $struct->id_file = 1;
        $struct->tag_key = 'key';
        $struct->tag_value = 'value';

        $this->assertSame(42, $dao->insert($struct));
    }

    #[Test]
    public function getByFileId_returns_empty_array_when_no_results(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with(['fileId' => 99]);

        $dao = new TestFilesPartsDao($this->makeStubDb($stmt));
        $dao->fetchResult = [];

        $this->assertSame([], $dao->getByFileId(99));
    }

    #[Test]
    public function getByFileId_returns_structs_when_results_exist(): void
    {
        $expected = new FilesPartsStruct();
        $expected->id = 1;
        $expected->id_file = 99;

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with(['fileId' => 99]);

        $dao = new TestFilesPartsDao($this->makeStubDb($stmt));
        $dao->fetchResult = [$expected];

        $result = $dao->getByFileId(99);

        $this->assertCount(1, $result);
        $this->assertSame($expected, $result[0]);
    }

    #[Test]
    public function getFirstAndLastSegment_returns_null_when_no_results(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with(['id' => 5]);

        $dao = new TestFilesPartsDao($this->makeStubDb($stmt));
        $dao->fetchResult = [];

        $this->assertNull($dao->getFirstAndLastSegment(5));
    }

    #[Test]
    public function getFirstAndLastSegment_returns_struct_when_result_exists(): void
    {
        $expected = new ShapelessConcreteStruct();
        $expected->first_segment = 10;
        $expected->last_segment = 20;
        $expected->id = 5;

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with(['id' => 5]);

        $dao = new TestFilesPartsDao($this->makeStubDb($stmt));
        $dao->fetchResult = [$expected];

        $this->assertSame($expected, $dao->getFirstAndLastSegment(5));
    }

    #[Test]
    public function getBySegmentId_returns_null_when_no_results(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with(['segmentId' => 7]);

        $dao = new TestFilesPartsDao($this->makeStubDb($stmt));
        $dao->fetchResult = [];

        $this->assertNull($dao->getBySegmentId(7));
    }

    #[Test]
    public function getBySegmentId_returns_struct_when_result_exists(): void
    {
        $expected = new FilesPartsStruct();
        $expected->id = 3;
        $expected->id_file = 7;
        $expected->tag_key = 'foo';
        $expected->tag_value = 'bar';

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with(['segmentId' => 7]);

        $dao = new TestFilesPartsDao($this->makeStubDb($stmt));
        $dao->fetchResult = [$expected];

        $this->assertSame($expected, $dao->getBySegmentId(7));
    }
}
