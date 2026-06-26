<?php

namespace Matecat\Core\Model\Files;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Files\FilesPartsDao;
use Model\Files\FilesPartsStruct;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use Utils\Registry\AppConfig;

class FilesPartsDaoTest extends AbstractTest
{
    private \PDO $pdoStub;
    private PDOStatement $stmtStub;

    protected function setUp(): void
    {
        parent::setUp();
        AppConfig::$SKIP_SQL_CACHE = true;

        [, $this->pdoStub, $this->stmtStub] = $this->createDatabaseMock();
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
    }

    // ─── insert() ───

    #[Test]
    public function insertReturnsLastInsertId(): void
    {
        $struct = new FilesPartsStruct();
        $struct->id_file = 10;
        $struct->tag_key = 'chapter';
        $struct->tag_value = 'Chapter 1';

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);
        $this->pdoStub->method('lastInsertId')->willReturn('42');

        $dao = new FilesPartsDao(\Model\DataAccess\Database::obtain());
        $result = $dao->insert($struct);

        $this->assertSame(42, $result);
    }

    #[Test]
    public function insertReturnsZeroOnNoRowInserted(): void
    {
        $struct = new FilesPartsStruct();
        $struct->id_file = 10;
        $struct->tag_key = 'chapter';
        $struct->tag_value = 'Chapter 1';

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(0);

        $dao = new FilesPartsDao(\Model\DataAccess\Database::obtain());
        $result = $dao->insert($struct);

        $this->assertSame(0, $result);
    }

    // ─── getFirstAndLastSegment() ───

    #[Test]
    public function getFirstAndLastSegmentReturnsStruct(): void
    {
        $struct = new ShapelessConcreteStruct();
        $struct->first_segment = '1';
        $struct->last_segment = '50';
        $struct->id = '5';

        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new FilesPartsDao(\Model\DataAccess\Database::obtain());
        $result = $dao->getFirstAndLastSegment(5);

        $this->assertInstanceOf(ShapelessConcreteStruct::class, $result);
    }

    #[Test]
    public function getFirstAndLastSegmentReturnsNullWhenNotFound(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new FilesPartsDao(\Model\DataAccess\Database::obtain());
        $result = $dao->getFirstAndLastSegment(999);

        $this->assertNull($result);
    }

    // ─── getByFileId() ───

    #[Test]
    public function getByFileIdReturnsStructs(): void
    {
        $struct = new FilesPartsStruct();
        $struct->id = 1;
        $struct->id_file = 10;
        $struct->tag_key = 'chapter';
        $struct->tag_value = 'Ch1';

        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new FilesPartsDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getByFileId(10);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertInstanceOf(FilesPartsStruct::class, $results[0]);
    }

    #[Test]
    public function getByFileIdReturnsEmptyArray(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new FilesPartsDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getByFileId(999);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    // ─── getBySegmentId() ───

    #[Test]
    public function getBySegmentIdReturnsStruct(): void
    {
        $struct = new FilesPartsStruct();
        $struct->id = 3;
        $struct->id_file = 10;

        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new FilesPartsDao(\Model\DataAccess\Database::obtain());
        $result = $dao->getBySegmentId(100);

        $this->assertInstanceOf(FilesPartsStruct::class, $result);
    }

    #[Test]
    public function getBySegmentIdReturnsNullWhenNotFound(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new FilesPartsDao(\Model\DataAccess\Database::obtain());
        $result = $dao->getBySegmentId(999);

        $this->assertNull($result);
    }
}
