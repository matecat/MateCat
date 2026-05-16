<?php

namespace unit\DAO\TestFileDAO;

use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use Model\Files\FileDao;
use Model\Files\FileStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;

class FileDaoTest extends AbstractTest
{
    private IDatabase $dbStub;
    private \PDO $pdoStub;
    private PDOStatement $stmtStub;

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

    #[Test]
    public function getByJobIdReturnsFileStructs(): void
    {
        $struct = new FileStruct();
        $struct->id = 1;
        $struct->id_project = 10;
        $struct->filename = 'test.xliff';

        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $results = FileDao::getByJobId(5);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertInstanceOf(FileStruct::class, $results[0]);
    }

    #[Test]
    public function getByJobIdReturnsEmptyArray(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $results = FileDao::getByJobId(999);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    #[Test]
    public function getByJobIdWithCustomTtl(): void
    {
        $struct = new FileStruct();
        $struct->id = 2;
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $results = FileDao::getByJobId(5, 3600);

        $this->assertCount(1, $results);
    }

    #[Test]
    public function getByProjectIdReturnsFileStructs(): void
    {
        $struct1 = new FileStruct();
        $struct1->id = 1;
        $struct2 = new FileStruct();
        $struct2->id = 2;

        $this->stmtStub->method('fetchAll')->willReturn([$struct1, $struct2]);

        $results = FileDao::getByProjectId(10);

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
    }

    #[Test]
    public function getByProjectIdReturnsEmptyArray(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $results = FileDao::getByProjectId(999);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    #[Test]
    public function getByProjectIdWithCustomTtl(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $results = FileDao::getByProjectId(10, 1200);

        $this->assertIsArray($results);
    }

    #[Test]
    public function updateFieldReturnsTrue(): void
    {
        $file = new FileStruct();
        $file->id = 1;
        $file->id_project = 10;
        $file->filename = 'test.xliff';

        $this->stmtStub->method('execute')->willReturn(true);

        $result = FileDao::updateField($file, 'filename', 'renamed.xliff');

        $this->assertTrue($result);
    }

    #[Test]
    public function updateFieldWithNullValue(): void
    {
        $file = new FileStruct();
        $file->id = 1;
        $file->id_project = 10;
        $file->filename = 'test.xliff';

        $this->stmtStub->method('execute')->willReturn(true);

        $result = FileDao::updateField($file, 'sha1_original_file', null);

        $this->assertTrue($result);
    }

    #[Test]
    public function updateFieldWithIntValue(): void
    {
        $file = new FileStruct();
        $file->id = 1;
        $file->id_project = 10;
        $file->filename = 'test.xliff';

        $this->stmtStub->method('execute')->willReturn(true);

        $result = FileDao::updateField($file, 'id_project', 20);

        $this->assertTrue($result);
    }

    #[Test]
    public function isFileInProjectReturnsRowCount(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);

        $result = FileDao::isFileInProject(5, 10);

        $this->assertSame(1, $result);
    }

    #[Test]
    public function isFileInProjectReturnsZeroWhenNotFound(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(0);

        $result = FileDao::isFileInProject(999, 10);

        $this->assertSame(0, $result);
    }

    #[Test]
    public function getByIdReturnsFileStruct(): void
    {
        $struct = new FileStruct();
        $struct->id = 5;
        $struct->id_project = 10;
        $struct->filename = 'doc.xliff';

        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $result = FileDao::getById(5);

        $this->assertInstanceOf(FileStruct::class, $result);
        $this->assertSame(5, $result->id);
    }

    #[Test]
    public function getByIdReturnsNullWhenNotFound(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $result = FileDao::getById(999);

        $this->assertNull($result);
    }

    #[Test]
    public function getByIdWithCustomTtl(): void
    {
        $struct = new FileStruct();
        $struct->id = 3;
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $result = FileDao::getById(3, 120);

        $this->assertInstanceOf(FileStruct::class, $result);
    }

    #[Test]
    public function deleteFailedProjectFilesReturnsZeroForEmptyArray(): void
    {
        $dao = new FileDao();
        $result = $dao->deleteFailedProjectFiles([]);

        $this->assertSame(0, $result);
    }

    #[Test]
    public function deleteFailedProjectFilesReturnsRowCount(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(3);

        $dao = new FileDao();
        $result = $dao->deleteFailedProjectFiles([1, 2, 3]);

        $this->assertSame(3, $result);
    }

    #[Test]
    public function deleteFailedProjectFilesWithSingleId(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);

        $dao = new FileDao();
        $result = $dao->deleteFailedProjectFiles([42]);

        $this->assertSame(1, $result);
    }

    #[Test]
    public function insertFilesJobCallsInsert(): void
    {
        $dbMock = $this->createMock(Database::class);
        $dbMock->method('getConnection')->willReturn($this->pdoStub);
        $dbMock->expects($this->once())
            ->method('insert')
            ->with('files_job', ['id_job' => 5, 'id_file' => 10])
            ->willReturn('1');

        $this->setDatabaseInstance($dbMock);

        FileDao::insertFilesJob(5, 10);
    }
}
