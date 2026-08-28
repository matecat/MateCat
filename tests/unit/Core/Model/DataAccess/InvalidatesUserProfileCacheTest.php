<?php

namespace Matecat\Core\Model\DataAccess;

use Controller\Abstracts\Authentication\UserStateStore;
use Matecat\TestHelpers\AbstractTest;
use Model\ConnectedServices\ConnectedServiceDao;
use Model\ConnectedServices\ConnectedServiceStruct;
use Model\DataAccess\IDatabase;
use Model\Teams\MembershipDao;
use Model\Teams\MembershipStruct;
use Model\Teams\TeamDao;
use Model\Teams\TeamStruct;
use Model\Users\MetadataDao;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionProperty;
use Utils\Registry\AppConfig;

/**
 * One test per DAO write boundary that must drop the cached user profile.
 *
 * These exist because the previous design — invalidating from the controllers — was maintained by
 * hand across nine call sites, so a future writer that forgot one produced a silently stale profile.
 * Each of these is mutation-checked: remove the hook from the DAO method and the matching test fails
 * on an unmet expectation, which is the only thing that makes the guarantee a property of the code.
 */
class InvalidatesUserProfileCacheTest extends AbstractTest
{
    private IDatabase $dbStub;
    private PDO $pdoStub;
    private PDOStatement $stmtStub;

    /** @var UserStateStore&MockObject */
    private UserStateStore&MockObject $store;

    protected function setUp(): void
    {
        parent::setUp();
        AppConfig::$SKIP_SQL_CACHE = true;

        [$this->dbStub, $this->pdoStub, $this->stmtStub] = $this->createDatabaseMock();

        $this->store = $this->createMock(UserStateStore::class);
    }

    protected function tearDown(): void
    {
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
    }

    /**
     * The store must be told exactly this uid, exactly once.
     */
    private function expectProfileDropped(int $uid): void
    {
        $this->store->expects($this->once())
            ->method('invalidateProfile')
            ->with($uid);
    }

    private function makeUser(int $uid = 77): UserStruct
    {
        $user             = new UserStruct();
        $user->uid        = $uid;
        $user->email      = 'boundary@example.com';
        $user->first_name = 'Boundary';
        $user->last_name  = 'User';
        $user->create_date = '2026-01-01 00:00:00';

        return $user;
    }

    private function makeService(int $uid = 77): ConnectedServiceStruct
    {
        $service             = new ConnectedServiceStruct();
        $service->id         = 5;
        $service->uid        = $uid;
        $service->service    = ConnectedServiceDao::GDRIVE_SERVICE;
        $service->email      = 'boundary@example.com';
        $service->name       = 'Boundary';
        $service->created_at = '2026-01-01 00:00:00';

        return $service;
    }

    // ─── UserDao ──────────────────────────────────────────────────────────

    #[Test]
    public function createUserDropsTheCachedProfile(): void
    {
        $this->pdoStub->method('lastInsertId')->willReturn('77');
        $this->stmtStub->method('fetchAll')->willReturn([$this->makeUser()]);

        $this->expectProfileDropped(77);

        (new UserDao(obtainTestDatabase()))
            ->setUserStateStore($this->store)
            ->createUser($this->makeUser());
    }

    #[Test]
    public function updateUserDropsTheCachedProfile(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([$this->makeUser()]);

        $this->expectProfileDropped(77);

        (new UserDao(obtainTestDatabase()))
            ->setUserStateStore($this->store)
            ->updateUser($this->makeUser());
    }

    #[Test]
    public function deleteUserDropsTheCachedProfile(): void
    {
        $this->stmtStub->method('rowCount')->willReturn(1);

        $this->expectProfileDropped(77);

        (new UserDao(obtainTestDatabase()))
            ->setUserStateStore($this->store)
            ->delete($this->makeUser());
    }

    /**
     * The gate matters: DELETE reports zero rows when the uid was already gone, and a profile does
     * not need dropping for a user whose row this call did not remove.
     */
    #[Test]
    public function deleteUserThatRemovedNothingDoesNotDropAnything(): void
    {
        $this->stmtStub->method('rowCount')->willReturn(0);

        $this->store->expects($this->never())->method('invalidateProfile');

        (new UserDao(obtainTestDatabase()))
            ->setUserStateStore($this->store)
            ->delete($this->makeUser());
    }

    // ─── MetadataDao ──────────────────────────────────────────────────────

    #[Test]
    public function metadataSetDropsTheCachedProfile(): void
    {
        $this->expectProfileDropped(77);

        (new MetadataDao(obtainTestDatabase()))
            ->setUserStateStore($this->store)
            ->set(77, 'some_key', 'some value');
    }

    #[Test]
    public function metadataDeleteDropsTheCachedProfile(): void
    {
        $this->expectProfileDropped(77);

        (new MetadataDao(obtainTestDatabase()))
            ->setUserStateStore($this->store)
            ->delete(77, 'some_key');
    }

    /**
     * The hooks sit on the writes precisely so they do not depend on a TTL: cacheTTL defaults to 0,
     * and at 0 DaoCacheTrait neither reads nor busts anything. A hook hung off destroyCache()
     * would therefore do nothing here, which is why the plan's earlier "hook the cache busts"
     * variant could not deliver the guarantee.
     */
    #[Test]
    public function theHookFiresOnADaoWithNoCacheTtl(): void
    {
        $dao = (new MetadataDao(obtainTestDatabase()))->setUserStateStore($this->store);

        $this->assertSame(0, (new ReflectionProperty($dao, 'cacheTTL'))->getValue($dao));

        $this->expectProfileDropped(77);

        $dao->set(77, 'some_key', 'some value');
    }

    // ─── MembershipDao ────────────────────────────────────────────────────

    /**
     * The one deliberate hook on a cache-bust rather than a write: team-shaped changes do not all
     * touch a membership row — a rename touches only the team — and this is the established fan-out
     * point, already called on membership insert and delete and already looped over every member of
     * a renamed team by TeamsController.
     */
    #[Test]
    public function destroyCacheUserTeamsDropsTheCachedProfile(): void
    {
        $this->expectProfileDropped(77);

        (new MembershipDao(obtainTestDatabase()))
            ->setUserStateStore($this->store)
            ->destroyCacheUserTeams($this->makeUser());
    }

    // ─── ConnectedServiceDao ──────────────────────────────────────────────

    #[Test]
    public function updateOauthTokenDropsTheCachedProfile(): void
    {
        $service = $this->createMock(ConnectedServiceStruct::class);
        $service->uid = 77;
        $service->expects($this->once())->method('setEncryptedAccessToken')->with('new-token');

        $this->expectProfileDropped(77);

        (new ConnectedServiceDao(obtainTestDatabase()))
            ->setUserStateStore($this->store)
            ->updateOauthToken('new-token', $service);
    }

    #[Test]
    public function setServiceExpiredDropsTheCachedProfile(): void
    {
        $this->expectProfileDropped(77);

        (new ConnectedServiceDao(obtainTestDatabase()))
            ->setUserStateStore($this->store)
            ->setServiceExpired(time(), $this->makeService());
    }

    #[Test]
    public function setDefaultServiceDropsTheCachedProfile(): void
    {
        $this->expectProfileDropped(77);

        (new ConnectedServiceDao(obtainTestDatabase()))
            ->setUserStateStore($this->store)
            ->setDefaultService($this->makeService());
    }

    #[Test]
    public function aDirectUpdateStructDropsTheCachedProfile(): void
    {
        // The path the live check caught: ConnectedServicesController::update() disables a service by
        // calling updateStruct() itself, so hooking only the three named write methods left it stale.
        $this->expectProfileDropped(77);

        (new ConnectedServiceDao(obtainTestDatabase()))
            ->setUserStateStore($this->store)
            ->updateStruct($this->makeService(), ['fields' => ['disabled_at']]);
    }

    #[Test]
    public function insertingAServiceDropsTheCachedProfile(): void
    {
        // The GDrive connect path inserts from outside the DAO, so a freshly connected service was
        // invisible until whichever named write method happened to run next.
        $this->pdoStub->method('lastInsertId')->willReturn('9');
        // buildInsertStatement() delegates to the database handle, which is a stub here, so the SQL it
        // would compose has to be supplied.
        $this->dbStub->method('buildInsertStatement')
            ->willReturn(['INSERT INTO connected_services ( uid ) VALUES ( :uid )', []]);

        $this->expectProfileDropped(77);

        (new ConnectedServiceDao(obtainTestDatabase()))
            ->setUserStateStore($this->store)
            ->insertStruct($this->makeService());
    }

    // ─── team-scoped fan-out ──────────────────────────────────────────────

    /**
     * @param list<int> $uids
     */
    private function stubTeamMembers(array $uids): void
    {
        $members = [];
        foreach ($uids as $uid) {
            $member          = new MembershipStruct();
            $member->id      = $uid;
            $member->id_team = 3;
            $member->uid     = $uid;
            $members[]       = $member;
        }

        $this->stmtStub->method('fetchAll')->willReturn($members);
    }

    /**
     * Every member of a team carries the whole member list inside their own profile, so a membership
     * change makes all of them stale — not only the member who moved. destroyCacheUserTeams() covers
     * the one whose row changed; this covers the rest, which is what the two deleted
     * TeamMembersController calls used to do for whoever was making the change.
     */
    #[Test]
    public function aMembershipChangeDropsTheProfileOfEveryMemberOfThatTeam(): void
    {
        $this->stubTeamMembers([77, 88, 99]);

        $dropped = [];
        $this->store->expects($this->exactly(3))
            ->method('invalidateProfile')
            ->willReturnCallback(function (int $uid) use (&$dropped) {
                $dropped[] = $uid;

                return true;
            });

        (new MembershipDao(obtainTestDatabase()))
            ->setUserStateStore($this->store)
            ->destroyCacheForListByTeamId(3);

        $this->assertSame([77, 88, 99], $dropped);
    }

    /**
     * The per-member project counts are rendered into every member's profile, and ProjectModel calls
     * this for both the old and the new team when a project moves or is reassigned.
     */
    #[Test]
    public function aProjectCountChangeDropsTheProfileOfEveryMemberOfThatTeam(): void
    {
        $this->stubTeamMembers([77, 88]);

        $dropped = [];
        $this->store->expects($this->exactly(2))
            ->method('invalidateProfile')
            ->willReturnCallback(function (int $uid) use (&$dropped) {
                $dropped[] = $uid;

                return true;
            });

        $team     = new TeamStruct();
        $team->id = 3;

        (new TeamDao(obtainTestDatabase()))
            ->setUserStateStore($this->store)
            ->destroyCacheAssigneeWithProjectsByTeam($team);

        $this->assertSame([77, 88], $dropped);
    }

    // ─── the seam itself ──────────────────────────────────────────────────

    /**
     * Why the hook is an injected collaborator and not a static call: the store reaches Redis
     * through DaoCacheTrait, which opens its connection on demand, so a hard-wired call would give
     * every DAO write test above a live connection to the application Redis database. Six of these
     * boundaries needed no Redis at all before this change.
     */
    #[Test]
    public function theStoreIsSubstitutableAndTheSetterIsChainable(): void
    {
        $dao = new UserDao(obtainTestDatabase());

        // Nothing is written here, so nothing may be invalidated either.
        $this->store->expects($this->never())->method('invalidateProfile');

        $this->assertSame($dao, $dao->setUserStateStore($this->store));
    }
}
