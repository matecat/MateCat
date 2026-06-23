<?php

namespace Matecat\Core\Model\Files;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Files\MetadataDao;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;

class MetadataDaoInjectedDbGuardTest extends AbstractTest
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function queriesUseInjectedDatabaseNotSingleton(): void
    {
        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->queryString = '';
        $stmtStub->method('execute')->willReturn(true);
        $stmtStub->method('rowCount')->willReturn(0);
        $stmtStub->method('fetch')->willReturn(false);
        $stmtStub->method('fetchAll')->willReturn([]);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        $db = $this->createMock(IDatabase::class);
        $db->expects($this->atLeastOnce())
            ->method('getConnection')
            ->willReturn($pdoStub);

        // Poison the singleton: it must NEVER be touched. Any Database::obtain()
        // fallback (full revert OR a partial/mixed path) hits this mock and trips
        // the never() expectation — a clean, deterministic failure that does not
        // depend on the real test DB schema. setDatabaseInstance() (not raw
        // reflection) sets the reset flag so tearDown restores the real DB.
        $poison = $this->createMock(IDatabase::class);
        $poison->expects($this->never())->method('getConnection');
        $this->setDatabaseInstance($poison);

        $dao = new MetadataDao($db);
        $dao->insert(1, 1, 'k', 'v');
    }
}
