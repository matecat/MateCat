<?php

namespace Matecat\Core\DAO\TestTeamDAO;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Teams\TeamDao;
use Model\Teams\TeamStruct;
use Model\Users\UserStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use TypeError;

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
        // Writes that evict a cache entry build its key from the statement they prepared, so a
        // statement double has to carry one. Uninitialised, the typed property throws instead.
        $stmt->queryString = 'stubbed';

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
    public function updateTeamName_runs_its_write_inside_a_transaction_scope(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->queryString = 'stubbed';

        // The scope opens and closes the transaction now. The DAO committing on the raw handle is
        // the mixed-handle shape this test pins against: a commit issued that way leaves the
        // deferral queue undrained.
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->expects($this->never())->method('commit');

        $db = $this->createMock(IDatabase::class);
        $db->expects($this->once())->method('transaction')->willReturnCallback(
            static fn(callable $callback) => $callback()
        );
        $db->method('getConnection')->willReturn($pdo);

        $team       = new TeamStruct(['id' => 7, 'created_by' => 42]);
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
    public function destroyCache_refuses_a_struct_that_carries_no_creator(): void
    {
        $dao = new TestTeamDao($this->makeDbStub());

        $this->expectException(TypeError::class);
        $dao->destroyCache(new TeamStruct(['id' => 7]));
    }
}
