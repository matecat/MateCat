<?php

use Model\Teams\MembershipDao;
use Model\Teams\MembershipStruct;
use Model\Teams\TeamDao;
use Model\Teams\TeamModel;
use Model\Teams\TeamStruct;
use Model\Users\UserStruct;
use TestHelpers\AbstractTest;
use Utils\Constants\Teams;

class TeamModelTest extends AbstractTest
{
    private ?UserStruct $user = null;
    private ?UserStruct $otherUser = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = Factory_User::create();
        $this->otherUser = Factory_User::create();
    }

    protected function tearDown(): void
    {
        if ($this->user !== null && $this->user->uid !== null) {
            $this->cleanupUserTeams($this->user->uid);
        }
        if ($this->otherUser !== null && $this->otherUser->uid !== null) {
            $this->cleanupUserTeams($this->otherUser->uid);
        }
        parent::tearDown();
    }

    private function cleanupUserTeams(int $uid): void
    {
        $conn = \Model\DataAccess\Database::obtain()->getConnection();
        $teamIds = $conn->query("SELECT id FROM teams WHERE created_by = $uid")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($teamIds as $teamId) {
            $conn->exec("DELETE FROM teams_users WHERE id_team = $teamId");
            $conn->exec("DELETE FROM teams WHERE id = $teamId");
        }
        $conn->exec("DELETE FROM users WHERE uid = $uid");
    }

    // ─── addMemberEmail / addMemberEmails ────────────────────────────────────

    public function test_addMemberEmail_stores_email(): void
    {
        $struct = new TeamStruct(['name' => 'test', 'type' => Teams::GENERAL, 'created_by' => $this->user->uid]);
        $model = new TeamModel($struct);

        $model->addMemberEmail('a@example.org');

        $ref = new ReflectionProperty($model, 'member_emails');
        $this->assertSame(['a@example.org'], $ref->getValue($model));
    }

    public function test_addMemberEmails_appends_multiple(): void
    {
        $struct = new TeamStruct(['name' => 'test', 'type' => Teams::GENERAL, 'created_by' => $this->user->uid]);
        $model = new TeamModel($struct);

        $model->addMemberEmails(['a@example.org', 'b@example.org']);

        $ref = new ReflectionProperty($model, 'member_emails');
        $this->assertSame(['a@example.org', 'b@example.org'], $ref->getValue($model));
    }

    // ─── removeMemberUids ────────────────────────────────────────────────────

    public function test_removeMemberUids_accumulates(): void
    {
        $struct = new TeamStruct(['name' => 'test', 'type' => Teams::GENERAL, 'created_by' => $this->user->uid]);
        $model = new TeamModel($struct);

        $model->removeMemberUids([1, 2]);
        $model->removeMemberUids([3]);

        $ref = new ReflectionProperty($model, 'uids_to_remove');
        $this->assertSame([1, 2, 3], $ref->getValue($model));
    }

    // ─── _checkType ──────────────────────────────────────────────────────────

    public function test_checkType_accepts_general(): void
    {
        $struct = new TeamStruct(['name' => 'test', 'type' => Teams::GENERAL, 'created_by' => $this->user->uid]);
        $model = new TeamModel($struct);

        $method = new ReflectionMethod($model, '_checkType');
        $method->invoke($model);

        $this->assertTrue(true); // no exception
    }

    public function test_checkType_accepts_personal(): void
    {
        $struct = new TeamStruct(['name' => 'test', 'type' => Teams::PERSONAL, 'created_by' => $this->user->uid]);
        $model = new TeamModel($struct);

        $method = new ReflectionMethod($model, '_checkType');
        $method->invoke($model);

        $this->assertTrue(true);
    }

    public function test_checkType_rejects_invalid_type(): void
    {
        $struct = new TeamStruct(['name' => 'test', 'type' => 'bogus', 'created_by' => $this->user->uid]);
        $model = new TeamModel($struct);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid Team Type");

        $method = new ReflectionMethod($model, '_checkType');
        $method->invoke($model);
    }

    // ─── _checkAddMembersToPersonalTeam ──────────────────────────────────────

    public function test_checkAddMembersToPersonalTeam_throws_for_personal(): void
    {
        $struct = new TeamStruct(['name' => 'test', 'type' => Teams::PERSONAL, 'created_by' => $this->user->uid]);
        $model = new TeamModel($struct);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Can not invite members to a Personal team");

        $method = new ReflectionMethod($model, '_checkAddMembersToPersonalTeam');
        $method->invoke($model);
    }

    public function test_checkAddMembersToPersonalTeam_allows_general(): void
    {
        $struct = new TeamStruct(['name' => 'test', 'type' => Teams::GENERAL, 'created_by' => $this->user->uid]);
        $model = new TeamModel($struct);

        $method = new ReflectionMethod($model, '_checkAddMembersToPersonalTeam');
        $method->invoke($model);

        $this->assertTrue(true);
    }

    // ─── _checkPersonalUnique ────────────────────────────────────────────────

    public function test_checkPersonalUnique_throws_when_personal_team_exists(): void
    {
        // Factory_User::create() already creates a personal team for $this->user
        $struct = new TeamStruct(['name' => 'personal', 'type' => Teams::PERSONAL, 'created_by' => $this->user->uid]);
        $model = new TeamModel($struct);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("User already has the personal team");

        $method = new ReflectionMethod($model, '_checkPersonalUnique');
        $method->invoke($model);
    }

    public function test_checkPersonalUnique_does_nothing_for_general_type(): void
    {
        $struct = new TeamStruct(['name' => 'test', 'type' => Teams::GENERAL, 'created_by' => $this->user->uid]);
        $model = new TeamModel($struct);

        $method = new ReflectionMethod($model, '_checkPersonalUnique');
        $method->invoke($model);

        $this->assertTrue(true);
    }

    // ─── _getRemovedMembersEmailList ─────────────────────────────────────────

    public function test_getRemovedMembersEmailList_excludes_current_user(): void
    {
        $struct = new TeamStruct(['name' => 'test', 'type' => Teams::GENERAL, 'created_by' => $this->user->uid]);
        $model = new TeamModel($struct);
        $model->setUser($this->user);

        $removedUsers = new ReflectionProperty($model, 'removed_users');
        $removedUsers->setValue($model, [$this->user, $this->otherUser]);

        $method = new ReflectionMethod($model, '_getRemovedMembersEmailList');
        $result = $method->invoke($model);

        $this->assertCount(1, $result);
        $this->assertSame($this->otherUser->uid, $result[0]->uid);
    }

    public function test_getRemovedMembersEmailList_empty_when_only_current_user(): void
    {
        $struct = new TeamStruct(['name' => 'test', 'type' => Teams::GENERAL, 'created_by' => $this->user->uid]);
        $model = new TeamModel($struct);
        $model->setUser($this->user);

        $removedUsers = new ReflectionProperty($model, 'removed_users');
        $removedUsers->setValue($model, [$this->user]);

        $method = new ReflectionMethod($model, '_getRemovedMembersEmailList');
        $result = $method->invoke($model);

        $this->assertEmpty($result);
    }

    // ─── getTeamId ───────────────────────────────────────────────────────────

    public function test_getTeamId_throws_when_id_is_null(): void
    {
        $struct = new TeamStruct(['name' => 'test', 'type' => Teams::GENERAL, 'created_by' => $this->user->uid]);
        // id is null by default
        $model = new TeamModel($struct);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Team must be persisted before this operation');

        $method = new ReflectionMethod($model, 'getTeamId');
        $method->invoke($model);
    }

    public function test_getTeamId_returns_id_when_set(): void
    {
        $struct = new TeamStruct(['name' => 'test', 'type' => Teams::GENERAL, 'created_by' => $this->user->uid]);
        $struct->id = 42;
        $model = new TeamModel($struct);

        $method = new ReflectionMethod($model, 'getTeamId');
        $this->assertSame(42, $method->invoke($model));
    }

    // ─── create (integration) ────────────────────────────────────────────────

    public function test_create_creates_team_with_members(): void
    {
        $struct = new TeamStruct([
            'name' => 'Integration Team',
            'created_by' => $this->user->uid,
            'type' => Teams::GENERAL
        ]);

        $model = new TeamModel($struct);
        $model->setUser($this->user);
        $model->addMemberEmail($this->otherUser->email);

        $result = $model->create();

        $this->assertNotNull($result->id);
        $this->assertSame('Integration Team', $result->name);
        $this->assertSame(Teams::GENERAL, $result->type);

        $members = $result->getMembers();
        $this->assertNotEmpty($members);

        $memberUids = array_map(fn(MembershipStruct $m) => $m->uid, $members);
        $this->assertContains($this->user->uid, $memberUids);
        $this->assertContains($this->otherUser->uid, $memberUids);

        // Cleanup
        $conn = \Model\DataAccess\Database::obtain()->getConnection();
        $conn->exec("DELETE FROM teams_users WHERE id_team = " . $result->id);
        $conn->exec("DELETE FROM teams WHERE id = " . $result->id);
    }

    public function test_create_rejects_personal_type_with_members(): void
    {
        $struct = new TeamStruct([
            'name' => 'Personal',
            'created_by' => $this->user->uid,
            'type' => Teams::PERSONAL
        ]);

        $model = new TeamModel($struct);
        $model->setUser($this->user);
        $model->addMemberEmail($this->otherUser->email);

        $this->expectException(InvalidArgumentException::class);
        $model->create();
    }

    public function test_create_rejects_invalid_type(): void
    {
        $struct = new TeamStruct([
            'name' => 'Bad Team',
            'created_by' => $this->user->uid,
            'type' => 'INVALID'
        ]);

        $model = new TeamModel($struct);
        $model->setUser($this->user);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid Team Type");
        $model->create();
    }

    // ─── updateMembers (integration) ─────────────────────────────────────────

    public function test_updateMembers_adds_new_member(): void
    {
        // Create a general team first
        $teamDao = new TeamDao();
        \Model\DataAccess\Database::obtain()->begin();
        $team = $teamDao->createUserTeam($this->user, [
            'type' => Teams::GENERAL,
            'name' => 'Update Test Team',
            'members' => []
        ]);
        \Model\DataAccess\Database::obtain()->commit();

        $model = new TeamModel($team);
        $model->setUser($this->user);
        $model->addMemberEmail($this->otherUser->email);

        $result = $model->updateMembers();

        $memberUids = array_map(fn(MembershipStruct $m) => $m->uid, $result);
        $this->assertContains($this->otherUser->uid, $memberUids);

        // Cleanup
        $conn = \Model\DataAccess\Database::obtain()->getConnection();
        $conn->exec("DELETE FROM teams_users WHERE id_team = " . $team->id);
        $conn->exec("DELETE FROM teams WHERE id = " . $team->id);
    }

    public function test_updateMembers_removes_member(): void
    {
        // Create a general team with both users
        $teamDao = new TeamDao();
        \Model\DataAccess\Database::obtain()->begin();
        $team = $teamDao->createUserTeam($this->user, [
            'type' => Teams::GENERAL,
            'name' => 'Remove Test Team',
            'members' => [$this->otherUser->email]
        ]);
        \Model\DataAccess\Database::obtain()->commit();

        $model = new TeamModel($team);
        $model->setUser($this->user);
        $model->removeMemberUids([$this->otherUser->uid]);

        $result = $model->updateMembers();

        $memberUids = array_map(fn(MembershipStruct $m) => $m->uid, $result);
        $this->assertNotContains($this->otherUser->uid, $memberUids);
        $this->assertContains($this->user->uid, $memberUids);

        // Cleanup
        $conn = \Model\DataAccess\Database::obtain()->getConnection();
        $conn->exec("DELETE FROM teams_users WHERE id_team = " . $team->id);
        $conn->exec("DELETE FROM teams WHERE id = " . $team->id);
    }

    public function test_updateMembers_throws_when_team_not_persisted(): void
    {
        $struct = new TeamStruct(['name' => 'test', 'type' => Teams::GENERAL, 'created_by' => $this->user->uid]);
        $model = new TeamModel($struct);
        $model->setUser($this->user);

        $this->expectException(RuntimeException::class);
        $model->updateMembers();
    }

    // ─── updateMembersProjectsCount (integration) ────────────────────────────

    public function test_updateMembersProjectsCount_returns_self(): void
    {
        $teamDao = new TeamDao();
        \Model\DataAccess\Database::obtain()->begin();
        $team = $teamDao->createUserTeam($this->user, [
            'type' => Teams::GENERAL,
            'name' => 'Projects Count Team',
            'members' => [$this->otherUser->email]
        ]);
        \Model\DataAccess\Database::obtain()->commit();

        $model = new TeamModel($team);
        $result = $model->updateMembersProjectsCount();

        $this->assertSame($model, $result);

        // Cleanup
        $conn = \Model\DataAccess\Database::obtain()->getConnection();
        $conn->exec("DELETE FROM teams_users WHERE id_team = " . $team->id);
        $conn->exec("DELETE FROM teams WHERE id = " . $team->id);
    }

    public function test_updateMembersProjectsCount_throws_when_team_not_persisted(): void
    {
        $struct = new TeamStruct(['name' => 'test', 'type' => Teams::GENERAL, 'created_by' => $this->user->uid]);
        $model = new TeamModel($struct);

        $this->expectException(RuntimeException::class);
        $model->updateMembersProjectsCount();
    }

    // ─── _getInvitedEmails ───────────────────────────────────────────────────

    public function test_getInvitedEmails_returns_only_non_member_emails(): void
    {
        // Create a team with both users as members
        $teamDao = new TeamDao();
        \Model\DataAccess\Database::obtain()->begin();
        $team = $teamDao->createUserTeam($this->user, [
            'type' => Teams::GENERAL,
            'name' => 'Invite Test',
            'members' => [$this->otherUser->email]
        ]);
        \Model\DataAccess\Database::obtain()->commit();

        $model = new TeamModel($team);
        $model->setUser($this->user);

        // Add emails: one existing member and one new
        $model->addMemberEmail($this->otherUser->email);
        $model->addMemberEmail('brand-new@example.org');

        // Set all_memberships from the team
        $allMemberships = new ReflectionProperty($model, 'all_memberships');
        $allMemberships->setValue($model, (new MembershipDao())->getMemberListByTeamId($team->id));

        $method = new ReflectionMethod($model, '_getInvitedEmails');
        $result = $method->invoke($model);

        $this->assertContains('brand-new@example.org', $result);
        $this->assertNotContains($this->otherUser->email, $result);

        // Cleanup
        $conn = \Model\DataAccess\Database::obtain()->getConnection();
        $conn->exec("DELETE FROM teams_users WHERE id_team = " . $team->id);
        $conn->exec("DELETE FROM teams WHERE id = " . $team->id);
    }

    // ─── _getNewMembershipEmailList ──────────────────────────────────────────

    public function test_getNewMembershipEmailList_excludes_current_user(): void
    {
        $struct = new TeamStruct([
            'name' => 'test team',
            'created_by' => $this->user->uid,
            'type' => Teams::GENERAL
        ]);

        $model = new TeamModel($struct);
        $model->setUser($this->user);
        $model->addMemberEmail($this->otherUser->email);

        $createdTeam = $model->create();

        $method = new ReflectionMethod($model, '_getNewMembershipEmailList');
        $result = $method->invoke($model);

        $this->assertCount(1, $result);
        $this->assertSame($this->otherUser->email, $result[0]->getUser()->email);

        // Cleanup
        $conn = \Model\DataAccess\Database::obtain()->getConnection();
        $conn->exec("DELETE FROM teams_users WHERE id_team = " . $createdTeam->id);
        $conn->exec("DELETE FROM teams WHERE id = " . $createdTeam->id);
    }

    // ─── create with only creator ────────────────────────────────────────────

    public function test_create_team_with_no_extra_members(): void
    {
        $struct = new TeamStruct([
            'name' => 'Solo Team',
            'created_by' => $this->user->uid,
            'type' => Teams::GENERAL
        ]);

        $model = new TeamModel($struct);
        $model->setUser($this->user);

        $result = $model->create();

        $this->assertNotNull($result->id);
        $members = $result->getMembers();
        $this->assertCount(1, $members);
        $this->assertSame($this->user->uid, $members[0]->uid);

        // Cleanup
        $conn = \Model\DataAccess\Database::obtain()->getConnection();
        $conn->exec("DELETE FROM teams_users WHERE id_team = " . $result->id);
        $conn->exec("DELETE FROM teams WHERE id = " . $result->id);
    }
}
