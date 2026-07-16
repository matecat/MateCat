<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

/**
 * Mock-seam unit test (Playbook §2).
 * ID block base 9025000 (reserved; this suite uses no real-DB seeding).
 * Per-suite owner identifier (unused — no DB rows seeded): ctrltest_9025000@example.org
 */

use Controller\API\App\JobTeamMembersController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\Projects\ProjectStruct;
use PHPUnit\Event\NoPreviousThrowableException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Exception as PHPUnitException;
use PHPUnit\Framework\InvalidArgumentException as PHPUnitInvalidArgumentException;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use TypeError;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

class TestableJobTeamMembersController extends JobTeamMembersController
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

class ValidatorTestableJobTeamMembersController extends JobTeamMembersController
{
    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }

    /**
     * ChunkPasswordValidator's constructor reads the request params through the
     * controller; return an empty set so registerValidators can be exercised in isolation.
     *
     * @return array<string,mixed>
     */
    public function getParams(): array
    {
        return [];
    }
}

class JobTeamMembersControllerTest extends AbstractTest
{
    /** @var ReflectionClass<JobTeamMembersController> */
    private ReflectionClass $reflector;
    private TestableJobTeamMembersController $controller;

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
        [$dbStub] = $this->createDatabaseMock();

        $this->controller = new TestableJobTeamMembersController();
        $this->reflector  = new ReflectionClass(JobTeamMembersController::class);

        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createStub(MatecatLogger::class));
        $this->reflector->getProperty('database')->setValue($this->controller, $dbStub);
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
    }

    /**
     * @throws ReflectionException
     */
    private function setProject(ProjectStruct $project): void
    {
        $this->reflector->getProperty('project')->setValue($this->controller, $project);
    }

    // ── members (happy path — captured payload) ─────────────────────────────

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
    public function members_renders_empty_member_list_for_team_without_members(): void
    {
        // The job-password capability has already resolved a project (and thus its team).
        $this->setProject(new ProjectStruct(['id_team' => 9025001]));

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

        $this->controller->members();

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
    public function members_throws_runtime_error_when_project_has_no_team(): void
    {
        // A project with no team must not silently fall back to an unscoped roster.
        $this->setProject(new ProjectStruct(['id_team' => null]));

        $response = $this->createMock(Response::class);
        $response->expects(self::never())->method('json');
        $this->reflector->getProperty('response')->setValue($this->controller, $response);

        $this->expectException(RuntimeException::class);

        $this->controller->members();
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
    public function registerValidators_appends_login_and_chunk_password_validators(): void
    {
        $controller = new ValidatorTestableJobTeamMembersController();
        $ref        = new ReflectionClass(JobTeamMembersController::class);

        $ref->getProperty('request')->setValue($controller, $this->createStub(Request::class));

        $ref->getMethod('registerValidators')->invoke($controller);

        $validators = $ref->getProperty('validators')->getValue($controller);
        self::assertIsArray($validators);
        self::assertCount(2, $validators);
        self::assertInstanceOf(LoginValidator::class, $validators[0]);
        // Authorization is by the (unguessable) job password capability — NOT a guessable
        // team name — which is what removes the member-enumeration surface (CWE-639).
        self::assertInstanceOf(ChunkPasswordValidator::class, $validators[1]);
    }
}
