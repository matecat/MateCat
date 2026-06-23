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

        // Poison the singleton: it must NEVER be touched. Any Database::obtain()
        // fallback (full revert OR a partial/mixed path) hits this mock and trips
        // the never() expectation — a clean, deterministic failure that does not
        // depend on the real test DB schema.
        $poison = $this->createMock(IDatabase::class);
        $poison->expects($this->never())->method('getConnection');
        $this->setDatabaseInstance($poison);

        $dao = new ChunkCompletionUpdateDao($injectedDb);
        $dao->updatePassword(1, 'old', 'new');
    }
}
