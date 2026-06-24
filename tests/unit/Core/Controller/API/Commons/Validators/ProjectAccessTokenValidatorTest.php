<?php

namespace Matecat\Core\Controller\API\Commons\Validators;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\ProjectAccessTokenValidator;
use Klein\Request;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

/**
 * Minimal controller exposing the seams ProjectAccessTokenValidator touches.
 * The validator reads the public $params property directly (in its ctor and
 * in _validate) and the Base ctor reads getRequest(); nothing else is needed.
 */
class ProjectAccessTokenValidatorTestController extends KleinController
{
    public function __construct()
    {
    }
}

/**
 * Real-DB suite. Reserved ID block base = 9_931_000 (project id only).
 */
class ProjectAccessTokenValidatorTest extends AbstractTest
{
    private const int B = 9_931_000;
    private const int PROJECT_ID = self::B;
    private const string PASSWORD = 'projtok_9931000';
    private const string EMAIL = 'ctrltest_9931000@example.org';

    private ProjectAccessTokenValidatorTestController $controller;
    private ReflectionClass $ctrlRef;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanTestData();
        $this->seedTestData();

        $this->controller = new ProjectAccessTokenValidatorTestController();
        $this->ctrlRef = new ReflectionClass(KleinController::class);

        // Base ctor calls $controller->getRequest(); provide a real Request.
        $this->setCtrlProp('request', new Request(
            [], [], [], ['REQUEST_URI' => '/api/v2/projects', 'REQUEST_METHOD' => 'GET']
        ));
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

    private function validToken(): string
    {
        return sha1(self::PROJECT_ID . self::PASSWORD);
    }

    private function seedTestData(): void
    {
        $conn = Database::obtain()->getConnection();
        $conn->exec(
            "INSERT INTO projects (id, id_customer, password, name, create_date, status_analysis) VALUES (" .
            self::PROJECT_ID . ", '" . self::EMAIL . "', '" . self::PASSWORD .
            "', 'CtrlTestProj9931000', NOW(), 'DONE')"
        );
    }

    private function cleanTestData(): void
    {
        $conn = Database::obtain()->getConnection();
        $conn->exec("DELETE FROM projects WHERE id = " . self::PROJECT_ID);
    }

    // ─── happy path: valid id + access token ───

    #[Test]
    public function validates_valid_project_access_token(): void
    {
        $this->controller->params = [
            'id_project' => (string) self::PROJECT_ID,
            'project_access_token' => $this->validToken(),
        ];

        $validator = new ProjectAccessTokenValidator($this->controller);

        // ctor parses + normalizes params (also writes id_project back as int)
        $this->assertSame(self::PROJECT_ID, $validator->getIdProject());
        $this->assertSame($this->validToken(), $validator->getAccessToken());
        $this->assertSame(self::PROJECT_ID, $this->controller->params['id_project']);

        $validator->_validate();

        $project = $validator->getProject();
        $this->assertInstanceOf(ProjectStruct::class, $project);
        $this->assertSame(self::PROJECT_ID, (int) $project->id);
        // success side-effect: password written into controller params
        $this->assertSame(self::PASSWORD, $this->controller->params['password']);
    }

    // ─── failure: existing project but wrong token => NotFoundException 404 ───

    #[Test]
    public function throws_not_found_when_access_token_is_invalid(): void
    {
        $this->controller->params = [
            'id_project' => (string) self::PROJECT_ID,
            'project_access_token' => 'definitely-wrong-token',
        ];

        $validator = new ProjectAccessTokenValidator($this->controller);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionCode(404);

        $validator->_validate();
    }

    // ─── failure: project does not exist => empty($project) branch, 404 ───

    #[Test]
    public function throws_not_found_when_project_does_not_exist(): void
    {
        $missingId = self::PROJECT_ID + 1;
        $this->controller->params = [
            'id_project' => (string) $missingId,
            'project_access_token' => sha1($missingId . self::PASSWORD),
        ];

        $validator = new ProjectAccessTokenValidator($this->controller);
        $this->assertNull($validator->getProject());

        $this->expectException(NotFoundException::class);
        $this->expectExceptionCode(404);

        $validator->_validate();
    }
}
