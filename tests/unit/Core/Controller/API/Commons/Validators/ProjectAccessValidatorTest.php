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
        $this->setCtrlProp($this->controller, 'database', obtainTestDatabase());

        $user = new UserStruct();
        $user->uid = self::UID;
        $user->email = self::EMAIL;
        $this->setCtrlProp($this->controller, 'user', $user);
        $this->setCtrlProp($this->controller, 'userIsLogged', true);
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
        $conn = obtainTestDatabase()->getConnection();
        $conn->exec("INSERT INTO users (uid, email, first_name, last_name) VALUES (" . self::UID . ", '" . self::EMAIL . "', 'T', 'U')");
        $conn->exec("INSERT INTO teams (id, name, created_by) VALUES (" . self::TEAM_ID . ", '" . self::TEAM_NAME . "', " . self::UID . ")");
        $conn->exec("INSERT INTO teams_users (uid, id_team, is_admin) VALUES (" . self::UID . ", " . self::TEAM_ID . ", 1)");
    }

    private function cleanTestData(): void
    {
        $conn = obtainTestDatabase()->getConnection();
        $conn->exec("DELETE FROM teams_users WHERE id_team = " . self::TEAM_ID);
        $conn->exec("DELETE FROM teams WHERE id = " . self::TEAM_ID);
        $conn->exec("DELETE FROM users WHERE uid = " . self::UID);
    }

    // ─── happy path: user belongs to the team, setTeam is invoked ───

    #[Test]
    public function validates_project_access_and_invokes_set_team(): void
    {
        $project = $this->makeProjectStruct(self::TEAM_ID);
        $validator = new ProjectAccessValidator($this->controller, $project, $this->controller->getUser());
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
        $this->setCtrlProp($ctrl, 'userIsLogged', true);
        $this->setCtrlProp($ctrl, 'request', $this->makeRequest());
        $this->setCtrlProp($ctrl, 'database', obtainTestDatabase());

        $project = $this->makeProjectStruct(self::TEAM_ID);
        $validator = new ProjectAccessValidator($ctrl, $project, $ctrl->getUser());

        // must not throw
        $validator->validate();
        $this->assertTrue(true);
    }

    // ─── user not logged in => AuthorizationError 401 ───

    #[Test]
    public function throws_authorization_error_when_user_not_logged_in(): void
    {
        $this->setCtrlProp($this->controller, 'userIsLogged', false);

        $project = $this->makeProjectStruct(self::TEAM_ID);
        $validator = new ProjectAccessValidator($this->controller, $project, $this->controller->getUser());

        $this->expectException(AuthorizationError::class);
        $this->expectExceptionCode(401);

        $validator->validate();
    }

    // ─── project without a team => AuthorizationError 401 ───

    #[Test]
    public function throws_authorization_error_when_project_has_no_team(): void
    {
        $project = new ProjectStruct();
        $project->id_team = null;
        $validator = new ProjectAccessValidator($this->controller, $project, $this->controller->getUser());

        $this->expectException(AuthorizationError::class);
        $this->expectExceptionCode(401);

        $validator->validate();
    }

    // ─── user not in team => AuthorizationError 401 ───

    #[Test]
    public function throws_authorization_error_when_user_does_not_belong_to_team(): void
    {
        $project = $this->makeProjectStruct(99999999);
        $validator = new ProjectAccessValidator($this->controller, $project, $this->controller->getUser());

        $this->expectException(AuthorizationError::class);
        $this->expectExceptionCode(401);

        $validator->validate();
    }

    // ─── what the refusal is allowed to say ───

    /**
     * A caller with no standing over the project must not learn from the refusal whether the project
     * sits in a team at all: the two team-related refusals answer the same opaque sentence, and the
     * one for a team-less project used to say "the user does not belong to team". Asserted by exact
     * comparison rather than expectExceptionMessage(), which matches on a substring and would still
     * pass if a detail were appended back onto the sentence.
     */
    #[Test]
    public function theTeamlessRefusalDoesNotDiscloseWhyAccessWasRefused(): void
    {
        $project = new ProjectStruct();
        $project->id_team = null;
        $validator = new ProjectAccessValidator($this->controller, $project, $this->controller->getUser());

        try {
            $validator->validate();
            $this->fail('a project with no team must refuse a caller who does not own it');
        } catch (AuthorizationError $e) {
            $this->assertSame('Not authorized', $e->getMessage());
            $this->assertSame(401, $e->getCode());
        }
    }

    /**
     * The refusal for a caller outside the team used to carry the team id, handing an unauthorized
     * caller an internal identifier it had no way to see otherwise. The sentence must stay opaque.
     */
    #[Test]
    public function theNonMemberRefusalDoesNotDiscloseTheTeamId(): void
    {
        $project = $this->makeProjectStruct(99999999);
        $validator = new ProjectAccessValidator($this->controller, $project, $this->controller->getUser());

        try {
            $validator->validate();
            $this->fail('a caller outside the project team must be refused');
        } catch (AuthorizationError $e) {
            $this->assertSame('Not authorized', $e->getMessage());
            $this->assertSame(401, $e->getCode());
            $this->assertStringNotContainsString('99999999', $e->getMessage());
        }
    }

    // ─── the owner short-circuit ───

    /**
     * A project outlives its owner's membership of the team it sits in — ProjectDao moves projects
     * between teams in bulk — so the owner must pass without a membership lookup or the application
     * takes projects away from the people who created them.
     */
    #[Test]
    public function theOwnerPassesWithoutBelongingToTheProjectTeam(): void
    {
        $project = $this->makeProjectStruct(99999999);
        $project->id_customer = self::EMAIL;

        $validator = new ProjectAccessValidator($this->controller, $project, $this->controller->getUser());
        $validator->validate();

        $this->assertNull($this->controller->capturedTeam, 'the membership lookup must not have run');
    }

    /**
     * id_team is nullable, so a project with no team at all is legal by construction. Its owner keeps it.
     */
    #[Test]
    public function theOwnerPassesForAProjectCarryingNoTeam(): void
    {
        $project = new ProjectStruct();
        $project->id_team = null;
        $project->id_customer = self::EMAIL;

        $validator = new ProjectAccessValidator($this->controller, $project, $this->controller->getUser());
        $validator->validate();

        $this->assertNull($this->controller->capturedTeam);
    }

    /**
     * An empty owner matches nobody, least of all a caller whose own address is empty — otherwise a
     * project created by an unauthenticated caller (id_customer = '') would be open to any identity
     * carrying no email. The membership lookup decides those, and here refuses.
     */
    #[Test]
    public function anEmptyOwnerMatchesNobody(): void
    {
        $user = new UserStruct();
        $user->uid = self::UID;
        $user->email = '';
        $this->setCtrlProp($this->controller, 'user', $user);

        $project = $this->makeProjectStruct(99999999);
        $project->id_customer = '';

        $validator = new ProjectAccessValidator($this->controller, $project, $this->controller->getUser());

        $this->expectException(AuthorizationError::class);
        $this->expectExceptionCode(401);

        $validator->validate();
    }

    /**
     * The owner comparison must read a struct that was assembled by hand as well as one the DAO built:
     * ProjectStruct::$id_customer is a non-nullable typed property with no default, so a struct that
     * never had it assigned would raise "must not be accessed before initialization" on a bare read.
     * Reaching the membership lookup at all is the assertion — it is what proves nothing threw earlier.
     */
    #[Test]
    public function anUnassignedOwnerIsTreatedAsEmptyRatherThanThrowing(): void
    {
        $project = $this->makeProjectStruct(self::TEAM_ID);

        $validator = new ProjectAccessValidator($this->controller, $project, $this->controller->getUser());
        $validator->validate();

        $this->assertSame(self::TEAM_ID, $this->controller->capturedTeam->id);
    }

    /**
     * Login state is still the request's, not the passed user's: an owner who is not logged in is refused
     * before the short-circuit is reached.
     */
    #[Test]
    public function anOwnerWhoIsNotLoggedInIsStillRefused(): void
    {
        $this->setCtrlProp($this->controller, 'userIsLogged', false);

        $project = $this->makeProjectStruct(self::TEAM_ID);
        $project->id_customer = self::EMAIL;

        $validator = new ProjectAccessValidator($this->controller, $project, $this->controller->getUser());

        $this->expectException(AuthorizationError::class);
        $this->expectExceptionCode(401);

        $validator->validate();
    }
}
