<?php

namespace Matecat\Core\Search;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Search\MySQLReplaceEventDao;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;

class MySQLReplaceEventDaoInjectedDbGuardTest extends AbstractTest
{
    #[Test]
    public function queriesUseInjectedDatabaseNotSingleton(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->queryString = '';
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(0);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $db = $this->createMock(IDatabase::class);
        $db->expects($this->atLeastOnce())
            ->method('getConnection')
            ->willReturn($pdo);

        $dao = new MySQLReplaceEventDao($db);
        $dao->getEvents(1, 1);
    }
}
