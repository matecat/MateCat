<?php

namespace Matecat\Core\Model\Files;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use Model\Files\MetadataDao;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

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

        // Poison the singleton so any Database::obtain() call would return a
        // different object — proving the DAO uses the injected $db instead.
        $poisonPdo = $this->createStub(PDO::class);
        $poisonDb  = $this->createStub(IDatabase::class);
        $poisonDb->method('getConnection')->willReturn($poisonPdo);

        $ref  = new ReflectionClass(Database::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, $poisonDb);

        $dao = new MetadataDao($db);
        $dao->insert(1, 1, 'k', 'v');
    }
}
