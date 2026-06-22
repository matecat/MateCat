<?php

namespace Matecat\Core\Controllers;

use Controller\API\V2\ActivityLogController;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\FeaturesBase\FeatureSet;
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
        $this->seedProject(self::BASE, $owner);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $owner, self::JOB_PASSWORD);
        $this->seedSegment(self::BASE);

        $conn = Database::obtain()->getConnection();
        $conn->exec(
            "INSERT IGNORE INTO activity_log (ID, id_project, id_job, action, ip, uid, event_date) VALUES ("
            . self::ACTIVITY_ID . ", " . $this->projectId(self::BASE) . ", " . $this->jobId(self::BASE)
            . ", 14, '127.0.0.1', " . $this->userId(self::BASE) . ", NOW())"
        );
    }

    /**
     * @throws Throwable
     */
    private function cleanTestData(): void
    {
        $conn = Database::obtain()->getConnection();
        $conn->exec("DELETE FROM activity_log WHERE ID = " . self::ACTIVITY_ID);
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

    // ─── lastOnJob ───

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
