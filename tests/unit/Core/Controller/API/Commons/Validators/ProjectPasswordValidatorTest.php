<?php

namespace Matecat\Core\Controller\API\Commons\Validators;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use Klein\Request;
use Matecat\TestHelpers\AbstractTest;
use Model\Exceptions\NotFoundException;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

/**
 * Minimal controller exposing the seams ProjectPasswordValidator touches.
 * The validator reads the public $params property directly (ctor + _validate)
 * and the Base ctor reads getRequest(); nothing else is needed.
 */
class ProjectPasswordValidatorTestController extends KleinController
{
    public function __construct()
    {
    }
}

/**
 * Real-DB suite. Reserved ID block base = 9_932_000 (project id only).
 */
class ProjectPasswordValidatorTest extends AbstractTest
{
    private const int B = 9_932_000;
    private const int PROJECT_ID = self::B;
    private const string PASSWORD = 'projpwd_9932000';
    private const string EMAIL = 'ctrltest_9932000@example.org';

    private ProjectPasswordValidatorTestController $controller;
    private ReflectionClass $ctrlRef;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanTestData();
        $this->seedTestData();

        $this->controller = new ProjectPasswordValidatorTestController();
        $this->ctrlRef = new ReflectionClass(KleinController::class);

        $this->setCtrlProp('request', new Request(
            [], [], [], ['REQUEST_URI' => '/api/v2/projects', 'REQUEST_METHOD' => 'GET']
        ));
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

    private function seedTestData(): void
    {
        $conn = obtainTestDatabase()->getConnection();
        $conn->exec(
            "INSERT INTO projects (id, id_customer, password, name, create_date, status_analysis) VALUES (" .
            self::PROJECT_ID . ", '" . self::EMAIL . "', '" . self::PASSWORD .
            "', 'CtrlTestProj9932000', NOW(), 'DONE')"
        );
    }

    private function cleanTestData(): void
    {
        $conn = obtainTestDatabase()->getConnection();
        $conn->exec("DELETE FROM projects WHERE id = " . self::PROJECT_ID);
    }

    // ─── happy path: valid id + password resolves the project ───

    #[Test]
    public function validates_valid_id_and_password(): void
    {
        $this->controller->params = [
            'id_project' => (string) self::PROJECT_ID,
            'password' => self::PASSWORD,
        ];

        $validator = new ProjectPasswordValidator($this->controller);

        // ctor parses + normalizes params (writes them back typed)
        $this->assertSame(self::PROJECT_ID, $validator->getIdProject());
        $this->assertSame(self::PASSWORD, $validator->getPassword());
        $this->assertSame(self::PROJECT_ID, $this->controller->params['id_project']);
        $this->assertSame(self::PASSWORD, $this->controller->params['password']);

        $validator->_validate();

        $project = $validator->getProject();
        $this->assertInstanceOf(ProjectStruct::class, $project);
        $this->assertSame(self::PROJECT_ID, (int) $project->id);
    }

    // ─── empty password => NotFoundException 404 (guard before DAO) ───

    #[Test]
    public function throws_not_found_when_password_is_missing(): void
    {
        $this->controller->params = [
            'id_project' => (string) self::PROJECT_ID,
        ];

        $validator = new ProjectPasswordValidator($this->controller);

        $this->assertSame('', $validator->getPassword());

        $this->expectException(NotFoundException::class);
        $this->expectExceptionCode(404);

        $validator->_validate();
    }

    // ─── valid password but wrong id => DAO raises NotFoundException ───

    #[Test]
    public function throws_not_found_when_no_project_matches(): void
    {
        $missingId = self::PROJECT_ID + 1;
        $this->controller->params = [
            'id_project' => (string) $missingId,
            'password' => self::PASSWORD,
        ];

        $validator = new ProjectPasswordValidator($this->controller);
        $this->assertNull($validator->getProject());
        $this->assertSame($missingId, $validator->getIdProject());

        $this->expectException(NotFoundException::class);

        $validator->_validate();
    }
}
