<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

/**
 * Mock-seam unit test (Playbook §2) for {@see ProjectCreationStatusController}.
 *
 * ID block base 9057000 (reserved): base+1 = project id 9057001.
 * Per-suite owner identifier (no real-DB rows seeded by this suite):
 * ctrltest_9057000@example.org
 *
 * The controller has no afterConstruct() override, so no refactor is required.
 * The Database singleton is swapped via the mock seam (AbstractTest::createDatabaseMock /
 * setDatabaseInstance). The async-result source ProjectQueue::getPublishedResults() reads
 * Redis directly; this suite seeds/clears its own reserved Redis key so the branches of
 * ProjectCreationStatusController::get() are deterministically exercised.
 */

use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\V2\ProjectCreationStatusController;
use Model\DataAccess\Database;
use Exception;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Projects\ProjectStruct;
use PDO;
use PDOStatement;
use PHPUnit\Event\NoPreviousThrowableException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Exception as PHPUnitFrameworkException;
use PHPUnit\Framework\InvalidArgumentException as PHPUnitInvalidArgumentException;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use ReflectionClass;
use ReflectionException;
use Utils\Constants\ProjectStatus;
use Utils\Logger\MatecatLogger;
use Utils\Redis\RedisHandler;
use Utils\Registry\AppConfig;

class TestableProjectCreationStatusController extends ProjectCreationStatusController
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

#[AllowMockObjectsWithoutExpectations]
class ProjectCreationStatusControllerTest extends AbstractTest
{
    private const int ID_PROJECT = 9057001;

    /** @var ReflectionClass<ProjectCreationStatusController> */
    private ReflectionClass $reflector;
    private TestableProjectCreationStatusController $controller;

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws Exception
     * @throws \TypeError
     */
    protected function setUp(): void
    {
        parent::setUp();

        AppConfig::$SKIP_SQL_CACHE = true;
        $this->clearRedisKey();
        $this->createDatabaseMock();

        $this->controller = new TestableProjectCreationStatusController();
        $this->reflector  = new ReflectionClass(ProjectCreationStatusController::class);

        $this->reflector->getProperty('database')->setValue($this->controller, Database::obtain());
        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createStub(MatecatLogger::class));
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    protected function tearDown(): void
    {
        $this->clearRedisKey();
        $this->resetDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
    }

    /**
     * @param array<string,mixed> $params
     *
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     */
    private function setRequestParams(array $params): void
    {
        $request = $this->createStub(Request::class);
        $request->method('param')->willReturnCallback(
            fn(string $key) => $params[$key] ?? null
        );
        $this->reflector->getProperty('request')->setValue($this->controller, $request);
    }

    /**
     * Seeds the reserved Redis key with the async creation result the worker would publish.
     *
     * @param array<string,mixed> $result
     *
     * @throws ReflectionException
     * @throws Exception
     */
    private function seedRedisResult(array $result): void
    {
        $redis = (new RedisHandler())->getConnection();
        $redis->set(
            sprintf(ProjectStatus::PROJECT_QUEUE_HASH, self::ID_PROJECT),
            json_encode($result)
        );
        $redis->disconnect();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    private function clearRedisKey(): void
    {
        $redis = (new RedisHandler())->getConnection();
        $redis->del(sprintf(ProjectStatus::PROJECT_QUEUE_HASH, self::ID_PROJECT));
        $redis->disconnect();
    }

    // ── get(): validation failure ──────────────────────────────────────────

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function get_throws_when_id_project_is_not_numeric(): void
    {
        $this->setRequestParams(['id_project' => 'not-a-number']);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->createStub(Response::class));

        try {
            $this->controller->get();
            self::fail('Expected Exception for non-numeric id_project');
        } catch (Exception $e) {
            self::assertSame(-1, $e->getCode());
            self::assertSame('ID project is not a valid integer', $e->getMessage());
        }
    }

    // ── get(): empty result → _letsWait() (202) ────────────────────────────

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws Exception
     */
    #[Test]
    public function get_returns_wait_payload_when_no_published_result(): void
    {
        // No Redis key seeded → getPublishedResults returns null → _letsWait().
        $this->setRequestParams(['id_project' => (string)self::ID_PROJECT]);

        $captured = null;
        $code     = null;

        $response = $this->createMock(Response::class);
        $response->method('code')->willReturnCallback(function (int $c) use (&$code, &$response) {
            $code = $c;
            return $response;
        });
        $response->method('json')->willReturnCallback(function ($data) use (&$captured, &$response) {
            $captured = $data;
            return $response;
        });
        $this->reflector->getProperty('response')->setValue($this->controller, $response);

        $this->controller->get();

        self::assertSame(202, $code);
        self::assertIsArray($captured);
        self::assertSame(202, $captured['status']);
        self::assertSame('Project in queue. Wait.', $captured['message']);
    }

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws Exception
     */
    #[Test]
    public function get_returns_wait_payload_when_result_has_empty_id_project(): void
    {
        // Authorized branch but the published result has no id_project → _letsWait().
        $this->seedRedisResult(['id_project' => 0, 'errors' => []]);
        $this->setRequestParams(['id_project' => (string)self::ID_PROJECT, 'password' => 'pwd123456789012']);

        $this->primeProjectLookup(found: true);

        $captured = null;
        $code     = null;

        $response = $this->createMock(Response::class);
        $response->method('code')->willReturnCallback(function (int $c) use (&$code, &$response) {
            $code = $c;
            return $response;
        });
        $response->method('json')->willReturnCallback(function ($data) use (&$captured, &$response) {
            $captured = $data;
            return $response;
        });
        $this->reflector->getProperty('response')->setValue($this->controller, $response);

        $this->controller->get();

        self::assertSame(202, $code);
        self::assertIsArray($captured);
        self::assertSame('Project in queue. Wait.', $captured['message']);
    }

    // ── get(): result carries errors ───────────────────────────────────────

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws Exception
     */
    #[Test]
    public function get_throws_error_from_published_result(): void
    {
        $this->seedRedisResult(['errors' => [['message' => 'Boom failure', 'code' => 17]]]);
        $this->setRequestParams(['id_project' => (string)self::ID_PROJECT]);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->createStub(Response::class));

        try {
            $this->controller->get();
            self::fail('Expected Exception carrying the published error');
        } catch (Exception $e) {
            self::assertSame(17, $e->getCode());
            self::assertSame('Boom failure', $e->getMessage());
        }
    }

    // ── get(): authorization failure ───────────────────────────────────────

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws Exception
     */
    #[Test]
    public function get_throws_authorization_error_when_password_does_not_match(): void
    {
        $this->seedRedisResult(['id_project' => self::ID_PROJECT]);
        $this->setRequestParams(['id_project' => (string)self::ID_PROJECT, 'password' => 'wrongpassword12']);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->createStub(Response::class));

        // Empty fetch from the DB seam → ProjectDao throws NotFoundException internally.
        $this->primeProjectLookup(found: false);

        $this->expectException(AuthorizationError::class);
        $this->expectExceptionMessage('Not Authorized.');

        $this->controller->get();
    }

    // ── get(): success → CreationStatus payload (200) ──────────────────────

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws Exception
     */
    #[Test]
    public function get_returns_creation_status_payload_on_success(): void
    {
        $this->seedRedisResult([
            'id_project'   => self::ID_PROJECT,
            'ppassword'    => 'pass9057001xxxx',
            'project_name' => 'Reserved Project 9057001',
            'analyze_url'  => 'https://example.org/analyze/9057001',
        ]);
        $this->setRequestParams(['id_project' => (string)self::ID_PROJECT, 'password' => 'pass9057001xxxx']);

        $this->primeProjectLookup(found: true);

        $captured = null;
        $response = $this->createMock(Response::class);
        $response->method('json')->willReturnCallback(function ($data) use (&$captured, &$response) {
            $captured = $data;
            return $response;
        });
        $this->reflector->getProperty('response')->setValue($this->controller, $response);

        $this->controller->get();

        self::assertIsArray($captured);
        self::assertSame(200, $captured['status']);
        self::assertSame('Project created', $captured['message']);
        self::assertSame(self::ID_PROJECT, $captured['id_project']);
        self::assertSame('pass9057001xxxx', $captured['project_pass']);
        self::assertSame('Reserved Project 9057001', $captured['project_name']);
        self::assertSame('https://example.org/analyze/9057001', $captured['analyze_url']);
    }

    /**
     * Installs a dedicated Database mock whose prepared statement returns either a populated
     * ProjectStruct row (authorized) or an empty result (NotFoundException → AuthorizationError),
     * driving ProjectDao::findByIdAndPassword without touching the real database.
     *
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     */
    private function primeProjectLookup(bool $found): void
    {
        $rows = [];
        if ($found) {
            $struct       = new ProjectStruct();
            $struct->id   = self::ID_PROJECT;
            $struct->name = 'Reserved Project 9057001';
            $rows         = [$struct];
        }

        $stmt = $this->createStub(PDOStatement::class);
        $stmt->queryString = '';
        $stmt->method('fetchAll')->willReturn($rows);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $db = $this->createStub(IDatabase::class);
        $db->method('getConnection')->willReturn($pdo);

        $this->setDatabaseInstance($db);
        // Controller no longer falls back to the Database singleton via getDatabase();
        // push the primed stub into its injected $database so ProjectDao uses it.
        $this->reflector->getProperty('database')->setValue($this->controller, $db);
    }
}
