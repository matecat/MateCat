<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

/**
 * Mock-seam unit test (Playbook §2) for {@see \Controller\API\V3\StatusController}.
 *
 * ID block base 9056000 (reserved; mock-seam tests use NO real-DB seeding).
 * ID block base 9942000 (reserved; real-DB seeding for index() branch coverage).
 * Per-suite owner identifier: ctrltest_9056000@example.org
 */

use Controller\API\Commons\Exceptions\NotFoundException;
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
use View\API\App\Json\Analysis\AnalysisProject;

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
        $this->reflector->getProperty('featureSet')->setValue($this->controller, new FeatureSet(obtainTestDatabase()));
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

    // ── Real-DB integration paths ──────────────────────────────────────────

    /**
     * Project 1886428330 exists in the test DB with job 1886428338 and real
     * segment_translations, so getProjectStatsVolumeAnalysis returns non-empty
     * rows.  loadObjects() builds at least one non-deleted chunk → chunksCount > 0
     * → response->json() is called (line 54).
     *
     * Covers: Status construction (line 34), fetchData()->getResult() (line 35),
     * the foreach loop body (lines 40-44), the chunksCount conditional (line 50),
     * and the json() call (line 54).
     *
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function index_calls_json_for_project_with_real_analysis_data(): void
    {
        // setUp installed a mock via createDatabaseMock(); reset it so that
        // obtainTestDatabase() falls back to Bootstrap::getDatabase() (the real DB).
        $this->resetDatabaseMock();
        $realDb = obtainTestDatabase();

        // Point the controller's own DB at the real test DB so
        // getProjectAndJobData(1886428330) returns the seeded row.
        $this->reflector->getProperty('database')->setValue($this->controller, $realDb);

        // The featureSet must also wrap the real DB so AbstractStatus::__construct()
        // can resolve the project via ProjectDao::findById(), and loadObjects() can
        // run the analysis and job queries.
        $this->reflector->getProperty('featureSet')->setValue($this->controller, new FeatureSet($realDb));

        // Inject a user so isLoggedIn() does not hit an uninitialized property.
        $user = new UserStruct();
        $user->uid   = 1;
        $user->email = 'ctrltest_9056000@example.org';
        $this->reflector->getProperty('user')->setValue($this->controller, $user);
        $this->reflector->getProperty('userIsLogged')->setValue($this->controller, true);

        // The response mock must accept json() exactly once with an AnalysisProject.
        $this->responseMock
            ->expects($this->once())
            ->method('json')
            ->with($this->isInstanceOf(AnalysisProject::class));

        $this->setRequestParams(['id_project' => '1886428330']);

        // Should complete without throwing — real chunks are not deleted.
        $this->controller->index();
    }

    /**
     * A project that exists in the DB but has NO segment_translations (pid 9942001)
     * causes getProjectStatsVolumeAnalysis to return [] and status_analysis='DONE'
     * skips the new/busy fallback → no jobs are added → chunksCount remains 0
     * → NotFoundException is thrown (line 51).
     *
     * Covers: the empty-jobs conditional (line 39) and the NotFoundException throw
     * (lines 50-51).
     *
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function index_throws_not_found_exception_when_project_has_no_chunks(): void
    {
        // Reset the mock so obtainTestDatabase() returns the real composition-root DB.
        $this->resetDatabaseMock();
        $realDb = obtainTestDatabase();
        $conn   = $realDb->getConnection();

        // Seed a minimal project + job with no segment_translations so that the
        // volume-analysis query returns an empty result set.
        $conn->exec(
            "INSERT IGNORE INTO projects
                 (id, password, id_customer, name, create_date, status_analysis, id_team, id_assignee)
             VALUES
                 (9942001, 'aabbccdd1122', 'ctrltest_9942000@example.org', 'StatusCtrlTest',
                  '2024-01-01 00:00:00', 'DONE', 32786, 18052)"
        );
        $conn->exec(
            "INSERT IGNORE INTO jobs
                 (id, password, id_project, job_first_segment, job_last_segment,
                  tm_keys, source, target, create_date, owner, status_owner, status)
             VALUES
                 (9942001, 'aabbccdd9999', 9942001, 9942001, 9942002,
                  '[]', 'en-GB', 'it-IT', '2024-01-01 00:00:00',
                  'ctrltest_9942000@example.org', 'active', 'active')"
        );

        try {
            $this->reflector->getProperty('database')->setValue($this->controller, $realDb);
            $this->reflector->getProperty('featureSet')->setValue($this->controller, new FeatureSet($realDb));

            $user = new UserStruct();
            $user->uid   = 1;
            $user->email = 'ctrltest_9942000@example.org';
            $this->reflector->getProperty('user')->setValue($this->controller, $user);
            $this->reflector->getProperty('userIsLogged')->setValue($this->controller, true);

            $this->responseMock->expects($this->never())->method('json');

            $this->setRequestParams(['id_project' => '9942001']);

            $this->expectException(NotFoundException::class);
            $this->expectExceptionMessageMatches("/doesn't have any jobs/i");

            $this->controller->index();
        } finally {
            // Always clean up seeded rows regardless of test outcome.
            $conn->exec("DELETE FROM jobs     WHERE id         = 9942001");
            $conn->exec("DELETE FROM projects WHERE id         = 9942001");
        }
    }
}
