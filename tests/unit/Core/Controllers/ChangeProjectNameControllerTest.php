<?php

namespace Matecat\Core\Controllers;

use Controller\API\V2\ChangeProjectNameController;
use Exception;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\FeaturesBase\FeatureSet;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Utils\Logger\MatecatLogger;

/**
 * Real-DB suite for ChangeProjectNameController.
 *
 * Reserved ID block base = 9039000 (Playbook §4):
 *   9039001 project, 9039002 job, 9039003 segment, 9039004 file,
 *   9039005 team, 9039006 user/uid, 9039012 teams_users row.
 * Per-suite owner email: ctrltest_9039000@example.org.
 * Clean ONLY by reserved id; never by shared keys.
 */
class TestableChangeProjectNameController extends ChangeProjectNameController
{
    public function __construct()
    {
    }

    protected function registerValidators(): void
    {
    }
}

#[AllowMockObjectsWithoutExpectations]
class ChangeProjectNameControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9039000;
    private const string PROJECT_PASSWORD = 'cpnpw';

    private ReflectionClass $reflector;
    private TestableChangeProjectNameController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws ReflectionException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);
        $this->seedScenario();

        $this->controller = new TestableChangeProjectNameController();
        $this->reflector = new ReflectionClass(ChangeProjectNameController::class);

        $this->requestStub = new Request();
        $this->responseMock = $this->createMock(Response::class);
        $httpStatus = $this->createMock(\Klein\HttpStatus::class);
        $httpStatus->method('setCode')->willReturnSelf();
        $this->responseMock->method('status')->willReturn($httpStatus);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);

        $user = new UserStruct();
        $user->uid = $this->userId(self::BASE);
        $user->email = $this->ownerEmail(self::BASE);
        $user->first_name = 'Ctrl';
        $user->last_name = 'Tester';
        $this->setProp('user', $user);
        $this->setProp('userIsLogged', true);

        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));
        $this->setProp('database', obtainTestDatabase());
    }

    protected function tearDown(): void
    {
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    private function seedScenario(): void
    {
        $owner = $this->ownerEmail(self::BASE);
        $this->seedUser(self::BASE, $owner);
        $this->seedTeam(self::BASE);
        $this->seedMembership(self::BASE);
        $this->seedProject(self::BASE, $owner, self::PROJECT_PASSWORD);
    }

    /**
     * @throws ReflectionException
     */
    private function setProp(string $name, mixed $value): void
    {
        $prop = $this->reflector->getProperty($name);
        $prop->setValue($this->controller, $value);
    }

    private function setRequestParams(array $params): void
    {
        $serverParams = ['REQUEST_URI' => '/api/v2/projects/name', 'REQUEST_METHOD' => 'POST'];
        $this->requestStub = new Request([], $params, [], $serverParams);
        $this->setProp('request', $this->requestStub);
    }

    private function loadSeededProject(): ProjectStruct
    {
        return (new ProjectDao(obtainTestDatabase()))
            ->findByIdAndPassword($this->projectId(self::BASE), self::PROJECT_PASSWORD);
    }

    /**
     * @throws ReflectionException
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        return $this->reflector->getMethod($method)->invoke($this->controller, ...$args);
    }

    // ─── changeName() happy path ───

    #[Test]
    public function changeName_renames_project_and_returns_id_and_name_payload(): void
    {
        $project = $this->loadSeededProject();
        $this->setProp('project', $project);

        $newName = 'Renamed Ctrl Project';
        $this->setRequestParams([
            'id_project' => (string) $this->projectId(self::BASE),
            'password' => self::PROJECT_PASSWORD,
            'name' => $newName,
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->changeName();

        $this->assertIsArray($captured);
        $this->assertSame((string) $this->projectId(self::BASE), $captured['id']);
        $this->assertSame($newName, $captured['name']);

        // Verify the rename was actually persisted to the DB.
        $reloaded = $this->loadSeededProject();
        $this->assertSame($newName, $reloaded->name);
    }

    #[Test]
    public function changeName_falls_back_to_default_name_when_name_is_empty(): void
    {
        $project = $this->loadSeededProject();
        $this->setProp('project', $project);

        $this->setRequestParams([
            'id_project' => (string) $this->projectId(self::BASE),
            'password' => self::PROJECT_PASSWORD,
            'name' => '',
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->changeName();

        $this->assertIsArray($captured);
        $this->assertArrayHasKey('name', $captured);
        $this->assertNotSame('', $captured['name']);
    }

    // ─── changeName() failure paths ───

    #[Test]
    public function changeName_throws_when_id_project_is_missing(): void
    {
        $this->setRequestParams(['password' => self::PROJECT_PASSWORD]);

        $this->expectException(InvalidArgumentException::class);

        $this->controller->changeName();
    }

    #[Test]
    public function changeName_throws_when_password_is_missing(): void
    {
        $this->setRequestParams(['id_project' => (string) $this->projectId(self::BASE)]);

        $this->expectException(InvalidArgumentException::class);

        $this->controller->changeName();
    }

    #[Test]
    public function changeName_throws_runtime_when_project_not_loaded(): void
    {
        $this->setRequestParams([
            'id_project' => (string) $this->projectId(self::BASE),
            'password' => self::PROJECT_PASSWORD,
            'name' => 'Whatever',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Project not loaded');

        $this->controller->changeName();
    }

    // ─── checkUserPermissions() (private) ───

    #[Test]
    public function checkUserPermissions_passes_for_team_member(): void
    {
        $project = $this->loadSeededProject();
        $user = $this->controller->getUser();

        // No exception expected.
        $this->invokePrivate('checkUserPermissions', [$project, $user]);
        $this->assertTrue(true);
    }

    #[Test]
    public function checkUserPermissions_throws_when_project_has_no_team(): void
    {
        $project = new ProjectStruct();
        $project->id = $this->projectId(self::BASE);
        $project->id_team = null;
        $user = $this->controller->getUser();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Project has no team');

        $this->invokePrivate('checkUserPermissions', [$project, $user]);
    }

    #[Test]
    public function checkUserPermissions_throws_when_user_not_in_team(): void
    {
        $project = $this->loadSeededProject();

        $stranger = new UserStruct();
        $stranger->uid = 99999999;
        $stranger->email = 'stranger_not_seeded@example.org';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('does not belong to the right team');

        $this->invokePrivate('checkUserPermissions', [$project, $stranger]);
    }

    // ─── changeProjectName() (private) ───

    #[Test]
    public function changeProjectName_persists_new_name(): void
    {
        $newName = 'Private Path Rename';

        $this->invokePrivate('changeProjectName', [
            $this->projectId(self::BASE),
            self::PROJECT_PASSWORD,
            $newName,
        ]);

        $reloaded = $this->loadSeededProject();
        $this->assertSame($newName, $reloaded->name);
    }

    // ─── registerValidators() ───

    #[Test]
    public function registerValidators_appends_login_and_project_password_validators(): void
    {
        // Exercise the REAL registerValidators() (the Testable subclass stubs it
        // out). It appends a LoginValidator and a ProjectPasswordValidator whose
        // onSuccess closure is registered (not executed) at this stage.
        $this->controller->params = [
            'id_project' => (string) $this->projectId(self::BASE),
            'password' => self::PROJECT_PASSWORD,
        ];

        $register = $this->reflector->getMethod('registerValidators');
        $register->invoke($this->controller);

        $validatorsProp = (new ReflectionClass(\Controller\Abstracts\KleinController::class))
            ->getProperty('validators');
        /** @var array<\Controller\API\Commons\Validators\Base> $validators */
        $validators = $validatorsProp->getValue($this->controller);

        $this->assertCount(2, $validators);
        $this->assertInstanceOf(
            \Controller\API\Commons\Validators\LoginValidator::class,
            $validators[0]
        );
        $this->assertInstanceOf(
            \Controller\API\Commons\Validators\ProjectPasswordValidator::class,
            $validators[1]
        );
    }

    #[Test]
    public function registerValidators_onSuccess_closure_loads_project_into_controller(): void
    {
        // Drive the ProjectPasswordValidator success path so its onSuccess
        // closure (the assignment inside registerValidators) runs and sets the
        // controller's $project — this covers the closure body, not just its
        // registration.
        $this->controller->params = [
            'id_project' => (string) $this->projectId(self::BASE),
            'password' => self::PROJECT_PASSWORD,
        ];

        $this->reflector->getMethod('registerValidators')->invoke($this->controller);

        $validatorsProp = (new ReflectionClass(\Controller\Abstracts\KleinController::class))
            ->getProperty('validators');
        /** @var array<\Controller\API\Commons\Validators\Base> $validators */
        $validators = $validatorsProp->getValue($this->controller);

        // validators[1] is the ProjectPasswordValidator; running it executes the
        // onSuccess callback registered by registerValidators().
        $validators[1]->validate();

        $projectProp = $this->reflector->getProperty('project');
        /** @var ProjectStruct $loaded */
        $loaded = $projectProp->getValue($this->controller);

        $this->assertInstanceOf(ProjectStruct::class, $loaded);
        $this->assertSame($this->projectId(self::BASE), (int) $loaded->id);
    }
}
