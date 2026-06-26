<?php

namespace Matecat\Core\Model\Files;

use Matecat\TestHelpers\AbstractTest;
use Model\Files\FileDao;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use Utils\Registry\AppConfig;

class FileDaoDeleteTest extends AbstractTest
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

    #[Test]
    public function deleteFailedProjectFilesReturnsZeroForEmptyArray(): void
    {
        $dao = new FileDao(\Model\DataAccess\Database::obtain());
        $result = $dao->deleteFailedProjectFiles([]);

        $this->assertSame(0, $result);
    }

    #[Test]
    public function deleteFailedProjectFilesReturnsRowCount(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(3);

        $dao = new FileDao(\Model\DataAccess\Database::obtain());
        $result = $dao->deleteFailedProjectFiles([1, 2, 3]);

        $this->assertSame(3, $result);
    }

    #[Test]
    public function deleteFailedProjectFilesWithSingleId(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);

        $dao = new FileDao(\Model\DataAccess\Database::obtain());
        $result = $dao->deleteFailedProjectFiles([42]);

        $this->assertSame(1, $result);
    }
}
