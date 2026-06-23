<?php

namespace Matecat\Core\DAO\TestProjectDAO;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Projects\ProjectDao;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;

class ProjectDaoInjectedDbGuardTest extends AbstractTest
{
    #[Test]
    public function queriesUseInjectedDatabaseNotSingleton(): void
    {
        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->queryString = '';
        $stmtStub->method('execute')->willReturn(true);
        $stmtStub->method('rowCount')->willReturn(0);
        $stmtStub->method('fetchAll')->willReturn([]);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        $db = $this->createMock(IDatabase::class);
        $db->expects($this->atLeastOnce())
            ->method('getConnection')
            ->willReturn($pdoStub);

        $dao = new ProjectDao($db);
        $dao->getJobIds(1);
    }
}
