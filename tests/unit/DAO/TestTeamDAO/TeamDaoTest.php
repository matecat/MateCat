<?php

namespace unit\DAO\TestTeamDAO;

use Model\DataAccess\IDatabase;
use Model\Teams\TeamDao;
use Model\Teams\TeamStruct;
use Model\Users\UserStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class TestTeamDao extends TeamDao
{
    public array $fetchResult   = [];
    public bool  $destroyResult = true;

    protected function _getStatementForQuery(string $query): PDOStatement
    {
        return $this->database->getConnection()->prepare($query);
    }

    protected function _fetchObjectMap(
        PDOStatement $stmt,
        string       $fetchClass,
        array        $bindParams,
        ?string      $keyMap = null
    ): array {
        $stmt->execute($bindParams);
        return $this->fetchResult;
    }

    protected function _destroyObjectCache(
        PDOStatement $stmt,
        string       $fetchClass,
        array        $bindParams
    ): bool {
        return $this->destroyResult;
    }
}

class TeamDaoTest extends AbstractTest
{
    private function makeDbStub(): IDatabase
    {
        $stmt = $this->createStub(PDOStatement::class);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $db = $this->createStub(IDatabase::class);
        $db->method('getConnection')->willReturn($pdo);

        return $db;
    }

    private function makeDbStubWithRowCount(int $rowCount): IDatabase
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('rowCount')->willReturn($rowCount);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $db = $this->createStub(IDatabase::class);
        $db->method('getConnection')->willReturn($pdo);

        return $db;
    }

    #[Test]
    public function delete_returns_row_count(): void
    {
        $dao    = new TeamDao($this->makeDbStubWithRowCount(1));
        $result = $dao->delete(new TeamStruct(['id' => 5]));

        $this->assertSame(1, $result);
    }

    #[Test]
    public function delete_returns_zero_when_no_row_deleted(): void
    {
        $dao    = new TeamDao($this->makeDbStubWithRowCount(0));
        $result = $dao->delete(new TeamStruct(['id' => 999]));

        $this->assertSame(0, $result);
    }

    #[Test]
    public function updateTeamName_returns_the_same_team_struct(): void
    {
        $dao = new TeamDao($this->makeDbStub());

        $team       = new TeamStruct(['id' => 1]);
        $team->name = 'New Name';

        $this->assertSame($team, $dao->updateTeamName($team));
    }

    #[Test]
    public function updateTeamName_calls_begin_then_commit_on_connection(): void
    {
        $stmt = $this->createStub(PDOStatement::class);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->expects($this->once())->method('commit');

        $db = $this->createMock(IDatabase::class);
        $db->expects($this->once())->method('begin');
        $db->method('getConnection')->willReturn($pdo);

        $team       = new TeamStruct(['id' => 7]);
        $team->name = 'Another Name';

        (new TeamDao($db))->updateTeamName($team);
    }

    #[Test]
    public function deleteTeam_returns_row_count(): void
    {
        $dao    = new TeamDao($this->makeDbStubWithRowCount(2));
        $result = $dao->deleteTeam(new TeamStruct(['id' => 10]));

        $this->assertSame(2, $result);
    }

    #[Test]
    public function deleteTeam_returns_zero_when_team_not_found(): void
    {
        $dao    = new TeamDao($this->makeDbStubWithRowCount(0));
        $result = $dao->deleteTeam(new TeamStruct(['id' => 10]));

        $this->assertSame(0, $result);
    }

    #[Test]
    public function findUserCreatedTeams_returns_team_struct_when_found(): void
    {
        $dao = new TestTeamDao($this->makeDbStub());

        $expected       = new TeamStruct(['id' => 1]);
        $expected->name = 'My Team';
        $dao->fetchResult = [$expected];

        $user      = new UserStruct();
        $user->uid = 42;

        $this->assertSame($expected, $dao->findUserCreatedTeams($user));
    }

    #[Test]
    public function findUserCreatedTeams_returns_null_when_not_found(): void
    {
        $dao = new TestTeamDao($this->makeDbStub());
        $dao->fetchResult = [];

        $user      = new UserStruct();
        $user->uid = 42;

        $this->assertNull($dao->findUserCreatedTeams($user));
    }

    #[Test]
    public function destroyCachePersonalByUid_returns_true_when_cache_destroyed(): void
    {
        $dao = new TestTeamDao($this->makeDbStub());
        $dao->destroyResult = true;

        $this->assertTrue($dao->destroyCachePersonalByUid(42));
    }

    #[Test]
    public function destroyCachePersonalByUid_returns_false_when_cache_not_found(): void
    {
        $dao = new TestTeamDao($this->makeDbStub());
        $dao->destroyResult = false;

        $this->assertFalse($dao->destroyCachePersonalByUid(99));
    }

    #[Test]
    public function destroyCacheUserCreatedTeams_returns_true_when_cache_destroyed(): void
    {
        $dao = new TestTeamDao($this->makeDbStub());
        $dao->destroyResult = true;

        $user      = new UserStruct();
        $user->uid = 42;

        $this->assertTrue($dao->destroyCacheUserCreatedTeams($user));
    }

    #[Test]
    public function destroyCacheUserCreatedTeams_returns_false_when_cache_not_found(): void
    {
        $dao = new TestTeamDao($this->makeDbStub());
        $dao->destroyResult = false;

        $user      = new UserStruct();
        $user->uid = 7;

        $this->assertFalse($dao->destroyCacheUserCreatedTeams($user));
    }
}
