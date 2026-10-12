<?php

namespace Matecat\Core\Model\JobSplitMerge;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\JobSplitMerge\JobSplitMergeManager;
use Model\JobSplitMerge\JobSplitMergeService;
use Model\JobSplitMerge\SplitMergeProjectData;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;

/**
 * The manager is a delegation layer over JobSplitMergeService. What matters here is which actor it
 * hands down: the acting user, and no longer the uid the DTO happens to carry.
 */
class JobSplitMergeManagerTest extends AbstractTest
{
    private IDatabase $dbStub;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->dbStub, , $stmtStub] = $this->createDatabaseMock();
        $stmtStub->method('fetchAll')->willReturn([]);
    }

    private function makeActor(int $uid = 21): UserStruct
    {
        $user = new UserStruct();
        $user->uid = $uid;
        $user->email = 'owner@example.org';

        return $user;
    }

    private function makeManager(UserStruct $actor, JobSplitMergeService $service): JobSplitMergeManager
    {
        $project = new ProjectStruct();
        $project->id = 41;
        $project->id_customer = 'owner@example.org';

        return new class ($project, $this->dbStub, null, $actor, $service) extends JobSplitMergeManager {
            public function __construct(
                ProjectStruct $project,
                IDatabase $database,
                ?\Utils\Session\SessionStore $session,
                UserStruct $actingUser,
                private readonly JobSplitMergeService $injectedService,
            ) {
                parent::__construct($project, $database, $session, $actingUser);
            }

            protected function getJobSplitMergeService(): JobSplitMergeService
            {
                return $this->injectedService;
            }
        };
    }

    #[Test]
    public function applySplitPassesTheActingUserAsTheActor(): void
    {
        $actor = $this->makeActor();
        $data = new SplitMergeProjectData(41, 'owner@example.org');

        $service = $this->createMock(JobSplitMergeService::class);
        $service->expects($this->once())
            ->method('applySplit')
            ->with($data, $actor);

        $this->makeManager($actor, $service)->applySplit($data);
    }

    #[Test]
    public function mergeAllPassesTheActingUserAsTheActor(): void
    {
        $actor = $this->makeActor(uid: 34);
        $data = new SplitMergeProjectData(41, 'owner@example.org');
        $chunks = [new JobStruct(), new JobStruct()];

        $service = $this->createMock(JobSplitMergeService::class);
        $service->expects($this->once())
            ->method('mergeALL')
            ->with($data, $chunks, $actor);

        $this->makeManager($actor, $service)->mergeALL($data, $chunks);
    }

    #[Test]
    public function getProjectDataCarriesTheProjectIdentity(): void
    {
        $data = $this->makeManager($this->makeActor(), $this->createStub(JobSplitMergeService::class))
            ->getProjectData();

        self::assertSame(41, $data->idProject);
        self::assertSame('owner@example.org', $data->idCustomer);
    }
}
