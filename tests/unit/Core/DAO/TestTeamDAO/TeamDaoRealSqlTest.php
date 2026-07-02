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
 * createUserTeam wraps MembershipDao::createList in its own transaction (the harness opens no
 * ambient transaction), so the team + membership rows are committed; they are tracked via the
 * returned structs so cleanup removes them.
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

        // committed to the DB on the same connection
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
        $this->assertTrue($this->dao->destroyCacheAssignee($team));
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
    public function destroyCachePersonalByUid_returns_bool_after_caching_the_read(): void
    {
        $this->makeTeamRow($this->creatorUid, Teams::PERSONAL);

        $this->dao->setCacheTTL(3600)->getPersonalByUid($this->creatorUid);
        $this->assertTrue($this->dao->destroyCachePersonalByUid($this->creatorUid));
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
    public function destroyCacheUserCreatedTeams_returns_bool_after_caching_the_read(): void
    {
        $this->makeTeamRow($this->creatorUid, Teams::GENERAL);
        $user = $this->userWithUid($this->creatorUid);

        $this->dao->setCacheTTL(3600)->findUserCreatedTeams($user);
        $this->assertTrue($this->dao->destroyCacheUserCreatedTeams($user));
    }

    #[Test]
    public function updateTeamName_persists_new_name(): void
    {
        $id = $this->makeTeamRow($this->creatorUid, Teams::GENERAL);

        $team       = new TeamStruct(['id' => $id]);
        $team->name = 'Renamed Team';

        $returned = $this->dao->updateTeamName($team);
        $this->assertSame($team, $returned);

        $reloaded = $this->dao->findById($id);
        $this->assertSame('Renamed Team', $reloaded->name);
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
