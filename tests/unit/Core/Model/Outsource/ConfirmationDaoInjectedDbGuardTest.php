<?php

namespace Matecat\Core\Model\Outsource;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Outsource\ConfirmationDao;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;

class ConfirmationDaoInjectedDbGuardTest extends AbstractTest
{
    #[Test]
    public function queriesUseInjectedDatabaseNotSingleton(): void
    {
        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->queryString = '';
        $stmtStub->method('execute')->willReturn(true);
        $stmtStub->method('rowCount')->willReturn(0);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        $db = $this->createMock(IDatabase::class);
        $db->expects($this->atLeastOnce())
            ->method('getConnection')
            ->willReturn($pdoStub);

        $dao = new ConfirmationDao($db);
        $dao->updatePassword(1, 'old', 'new');
    }
}
