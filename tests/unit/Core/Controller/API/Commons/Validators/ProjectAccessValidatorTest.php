<?php

namespace Matecat\Core\Controller\API\Commons\Validators;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\Commons\Validators\ProjectAccessValidator;
use Klein\Request;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\Projects\ProjectStruct;
use Model\Teams\TeamStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

/**
 * Minimal controller exposing the seams ProjectAccessValidator touches:
 * getRequest(), getUser(), and setTeam() (so the method_exists() branch fires).
 */
class ProjectAccessValidatorTestController extends KleinController
{
    public ?TeamStruct $capturedTeam = null;

    public function __construct()
    {
    }

    public function setTeam(TeamStruct $team): void
    {
        $this->capturedTeam = $team;
    }
}

/**
 * Minimal controller WITHOUT setTeam — exercises the branch where method_exists() returns false.
 */
class ProjectAccessValidatorTestControllerNoSetTeam extends KleinController
{
    public function __construct()
    {
    }
}

/**
 * Real-DB suite. Reserved ID block base = 9_930_000.
 */
class ProjectAccessValidatorTest extends AbstractTest
{
    private const int B = 9_930_000;
    private const int UID = self::B;
    private const int TEAM_ID = self::B + 1;
    private const string TEAM_NAME = 'CtrlTestTeam9930000';
    private const string EMAIL = 'ctrltest_9930000@example.org';

    private ProjectAccessValidatorTestController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanTestData();
        $this->seedTestData();

        $this->controller = new ProjectAccessValidatorTestController();
        $this->setCtrlProp($this->controller, 'request', $this->makeRequest());
        $this->setCtrlProp($this->controller, 'database', Database::obtain());

        $user = new UserStruct();
        $user->uid = self::UID;
        $user->email = self::EMAIL;
        $this->setCtrlProp($this->controller, 'user', $user);
    }

    protected function tearDown(): void
    {
        $this->cleanTestData();
        parent::tearDown();
    }

    private function setCtrlProp(KleinController $ctrl, string $name, mixed $value): void
    {
        $c = new ReflectionClass(KleinController::class);
        while ($c !== false && !$c->hasProperty($name)) {
            $c = $c->getParentClass();
        }
        $p = $c->getProperty($name);
        $p->setAccessible(true);
        $p->setValue($ctrl, $value);
    }

    private function makeRequest(): Request
    {
        return new Request([], [], [], ['REQUEST_URI' => '/api/v2/projects', 'REQUEST_METHOD' => 'GET']);
    }

    private function makeProjectStruct(int $id_team): ProjectStruct
    {
        $project = new ProjectStruct();
        $project->id_team = $id_team;

        return $project;
    }

    private function seedTestData(): void
    {
        $conn = Database::obtain()->getConnection();
        $conn->exec("INSERT INTO users (uid, email, first_name, last_name) VALUES (" . self::UID . ", '" . self::EMAIL . "', 'T', 'U')");
        $conn->exec("INSERT INTO teams (id, name, created_by) VALUES (" . self::TEAM_ID . ", '" . self::TEAM_NAME . "', " . self::UID . ")");
        $conn->exec("INSERT INTO teams_users (uid, id_team, is_admin) VALUES (" . self::UID . ", " . self::TEAM_ID . ", 1)");
    }

    private function cleanTestData(): void
    {
        $conn = Database::obtain()->getConnection();
        $conn->exec("DELETE FROM teams_users WHERE id_team = " . self::TEAM_ID);
        $conn->exec("DELETE FROM teams WHERE id = " . self::TEAM_ID);
        $conn->exec("DELETE FROM users WHERE uid = " . self::UID);
    }

    // ─── happy path: user belongs to the team, setTeam is invoked ───

    #[Test]
    public function validates_project_access_and_invokes_set_team(): void
    {
        $project = $this->makeProjectStruct(self::TEAM_ID);
        $validator = new ProjectAccessValidator($this->controller, $project);
        $validator->validate();

        $this->assertInstanceOf(TeamStruct::class, $this->controller->capturedTeam);
        $this->assertSame(self::TEAM_ID, $this->controller->capturedTeam->id);
    }

    // ─── happy path: controller without setTeam — branch skipped silently ───

    #[Test]
    public function validates_project_access_without_set_team_method(): void
    {
        $ctrl = new ProjectAccessValidatorTestControllerNoSetTeam();
        $user = new UserStruct();
        $user->uid = self::UID;
        $user->email = self::EMAIL;
        $this->setCtrlProp($ctrl, 'user', $user);
        $this->setCtrlProp($ctrl, 'request', $this->makeRequest());
        $this->setCtrlProp($ctrl, 'database', Database::obtain());

        $project = $this->makeProjectStruct(self::TEAM_ID);
        $validator = new ProjectAccessValidator($ctrl, $project);

        // must not throw
        $validator->validate();
        $this->assertTrue(true);
    }

    // NOTE: the "user not logged in" branch (ProjectAccessValidator::_validate lines 47-48,
    // `if (empty($this->controller->getUser()))`) is unreachable defensive code: getUser():
    // UserStruct is a non-null typed contract, so empty() on the returned object is always
    // false. It cannot be exercised without a type-incompatible stub, so it is intentionally
    // not tested.

    // ─── user not in team => AuthorizationError 401 ───

    #[Test]
    public function throws_authorization_error_when_user_does_not_belong_to_team(): void
    {
        $project = $this->makeProjectStruct(99999999);
        $validator = new ProjectAccessValidator($this->controller, $project);

        $this->expectException(AuthorizationError::class);
        $this->expectExceptionCode(401);

        $validator->validate();
    }
}
