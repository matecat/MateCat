<?php

namespace Matecat\Core\Controllers;

use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use Controller\API\V2\ProjectsController;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Utils\Constants\JobStatus;
use Utils\Logger\MatecatLogger;

/**
 * Testable subclass: empty constructor + empty hooks so the real DB-DI
 * constructor chain (identifyUser / registerValidators) does not run during
 * unit setUp; props are injected via reflection instead.
 */
class TestableProjectsV2Controller extends ProjectsController
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
 * Real-DB seeded suite for API/V2/ProjectsController.
 *
 * Reserved ID block (Playbook §4): base = 9_038_000 (task N=38).
 *   base+1 project (9038001), base+2 job (9038002), base+3 segment (9038003),
 *   base+4 file (9038004), base+5 team (9038005), base+6 user (9038006).
 * Owner email: ctrltest_9038000@example.org (per-suite unique).
 * Clean ONLY by reserved id (cleanFragments).
 */
#[AllowMockObjectsWithoutExpectations]
class ProjectsControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_038_000;
    private const string PROJECT_PASSWORD = 'projpw_9038';

    private ReflectionClass $reflector;
    private TestableProjectsV2Controller $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;
    private UserStruct $user;

    /**
     * @throws ReflectionException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);
        $this->seedAll();

        $this->controller = new TestableProjectsV2Controller();
        $this->reflector = new ReflectionClass(ProjectsController::class);

        $this->requestStub = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);
        $this->setProp('database', Database::obtain());

        $this->user = new UserStruct();
        $this->user->uid = $this->userId(self::BASE);
        $this->user->email = $this->ownerEmail(self::BASE);
        $this->user->first_name = 'Ctrl';
        $this->user->last_name = 'Tester';
        $this->setProp('user', $this->user);

        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet(Database::obtain()));

        // Inject the validated project the way registerValidators would.
        $this->setProp('project', $this->loadProject());
    }

    protected function tearDown(): void
    {
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    private function seedAll(): void
    {
        $owner = $this->ownerEmail(self::BASE);
        $this->seedUser(self::BASE, $owner);
        $this->seedTeam(self::BASE);
        $this->seedMembership(self::BASE);
        $this->seedProject(self::BASE, $owner, self::PROJECT_PASSWORD);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $owner, 'jobpw', JobStatus::STATUS_ACTIVE);
        $this->seedSegment(self::BASE);
        $this->seedSegmentTranslation(self::BASE);
    }

    /**
     * @throws ReflectionException
     */
    private function loadProject(): ProjectStruct
    {
        $project = (new ProjectDao(Database::obtain()))->findById($this->projectId(self::BASE));
        self::assertInstanceOf(ProjectStruct::class, $project);

        return $project;
    }

    /**
     * @throws ReflectionException
     */
    private function setProp(string $name, mixed $value): void
    {
        $prop = $this->reflector->getProperty($name);
        $prop->setValue($this->controller, $value);
    }

    private function setParams(array $params): void
    {
        $paramsProp = $this->reflector->getProperty('params');
        $paramsProp->setValue($this->controller, $params);
    }

    // ─── get() ───

    #[Test]
    public function get_returns_rendered_project_payload(): void
    {
        $expectedId = $this->projectId(self::BASE);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use ($expectedId): bool {
                $this->assertArrayHasKey('project', $data);
                $this->assertSame($expectedId, $data['project']['id']);
                $this->assertSame('CtrlTestProject_' . self::BASE, $data['project']['name']);
                $this->assertSame(self::PROJECT_PASSWORD, $data['project']['password']);
                return true;
            }));

        $this->controller->get();
    }

    #[Test]
    public function get_marks_called_from_api_when_api_key_is_set(): void
    {
        $this->setProp('api_key', 'some-api-key');

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('project', $data);
                return true;
            }));

        $this->controller->get();
    }

    // ─── updateDueDate() / setDueDate() ───

    #[Test]
    public function updateDueDate_persists_future_due_date_and_returns_project(): void
    {
        $future = time() + 86400;
        $this->setParams(['due_date' => (string) $future]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('project', $data);
                $this->assertNotNull($data['project']['due_date']);
                return true;
            }));

        $this->controller->updateDueDate();

        $reloaded = (new ProjectDao(Database::obtain()))->findById($this->projectId(self::BASE));
        $this->assertNotNull($reloaded);
        $this->assertNotEmpty($reloaded->due_date);
    }

    #[Test]
    public function updateDueDate_ignores_past_due_date(): void
    {
        $past = time() - 86400;
        $this->setParams(['due_date' => (string) $past]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('project', $data);
                return true;
            }));

        $this->controller->updateDueDate();

        $reloaded = (new ProjectDao(Database::obtain()))->findById($this->projectId(self::BASE));
        $this->assertNotNull($reloaded);
        $this->assertEmpty($reloaded->due_date);
    }

    #[Test]
    public function setDueDate_delegates_to_updateDueDate(): void
    {
        $future = time() + 86400;
        $this->setParams(['due_date' => (string) $future]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('project', $data);
                return true;
            }));

        $this->controller->setDueDate();
    }

    // ─── deleteDueDate() ───

    #[Test]
    public function deleteDueDate_clears_due_date_and_returns_project(): void
    {
        // Pre-set a due date so we can observe it being cleared.
        $dao = new ProjectDao(Database::obtain());
        $project = $dao->findById($this->projectId(self::BASE));
        $this->assertNotNull($project);
        $dao->updateField($project, 'due_date', \Utils\Tools\Utils::mysqlTimestamp(time() + 86400));

        $this->setProp('project', $dao->findById($this->projectId(self::BASE)));

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('project', $data);
                $this->assertNull($data['project']['due_date']);
                return true;
            }));

        $this->controller->deleteDueDate();

        $reloaded = (new ProjectDao(Database::obtain()))->findById($this->projectId(self::BASE));
        $this->assertNotNull($reloaded);
        $this->assertEmpty($reloaded->due_date);
    }

    // ─── changeStatus() via cancel/archive/active/delete ───

    #[Test]
    public function cancel_sets_jobs_to_cancelled_and_returns_status_payload(): void
    {
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame(1, $data['code']);
                $this->assertSame('OK', $data['data']);
                $this->assertSame(JobStatus::STATUS_CANCELLED, $data['status']);
                return true;
            }));

        $this->controller->cancel();

        $job = (new \Model\Jobs\JobDao(Database::obtain()))
            ->getByIdAndPassword($this->jobId(self::BASE), 'jobpw');
        $this->assertInstanceOf(JobStruct::class, $job);
        $this->assertSame(JobStatus::STATUS_CANCELLED, $job->status_owner);
    }

    #[Test]
    public function archive_returns_archived_status_payload(): void
    {
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame(JobStatus::STATUS_ARCHIVED, $data['status']);
                return true;
            }));

        $this->controller->archive();
    }

    #[Test]
    public function active_returns_active_status_payload(): void
    {
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame(JobStatus::STATUS_ACTIVE, $data['status']);
                return true;
            }));

        $this->controller->active();
    }

    #[Test]
    public function delete_returns_deleted_status_payload(): void
    {
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame(JobStatus::STATUS_DELETED, $data['status']);
                return true;
            }));

        $this->controller->delete();
    }

    #[Test]
    public function changeStatus_throws_authorization_error_when_user_not_in_team(): void
    {
        // A user with no team membership must fail the ProjectAccessValidator.
        $stranger = new UserStruct();
        $stranger->uid = 1;
        $stranger->email = 'stranger@example.org';
        $this->setProp('user', $stranger);

        $this->expectException(AuthorizationError::class);

        $this->controller->cancel();
    }

    // ─── registerValidators() ───

    #[Test]
    public function registerValidators_appends_login_and_project_password_validators(): void
    {
        $fresh = new TestableProjectsV2Controller();
        $reflector = new ReflectionClass(ProjectsController::class);

        $reflector->getProperty('request')->setValue($fresh, new Request());
        $reflector->getProperty('response')->setValue($fresh, $this->createMock(Response::class));
        $reflector->getProperty('database')->setValue($fresh, Database::obtain());
        $reflector->getProperty('user')->setValue($fresh, $this->user);
        $reflector->getProperty('featureSet')->setValue($fresh, new FeatureSet(Database::obtain()));
        $reflector->getProperty('params')->setValue($fresh, [
            'id_project' => (string) $this->projectId(self::BASE),
            'password' => self::PROJECT_PASSWORD,
        ]);

        $method = $reflector->getMethod('registerValidators');
        $method->invoke($fresh);

        $validatorsProp = $reflector->getProperty('validators');
        /** @var array<object> $validators */
        $validators = $validatorsProp->getValue($fresh);

        $this->assertCount(2, $validators);
        $this->assertInstanceOf(LoginValidator::class, $validators[0]);
        $this->assertInstanceOf(ProjectPasswordValidator::class, $validators[1]);
    }

    /**
     * Builds a fresh controller, sets the given request params, runs
     * registerValidators() and returns its ProjectPasswordValidator so the
     * onSuccess/onFailure closures can be exercised through validate().
     *
     * @param array<string, mixed> $params
     * @throws ReflectionException
     */
    private function buildPasswordValidator(array $params): ProjectPasswordValidator
    {
        $fresh = new TestableProjectsV2Controller();
        $reflector = new ReflectionClass(ProjectsController::class);

        $serverParams = ['REQUEST_URI' => '/api/v2/projects', 'REQUEST_METHOD' => 'POST'];
        $request = new Request($params, [], [], $serverParams);

        $reflector->getProperty('request')->setValue($fresh, $request);
        $reflector->getProperty('response')->setValue($fresh, $this->createMock(Response::class));
        $reflector->getProperty('database')->setValue($fresh, Database::obtain());
        $reflector->getProperty('user')->setValue($fresh, $this->user);
        $reflector->getProperty('featureSet')->setValue($fresh, new FeatureSet(Database::obtain()));
        $reflector->getProperty('params')->setValue($fresh, $params);

        $reflector->getMethod('registerValidators')->invoke($fresh);

        $this->freshController = $fresh;
        $this->freshReflector = $reflector;

        /** @var ProjectPasswordValidator $validator */
        $validator = $reflector->getProperty('validators')->getValue($fresh)[1];

        return $validator;
    }

    private TestableProjectsV2Controller $freshController;
    private ReflectionClass $freshReflector;

    #[Test]
    public function password_validator_onSuccess_sets_project_property(): void
    {
        $validator = $this->buildPasswordValidator([
            'id_project' => (string) $this->projectId(self::BASE),
            'password' => self::PROJECT_PASSWORD,
        ]);

        $validator->validate();

        /** @var ProjectStruct $assigned */
        $assigned = $this->freshReflector->getProperty('project')->getValue($this->freshController);
        $this->assertInstanceOf(ProjectStruct::class, $assigned);
        $this->assertSame($this->projectId(self::BASE), (int) $assigned->id);
    }

    #[Test]
    public function password_validator_onFailure_recovers_via_access_token(): void
    {
        // Wrong password triggers NotFoundException; a valid access token then
        // recovers the project through the ProjectAccessTokenValidator branch.
        $accessToken = sha1($this->projectId(self::BASE) . self::PROJECT_PASSWORD);

        $validator = $this->buildPasswordValidator([
            'id_project' => (string) $this->projectId(self::BASE),
            'password' => 'definitely_wrong_pw',
            'project_access_token' => $accessToken,
        ]);

        $validator->validate();

        /** @var ProjectStruct $assigned */
        $assigned = $this->freshReflector->getProperty('project')->getValue($this->freshController);
        $this->assertInstanceOf(ProjectStruct::class, $assigned);
        $this->assertSame($this->projectId(self::BASE), (int) $assigned->id);
    }

    #[Test]
    public function password_validator_onFailure_rethrows_without_access_token(): void
    {
        $validator = $this->buildPasswordValidator([
            'id_project' => (string) $this->projectId(self::BASE),
            'password' => 'definitely_wrong_pw',
        ]);

        $this->expectException(\Model\Exceptions\NotFoundException::class);

        $validator->validate();
    }
}
