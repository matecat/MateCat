<?php

namespace Matecat\Core\DAO\TestTeamDAO;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Teams\TeamDao;
use Model\Users\UserStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;

class TeamDaoInjectedDbGuardTest extends AbstractTest
{
    /** Injected into the DAO under test — the only DB the method should ever touch. */
    private IDatabase&MockObject $injectedDbMock;

    /**
     * Installed as the Database singleton — provides a working PDO for any
     * no-arg DAO constructions inside nested collaborators (e.g. UserDao inside
     * MembershipDao::createList) that are not part of this fix's scope.
     */
    private IDatabase&Stub $singletonDbStub;

    /**
     * Injected PDO stub. inTransaction() returns FALSE on the first call so that the pre-fix
     * obtainTestDatabase()->getConnection()->inTransaction() path is exercised — the fix routes
     * that call through $this->database->getConnection() instead. It carries no expectation: the
     * transaction boundary itself is asserted one level up, on the injected IDatabase.
     */
    private PDO&Stub $injectedPdoMock;

    /** Singleton PDO stub — permissive, used only by nested no-arg DAOs. */
    private PDO&Stub $singletonPdoStub;

    private PDOStatement&Stub $stmtStub;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stmtStub = $this->createStub(PDOStatement::class);
        $this->stmtStub->queryString = '';
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(0);
        $this->stmtStub->method('fetch')->willReturn(false);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        // Injected PDO: inTransaction() returns false on the first call, so openTransaction()
        // decides it owns the transaction and opens one, then true for every call after it, so
        // MembershipDao::createList proceeds and commitTransaction() finds a transaction to close.
        $inTransactionCalls = 0;
        $this->injectedPdoMock = $this->createStub(PDO::class);
        $this->injectedPdoMock->method('prepare')->willReturn($this->stmtStub);
        $this->injectedPdoMock->method('lastInsertId')->willReturn('1');
        $this->injectedPdoMock->method('inTransaction')
            ->willReturnCallback(static function () use (&$inTransactionCalls): bool {
                return $inTransactionCalls++ > 0;
            });
        $this->injectedPdoMock->method('beginTransaction')->willReturn(true);
        $this->injectedPdoMock->method('commit')->willReturn(true);

        // Singleton PDO: permissive stub for nested no-arg DAOs (UserDao etc.).
        $this->singletonPdoStub = $this->createStub(PDO::class);
        $this->singletonPdoStub->method('prepare')->willReturn($this->stmtStub);
        $this->singletonPdoStub->method('lastInsertId')->willReturn('1');
        $this->singletonPdoStub->method('inTransaction')->willReturn(true);

        // Injected mock: routes all TeamDao's own DB calls through here.
        // atLeastOnce on getConnection confirms the fixed code uses the injected DB.
        $this->injectedDbMock = $this->createMock(IDatabase::class);
        $this->injectedDbMock->expects($this->atLeastOnce())
            ->method('getConnection')
            ->willReturn($this->injectedPdoMock);
        // The transaction is opened and closed through IDatabase rather than through the PDO
        // handle, which is what lets Database::commit() drain the IDatabase::onCommit() queue —
        // a raw PDO commit leaves it untouched and the next begin() discards it. Asserting it here
        // rather than on beginTransaction() keeps the guard pointed at the boundary that matters.
        $this->injectedDbMock->expects($this->atLeastOnce())
            ->method('begin')
            ->willReturn($this->injectedPdoMock);
        $this->injectedDbMock->expects($this->atLeastOnce())
            ->method('commit');
        $this->injectedDbMock->method('buildInsertStatement')
            ->willReturn(['INSERT INTO teams (name) VALUES (:name)', []]);

        // Singleton stub: absorbs no-arg DAO constructions in nested collaborators.
        $this->singletonDbStub = $this->createStub(IDatabase::class);
        $this->singletonDbStub->method('getConnection')->willReturn($this->singletonPdoStub);
        $this->singletonDbStub->method('buildInsertStatement')
            ->willReturn(['INSERT INTO teams (name) VALUES (:name)', []]);

        $this->setDatabaseInstance($this->singletonDbStub);
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();
        parent::tearDown();
    }

    #[Test]
    public function queriesUseInjectedDatabaseNotSingleton(): void
    {
        $user = new UserStruct();
        $user->uid = 42;
        $user->email = 'test@example.com';

        // members=[] — after createUserTeam appends the creator email the filter
        // produces ['test@example.com']. MembershipDao::createList calls
        // (new UserDao)->getByEmails() which returns [] (stub fetchAll=[]) → early-return [].
        $params = [
            'name'    => 't',
            'type'    => 'personal',
            'members' => [],
        ];

        $dao = new TeamDao($this->injectedDbMock);
        $dao->createUserTeam($user, $params);

        // The atLeastOnce expectations set in setUp() are the assertions. getConnection() proves
        // the queries run on the injected handle rather than on the singleton; begin() and commit()
        // prove the transaction is opened and closed through IDatabase, which is what keeps the
        // IDatabase::onCommit() queue drainable. Pre-fix code called beginTransaction() straight on
        // the PDO handle and never committed at all, so both of the latter go unmet.
    }
}
