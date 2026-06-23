<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

/**
 * Mock-seam unit test (Playbook §2) for {@see \Controller\API\V3\StatusController}.
 *
 * ID block base 9056000 (reserved; this suite uses NO real-DB seeding — pure mock seam).
 * Per-suite owner identifier (unused — no DB rows seeded): ctrltest_9056000@example.org
 */

use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use Controller\API\V3\StatusController;
use Exception;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;
use PDO;
use PDOStatement;
use PHPUnit\Event\NoPreviousThrowableException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Exception as PHPUnitException;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\InvalidArgumentException as PHPUnitInvalidArgumentException;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use ReflectionClass;
use ReflectionException;
use TypeError;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

/**
 * Bare subclass: neutralised constructor + empty hooks so the controller can be
 * built without the Klein request lifecycle.
 */
class TestableStatusV3Controller extends StatusController
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
 * Subclass that keeps the REAL registerValidators() so the refactored hook is exercised.
 */
class ValidatorTestableStatusV3Controller extends StatusController
{
    public function __construct()
    {
    }
}

#[AllowMockObjectsWithoutExpectations]
class StatusV3ControllerTest extends AbstractTest
{
    /** @var ReflectionClass<StatusController> */
    private ReflectionClass $reflector;
    private TestableStatusV3Controller $controller;
    private Response&MockObject $responseMock;

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws Exception
     * @throws TypeError
     * @throws NoPreviousThrowableException
     * @throws PHPUnitInvalidArgumentException
     */
    protected function setUp(): void
    {
        parent::setUp();

        AppConfig::$SKIP_SQL_CACHE = true;
        [$dbStub] = $this->createDatabaseMock();

        $this->controller  = new TestableStatusV3Controller();
        $this->reflector   = new ReflectionClass(StatusController::class);
        $this->responseMock = $this->createMock(Response::class);

        $this->reflector->getProperty('response')->setValue($this->controller, $this->responseMock);
        $this->reflector->getProperty('database')->setValue($this->controller, $dbStub);
        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createMock(MatecatLogger::class));

        $user = new UserStruct();
        $user->uid   = 1;
        $user->email = 'ctrltest_9056000@example.org';
        $this->reflector->getProperty('user')->setValue($this->controller, $user);

        // AbstractStatus resolves its ProjectDao from the FeatureSet's database;
        // give it the real seeded test DB so an unresolvable pid returns null
        // (→ "Project not found") instead of a stub crash.
        $this->reflector->getProperty('featureSet')->setValue($this->controller, new FeatureSet(\Model\DataAccess\Database::obtain()));
    }

    protected function tearDown(): void
    {
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
    }

    /**
     * @param array<string,mixed> $params
     *
     * @throws ReflectionException
     */
    private function setRequestParams(array $params): void
    {
        $serverParams = ['REQUEST_URI' => '/api/v3/status', 'REQUEST_METHOD' => 'GET'];
        $request      = new Request($params, [], [], $serverParams);
        $this->reflector->getProperty('request')->setValue($this->controller, $request);
    }

    // ── registerValidators (refactored hook) ───────────────────────────────

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws PHPUnitException
     */
    #[Test]
    public function registerValidators_appends_login_and_project_password_validators(): void
    {
        $controller = new ValidatorTestableStatusV3Controller();
        $ref        = new ReflectionClass(StatusController::class);

        $request = new Request(
            ['id_project' => '42', 'password' => 'secret'],
            [],
            [],
            ['REQUEST_URI' => '/api/v3/status', 'REQUEST_METHOD' => 'GET']
        );
        $ref->getProperty('request')->setValue($controller, $request);
        $controller->params = ['id_project' => '42', 'password' => 'secret'];

        $method = $ref->getMethod('registerValidators');
        $method->invoke($controller);

        $validators = $ref->getProperty('validators')->getValue($controller);

        self::assertIsArray($validators);
        self::assertCount(2, $validators);
        self::assertInstanceOf(LoginValidator::class, $validators[0]);
        self::assertInstanceOf(ProjectPasswordValidator::class, $validators[1]);
    }

    // ── index() ────────────────────────────────────────────────────────────

    /**
     * The DAO call on line ~33 runs against the mocked DB (empty fetchAll), so
     * Status construction cannot resolve the project and throws. This drives the
     * id_project param read + ProjectDao::getProjectAndJobData + Status ctor.
     *
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws TypeError
     * @throws ExpectationFailedException
     * @throws NoPreviousThrowableException
     * @throws PHPUnitInvalidArgumentException
     */
    #[Test]
    public function index_reads_id_project_param_and_queries_project_data(): void
    {
        $this->setRequestParams(['id_project' => '9056001']);

        // getProjectAndJobData() returns one project-data row through the stubbed
        // statement, so the Status ctor reads a valid pid but ProjectDao::findById()
        // resolves nothing → a "Project not found" Exception is raised. The success
        // branch (json()) is unreachable without a fully hydrated Status (real-DB).
        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->queryString = '';
        $stmtStub->method('fetchAll')->willReturn([['pid' => 9056001]]);
        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);
        $dbStub = $this->createStub(IDatabase::class);
        $dbStub->method('getConnection')->willReturn($pdoStub);
        $this->setDatabaseInstance($dbStub);
        // Controller uses its injected $database (not the singleton); point it at the primed stub.
        $this->reflector->getProperty('database')->setValue($this->controller, $dbStub);

        $this->responseMock->expects($this->never())->method('json');

        try {
            $this->controller->index();
            self::fail('Expected an exception because no project is resolvable');
        } catch (Exception $e) {
            self::assertStringContainsString('Project not found', $e->getMessage());
        }
    }

    /**
     * Missing id_project means ProjectDao::getProjectAndJobData() receives null for
     * its int $pid parameter, raising the declared \TypeError; covers the param-read
     * + DAO-call branch with an absent parameter. json() is never reached.
     *
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function index_throws_type_error_when_id_project_param_absent(): void
    {
        $this->setRequestParams([]);

        $this->responseMock->expects($this->never())->method('json');

        $this->expectException(TypeError::class);

        $this->controller->index();
    }
}
