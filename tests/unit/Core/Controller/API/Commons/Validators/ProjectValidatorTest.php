<?php

namespace Matecat\Core\Controller\API\Commons\Validators;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Validators\ProjectValidator;
use Klein\Request;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\Projects\ProjectStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

/**
 * Minimal controller exposing the seam ProjectValidator's Base ctor touches: getRequest().
 * ProjectValidator drives the rest through its own setters (setIdProject/setUser/setProject/setFeature).
 */
class ProjectValidatorTestController extends KleinController
{
    public function __construct()
    {
    }
}

/**
 * Real-DB suite. Reserved ID block base = 9_924_000 (project id = base).
 */
class ProjectValidatorTest extends AbstractTest
{
    private const int B = 9_924_000;
    private const int PROJECT_ID = self::B;
    private const string EMAIL = 'ctrltest_9924000@example.org';

    private ProjectValidatorTestController $controller;
    private ReflectionClass $ctrlRef;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanTestData();
        $this->seedTestData();

        $this->controller = new ProjectValidatorTestController();
        $this->ctrlRef = new ReflectionClass(KleinController::class);
        $this->setCtrlProp('request', new Request([], [], [], ['REQUEST_URI' => '/api/v2/projects', 'REQUEST_METHOD' => 'GET']));
        $this->setCtrlProp('database', Database::obtain());
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

    private function seedTestData(): void
    {
        $conn = Database::obtain()->getConnection();
        $conn->exec(
            "INSERT INTO projects (id, id_customer, password, name, create_date, status_analysis) VALUES ("
            . self::PROJECT_ID . ", '" . self::EMAIL . "', 'projpw_9924000', 'CtrlTestProject9924000', NOW(), 'DONE')"
        );
    }

    private function cleanTestData(): void
    {
        $conn = Database::obtain()->getConnection();
        $conn->exec("DELETE FROM projects WHERE id = " . self::PROJECT_ID);
    }

    private function makeUser(string $email): UserStruct
    {
        $user = new UserStruct();
        $user->uid = self::B;
        $user->email = $email;

        return $user;
    }

    private function makeValidator(): ProjectValidator
    {
        return new ProjectValidator($this->controller);
    }

    // ─── happy path: project found, no feature, owner matches ───

    #[Test]
    public function validates_when_user_is_project_owner(): void
    {
        $validator = $this->makeValidator();
        $validator->setIdProject(self::PROJECT_ID);
        $validator->setUser($this->makeUser(self::EMAIL));

        $validator->validate();

        $this->assertInstanceOf(ProjectStruct::class, $validator->getProject());
        $this->assertSame(self::PROJECT_ID, $validator->getProject()->id);
    }

    // ─── pre-set project bypasses findById ───

    #[Test]
    public function uses_preset_project_without_querying_db(): void
    {
        $project = new ProjectStruct();
        $project->id = self::PROJECT_ID;
        $project->id_customer = self::EMAIL;

        $validator = $this->makeValidator();
        $validator->setProject($project);
        $validator->setUser($this->makeUser(self::EMAIL));

        $validator->validate();

        $this->assertSame($project, $validator->getProject());
    }

    // ─── project not found => NotFoundException 404 ───

    #[Test]
    public function throws_not_found_when_project_missing(): void
    {
        $validator = $this->makeValidator();
        $validator->setIdProject(99_999_999);
        $validator->setUser($this->makeUser(self::EMAIL));

        $this->expectException(NotFoundException::class);
        $this->expectExceptionCode(404);

        $validator->validate();
    }

    // ─── feature requested but not enabled => NotFoundException 404 ───

    #[Test]
    public function throws_not_found_when_feature_not_enabled(): void
    {
        $validator = $this->makeValidator();
        $validator->setIdProject(self::PROJECT_ID);
        $validator->setUser($this->makeUser(self::EMAIL));
        $validator->setFeature('ctrltest_unknown_feature_9924000');

        $this->expectException(NotFoundException::class);
        $this->expectExceptionCode(404);

        $validator->validate();
    }

    // ─── no user => AuthenticationError 401 ───

    #[Test]
    public function throws_authentication_error_when_user_missing(): void
    {
        $validator = $this->makeValidator();
        $validator->setIdProject(self::PROJECT_ID);

        $this->expectException(AuthenticationError::class);
        $this->expectExceptionCode(401);

        $validator->validate();
    }

    // ─── user is not the owner => NotFoundException 403 ───

    #[Test]
    public function throws_not_found_when_user_not_owner(): void
    {
        $validator = $this->makeValidator();
        $validator->setIdProject(self::PROJECT_ID);
        $validator->setUser($this->makeUser('ctrltest_other_9924000@example.org'));

        $this->expectException(NotFoundException::class);
        $this->expectExceptionCode(403);

        $validator->validate();
    }
}
