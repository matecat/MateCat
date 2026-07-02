<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers\Api\V2;

use Controller\API\V2\ProjectCompletionStatus;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Utils\Logger\MatecatLogger;

/**
 * Testable subclass: empty constructor bypasses Klein DI wiring so properties
 * can be injected via reflection (GDrive/OAuthControllerTest pattern). The
 * protected registerValidators()/validateRequest() are exposed as public
 * pass-throughs so the whole request lifecycle can be driven explicitly.
 */
class TestableProjectCompletionStatusController extends ProjectCompletionStatus
{
    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }

    public function callRegisterValidators(): void
    {
        $this->registerValidators();
    }

    public function callValidateRequest(): void
    {
        $this->validateRequest();
    }
}

/**
 * ProjectCompletionStatusV2ControllerTest (real-DB, reserved block 9_072_000).
 *
 * Reserved ID block base = 9_072_000 (range 9072000-9072099, hermetic).
 *   base+1 project, base+6 uid. Per-suite owner = ctrltest_9072000@example.org.
 *
 * Coverage strategy for lib/Controller/API/V2/ProjectCompletionStatus.php:
 *   - registerValidators() BOTH branches: with a `password` request param
 *     (ProjectPasswordValidator appended + its onSuccess closure fired) and
 *     without it (ProjectValidator-only path). Driven through the real
 *     validator chain against a seeded project whose `project_completion`
 *     feature is enabled via project_metadata, so onSuccess sets $this->project.
 *   - status() end-to-end: after successful validation, the inline
 *     `new ProjectCompletionStatusModel(..., new FeatureSet($db))->getStatus()`
 *     runs real chunk/completion DB logic. A project with no jobs exercises the
 *     full method (empty loop → completed=true) and covers the json response.
 *   - status() null-project guard: the `?? throw new RuntimeException` arm.
 */
#[AllowMockObjectsWithoutExpectations]
class ProjectCompletionStatusV2ControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_072_000;
    private const string PROJECT_PASSWORD = 'projpw';

    private ReflectionClass $reflector;
    private TestableProjectCompletionStatusController $controller;
    private Response&MockObject $responseMock;
    private IDatabase $realDb;
    private string $owner;

    /**
     * @throws ReflectionException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->owner  = $this->ownerEmail(self::BASE);
        $this->realDb = obtainTestDatabase();

        // Hermetic: clear then seed our reserved rows.
        $this->cleanFragments(self::BASE);
        $this->cleanMetadata();
        $this->seedProject(self::BASE, $this->owner, self::PROJECT_PASSWORD);
        $this->enableProjectCompletionFeature();

        $this->controller = new TestableProjectCompletionStatusController();
        $this->reflector  = new ReflectionClass(ProjectCompletionStatus::class);

        $this->responseMock = $this->createMock(Response::class);

        $user             = new UserStruct();
        $user->uid        = self::BASE + 6;
        $user->email      = $this->owner; // must equal project->id_customer for inProjectScope()
        $user->first_name = 'Completion';
        $user->last_name  = 'Tester';

        $this->setProp('response', $this->responseMock);
        $this->setProp('user', $user);
        $this->setProp('logger', $this->createStub(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet($this->realDb));
        $this->setProp('database', $this->realDb);
    }

    protected function tearDown(): void
    {
        $this->cleanFragments(self::BASE);
        $this->cleanMetadata();

        parent::tearDown();
    }

    // ─── helpers ───

    private function setProp(string $name, mixed $value): void
    {
        $p = $this->reflector->getProperty($name);
        $p->setValue($this->controller, $value);
    }

    private function getProp(string $name): mixed
    {
        return $this->reflector->getProperty($name)->getValue($this->controller);
    }

    /**
     * Wire request + the merged params array the way KleinController's
     * constructor normally would (bypassed by the empty Testable ctor).
     *
     * @param array<string, mixed> $params
     */
    private function wireRequest(array $params): void
    {
        $server  = ['REQUEST_URI' => '/api/v2/projects/status', 'REQUEST_METHOD' => 'GET'];
        $request = new Request($params, [], [], $server);
        $this->setProp('request', $request);
        $this->setProp('params', $params);
    }

    private function enableProjectCompletionFeature(): void
    {
        $projectId = $this->projectId(self::BASE);
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO project_metadata (id_project, `key`, value) "
            . "VALUES ($projectId, 'features', 'project_completion')"
        );
    }

    private function cleanMetadata(): void
    {
        $this->seedConnection()->exec(
            "DELETE FROM project_metadata WHERE id_project = " . $this->projectId(self::BASE)
        );
    }

    // ─── registerValidators(): password ABSENT branch + full status() flow ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function status_without_password_param_validates_and_returns_project_status(): void
    {
        $this->wireRequest(['id_project' => $this->projectId(self::BASE)]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $payload) use (&$captured): bool {
                $captured = $payload;

                return true;
            }));

        $this->controller->callRegisterValidators();
        $this->controller->callValidateRequest();
        $this->controller->status();

        $this->assertNotNull($captured);
        $this->assertArrayHasKey('project_status', $captured);
        $status = $captured['project_status'];
        $this->assertSame((int)$this->projectId(self::BASE), (int)$status['id']);
        // no jobs seeded → empty loop → completed
        $this->assertTrue($status['completed']);
        $this->assertSame([], $status['translate']);
        $this->assertSame([], $status['revise']);
    }

    // ─── registerValidators(): password PRESENT branch ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function status_with_password_param_uses_password_validator_and_returns_status(): void
    {
        $this->wireRequest([
            'id_project' => $this->projectId(self::BASE),
            'password'   => self::PROJECT_PASSWORD,
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $payload) use (&$captured): bool {
                $captured = $payload;

                return true;
            }));

        $this->controller->callRegisterValidators();
        $this->controller->callValidateRequest();

        // onSuccess of ProjectPasswordValidator must have populated $this->project.
        $this->assertNotNull($this->getProp('project'));

        $this->controller->status();

        $this->assertNotNull($captured);
        $this->assertArrayHasKey('project_status', $captured);
        $this->assertSame((int)$this->projectId(self::BASE), (int)$captured['project_status']['id']);
    }

    // ─── status(): null-project guard (the ?? throw arm) ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function status_throws_when_project_not_resolved(): void
    {
        $this->wireRequest(['id_project' => $this->projectId(self::BASE)]);

        // registerValidators() is called but validation is NOT run, so
        // $this->project stays null and status() must hit the throw arm.
        $this->controller->callRegisterValidators();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Project not found');

        $this->controller->status();
    }
}
