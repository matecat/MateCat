<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

/**
 * Mock-seam unit test (Playbook §2).
 * ID block base 9020000 (reserved; this suite uses no real-DB seeding).
 * Per-suite owner identifier (unused — no DB rows seeded): ctrltest_9020000@example.org
 */

use Controller\API\App\GetVolumeAnalysisController;
use Controller\API\Commons\Validators\JobPasswordValidator;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use InvalidArgumentException;
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
use Throwable;
use TypeError;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

class TestableGetVolumeAnalysisController extends GetVolumeAnalysisController
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

class ValidatorTestableGetVolumeAnalysisController extends GetVolumeAnalysisController
{
    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }
}

class GetVolumeAnalysisControllerTest extends AbstractTest
{
    /** @var ReflectionClass<GetVolumeAnalysisController> */
    private ReflectionClass $reflector;
    private TestableGetVolumeAnalysisController $controller;

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

        $this->controller = new TestableGetVolumeAnalysisController();
        $this->reflector  = new ReflectionClass(GetVolumeAnalysisController::class);

        $this->reflector->getProperty('response')->setValue($this->controller, $this->createStub(Response::class));
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

    // ── validateTheRequest (private, exercised via registerValidators) ──────

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws \PHPUnit\Framework\AssertionFailedError
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function validateTheRequest_throws_when_id_project_missing(): void
    {
        $this->setRequestParams([]);

        $method = $this->reflector->getMethod('validateTheRequest');

        try {
            $method->invoke($this->controller);
            self::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            self::assertSame(-1, $e->getCode());
            self::assertSame('No id project provided', $e->getMessage());
        }
    }

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws \PHPUnit\Framework\AssertionFailedError
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function validateTheRequest_throws_when_password_missing(): void
    {
        $this->setRequestParams(['id_project' => '42']);

        $method = $this->reflector->getMethod('validateTheRequest');

        try {
            $method->invoke($this->controller);
            self::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            self::assertSame(-2, $e->getCode());
            self::assertSame('No password provided', $e->getMessage());
        }
    }

    /**
     * @throws ReflectionException
     * @throws InvalidArgumentException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function validateTheRequest_passes_when_project_and_password_present(): void
    {
        $this->setRequestParams(['id_project' => '42', 'password' => 'secret']);

        $method = $this->reflector->getMethod('validateTheRequest');
        $result = $method->invoke($this->controller);

        // void method returns null and throws nothing on the happy branch.
        self::assertNull($result);
    }

    // ── registerValidators ─────────────────────────────────────────────────

    /**
     * @throws ReflectionException
     * @throws InvalidArgumentException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws PHPUnitException
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function registerValidators_appends_project_password_validator_without_id_job(): void
    {
        $controller = new ValidatorTestableGetVolumeAnalysisController();
        $ref        = new ReflectionClass(GetVolumeAnalysisController::class);

        $this->setRequestParamsOn($ref, $controller, ['id_project' => '42', 'password' => 'secret']);

        $ref->getMethod('registerValidators')->invoke($controller);

        $validators = $ref->getProperty('validators')->getValue($controller);
        self::assertIsArray($validators);
        self::assertCount(2, $validators);
        self::assertInstanceOf(ProjectPasswordValidator::class, $validators[1]);
    }

    /**
     * @throws ReflectionException
     * @throws InvalidArgumentException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws PHPUnitException
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function registerValidators_appends_job_password_validator_with_id_job(): void
    {
        $controller = new ValidatorTestableGetVolumeAnalysisController();
        $ref        = new ReflectionClass(GetVolumeAnalysisController::class);

        $this->setRequestParamsOn($ref, $controller, ['id_project' => '42', 'password' => 'secret', 'id_job' => '7']);

        $ref->getMethod('registerValidators')->invoke($controller);

        $validators = $ref->getProperty('validators')->getValue($controller);
        self::assertIsArray($validators);
        self::assertCount(2, $validators);
        self::assertInstanceOf(JobPasswordValidator::class, $validators[1]);
    }

    /**
     * @throws ReflectionException
     * @throws InvalidArgumentException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function registerValidators_propagates_validation_error(): void
    {
        $controller = new ValidatorTestableGetVolumeAnalysisController();
        $ref        = new ReflectionClass(GetVolumeAnalysisController::class);

        $this->setRequestParamsOn($ref, $controller, []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-1);

        $ref->getMethod('registerValidators')->invoke($controller);
    }

    /**
     * @param ReflectionClass<GetVolumeAnalysisController> $ref
     * @param array<string,mixed>                          $params
     *
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     */
    private function setRequestParamsOn(ReflectionClass $ref, object $controller, array $params): void
    {
        $request = $this->createStub(Request::class);
        $request->method('param')->willReturnCallback(
            fn(string $key) => $params[$key] ?? null
        );
        $ref->getProperty('request')->setValue($controller, $request);
        $ref->getProperty('params')->setValue($controller, $params);
    }

    // ── analysis (failure branch — Status not unit-injectable) ──────────────

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function analysis_throws_when_project_data_empty(): void
    {
        // Mock seam returns an empty result set for getProjectAndJobData, so the
        // internally-constructed Status cannot resolve the project pid.
        $this->reflector->getProperty('params')->setValue($this->controller, ['id_project' => 9020001]);
        $this->setRequestParams(['id_project' => '9020001', 'password' => 'secret']);

        $this->expectException(Throwable::class);

        $this->controller->analysis();
    }
}
