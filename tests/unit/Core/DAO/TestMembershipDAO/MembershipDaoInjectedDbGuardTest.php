<?php

namespace Matecat\Core\DAO\TestMembershipDAO;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Teams\MembershipDao;
use Model\Teams\TeamStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;

class MembershipDaoInjectedDbGuardTest extends AbstractTest
{
    /** Injected into the DAO under test — carries the atLeastOnce expectation. */
    private IDatabase&MockObject $injectedDbMock;

    /** Installed as the Database singleton — absorbs UserDao's no-arg obtain() calls. */
    private IDatabase&Stub $singletonDbStub;

    private PDO&Stub $pdoStub;
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

        $this->pdoStub = $this->createStub(PDO::class);
        $this->pdoStub->method('prepare')->willReturn($this->stmtStub);
        $this->pdoStub->method('inTransaction')->willReturn(true);

        // Injected mock: asserts getConnection is called at least once (proves the fix).
        $this->injectedDbMock = $this->createMock(IDatabase::class);
        $this->injectedDbMock->expects($this->atLeastOnce())
            ->method('getConnection')
            ->willReturn($this->pdoStub);

        // Singleton stub: absorbs UserDao internal obtainTestDatabase() calls without asserting.
        $this->singletonDbStub = $this->createStub(IDatabase::class);
        $this->singletonDbStub->method('getConnection')->willReturn($this->pdoStub);

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
        $dao = new MembershipDao($this->injectedDbMock);

        // createList checks inTransaction() — fixed source uses $this->database->getConnection()
        // (the injectedDbMock), satisfying atLeastOnce. UserDao's no-arg obtain() hits the
        // singletonDbStub. getByEmails(['x@example.com']) returns [] → early-exit return [].
        $result = $dao->createList([
            'team'    => new TeamStruct(),
            'members' => ['x@example.com'],
        ]);

        $this->assertSame([], $result);
    }
}
