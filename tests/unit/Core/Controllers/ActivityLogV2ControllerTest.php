<?php

namespace Matecat\Core\Controllers;

use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\V2\ActivityLogController;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Throwable;
use Utils\Logger\MatecatLogger;

class TestableActivityLogV2Controller extends ActivityLogController
{
    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }

    protected function registerValidators(): void
    {
    }
}

/**
 * Real-DB suite for API/V2/ActivityLogController.
 *
 * Reserved ID block (Playbook §4): base = 9_041_000 (task N=41).
 *   base+1 project, base+2 job, base+3 segment, base+4 file, base+6 user.
 * Per-suite owner email: ctrltest_9041000@example.org.
 * Clean ONLY by reserved id; never by shared keys.
 */
#[AllowMockObjectsWithoutExpectations]
class ActivityLogV2ControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_041_000;
    private const string JOB_PASSWORD = 'actlogpw';
    private const int ACTIVITY_ID = 9_041_900;

    /** A logged-in user who is not a member of the project's team. */
    private const int OUTSIDER_UID = 9_041_950;

    /** A project owned by no team, to prove a null id_team is refused rather than trusted. */
    private const int NO_TEAM_PROJECT_ID = 9_041_960;

    /** @var ReflectionClass<ActivityLogController> */
    private ReflectionClass $reflector;
    private TestableActivityLogV2Controller $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanTestData();
        $this->seedTestData();

        $this->controller = new TestableActivityLogV2Controller();
        $this->reflector = new ReflectionClass(ActivityLogController::class);

        $this->requestStub = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->responseMock);
        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createMock(MatecatLogger::class));
        $this->reflector->getProperty('featureSet')->setValue($this->controller, new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));
        $this->reflector->getProperty('database')->setValue($this->controller, obtainTestDatabase());

        // Production runs LoginValidator before the action, so the user is always identified by the
        // time these methods execute. The test controller no-ops registerValidators(), so the tests
        // set the user explicitly to reproduce that precondition for the team-membership check.
        $this->setControllerUser($this->userId(self::BASE));
    }

    /**
     * @throws Throwable
     */
    protected function tearDown(): void
    {
        $this->cleanTestData();
        parent::tearDown();
    }

    /**
     * @throws Throwable
     */
    private function seedTestData(): void
    {
        $owner = $this->ownerEmail(self::BASE);

        $this->seedUser(self::BASE);
        $this->seedTeam(self::BASE);
        $this->seedMembership(self::BASE);
        $this->seedProject(self::BASE, $owner);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $owner, self::JOB_PASSWORD);
        $this->seedSegment(self::BASE);

        $conn = obtainTestDatabase()->getConnection();
        $conn->exec(
            "INSERT IGNORE INTO activity_log (ID, id_project, id_job, action, ip, uid, event_date) VALUES ("
            . self::ACTIVITY_ID . ", " . $this->projectId(self::BASE) . ", " . $this->jobId(self::BASE)
            . ", 14, '127.0.0.1', " . $this->userId(self::BASE) . ", NOW())"
        );

        // A project deliberately owned by no team, so the "null id_team" branch is exercised: a
        // project without a team has nobody to be a member of and must be refused. No log row is
        // needed — the team guard refuses before the log is ever read.
        $conn->exec(
            "INSERT IGNORE INTO projects (id, id_customer, password, name, create_date, status_analysis, id_team) "
            . "VALUES (" . self::NO_TEAM_PROJECT_ID . ", '$owner', 'projpw', 'CtrlNoTeamProject', NOW(), 'DONE', NULL)"
        );
    }

    /**
     * @throws Throwable
     */
    private function cleanTestData(): void
    {
        $conn = obtainTestDatabase()->getConnection();
        $conn->exec("DELETE FROM activity_log WHERE ID = " . self::ACTIVITY_ID);
        $conn->exec("DELETE FROM projects WHERE id = " . self::NO_TEAM_PROJECT_ID);
        $this->cleanFragments(self::BASE);
    }

    /**
     * Set request + controller params so the validators (which read both
     * $controller->params and $controller->getRequest()->param()) resolve.
     *
     * @param array<string, mixed> $params
     * @throws ReflectionException
     */
    private function setRequestParams(array $params): void
    {
        $serverParams = ['REQUEST_URI' => '/api/v2/activity', 'REQUEST_METHOD' => 'GET'];
        $this->requestStub = new Request($params, [], [], $serverParams);
        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
        $this->reflector->getProperty('params')->setValue($this->controller, $params);
    }

    /**
     * Stand in for the user LoginValidator would have identified in production. It sets the logged
     * flag as well: the flag is a typed property with no default, so a validator that reads it on a
     * controller built here fails on the uninitialized property rather than on the authorization
     * decision under test.
     *
     * @throws ReflectionException
     */
    private function setControllerUser(int $uid): void
    {
        $user = new UserStruct();
        $user->uid = $uid;
        $this->reflector->getProperty('user')->setValue($this->controller, $user);
        $this->reflector->getProperty('userIsLogged')->setValue($this->controller, true);
    }

    // ─── allOnProject ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function allOnProject_returns_seeded_activity_records(): void
    {
        $this->setRequestParams([
            'id_project' => (string) $this->projectId(self::BASE),
            'password'   => 'projpw',
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->allOnProject();

        $this->assertIsArray($captured);
        $this->assertCount(1, $captured);
        $this->assertSame(self::ACTIVITY_ID, $captured[0]['id']);
        $this->assertSame($this->jobId(self::BASE), $captured[0]['id_job']);
        $this->assertSame($this->projectId(self::BASE), $captured[0]['id_project']);
        $this->assertSame('Access to the Translate page', $captured[0]['action']);
        $this->assertSame($this->userId(self::BASE), $captured[0]['uid']);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function allOnProject_throws_not_found_for_wrong_password(): void
    {
        $this->setRequestParams([
            'id_project' => (string) $this->projectId(self::BASE),
            'password'   => 'wrong_password_xyz',
        ]);

        $this->expectException(NotFoundException::class);

        $this->controller->allOnProject();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function allOnProject_throws_not_found_when_password_empty(): void
    {
        $this->setRequestParams([
            'id_project' => (string) $this->projectId(self::BASE),
            'password'   => '',
        ]);

        $this->expectException(NotFoundException::class);

        $this->controller->allOnProject();
    }

    /**
     * The project id + password only prove the caller followed a shared link. The log lists the name,
     * email and IP of everyone who worked on the project, so a logged-in user who is not a member of
     * the owning team must be refused even with the correct password.
     *
     * @throws Throwable
     */
    #[Test]
    public function allOnProject_denies_a_non_member(): void
    {
        $this->setControllerUser(self::OUTSIDER_UID);
        $this->setRequestParams([
            'id_project' => (string) $this->projectId(self::BASE),
            'password'   => 'projpw',
        ]);

        $this->responseMock->expects($this->never())->method('json');

        $this->expectException(AuthorizationError::class);
        $this->expectExceptionCode(401);

        $this->controller->allOnProject();
    }

    /**
     * A project with no team has nobody to be a member of, so the null id_team is refused rather than
     * handed to the membership lookup.
     *
     * @throws Throwable
     */
    #[Test]
    public function allOnProject_denies_a_project_with_no_team(): void
    {
        $this->setRequestParams([
            'id_project' => (string) self::NO_TEAM_PROJECT_ID,
            'password'   => 'projpw',
        ]);

        $this->responseMock->expects($this->never())->method('json');

        $this->expectException(AuthorizationError::class);
        $this->expectExceptionCode(401);

        $this->controller->allOnProject();
    }

    // ─── lastOnProject ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function lastOnProject_returns_activity_key_with_last_record(): void
    {
        $this->setRequestParams([
            'id_project' => (string) $this->projectId(self::BASE),
            'password'   => 'projpw',
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->lastOnProject();

        $this->assertIsArray($captured);
        $this->assertArrayHasKey('activity', $captured);
        $this->assertCount(1, $captured['activity']);
        $this->assertSame(self::ACTIVITY_ID, $captured['activity'][0]['id']);
        $this->assertSame($this->jobId(self::BASE), $captured['activity'][0]['id_job']);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function lastOnProject_throws_not_found_for_wrong_password(): void
    {
        $this->setRequestParams([
            'id_project' => (string) $this->projectId(self::BASE),
            'password'   => 'wrong_password_xyz',
        ]);

        $this->expectException(NotFoundException::class);

        $this->controller->lastOnProject();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function lastOnProject_denies_a_non_member(): void
    {
        $this->setControllerUser(self::OUTSIDER_UID);
        $this->setRequestParams([
            'id_project' => (string) $this->projectId(self::BASE),
            'password'   => 'projpw',
        ]);

        $this->responseMock->expects($this->never())->method('json');

        $this->expectException(AuthorizationError::class);
        $this->expectExceptionCode(401);

        $this->controller->lastOnProject();
    }

    // ─── lastOnJob (job password capability — team membership intentionally not required) ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function lastOnJob_returns_activity_key_with_last_record(): void
    {
        $this->setRequestParams([
            'id_job'   => (string) $this->jobId(self::BASE),
            'password' => self::JOB_PASSWORD,
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->lastOnJob();

        $this->assertIsArray($captured);
        $this->assertArrayHasKey('activity', $captured);
        $this->assertCount(1, $captured['activity']);
        $this->assertSame(self::ACTIVITY_ID, $captured['activity'][0]['id']);
        $this->assertSame($this->jobId(self::BASE), $captured['activity'][0]['id_job']);
    }

    /**
     * The job password alone used to be enough here, so anyone holding a working link to one job could
     * read the name, email and IP of whoever last acted on it. The record this endpoint returns is the
     * same shape the project-wide reads restrict to the owning team, and no client in the tree calls
     * this endpoint on a job password alone, so it now carries the same restriction: the project's
     * owner, or a member of its team.
     *
     * @throws Throwable
     */
    #[Test]
    public function lastOnJob_denies_a_non_member(): void
    {
        $this->setControllerUser(self::OUTSIDER_UID);
        $this->setRequestParams([
            'id_job'   => (string) $this->jobId(self::BASE),
            'password' => self::JOB_PASSWORD,
        ]);

        $this->responseMock->expects($this->never())->method('json');

        $this->expectException(AuthorizationError::class);
        $this->expectExceptionCode(401);

        $this->controller->lastOnJob();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function lastOnJob_throws_not_found_for_wrong_password(): void
    {
        $this->setRequestParams([
            'id_job'   => (string) $this->jobId(self::BASE),
            'password' => 'wrong_password_xyz',
        ]);

        $this->expectException(NotFoundException::class);

        $this->controller->lastOnJob();
    }
}
