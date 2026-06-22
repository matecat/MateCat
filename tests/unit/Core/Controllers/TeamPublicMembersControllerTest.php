<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

/**
 * Mock-seam unit test (Playbook §2).
 * ID block base 9023000 (reserved; this suite uses no real-DB seeding).
 * Per-suite owner identifier (unused — no DB rows seeded): ctrltest_9023000@example.org
 */

use Controller\API\App\TeamPublicMembersController;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\TeamAccessValidator;
use Exception;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Event\NoPreviousThrowableException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Exception as PHPUnitException;
use PHPUnit\Framework\InvalidArgumentException as PHPUnitInvalidArgumentException;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use ReflectionClass;
use ReflectionException;
use TypeError;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

class TestableTeamPublicMembersController extends TeamPublicMembersController
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

class ValidatorTestableTeamPublicMembersController extends TeamPublicMembersController
{
    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }
}

class TeamPublicMembersControllerTest extends AbstractTest
{
    /** @var ReflectionClass<TeamPublicMembersController> */
    private ReflectionClass $reflector;
    private TestableTeamPublicMembersController $controller;

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws Exception
     * @throws TypeError
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     */
    protected function setUp(): void
    {
        parent::setUp();

        AppConfig::$SKIP_SQL_CACHE = true;
        $this->createDatabaseMock();

        $this->controller = new TestableTeamPublicMembersController();
        $this->reflector  = new ReflectionClass(TeamPublicMembersController::class);

        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createStub(MatecatLogger::class));
    }

    protected function tearDown(): void
    {
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

    // ── publicList (happy path — captured payload) ──────────────────────────

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws Exception
     * @throws TypeError
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function publicList_renders_empty_member_list_for_team_without_members(): void
    {
        $this->setRequestParams(['id_team' => 9023001]);

        // The mock seam returns no rows for getMemberListByTeamId, so the public
        // renderer emits an empty list. Capture and assert the concrete payload.
        $captured = null;
        $response = $this->createMock(Response::class);
        $response->expects(self::once())
            ->method('json')
            ->with(self::callback(function ($data) use (&$captured) {
                $captured = $data;

                return true;
            }));
        $this->reflector->getProperty('response')->setValue($this->controller, $response);

        $this->controller->publicList();

        self::assertIsArray($captured);
        self::assertSame([], $captured);
    }

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws Exception
     * @throws TypeError
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function publicList_throws_type_error_when_id_team_missing(): void
    {
        // Missing id_team param resolves to null; the DAO signature requires int,
        // so the action surfaces a TypeError before any JSON is emitted.
        $this->setRequestParams([]);

        $response = $this->createMock(Response::class);
        $response->expects(self::never())->method('json');
        $this->reflector->getProperty('response')->setValue($this->controller, $response);

        $this->expectException(TypeError::class);

        $this->controller->publicList();
    }

    // ── registerValidators ──────────────────────────────────────────────────

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws PHPUnitException
     * @throws Exception
     * @throws TypeError
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function registerValidators_appends_login_and_team_access_validators(): void
    {
        $controller = new ValidatorTestableTeamPublicMembersController();
        $ref        = new ReflectionClass(TeamPublicMembersController::class);

        $ref->getProperty('request')->setValue($controller, $this->createStub(Request::class));

        $ref->getMethod('registerValidators')->invoke($controller);

        $validators = $ref->getProperty('validators')->getValue($controller);
        self::assertIsArray($validators);
        self::assertCount(2, $validators);
        self::assertInstanceOf(LoginValidator::class, $validators[0]);
        self::assertInstanceOf(TeamAccessValidator::class, $validators[1]);
    }
}
