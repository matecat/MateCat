<?php

namespace Matecat\Core\DAO\TestTeamDAO;

use DomainException;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\Teams\MembershipStruct;
use Model\Teams\TeamDao;
use Model\Teams\TeamStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Utils\Constants\Teams;

/**
 * Real-SQL coverage for TeamDao (campaign dao-realsql-90).
 *
 * Every public method runs against the live unittest DB on the single per-test connection.
 * Reads are driven by directly-built teams / teams_users / projects rows; the mutating methods
 * (createPersonalTeam / createUserTeam / updateTeamName / delete / deleteTeam) build their own
 * isolated rows and the residue gate asserts whole-table COUNT(*) is unchanged after cleanup.
 *
 * createUserTeam runs MembershipDao::addMembersByEmail inside a transaction scope (the harness opens no
 * ambient transaction, so the scope is the outermost one) and commits it, so the team + membership
 * rows are committed; they are tracked via the returned structs so cleanup removes them. An earlier
 * version of that boundary never committed at all — its guard asked for "no transaction open",
 * which is true only when there is nothing to commit — and every assertion below still passed,
 * because a connection reads its own uncommitted rows.
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class TeamDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private TeamDao $dao;
    private int $creatorUid;
    private string $creatorEmail;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startRealSql(['teams', 'teams_users', 'users', 'projects']);

        $creator            = $this->fixtures->makeUser();
        $this->creatorUid   = $creator['uid'];
        $this->creatorEmail = $creator['email'];

        $this->dao = new TeamDao($this->realSqlDb());
    }

    protected function tearDown(): void
    {
        $this->finishRealSql();
        parent::tearDown();
    }

    /** Insert a teams row directly and track it for cleanup. */
    private function makeTeamRow(int $createdBy, string $type = Teams::PERSONAL): int
    {
        $team = $this->fixtures->makeTeam($createdBy, $type);
        // makeTeam tracks the row itself via insertAi; return its id.
        return $team['id'];
    }

    /** Build a UserStruct carrying only a uid (the DAO reads ->uid / ->email). */
    private function userWithUid(int $uid): UserStruct
    {
        $user      = new UserStruct();
        $user->uid = $uid;

        return $user;
    }

    #[Test]
    public function dao_uses_the_injected_connection_not_the_singleton(): void
    {
        $this->assertDaoUsesTestConnection($this->dao);
    }

    #[Test]
    public function findById_hit_and_miss(): void
    {
        $id = $this->makeTeamRow($this->creatorUid, Teams::GENERAL);

        $team = $this->dao->findById($id);
        $this->assertInstanceOf(TeamStruct::class, $team);
        $this->assertSame($id, $team->id);
        $this->assertSame($this->creatorUid, $team->created_by);

        $this->assertNull($this->dao->findById(self::ASSIGNABLE_ID_FLOOR + 990001));
    }

    #[Test]
    public function delete_returns_row_count_and_zero_for_unknown(): void
    {
        $id = $this->makeTeamRow($this->creatorUid, Teams::GENERAL);

        // a personal-or-not team is deleted unconditionally by delete()
        $this->assertSame(1, $this->dao->delete(new TeamStruct(['id' => $id])));
        $this->assertNull($this->dao->findById($id));

        // deleting an unknown id touches no row
        $this->assertSame(0, $this->dao->delete(new TeamStruct(['id' => self::ASSIGNABLE_ID_FLOOR + 990002])));
    }

    #[Test]
    public function createPersonalTeam_inserts_team_and_creator_membership(): void
    {
        $creator       = new UserStruct();
        $creator->uid  = $this->creatorUid;
        $creator->email = $this->creatorEmail;

        $team = $this->dao->createPersonalTeam($creator);
        $this->trackTeamAndMembers($team);

        $this->assertNotNull($team->id);
        $this->assertSame(Teams::PERSONAL, $team->type);
        $this->assertSame('Personal', $team->name);

        $members = $team->getMembers();
        $this->assertCount(1, $members);
        $this->assertSame($this->creatorUid, $members[0]->uid);
        // creator is_admin arm (created_by == uid)
        $this->assertTrue((bool)$members[0]->is_admin);

        // reachable on the same connection; that the commit really happened is asserted separately,
        // by createUserTeam_leaves_no_open_transaction_when_it_owns_one()
        $reloaded = $this->dao->findById($team->id);
        $this->assertInstanceOf(TeamStruct::class, $reloaded);
    }

    #[Test]
    public function createUserTeam_general_with_extra_and_null_member(): void
    {
        $second = $this->fixtures->makeUser();

        $creator        = new UserStruct();
        $creator->uid   = $this->creatorUid;
        $creator->email = $this->creatorEmail;

        // null member exercises the array_filter() drop; the creator email is appended internally
        $team = $this->dao->createUserTeam($creator, [
            'name'    => 'Acme General',
            'type'    => Teams::GENERAL,
            'members' => [$second['email'], null],
        ]);
        $this->trackTeamAndMembers($team);

        $this->assertSame(Teams::GENERAL, $team->type);
        $this->assertSame('Acme General', $team->name);

        $uids = array_map(static fn(MembershipStruct $m) => $m->uid, $team->getMembers());
        sort($uids);
        $expected = [$this->creatorUid, $second['uid']];
        sort($expected);
        $this->assertSame($expected, $uids);

        // is_admin true only for the creator, false for the extra member (both arms)
        $byUid = [];
        foreach ($team->getMembers() as $m) {
            $byUid[$m->uid] = (bool)$m->is_admin;
        }
        $this->assertTrue($byUid[$this->creatorUid]);
        $this->assertFalse($byUid[$second['uid']]);
    }

    /**
     * The phantom team. createUserTeam() re-reads the row it has just inserted with a 24-hour TTL,
     * and both its callers wrap it in a transaction — SignupModel::processSignup() and
     * TeamModel::createUserTeam() — so the insert runs inside that transaction too. Before the
     * cache layer learned to skip a populate taken inside one, that read published an uncommitted
     * row: a rollback anywhere later in signup left a team that does not exist readable from cache
     * for a day.
     */
    #[Test]
    public function createUserTeam_publishes_nothing_while_the_callers_transaction_is_open(): void
    {
        $database = $this->realSqlDb();
        $this->flushDaoCache();

        $creator = new UserStruct();
        $creator->uid = $this->creatorUid;
        $creator->email = $this->creatorEmail;

        // Stand in for SignupModel/TeamModel, which own the transaction createUserTeam joins.
        $database->begin();
        try {
            $this->dao->createUserTeam($creator, [
                'name' => 'Acme Phantom',
                'type' => Teams::GENERAL,
                'members' => [],
            ]);

            self::assertSame(
                [],
                $this->daoCacheRedis()->keys('*'),
                'nothing may be cached from inside the transaction: the rows are not public yet'
            );
        } finally {
            // The rollback is the point: after it the team never existed.
            $database->rollback();
        }

        self::assertNull($this->dao->findById(0), 'sanity: the DAO reads through to the database');
        self::assertSame([], $this->daoCacheRedis()->keys('*'), 'and the rollback left nothing behind');
    }

    /**
     * createUserTeam() opened its own transaction and then guarded the commit on "no transaction is
     * open", which is true only when there is nothing to commit. Called outside a transaction it
     * therefore returned with one still open, on a connection a worker holds across messages.
     */
    #[Test]
    public function createUserTeam_leaves_no_open_transaction_when_it_owns_one(): void
    {
        $connection = $this->realSqlDb()->getConnection();
        $this->assertFalse($connection->inTransaction(), 'precondition: the harness opens no transaction');

        $creator = new UserStruct();
        $creator->uid = $this->creatorUid;
        $creator->email = $this->creatorEmail;

        $team = $this->dao->createUserTeam($creator, [
            'name' => 'Acme Transaction Boundary',
            'type' => Teams::GENERAL,
            'members' => [],
        ]);
        $this->trackTeamAndMembers($team);

        $this->assertFalse(
            $connection->inTransaction(),
            'createUserTeam() must close the transaction it opened'
        );
    }

    #[Test]
    public function getAssigneeWithProjectsByTeam_groups_counts_per_assignee(): void
    {
        $idTeam = $this->makeTeamRow($this->creatorUid, Teams::GENERAL);
        $other  = $this->fixtures->makeUser();

        // two projects for the creator + one for another assignee under the same team
        $this->fixtures->makeProjectDetailed(['id_team' => $idTeam, 'id_assignee' => $this->creatorUid]);
        $this->fixtures->makeProjectDetailed(['id_team' => $idTeam, 'id_assignee' => $this->creatorUid]);
        $this->fixtures->makeProjectDetailed(['id_team' => $idTeam, 'id_assignee' => $other['uid']]);

        $rows = $this->dao->getAssigneeWithProjectsByTeam(new TeamStruct(['id' => $idTeam]));

        $this->assertCount(2, $rows);
        $this->assertContainsOnlyInstancesOf(MembershipStruct::class, $rows);

        $byUid = [];
        foreach ($rows as $row) {
            $byUid[(int)$row->uid] = $row->getAssignedProjects();
        }
        $this->assertSame(2, $byUid[$this->creatorUid]);
        $this->assertSame(1, $byUid[$other['uid']]);
    }

    #[Test]
    public function destroyCacheAssignee_returns_bool_after_caching_the_read(): void
    {
        $idTeam = $this->makeTeamRow($this->creatorUid, Teams::GENERAL);
        $this->fixtures->makeProjectDetailed(['id_team' => $idTeam, 'id_assignee' => $this->creatorUid]);
        $team = new TeamStruct(['id' => $idTeam]);

        // populate the cache, then destroy it
        $this->dao->setCacheTTL(3600)->getAssigneeWithProjectsByTeam($team);
        $this->assertTrue($this->dao->destroyCacheAssigneeWithProjectsByTeam($team));
    }

    #[Test]
    public function getPersonalByUser_delegates_and_throws_on_null_uid(): void
    {
        $this->makeTeamRow($this->creatorUid, Teams::PERSONAL);

        $team = $this->dao->getPersonalByUser($this->userWithUid($this->creatorUid));
        $this->assertInstanceOf(TeamStruct::class, $team);
        $this->assertSame(Teams::PERSONAL, $team->type);
        $this->assertSame($this->creatorUid, $team->created_by);

        // a UserStruct with no uid trips the DomainException guard
        $this->expectException(DomainException::class);
        $this->dao->getPersonalByUser(new UserStruct());
    }

    #[Test]
    public function getPersonalByUid_returns_the_personal_team(): void
    {
        $this->makeTeamRow($this->creatorUid, Teams::PERSONAL);

        $team = $this->dao->getPersonalByUid($this->creatorUid);
        $this->assertSame($this->creatorUid, $team->created_by);
        $this->assertSame(Teams::PERSONAL, $team->type);
    }

    #[Test]
    public function destroyCache_drops_the_personal_team_entry(): void
    {
        $id = $this->makeTeamRow($this->creatorUid, Teams::PERSONAL);

        $this->dao->setCacheTTL(3600)->getPersonalByUid($this->creatorUid);
        $this->renameBehindTheCache($id, 'rn_personal');

        $team = $this->dao->findById($id);
        $this->assertInstanceOf(TeamStruct::class, $team);
        $this->dao->destroyCache($team);

        $this->assertSame('rn_personal', $this->dao->setCacheTTL(3600)->getPersonalByUid($this->creatorUid)->name);
    }

    #[Test]
    public function findUserCreatedTeams_hit_and_miss(): void
    {
        $this->makeTeamRow($this->creatorUid, Teams::GENERAL);

        $team = $this->dao->findUserCreatedTeams($this->userWithUid($this->creatorUid));
        $this->assertInstanceOf(TeamStruct::class, $team);
        $this->assertSame($this->creatorUid, $team->created_by);

        // a user with no created teams returns null ([0] ?? null arm)
        $this->assertNull($this->dao->findUserCreatedTeams($this->userWithUid(self::ASSIGNABLE_ID_FLOOR + 990003)));
    }

    #[Test]
    public function destroyCache_drops_the_user_created_teams_entry(): void
    {
        $id = $this->makeTeamRow($this->creatorUid, Teams::GENERAL);
        $user = $this->userWithUid($this->creatorUid);

        $this->dao->setCacheTTL(3600)->findUserCreatedTeams($user);
        $this->renameBehindTheCache($id, 'rn_created');

        $team = $this->dao->findById($id);
        $this->assertInstanceOf(TeamStruct::class, $team);
        $this->dao->destroyCache($team);

        $this->assertSame('rn_created', $this->dao->setCacheTTL(3600)->findUserCreatedTeams($user)?->name);
    }

    /** Writes on the connection, so a cached read keeps answering with the row it holds. */
    private function renameBehindTheCache(int $id, string $name): void
    {
        $statement = $this->realSqlDb()->getConnection()->prepare('UPDATE teams SET name = :name WHERE id = :id');
        $statement->execute(['name' => $name, 'id' => $id]);
    }

    #[Test]
    public function updateTeamName_persists_new_name(): void
    {
        $id = $this->makeTeamRow($this->creatorUid, Teams::GENERAL);

        $team       = new TeamStruct(['id' => $id, 'created_by' => $this->creatorUid]);
        $team->name = 'Renamed Team';

        $returned = $this->dao->updateTeamName($team);
        $this->assertSame($team, $returned);

        $reloaded = $this->dao->findById($id);
        $this->assertSame('Renamed Team', $reloaded->name);
    }

    /**
     * A rename has to reach the readers that go through the cache rather than through the caller's
     * own struct, and `fetchById` is cached for a day at a time by
     * {@see \Model\Teams\MembershipStruct::getTeam()} and by the team-members endpoint.
     *
     * Written as the sequence that exposed it rather than as an assertion about eviction: prime the
     * cache the way opening the members page does, rename, then read it back the way the membership
     * email does. Before the fix the second read returned the previous name, which is how a renamed
     * team went on announcing its old one by email for up to twenty-four hours.
     */
    /**
     * A personal team is renameable like any other: TeamsController::update() resolves the team
     * through the membership the caller holds, and the creator is a member of their own personal
     * team. The row is cached a second time under created_by + type, read with a 24-hour TTL by
     * TeamModel, and that entry publishes the name too.
     */
    #[Test]
    public function updateTeamName_does_not_leave_the_old_name_in_the_personal_team_cache(): void
    {
        $id = $this->makeTeamRow($this->creatorUid, Teams::PERSONAL);

        $this->dao->setCacheTTL(60 * 60 * 24)->getPersonalByUid($this->creatorUid);

        $team = $this->dao->findById($id);
        self::assertInstanceOf(TeamStruct::class, $team);
        $team->name = 'Renamed personal';
        (new TeamDao($this->realSqlDb()))->updateTeamName($team);

        self::assertSame(
            'Renamed personal',
            $this->dao->setCacheTTL(60 * 60 * 24)->getPersonalByUid($this->creatorUid)->name,
            'the read keyed on the creator addresses the same row and has to go with the rename'
        );
    }

    #[Test]
    public function updateTeamName_does_not_leave_the_old_name_in_the_fetchById_cache(): void
    {
        $id = $this->makeTeamRow($this->creatorUid, Teams::GENERAL);

        /** @var TeamStruct $primed */
        $primed = $this->dao->setCacheTTL(60 * 60 * 24)->fetchById($id, TeamStruct::class);
        $this->assertNotSame('Renamed Team', $primed->name, 'precondition: the cache holds the original name');

        $team = new TeamStruct(['id' => $id, 'created_by' => $this->creatorUid]);
        $team->name = 'Renamed Team';
        (new TeamDao($this->realSqlDb()))->updateTeamName($team);

        /** @var TeamStruct $afterRename */
        $afterRename = (new TeamDao($this->realSqlDb()))
            ->setCacheTTL(60 * 60 * 24)
            ->fetchById($id, TeamStruct::class);

        $this->assertSame('Renamed Team', $afterRename->name);
    }

    #[Test]
    public function deleteTeam_deletes_non_personal_and_skips_personal(): void
    {
        $idGeneral = $this->makeTeamRow($this->creatorUid, Teams::GENERAL);
        $this->assertSame(1, $this->dao->deleteTeam(new TeamStruct(['id' => $idGeneral])));
        $this->assertNull($this->dao->findById($idGeneral));

        // a personal team is protected by the `type != 'personal'` guard -> 0 rows deleted
        $idPersonal = $this->makeTeamRow($this->creatorUid, Teams::PERSONAL);
        $this->assertSame(0, $this->dao->deleteTeam(new TeamStruct(['id' => $idPersonal])));
        $this->assertInstanceOf(TeamStruct::class, $this->dao->findById($idPersonal));
    }

    /** Track the DAO-committed team + membership rows so cleanup removes them (residue gate). */
    private function trackTeamAndMembers(TeamStruct $team): void
    {
        if ($team->id !== null) {
            $this->fixtures->trackExisting('teams', ['id' => $team->id]);
        }
        foreach ($team->getMembers() as $member) {
            if ($member->id !== null) {
                $this->fixtures->trackExisting('teams_users', ['id' => $member->id]);
            }
        }
    }
}
