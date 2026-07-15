<?php

namespace Matecat\Core\View\API\V2\Json;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\Teams\MembershipStruct;
use Model\Teams\TeamStruct;
use Model\Users\UserDao;
use PHPUnit\Framework\Attributes\CoversClass;
use Utils\Constants\Teams;
use View\API\V2\Json\Team;

#[CoversClass(Team::class)]
class TeamTest extends AbstractTest
{
    private function makeTeam(int $id = 1, string $name = 'Test Team'): TeamStruct
    {
        $team             = new TeamStruct();
        $team->id         = $id;
        $team->name       = $name;
        $team->type       = Teams::PERSONAL;
        $team->created_by = 42;
        $team->created_at = '2024-01-15 10:00:00';

        return $team;
    }

    private function makeTestableTeam(?array $data = null): Team
    {
        $userDao = new UserDao(obtainTestDatabase());
        return new class ($userDao, $data) extends Team {
            /** @return array<string> */
            protected function getPendingInvitations(int $teamId): array
            {
                return [];
            }
        };
    }

    public function testConstructorAcceptsNullData(): void
    {
        $view = $this->makeTestableTeam();
        $this->assertInstanceOf(Team::class, $view);
    }

    public function testConstructorAcceptsArrayOfTeams(): void
    {
        $team = $this->makeTeam();
        $view = $this->makeTestableTeam([$team]);
        $this->assertInstanceOf(Team::class, $view);
    }

    public function testRenderItemReturnsExpectedKeys(): void
    {
        $team = $this->makeTeam(5, 'My Team');
        $view = $this->makeTestableTeam();

        $result = $view->renderItem($team);

        $this->assertSame(5, $result['id']);
        $this->assertSame('My Team', $result['name']);
        $this->assertSame(Teams::PERSONAL, $result['type']);
        $this->assertSame(42, $result['created_by']);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertArrayHasKey('pending_invitations', $result);
        $this->assertSame([], $result['pending_invitations']);
    }

    public function testRenderItemIncludesMembersWhenPresent(): void
    {
        $member           = new MembershipStruct();
        $member->id       = 1;
        $member->id_team  = 5;
        $member->uid      = 100;
        $member->is_admin = false;

        $user             = new \Model\Users\UserStruct();
        $user->uid        = 100;
        $user->email      = 'member@example.com';
        $user->first_name = 'Member';
        $user->last_name  = 'User';
        $member->setUser($user);
        $member->setUserMetadata([]);

        $team = $this->makeTeam(5);
        $team->setMembers([$member]);

        $view   = $this->makeTestableTeam();
        $result = $view->renderItem($team);

        $this->assertArrayHasKey('members', $result);
        $this->assertIsArray($result['members']);
        $this->assertCount(1, $result['members']);
    }

    public function testRenderItemOmitsMembersWhenEmpty(): void
    {
        $team   = $this->makeTeam();
        $view   = $this->makeTestableTeam();
        $result = $view->renderItem($team);

        $this->assertArrayNotHasKey('members', $result);
    }

    public function testRenderUsesConstructorDataWhenNoArgPassed(): void
    {
        $team1 = $this->makeTeam(1, 'Team One');
        $team2 = $this->makeTeam(2, 'Team Two');
        $view  = $this->makeTestableTeam([$team1, $team2]);

        $result = $view->render();

        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]['id']);
        $this->assertSame(2, $result[1]['id']);
    }

    public function testRenderUsesPassedDataOverConstructorData(): void
    {
        $team1  = $this->makeTeam(1, 'Team One');
        $team2  = $this->makeTeam(2, 'Team Two');
        $view   = $this->makeTestableTeam([$team1]);

        $result = $view->render([$team2]);

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]['id']);
    }

    public function testRenderWithNullDataAndNoConstructorDataReturnsEmpty(): void
    {
        $view   = $this->makeTestableTeam();
        $result = $view->render();

        $this->assertSame([], $result);
    }

    public function testRenderWithEmptyArrayReturnsEmpty(): void
    {
        $view   = $this->makeTestableTeam([]);
        $result = $view->render();

        $this->assertSame([], $result);
    }
}
