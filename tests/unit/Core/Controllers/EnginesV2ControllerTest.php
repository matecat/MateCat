<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

/**
 * Mock-seam unit test (Playbook §2) for API/V2/EnginesController.
 * ID block base 9049000 (reserved; this suite uses no real-DB seeding).
 * Per-suite owner identifier (unused — no DB rows seeded): ctrltest_9049000@example.org
 */

use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\V2\EnginesController;
use Exception;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Engines\Structs\EngineStruct;
use Model\Users\UserStruct;
use PDO;
use PDOStatement;
use PHPUnit\Event\NoPreviousThrowableException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Exception as PHPUnitException;
use PHPUnit\Framework\InvalidArgumentException as PHPUnitInvalidArgumentException;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use PHPUnit\Framework\MockObject\Stub;
use ReflectionClass;
use ReflectionException;
use TypeError;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

class TestableEnginesV2Controller extends EnginesController
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

class ValidatorTestableEnginesV2Controller extends EnginesController
{
    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }
}

class EnginesV2ControllerTest extends AbstractTest
{
    /** @var ReflectionClass<EnginesController> */
    private ReflectionClass $reflector;
    private TestableEnginesV2Controller $controller;
    private Stub&PDOStatement $stmtStub;

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitException
     * @throws TypeError
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws \Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        AppConfig::$SKIP_SQL_CACHE = true;

        $this->stmtStub            = $this->createStub(PDOStatement::class);
        $this->stmtStub->queryString = '';
        $pdoStub                   = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($this->stmtStub);
        $dbStub                    = $this->createStub(IDatabase::class);
        $dbStub->method('getConnection')->willReturn($pdoStub);
        $this->setDatabaseInstance($dbStub);

        $this->controller = new TestableEnginesV2Controller();
        $this->reflector  = new ReflectionClass(EnginesController::class);

        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createStub(MatecatLogger::class));

        $user      = new UserStruct();
        $user->uid = 9049010;
        $this->reflector->getProperty('user')->setValue($this->controller, $user);
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
    }

    /**
     * @param list<EngineStruct> $rows
     */
    private function stubEngineRows(array $rows): void
    {
        $this->stmtStub->method('fetchAll')->willReturn($rows);
    }

    private function makeEngine(int $id, string $name, string $classLoad): EngineStruct
    {
        $e                   = new EngineStruct();
        $e->id               = $id;
        $e->name             = $name;
        $e->type             = 'MT';
        $e->description      = 'desc-' . $id;
        $e->class_load       = $classLoad;
        $e->others           = '[]';
        $e->extra_parameters = '[]';
        $e->active           = true;
        $e->uid              = 9049010;

        return $e;
    }

    // ── registerValidators ──────────────────────────────────────────────────

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws \Exception
     * @throws TypeError
     */
    #[Test]
    public function registerValidators_appends_login_validator(): void
    {
        $controller = new ValidatorTestableEnginesV2Controller();
        $ref        = new ReflectionClass(EnginesController::class);

        $ref->getProperty('request')->setValue($controller, $this->createStub(Request::class));

        $ref->getMethod('registerValidators')->invoke($controller);

        $validators = $ref->getProperty('validators')->getValue($controller);
        self::assertIsArray($validators);
        self::assertCount(1, $validators);
        self::assertInstanceOf(LoginValidator::class, $validators[0]);
    }

    // ── listEngines (happy path — concrete payload) ─────────────────────────

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws Exception
     */
    #[Test]
    public function listEngines_renders_active_engines_for_the_user(): void
    {
        $this->stubEngineRows([
            $this->makeEngine(9049020, 'MyMemory MT', 'MyMemory'),
        ]);

        $captured = null;
        $response = $this->createMock(Response::class);
        $response->expects(self::once())
            ->method('json')
            ->with(self::callback(function ($payload) use (&$captured) {
                $captured = $payload;

                return is_array($payload);
            }));
        $this->reflector->getProperty('response')->setValue($this->controller, $response);

        $this->controller->listEngines();

        self::assertIsArray($captured);
        self::assertCount(1, $captured);
        self::assertSame(9049020, $captured[0]['id']);
        self::assertSame('MyMemory MT', $captured[0]['name']);
        self::assertSame('MyMemory', $captured[0]['engine_type']);
    }

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws Exception
     */
    #[Test]
    public function listEngines_renders_empty_payload_when_user_has_no_engines(): void
    {
        $this->stubEngineRows([]);

        $captured = null;
        $response = $this->createMock(Response::class);
        $response->expects(self::once())
            ->method('json')
            ->with(self::callback(function ($payload) use (&$captured) {
                $captured = $payload;

                return is_array($payload);
            }));
        $this->reflector->getProperty('response')->setValue($this->controller, $response);

        $this->controller->listEngines();

        self::assertSame([], $captured);
    }

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws Exception
     */
    #[Test]
    public function listEngines_renders_multiple_engines_with_resolved_types(): void
    {
        // Multiple engines render their engine_type from the resolved class_load.
        $this->stubEngineRows([
            $this->makeEngine(9049030, 'Some MT', 'MyMemory'),
            $this->makeEngine(9049031, 'Another MT', 'MMT'),
        ]);

        $captured = null;
        $response = $this->createMock(Response::class);
        $response->expects(self::once())
            ->method('json')
            ->with(self::callback(function ($payload) use (&$captured) {
                $captured = $payload;

                return is_array($payload);
            }));
        $this->reflector->getProperty('response')->setValue($this->controller, $response);

        $this->controller->listEngines();

        self::assertIsArray($captured);
        self::assertCount(2, $captured);
        self::assertSame('MyMemory', $captured[0]['engine_type']);
        self::assertSame('MMT', $captured[1]['engine_type']);
    }
}
