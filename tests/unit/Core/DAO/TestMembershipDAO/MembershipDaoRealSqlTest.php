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
use Throwable;
use TypeError;

/**
 * Real-SQL coverage for MembershipDao (campaign dao-realsql-90).
 *
 * Every public SQL method is exercised against the real unittest DB: findUserTeams,
 * findTeamByIdAndUser, findTeamByIdAndName, getMemberListByTeamId (traverse on/off),
 * deleteUserFromTeam, addMembersByEmail (+ its transaction guard) and the three destroyCache*
 * evictions. Fixtures (User -> Team -> teams_users) are built through TestFixtureBuilder and
 * reverse-FK cleaned; rows the DAO inserts itself are registered via trackExisting
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
    public function destroyCache_evicts_the_three_reads_a_membership_row_addresses(): void
    {
        $this->dao->setCacheTTL(60);
        $this->dao->findUserTeams($this->user());
        $this->dao->findTeamByIdAndUser($this->teamId, $this->user());
        $this->dao->getMemberListByTeamId($this->teamId, false);

        // Two of the three select teams.* through the join, so the name reaches them; the member list
        // is the teams_users row itself.
        $this->writeBehindTheCache('UPDATE teams SET name = :name WHERE id = :id', ['name' => 'rsq_renamed', 'id' => $this->teamId]);
        $this->writeBehindTheCache('UPDATE teams_users SET is_admin = 0 WHERE id_team = :id AND uid = :uid', ['id' => $this->teamId, 'uid' => $this->uid]);

        $this->dao->destroyCache(new MembershipStruct(['uid' => $this->uid, 'id_team' => $this->teamId]));

        $teams = $this->dao->setCacheTTL(60)->findUserTeams($this->user());
        $this->assertIsArray($teams);
        $this->assertSame('rsq_renamed', $teams[0]->name);
        $this->assertSame('rsq_renamed', $this->dao->setCacheTTL(60)->findTeamByIdAndUser($this->teamId, $this->user())?->name);
        $this->assertFalse((bool)$this->dao->setCacheTTL(60)->getMemberListByTeamId($this->teamId, false)[0]->is_admin);
    }

    #[Test]
    public function destroyCache_refuses_a_membership_that_names_no_user(): void
    {
        $this->expectException(TypeError::class);
        $this->dao->destroyCache(new MembershipStruct(['id_team' => $this->teamId]));
    }

    /**
     * @param array<string, int|string> $bind
     */
    private function writeBehindTheCache(string $sql, array $bind): void
    {
        $this->realSqlDb()->getConnection()->prepare($sql)->execute($bind);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function addMembersByEmail_inserts_memberships_for_known_emails(): void
    {
        $newUser = $this->fixtures->makeUser();
        $team = new TeamStruct(['id' => $this->teamId, 'created_by' => $this->uid]);

        $created = $this->realSqlDb()->transaction(
            fn(): array => $this->dao->addMembersByEmail($team, [$newUser['email']])
        );

        foreach ($created as $m) {
            $this->fixtures->trackExisting('teams_users', ['id' => (int)$m->id]);
        }

        $this->assertCount(1, $created);
        $this->assertInstanceOf(MembershipStruct::class, $created[0]);
        $this->assertSame((int)$newUser['uid'], (int)$created[0]->uid);
        $this->assertSame($this->teamId, (int)$created[0]->id_team);
    }

    /**
     * An address with no account behind it is not an error: the team invitation flow hands over
     * whatever was typed, and the addresses that match nothing simply create no membership.
     *
     * @throws Throwable
     */
    #[Test]
    public function addMembersByEmail_returns_empty_when_no_email_matches_a_user(): void
    {
        $team = new TeamStruct(['id' => $this->teamId, 'created_by' => $this->uid]);

        $created = $this->realSqlDb()->transaction(
            fn(): array => $this->dao->addMembersByEmail(
                $team,
                ['no_such_user_' . bin2hex(random_bytes(6)) . '@example.test']
            )
        );

        $this->assertSame([], $created);
    }

    /**
     * One row per member, so a failure halfway leaves a team holding part of its member list. The
     * guard refuses to start rather than write that.
     */
    #[Test]
    public function addMembersByEmail_throws_when_not_wrapped_in_a_transaction(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('requires to be wrapped in a transaction');

        $this->dao->addMembersByEmail(new TeamStruct(['id' => $this->teamId]), ['x@example.test']);
    }

    /**
     * The inherited stub is the whole implementation here: this DAO writes memberships from email
     * addresses, which is not the struct-list insert the name describes, and it exposes that under
     * its own name instead.
     */
    #[Test]
    public function createList_is_not_implemented_by_this_dao(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('must be overridden');

        $this->dao->createList([new MembershipStruct()]);
    }
}
