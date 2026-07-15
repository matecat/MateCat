<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

/**
 * Real-DB unit test (Playbook harness).
 * ID block: 9_972_000..9_972_999 (registry-reserved for this file).
 */

use Controller\API\App\GetVolumeAnalysisController;
use Controller\API\Commons\Validators\JobPasswordValidator;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use InvalidArgumentException;
use Exception;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;
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
    private const int TEST_PROJECT_ID = 9972000;
    private const int TEST_JOB_ID = 9972001;
    private const string TEST_PROJECT_PASSWORD = 'volanalysis_pw';
    private const string TEST_JOB_PASSWORD = 'volanalysis_job_pw';

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

        $database = obtainTestDatabase();

        $this->controller = new TestableGetVolumeAnalysisController();
        $this->reflector  = new ReflectionClass(GetVolumeAnalysisController::class);

        $this->reflector->getProperty('database')->setValue($this->controller, $database);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->createStub(Response::class));
        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createStub(MatecatLogger::class));
        $this->reflector->getProperty('featureSet')->setValue($this->controller, new FeatureSet($database));

        $user      = new UserStruct();
        $user->uid = -1;
        $this->reflector->getProperty('user')->setValue($this->controller, $user);
        $this->reflector->getProperty('userIsLogged')->setValue($this->controller, true);
    }

    protected function tearDown(): void
    {
        $this->cleanTestData();
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
    }

    private function seedTestData(): void
    {
        $conn = obtainTestDatabase()->getConnection();
        $this->cleanTestData();

        $conn->exec(
            "INSERT INTO projects (id, id_customer, password, name, create_date, status_analysis) VALUES (" .
            self::TEST_PROJECT_ID . ", 'test@example.org', '" . self::TEST_PROJECT_PASSWORD . "', 'TestVolAnalysis', NOW(), 'DONE')"
        );
        $conn->exec(
            "INSERT INTO jobs (id, password, id_project, source, target, job_first_segment, job_last_segment, owner, tm_keys, create_date, disabled) VALUES (" .
            self::TEST_JOB_ID . ", '" . self::TEST_JOB_PASSWORD . "', " . self::TEST_PROJECT_ID . ", 'en-US', 'it-IT', 1, 1, 'test@example.org', '[]', NOW(), 0)"
        );
    }

    private function cleanTestData(): void
    {
        $conn = obtainTestDatabase()->getConnection();
        $conn->exec("DELETE FROM jobs WHERE id = " . self::TEST_JOB_ID);
        $conn->exec("DELETE FROM projects WHERE id = " . self::TEST_PROJECT_ID);
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

    // ── registerValidators ───────────────────────────────────────────────

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

    // ── analysis (failure branch — project not found) ───────────────────

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
        // id_project has no matching rows in the real test DB, so
        // getProjectAndJobData() returns [] and the internally-constructed
        // Status cannot resolve the project pid.
        $this->reflector->getProperty('params')->setValue($this->controller, ['id_project' => 9972099]);
        $this->setRequestParams(['id_project' => '9972099', 'password' => 'secret']);

        $this->expectException(Throwable::class);

        $this->controller->analysis();
    }

    // ── analysis (happy path — real project+job, no segments) ───────────

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function analysis_returns_json_response_for_project_with_no_segments(): void
    {
        $this->seedTestData();

        $realResponse = new Response();
        $this->reflector->getProperty('response')->setValue($this->controller, $realResponse);
        $this->reflector->getProperty('params')->setValue($this->controller, ['id_project' => self::TEST_PROJECT_ID]);
        $this->setRequestParams(['id_project' => (string)self::TEST_PROJECT_ID, 'password' => self::TEST_PROJECT_PASSWORD]);

        $response = $this->controller->analysis();

        self::assertSame(200, $response->code());

        $body = json_decode((string)$response->body(), true);
        self::assertIsArray($body);
    }
}
