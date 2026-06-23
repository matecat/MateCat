<?php

namespace Matecat\Core\Search;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Search\MySQLReplaceEventIndexDao;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;

class MySQLReplaceEventIndexDaoInjectedDbGuardTest extends AbstractTest
{
    #[Test]
    public function queriesUseInjectedDatabaseNotSingleton(): void
    {
        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->queryString = '';
        $stmtStub->method('execute')->willReturn(true);
        $stmtStub->method('rowCount')->willReturn(0);
        $stmtStub->method('fetch')->willReturn([['v' => 1]]);
        $stmtStub->method('fetchColumn')->willReturn(0);
        $stmtStub->method('fetchAll')->willReturn([]);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        $db = $this->createMock(IDatabase::class);
        $db->expects($this->atLeastOnce())
            ->method('getConnection')
            ->willReturn($pdoStub);

        $dao = new MySQLReplaceEventIndexDao($db);
        $dao->getActualIndex(1);
    }
}
