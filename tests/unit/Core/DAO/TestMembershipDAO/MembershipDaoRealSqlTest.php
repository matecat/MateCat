<?php

namespace Matecat\Core\DAO\TestMembershipDAO;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\Teams\MembershipDao;
use Model\Teams\MembershipStruct;
use Model\Teams\TeamStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Real-SQL coverage for MembershipDao (campaign dao-realsql-90).
 *
 * Every public SQL method is exercised against the real unittest DB: findUserTeams,
 * findTeamByIdAndUser, findTeamByIdAndName, getMemberListByTeamId (traverse on/off),
 * deleteUserFromTeam, createList (+ its validation branches) and the three destroyCache*
 * evictions. Fixtures (User -> Team -> teams_users) are built through TestFixtureBuilder and
 * reverse-FK cleaned; rows the DAO inserts itself (createList) are registered via trackExisting
 * so the whole-table residue gate returns to baseline (DoD c).
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class MembershipDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private const array TABLE_DEPS = ['users', 'teams', 'teams_users', 'user_metadata'];

    private MembershipDao $dao;
    private int $uid;
    private int $teamId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertDbWriteGuard();
        $this->startRealSql(self::TABLE_DEPS);

        $this->dao = new MembershipDao($this->realSqlDb());
        $this->assertDaoUsesTestConnection($this->dao);

        $this->uid = $this->fixtures->makeUser()['uid'];
        $this->teamId = $this->fixtures->makeTeam($this->uid)['id'];
        $this->fixtures->makeTeamUser($this->teamId, $this->uid, true);
    }

    protected function tearDown(): void
    {
        $this->finishRealSql();
        parent::tearDown();
    }

    private function user(?int $uid = null): UserStruct
    {
        return new UserStruct(['uid' => $uid ?? $this->uid]);
    }

    #[Test]
    public function findUserTeams_returns_the_team_the_user_belongs_to(): void
    {
        $teams = $this->dao->findUserTeams($this->user());

        $this->assertIsArray($teams);
        $ids = array_map(fn(TeamStruct $t) => (int)$t->id, $teams);
        $this->assertContains($this->teamId, $ids);
    }

    #[Test]
    public function findTeamByIdAndUser_matches_only_the_owning_user(): void
    {
        $team = $this->dao->findTeamByIdAndUser($this->teamId, $this->user());
        $this->assertInstanceOf(TeamStruct::class, $team);
        $this->assertSame($this->teamId, (int)$team->id);

        $this->assertNull($this->dao->findTeamByIdAndUser($this->teamId, $this->user($this->uid + 999999)));
    }

    #[Test]
    public function getMemberListByTeamId_with_traverse_loads_users(): void
    {
        // a metadata row so the traverse enrichment also exercises the setUserMetadata branch
        $conn = $this->realSqlDb()->getConnection();
        $ins = $conn->prepare("INSERT INTO user_metadata (uid, `key`, value) VALUES (:uid, :k, :v)");
        $ins->execute(['uid' => $this->uid, 'k' => 'rsq_key', 'v' => 'rsq_value']);
        $this->fixtures->trackExisting('user_metadata', ['id' => (int)$conn->lastInsertId()]);

        $members = $this->dao->getMemberListByTeamId($this->teamId, true);

        $this->assertCount(1, $members);
        $this->assertInstanceOf(MembershipStruct::class, $members[0]);
        $this->assertSame($this->uid, (int)$members[0]->uid);
        // traverse=true ran the UserDao/MetadataDao enrichment branch and called setUser():
        // getUser() is a self-fetch accessor requiring an injected DAO.
        $this->assertInstanceOf(UserStruct::class, $members[0]->getUser(new \Model\Users\UserDao($this->realSqlDb())));
    }

    #[Test]
    public function getMemberListByTeamId_without_traverse_skips_user_loading(): void
    {
        $members = $this->dao->getMemberListByTeamId($this->teamId, false);

        $this->assertCount(1, $members);
        $this->assertSame($this->uid, (int)$members[0]->uid);
    }

    #[Test]
    public function deleteUserFromTeam_removes_the_membership_and_returns_the_user(): void
    {
        $removed = $this->dao->deleteUserFromTeam($this->uid, $this->teamId);

        $this->assertInstanceOf(UserStruct::class, $removed);
        $this->assertSame($this->uid, (int)$removed->uid);
        $this->assertCount(0, $this->dao->getMemberListByTeamId($this->teamId, false));

        // second delete: nothing left to remove -> null
        $this->assertNull($this->dao->deleteUserFromTeam($this->uid, $this->teamId));
    }

    #[Test]
    public function createList_inserts_memberships_for_known_emails(): void
    {
        $newUser = $this->fixtures->makeUser();
        $team = new TeamStruct(['id' => $this->teamId, 'created_by' => $this->uid]);

        $conn = $this->realSqlDb()->getConnection();
        $conn->beginTransaction();
        $created = $this->dao->createList(['team' => $team, 'members' => [$newUser['email']]]);
        $conn->commit();

        foreach ($created as $m) {
            $this->fixtures->trackExisting('teams_users', ['id' => (int)$m->id]);
        }

        $this->assertCount(1, $created);
        $this->assertInstanceOf(MembershipStruct::class, $created[0]);
        $this->assertSame((int)$newUser['uid'], (int)$created[0]->uid);
        $this->assertSame($this->teamId, (int)$created[0]->id_team);
    }

    #[Test]
    public function createList_returns_empty_when_no_email_matches_a_user(): void
    {
        $team = new TeamStruct(['id' => $this->teamId, 'created_by' => $this->uid]);

        $conn = $this->realSqlDb()->getConnection();
        $conn->beginTransaction();
        $created = $this->dao->createList([
            'team'    => $team,
            'members' => ['no_such_user_' . bin2hex(random_bytes(6)) . '@example.test'],
        ]);
        $conn->commit();

        $this->assertSame([], $created);
    }

    #[Test]
    public function createList_throws_when_not_wrapped_in_a_transaction(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('requires to be wrapped in a transaction');

        $this->dao->createList([
            'team'    => new TeamStruct(['id' => $this->teamId]),
            'members' => ['x@example.test'],
        ]);
    }

    #[Test]
    public function createList_validates_payload_shape(): void
    {
        $conn = $this->realSqlDb()->getConnection();

        // missing required keys
        $conn->beginTransaction();
        try {
            $this->dao->createList(['members' => ['x@example.test']]);
            $this->fail('expected exception for missing keys');
        } catch (Exception $e) {
            $this->assertStringContainsString('Missing required keys', $e->getMessage());
        } finally {
            $conn->rollBack();
        }

        // members not an array
        $conn->beginTransaction();
        try {
            $this->dao->createList(['team' => new TeamStruct(), 'members' => 'nope']);
            $this->fail('expected exception for non-array members');
        } catch (Exception $e) {
            $this->assertStringContainsString('members must be an array', $e->getMessage());
        } finally {
            $conn->rollBack();
        }

        // team not a TeamStruct
        $conn->beginTransaction();
        try {
            $this->dao->createList(['team' => 'nope', 'members' => ['x@example.test']]);
            $this->fail('expected exception for non-TeamStruct team');
        } catch (Exception $e) {
            $this->assertStringContainsString('team must be a TeamStruct', $e->getMessage());
        } finally {
            $conn->rollBack();
        }
    }

    #[Test]
    public function destroyCache_methods_evict_primed_entries(): void
    {
        $this->dao->setCacheTTL(60);

        // findUserTeams cache
        $this->dao->findUserTeams($this->user());
        $this->assertTrue($this->dao->destroyCacheUserTeams($this->user()));

        // findTeamByIdAndUser cache
        $this->dao->findTeamByIdAndUser($this->teamId, $this->user());
        $this->assertTrue($this->dao->destroyCacheTeamByIdAndUser($this->teamId, $this->user()));

        // getMemberListByTeamId cache
        $this->dao->getMemberListByTeamId($this->teamId, false);
        $this->assertTrue($this->dao->destroyCacheForListByTeamId($this->teamId));
    }
}
