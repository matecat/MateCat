<?php

namespace Matecat\Core\Model\Jobs;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Jobs\MetadataDao;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;

/**
 * Guards the obtain() removal: MetadataDao must take its PDO connection from
 * the INJECTED IDatabase in set(), bulkSet(), and delete(), never from the
 * Database::obtain() singleton for query preparation.
 *
 * Strategy: the singleton PDO stub's prepare() throws a PDOException. If any
 * of the 3 direct obtain() calls in set/bulkSet/delete remain, the test crashes.
 * The injected PDO is fully stubbed so the DAO can complete successfully.
 * TransactionalTrait calls inTransaction()/begin()/commit() on the singleton —
 * those methods are NOT prepare(), so the trait's singleton usage does not
 * trigger the exception.
 */
class MetadataDaoInjectedDbGuardTest extends AbstractTest
{
    protected function setUp(): void
    {
        parent::setUp();

        // Singleton PDO: prepare() throws so any obtain()->getConnection()->prepare()
        // in the DAO's own methods will blow up.
        $singletonStmt = $this->createStub(PDOStatement::class);
        $singletonStmt->queryString = '';

        $singletonPdo = $this->createStub(PDO::class);
        $singletonPdo->method('prepare')->willThrowException(new PDOException('singleton PDO must not be used for queries'));
        $singletonPdo->method('inTransaction')->willReturn(false);

        $singletonDb = $this->createStub(IDatabase::class);
        $singletonDb->method('getConnection')->willReturn($singletonPdo);
        $singletonDb->method('begin')->willReturn($singletonPdo);

        $this->setDatabaseInstance($singletonDb);
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();
        parent::tearDown();
    }

    #[Test]
    public function queriesUseInjectedDatabaseNotSingleton(): void
    {
        $injectedStmt = $this->createStub(PDOStatement::class);
        $injectedStmt->queryString = '';
        $injectedStmt->method('execute')->willReturn(true);
        $injectedStmt->method('rowCount')->willReturn(0);
        $injectedStmt->method('fetch')->willReturn(false);
        $injectedStmt->method('fetchAll')->willReturn([]);

        $injectedPdo = $this->createStub(PDO::class);
        $injectedPdo->method('prepare')->willReturn($injectedStmt);

        $injectedDb = $this->createMock(IDatabase::class);
        $injectedDb->expects($this->atLeastOnce())
            ->method('getConnection')
            ->willReturn($injectedPdo);

        $dao = new MetadataDao($injectedDb);
        $dao->set(1, 'pw', 'k', 'v');
    }
}
