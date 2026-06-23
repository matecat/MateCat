<?php

namespace Matecat\Core\DAO\TestChunkCompletionEventDAO;

use Matecat\TestHelpers\AbstractTest;
use Model\ChunksCompletion\ChunkCompletionEventDao;
use Model\DataAccess\IDatabase;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;

class ChunkCompletionEventDaoInjectedDbGuardTest extends AbstractTest
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
        $db->expects($this->atLeastOnce())->method('getConnection')->willReturn($pdo);

        $dao = new ChunkCompletionEventDao($db);
        $dao->updatePassword(1, 'old', 'new');
    }
}
