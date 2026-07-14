<?php

namespace Matecat\Core\Controller\API\Commons\Validators;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\Commons\Validators\TeamAccessValidator;
use Klein\Request;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\Teams\TeamStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

/**
 * Minimal controller exposing the seams TeamAccessValidator touches:
 * getRequest(), getUser(), and setTeam() (so the method_exists() branch fires).
 */
class TeamAccessValidatorTestController extends KleinController
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
 * Real-DB suite. Reserved ID block base = 9_910_000 (uid, team +1).
 */
class TeamAccessValidatorTest extends AbstractTest
{
    private const int B = 9_910_000;
    private const int UID = self::B;
    private const int TEAM_ID = self::B + 1;
    private const int ATTACKER_UID = self::B + 2;
    private const string TEAM_NAME = 'CtrlTestTeam9910000';
    private const string EMAIL = 'ctrltest_9910000@example.org';

    private TeamAccessValidatorTestController $controller;
    private ReflectionClass $ctrlRef;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanTestData();
        $this->seedTestData();

        $this->controller = new TeamAccessValidatorTestController();
        $this->ctrlRef = new ReflectionClass(KleinController::class);

        $user = new UserStruct();
        $user->uid = self::UID;
        $user->email = self::EMAIL;
        $this->setCtrlProp('user', $user);
        $this->setCtrlProp('database', obtainTestDatabase());
    }

    protected function tearDown(): void
    {
        $this->cleanTestData();
        parent::tearDown();
    }

    private function setCtrlProp(string $name, mixed $value): void
    {
        $c = $this->ctrlRef;
        while ($c !== false && !$c->hasProperty($name)) {
            $c = $c->getParentClass();
        }
        $p = $c->getProperty($name);
        $p->setAccessible(true);
        $p->setValue($this->controller, $value);
    }

    private function setRequest(array $get): void
    {
        $this->setCtrlProp('request', new Request($get, [], [], ['REQUEST_URI' => '/api/v2/teams', 'REQUEST_METHOD' => 'GET']));
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

    // ─── user-path (no team_name) + setTeam branch ───

    #[Test]
    public function validates_by_user_membership_and_invokes_setTeam(): void
    {
        $this->setRequest(['id_team' => (string) self::TEAM_ID]);

        $validator = new TeamAccessValidator($this->controller);
        $validator->_validate();

        $this->assertInstanceOf(TeamStruct::class, $validator->team);
        $this->assertSame(self::TEAM_ID, $validator->team->id);
        $this->assertSame(self::TEAM_ID, $this->controller->capturedTeam->id);
    }

    // ─── IDOR regression: the team_name parameter must NOT be an authorization path ───

    #[Test]
    public function blocks_non_member_even_when_team_name_supplied(): void
    {
        // Attacker: authenticated but NOT a member of TEAM_ID, injects the (correct) team_name.
        $attacker = new UserStruct();
        $attacker->uid = self::ATTACKER_UID;
        $attacker->email = 'attacker_9910000@example.org';
        $this->setCtrlProp('user', $attacker);

        $this->setRequest([
            'id_team' => (string) self::TEAM_ID,
            'team_name' => base64_encode(self::TEAM_NAME),
        ]);

        // team_name is inert: access is resolved purely by membership → no row → 401.
        $validator = new TeamAccessValidator($this->controller);

        $this->expectException(AuthorizationError::class);
        $this->expectExceptionCode(401);

        $validator->_validate();
    }

    // ─── the team_name parameter is ignored for members too (membership path wins) ───

    #[Test]
    public function member_is_authorized_regardless_of_team_name_param(): void
    {
        $this->setRequest([
            'id_team' => (string) self::TEAM_ID,
            'team_name' => base64_encode(self::TEAM_NAME),
        ]);

        $validator = new TeamAccessValidator($this->controller);
        $validator->_validate();

        $this->assertSame(self::TEAM_ID, $validator->team->id);
    }

    // ─── no membership => AuthorizationError 401 ───

    #[Test]
    public function throws_authorization_error_when_no_team_found(): void
    {
        $this->setRequest(['id_team' => '99999999']);

        $validator = new TeamAccessValidator($this->controller);

        $this->expectException(AuthorizationError::class);
        $this->expectExceptionCode(401);

        $validator->_validate();
    }
}
