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
     * Injected PDO stub. inTransaction() returns FALSE so that the pre-fix
     * obtainTestDatabase()->getConnection()->inTransaction() path is exercised —
     * the fix routes that call through $this->database->getConnection() instead,
     * and beginTransaction() + commit() are called on this same stub.
     */
    private PDO&MockObject $injectedPdoMock;

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

        // Injected PDO: inTransaction() returns false on the first call so beginTransaction()
        // IS invoked (proving the transaction path routes through the injected mock), then
        // returns true for all subsequent calls so MembershipDao::createList proceeds and the
        // final commit guard is skipped.
        $this->injectedPdoMock = $this->createMock(PDO::class);
        $this->injectedPdoMock->method('prepare')->willReturn($this->stmtStub);
        $this->injectedPdoMock->method('lastInsertId')->willReturn('1');
        $this->injectedPdoMock->method('inTransaction')
            ->willReturnOnConsecutiveCalls(false, true, true, true);
        $this->injectedPdoMock->expects($this->atLeastOnce())
            ->method('beginTransaction')
            ->willReturn(true);
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

        // The atLeastOnce beginTransaction on injectedPdoMock is the key assertion:
        // pre-fix code calls obtainTestDatabase()->getConnection()->beginTransaction()
        // (hits the singleton PDO, not the injected one → expectation unmet → FAIL).
        // Fixed code calls $this->database->getConnection()->beginTransaction()
        // (hits injectedPdoMock → expectation met → PASS).
    }
}
