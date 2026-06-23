<?php

namespace Matecat\Core\Controllers\Authentication;

use Controller\Abstracts\Authentication\UserProfileBuilder;
use Matecat\TestHelpers\AbstractTest;
use Model\ConnectedServices\ConnectedServiceDao;
use Model\Teams\MembershipDao;
use Model\Teams\TeamDao;
use Model\Teams\TeamStruct;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(UserProfileBuilder::class)]
class UserProfileBuilderTest extends AbstractTest
{
    /** @var MembershipDao&MockObject */
    private MembershipDao&MockObject $membershipDao;

    /** @var ConnectedServiceDao&MockObject */
    private ConnectedServiceDao&MockObject $connectedServiceDao;

    private UserProfileBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->membershipDao       = $this->createMock(MembershipDao::class);
        $this->connectedServiceDao = $this->createMock(ConnectedServiceDao::class);
        $userDao = $this->createStub(UserDao::class);
        $userDao->method('setCacheTTL')->willReturnSelf();
        $teamDao = $this->createStub(TeamDao::class);
        $this->builder = new UserProfileBuilder($this->membershipDao, $this->connectedServiceDao, $userDao, $teamDao);
    }

    private function makeUser(): UserStruct
    {
        $user             = new UserStruct();
        $user->uid        = 1;
        $user->email      = 'test@test.com';
        $user->first_name = 'Test';
        $user->last_name  = 'User';

        return $user;
    }

    #[Test]
    public function buildReturnsArrayWithEmptyTeams(): void
    {
        $this->membershipDao->method('findUserTeams')->willReturn([]);
        $this->connectedServiceDao->method('findServicesByUser')->willReturn([]);

        $result = $this->builder->build($this->makeUser());

        $this->assertIsArray($result);
    }

    #[Test]
    public function buildHandlesNullTeams(): void
    {
        // findUserTeams may return null — builder must coalesce to [].
        $this->membershipDao->method('findUserTeams')->willReturn(null);
        $this->connectedServiceDao->method('findServicesByUser')->willReturn([]);

        $result = $this->builder->build($this->makeUser());

        $this->assertIsArray($result);
    }

    #[Test]
    public function buildMapsEachTeamThroughTeamModel(): void
    {
        // A team with a non-existent id → updateMembersProjectsCount finds no
        // members and returns cleanly, exercising the array_map body.
        $team = new TeamStruct(['id' => 999999999, 'name' => 'Phantom', 'type' => 'general', 'created_at' => '2024-01-01 00:00:00', 'created_by' => 1]);
        $this->membershipDao->method('findUserTeams')->willReturn([$team]);
        $this->connectedServiceDao->method('findServicesByUser')->willReturn([]);

        $result = $this->builder->build($this->makeUser());

        $this->assertIsArray($result);
    }

    #[Test]
    public function buildQueriesBothDaosForTheGivenUser(): void
    {
        $user = $this->makeUser();

        $this->membershipDao->expects($this->once())
            ->method('findUserTeams')
            ->with($user)
            ->willReturn([]);
        $this->connectedServiceDao->expects($this->once())
            ->method('findServicesByUser')
            ->with($user)
            ->willReturn([]);

        $this->builder->build($user);
    }
}
