<?php

namespace Matecat\Core\DAO\TestChunkCompletionUpdateDAO;

use Matecat\TestHelpers\AbstractTest;
use Model\ChunksCompletion\ChunkCompletionUpdateDao;
use Model\DataAccess\IDatabase;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;

class ChunkCompletionUpdateDaoInjectedDbGuardTest extends AbstractTest
{
    #[Test]
    public function queriesUseInjectedDatabaseNotSingleton(): void
    {
        $injectedStmt = $this->createStub(PDOStatement::class);
        $injectedStmt->queryString = '';
        $injectedStmt->method('execute')->willReturn(true);
        $injectedStmt->method('rowCount')->willReturn(0);
        $injectedStmt->method('fetchAll')->willReturn([]);

        $injectedPdo = $this->createStub(PDO::class);
        $injectedPdo->method('prepare')->willReturn($injectedStmt);

        $injectedDb = $this->createMock(IDatabase::class);
        $injectedDb->expects($this->atLeastOnce())
            ->method('getConnection')
            ->willReturn($injectedPdo);

        $dao = new ChunkCompletionUpdateDao($injectedDb);
        $dao->updatePassword(1, 'old', 'new');
    }
}
