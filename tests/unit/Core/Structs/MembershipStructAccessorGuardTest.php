<?php

namespace Matecat\Core\Structs;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Teams\MembershipStruct;
use Model\Teams\TeamDao;
use Model\Teams\TeamStruct;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;

/**
 * RED→GREEN guard tests for MembershipStruct::getUser / getTeam singleton removal (T2).
 *
 * Written BEFORE the implementation change (TDD strict RED step).
 * After T2: getUser(UserDao $userDao) and getTeam(TeamDao $teamDao) — injected DAOs used,
 * singleton never touched.
 */
class MembershipStructAccessorGuardTest extends AbstractTest
{
    private MembershipStruct $struct;

    public function setUp(): void
    {
        parent::setUp();

        $this->struct           = new MembershipStruct();
        $this->struct->id       = 1;
        $this->struct->id_team  = 10;
        $this->struct->uid      = 42;
        $this->struct->is_admin = false;
    }

    /**
     * getUser must use the injected UserDao, never the singleton.
     *
     * Before T2: getUser() calls `new UserDao(\Model\DataAccess\Database::obtain())` which hits Database::obtain() → poison fails.
     * After T2: getUser(UserDao $userDao) uses $userDao directly → singleton never touched → GREEN.
     */
    #[Test]
    public function getUser_uses_injected_dao_not_singleton(): void
    {
        $expectedUser            = new UserStruct();
        $expectedUser->uid       = 42;
        $expectedUser->email     = 'member@example.com';
        $expectedUser->first_name = 'John';
        $expectedUser->last_name  = 'Doe';

        $mockUserDao = $this->createMock(UserDao::class);
        $mockUserDao->method('setCacheTTL')->willReturnSelf();
        $mockUserDao->expects($this->once())
            ->method('getByUid')
            ->with(42)
            ->willReturn($expectedUser);

        // Poison singleton — must never be touched after T2
        $poison = $this->createMock(IDatabase::class);
        $poison->expects($this->never())->method('getConnection');
        $this->setDatabaseInstance($poison);

        // After T2: getUser(UserDao $userDao) — mandatory param
        $result = $this->struct->getUser($mockUserDao);

        $this->assertSame($expectedUser, $result);
    }

    /**
     * getUser returns memoized value on second call — DAO is only called once.
     */
    #[Test]
    public function getUser_memoizes_result(): void
    {
        $expectedUser        = new UserStruct();
        $expectedUser->uid   = 42;
        $expectedUser->email = 'member@example.com';

        $mockUserDao = $this->createMock(UserDao::class);
        $mockUserDao->method('setCacheTTL')->willReturnSelf();
        $mockUserDao->expects($this->once()) // only once even with two calls
            ->method('getByUid')
            ->willReturn($expectedUser);

        $poison = $this->createMock(IDatabase::class);
        $poison->expects($this->never())->method('getConnection');
        $this->setDatabaseInstance($poison);

        $result1 = $this->struct->getUser($mockUserDao);
        $result2 = $this->struct->getUser($mockUserDao);

        $this->assertSame($result1, $result2);
    }

    /**
     * getUser skips DAO when user was pre-set via setUser().
     */
    #[Test]
    public function getUser_skips_dao_when_user_already_set(): void
    {
        $preSet        = new UserStruct();
        $preSet->uid   = 42;
        $preSet->email = 'preset@example.com';
        $this->struct->setUser($preSet);

        $mockUserDao = $this->createMock(UserDao::class);
        $mockUserDao->expects($this->never())->method('getByUid');

        $poison = $this->createMock(IDatabase::class);
        $poison->expects($this->never())->method('getConnection');
        $this->setDatabaseInstance($poison);

        $result = $this->struct->getUser($mockUserDao);

        $this->assertSame($preSet, $result);
    }

    /**
     * getTeam must use the injected TeamDao, never the singleton.
     *
     * Before T2: getTeam() calls `new TeamDao(\Model\DataAccess\Database::obtain())` which hits Database::obtain() → poison fails.
     * After T2: getTeam(TeamDao $teamDao) uses $teamDao directly → singleton never touched → GREEN.
     */
    #[Test]
    public function getTeam_uses_injected_dao_not_singleton(): void
    {
        $expectedTeam       = new TeamStruct();
        $expectedTeam->id   = 10;
        $expectedTeam->name = 'My Team';

        $mockTeamDao = $this->createMock(TeamDao::class);
        $mockTeamDao->method('setCacheTTL')->willReturnSelf();
        $mockTeamDao->expects($this->once())
            ->method('fetchById')
            ->with(10, TeamStruct::class)
            ->willReturn($expectedTeam);

        // Poison singleton — must never be touched after T2
        $poison = $this->createMock(IDatabase::class);
        $poison->expects($this->never())->method('getConnection');
        $this->setDatabaseInstance($poison);

        // After T2: getTeam(TeamDao $teamDao) — mandatory param
        $result = $this->struct->getTeam($mockTeamDao);

        $this->assertSame($expectedTeam, $result);
    }

    /**
     * getTeam returns memoized value on second call — DAO is only called once.
     */
    #[Test]
    public function getTeam_memoizes_result(): void
    {
        $expectedTeam       = new TeamStruct();
        $expectedTeam->id   = 10;
        $expectedTeam->name = 'My Team';

        $mockTeamDao = $this->createMock(TeamDao::class);
        $mockTeamDao->method('setCacheTTL')->willReturnSelf();
        $mockTeamDao->expects($this->once()) // only once even with two calls
            ->method('fetchById')
            ->willReturn($expectedTeam);

        $poison = $this->createMock(IDatabase::class);
        $poison->expects($this->never())->method('getConnection');
        $this->setDatabaseInstance($poison);

        $result1 = $this->struct->getTeam($mockTeamDao);
        $result2 = $this->struct->getTeam($mockTeamDao);

        $this->assertSame($result1, $result2);
    }
}
