<?php

namespace Matecat\Core\Model\Users;

use Matecat\TestHelpers\AbstractTest;
use Model\Jobs\JobDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Teams\TeamDao;
use Model\Teams\TeamStruct;
use Model\Users\RedeemableProject;
use Model\Users\UserStruct;

class RedeemableProjectTest extends AbstractTest
{
    private UserStruct $user;
    private ProjectDao $projectDao;
    private JobDao $jobDao;
    private TeamDao $teamDao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createStub(UserStruct::class);
        $this->projectDao = $this->createStub(ProjectDao::class);
        $this->jobDao = $this->createStub(JobDao::class);
        $this->teamDao = $this->createStub(TeamDao::class);
    }

    public function testConstructorRequiresInjectedDaos(): void
    {
        $params = (new \ReflectionMethod(RedeemableProject::class, '__construct'))->getParameters();

        foreach (['projectDao' => $params[3], 'jobDao' => $params[4]] as $name => $param) {
            $this->assertFalse($param->isOptional(), "$name must be a mandatory ctor dependency (no Database::obtain() fallback)");
            $this->assertFalse($param->allowsNull(), "$name ctor param must not be nullable");
        }
    }

    private function make(array &$session): RedeemableProject
    {
        return new RedeemableProject($this->user, $session, $this->teamDao, $this->projectDao, $this->jobDao);
    }

    public function testIsPresentReturnsFalseWhenNoSessionPid(): void
    {
        $session = [];
        $rp = $this->make($session);

        $this->assertFalse($rp->isPresent());
    }

    public function testIsPresentReturnsFalseWhenProjectNotFound(): void
    {
        $session = ['last_created_pid' => 999];
        $this->projectDao->method('findById')->willReturn(null);
        $rp = $this->make($session);

        $this->assertFalse($rp->isPresent());
    }

    public function testIsPresentReturnsTrueWhenProjectFound(): void
    {
        $session = ['last_created_pid' => 1];
        $project = new ProjectStruct();
        $this->projectDao->method('findById')->willReturn($project);
        $rp = $this->make($session);

        $this->assertTrue($rp->isPresent());
    }

    public function testIsRedeemableReturnsFalseByDefault(): void
    {
        $session = [];
        $rp = $this->make($session);

        $this->assertFalse($rp->isRedeemable());
    }

    public function testIsRedeemableReturnsTrueWhenSessionFlagSet(): void
    {
        $session = ['redeem_project' => true];
        $rp = $this->make($session);

        $this->assertTrue($rp->isRedeemable());
    }

    public function testIsRedeemableReturnsFalseWhenFlagNotTrue(): void
    {
        $session = ['redeem_project' => 'yes'];
        $rp = $this->make($session);

        $this->assertFalse($rp->isRedeemable());
    }

    public function testGetProjectReturnsNullInitially(): void
    {
        $session = [];
        $rp = $this->make($session);

        $this->assertNull($rp->getProject());
    }

    public function testGetProjectReturnsProjectAfterIsPresent(): void
    {
        $session = ['last_created_pid' => 1];
        $project = new ProjectStruct();
        $this->projectDao->method('findById')->willReturn($project);
        $rp = $this->make($session);

        $rp->isPresent();
        $this->assertSame($project, $rp->getProject());
    }

    public function testClearRemovesSessionKeys(): void
    {
        $session = ['redeem_project' => true, 'last_created_pid' => 1, 'other' => 'kept'];
        $rp = $this->make($session);

        $rp->clear();

        $this->assertArrayNotHasKey('redeem_project', $session);
        $this->assertArrayNotHasKey('last_created_pid', $session);
        $this->assertArrayHasKey('other', $session);
    }

    public function testRedeemCallsDaoWhenPresentAndRedeemable(): void
    {
        $project = new ProjectStruct();
        $project->id = 1;
        $session = ['last_created_pid' => 1, 'redeem_project' => true];

        $projectDao = $this->createMock(ProjectDao::class);
        $jobDao = $this->createMock(JobDao::class);
        $projectDao->method('findById')->willReturn($project);

        $team = new TeamStruct();
        $team->id = 10;
        $this->user->method('getEmail')->willReturn('test@example.com');
        $this->user->method('getPersonalTeam')->willReturn($team);
        $this->user->method('getUid')->willReturn(42);

        $projectDao->expects($this->once())->method('updateStruct');
        $jobDao->expects($this->once())->method('updateOwner');

        $rp = new RedeemableProject($this->user, $session, $this->teamDao, $projectDao, $jobDao);
        $rp->redeem();

        $this->assertEquals('test@example.com', $project->id_customer);
        $this->assertEquals(10, $project->id_team);
        $this->assertEquals(42, $project->id_assignee);
    }

    public function testRedeemSkipsWhenNotPresent(): void
    {
        $projectDao = $this->createMock(ProjectDao::class);
        $jobDao = $this->createMock(JobDao::class);
        $session = ['redeem_project' => true];
        $projectDao->expects($this->never())->method('updateStruct');
        $jobDao->expects($this->never())->method('updateOwner');

        $rp = new RedeemableProject($this->user, $session, $this->teamDao, $projectDao, $jobDao);
        $rp->redeem();
    }

    public function testRedeemSkipsWhenNotRedeemable(): void
    {
        $project = new ProjectStruct();
        $session = ['last_created_pid' => 1];
        $projectDao = $this->createMock(ProjectDao::class);
        $projectDao->method('findById')->willReturn($project);
        $projectDao->expects($this->never())->method('updateStruct');

        $rp = new RedeemableProject($this->user, $session, $this->teamDao, $projectDao, $this->jobDao);
        $rp->redeem();
    }

    public function testTryToRedeemCallsRedeemWhenEligible(): void
    {
        $project = new ProjectStruct();
        $project->id = 1;
        $session = ['last_created_pid' => 1, 'redeem_project' => true];

        $projectDao = $this->createMock(ProjectDao::class);
        $jobDao = $this->createMock(JobDao::class);
        $projectDao->method('findById')->willReturn($project);

        $team = new TeamStruct();
        $team->id = 10;
        $this->user->method('getEmail')->willReturn('test@example.com');
        $this->user->method('getPersonalTeam')->willReturn($team);
        $this->user->method('getUid')->willReturn(42);

        $projectDao->expects($this->once())->method('updateStruct');
        $jobDao->expects($this->once())->method('updateOwner');

        $rp = new RedeemableProject($this->user, $session, $this->teamDao, $projectDao, $jobDao);
        $rp->tryToRedeem();
    }

    public function testTryToRedeemDoesNothingWhenNotEligible(): void
    {
        $projectDao = $this->createMock(ProjectDao::class);
        $session = [];
        $projectDao->expects($this->never())->method('updateStruct');

        $rp = new RedeemableProject($this->user, $session, $this->teamDao, $projectDao, $this->jobDao);
        $rp->tryToRedeem();
    }

    public function testGetDestinationURLReturnsNullWhenNotPresent(): void
    {
        $session = [];
        $rp = $this->make($session);

        $this->assertNull($rp->getDestinationURL());
    }

    public function testGetDestinationURLReturnsUrlWhenPresent(): void
    {
        $project = new ProjectStruct();
        $project->name = 'My Project';
        $project->id = 42;
        $project->password = 'abc123';
        $session = ['last_created_pid' => 42];
        $this->projectDao->method('findById')->willReturn($project);

        $rp = $this->make($session);
        $url = $rp->getDestinationURL();

        $this->assertNotNull($url);
        $this->assertStringContainsString('/analyze/', $url);
        $this->assertStringContainsString('42-abc123', $url);
    }

    public function testGetProjectCachesResult(): void
    {
        $session = ['last_created_pid' => 1];
        $project = new ProjectStruct();
        $projectDao = $this->createMock(ProjectDao::class);
        $projectDao->expects($this->once())->method('findById')->willReturn($project);

        $rp = new RedeemableProject($this->user, $session, $this->teamDao, $projectDao, $this->jobDao);
        $rp->isPresent();
        $rp->isPresent();

        $this->assertSame($project, $rp->getProject());
    }
}
