<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\ChangeJobsStatusController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\Exceptions\NotFoundException;
use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;
use PDOException;
use PHPUnit\Event\NoPreviousThrowableException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Exception as PHPUnitException;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\InvalidArgumentException as PHPUnitInvalidArgumentException;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use TypeError;
use Utils\Constants\JobStatus;
use Utils\Logger\MatecatLogger;

/**
 * Real-DB suite for {@see ChangeJobsStatusController}.
 *
 * Reserved ID block (Playbook §4): base = 9_011_000 (task N=11).
 *   9011001 project, 9011002 job, 9011003 segment, 9011004 file.
 * Owner email: ctrltest_9011000@example.org (never the shared test@example.org).
 * Clean ONLY by reserved id; clean-then-seed in setUp(); parent::tearDown() last.
 */
class TestableChangeJobsStatusController extends ChangeJobsStatusController
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
class ChangeJobsStatusControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_011_000;
    private const string JOB_PASSWORD = 'jobpw';
    private const string PROJECT_PASSWORD = 'projpw';

    /** @var ReflectionClass<ChangeJobsStatusController> */
    private ReflectionClass $reflector;
    private TestableChangeJobsStatusController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws NoPreviousThrowableException
     * @throws PHPUnitInvalidArgumentException
     * @throws MockObjectException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);
        $this->seedTestData();

        $this->controller = new TestableChangeJobsStatusController();
        $this->reflector  = new ReflectionClass(ChangeJobsStatusController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);

        $user        = new UserStruct();
        $user->uid   = $this->userId(self::BASE);
        $user->email = $this->ownerEmail(self::BASE);
        $this->setProp('user', $user);

        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet());
    }

    /**
     * @throws PDOException
     */
    protected function tearDown(): void
    {
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    /**
     * @throws PDOException
     */
    private function seedTestData(): void
    {
        $owner = $this->ownerEmail(self::BASE);

        $this->seedProject(self::BASE, $owner, self::PROJECT_PASSWORD);
        $this->seedFile(self::BASE);
        $this->seedSegment(self::BASE);
        $this->seedJob(self::BASE, $owner, self::JOB_PASSWORD, JobStatus::STATUS_ACTIVE);
        $this->seedSegmentTranslation(self::BASE);
    }

    /**
     * @throws ReflectionException
     */
    private function setProp(string $name, mixed $value): void
    {
        $prop = $this->reflector->getProperty($name);
        $prop->setValue($this->controller, $value);
    }

    /**
     * @param array<string, string> $params
     *
     * @throws ReflectionException
     */
    private function setRequestParams(array $params): void
    {
        $serverParams       = ['REQUEST_URI' => '/api/app/changeJobsStatus', 'REQUEST_METHOD' => 'POST'];
        $this->requestStub  = new Request($params, [], [], $serverParams);
        $this->setProp('request', $this->requestStub);
    }

    /**
     * @param array<int, mixed> $args
     *
     * @throws ReflectionException
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        return $this->reflector->getMethod($method)->invoke($this->controller, ...$args);
    }

    // ─── validateTheRequest ───

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateTheRequest_throws_on_invalid_status(): void
    {
        $this->setRequestParams([
            'id'         => (string) $this->jobId(self::BASE),
            'res'        => 'job',
            'password'   => self::JOB_PASSWORD,
            'new_status' => 'not_a_real_status',
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid Status');

        $this->invokePrivate('validateTheRequest');
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateTheRequest_throws_when_new_status_missing(): void
    {
        $this->setRequestParams([
            'id'       => (string) $this->jobId(self::BASE),
            'password' => self::JOB_PASSWORD,
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid Status');

        $this->invokePrivate('validateTheRequest');
    }

    /**
     * @throws ReflectionException
     * @throws PHPUnitException
     * @throws ExpectationFailedException
     */
    #[Test]
    public function validateTheRequest_returns_expected_shape_for_valid_input(): void
    {
        $this->setRequestParams([
            'pn'         => 'MyProjectName',
            'id'         => (string) $this->jobId(self::BASE),
            'res'        => 'job',
            'password'   => self::JOB_PASSWORD,
            'new_status' => JobStatus::STATUS_ARCHIVED,
        ]);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertIsArray($result);
        $this->assertSame('MyProjectName', $result['pn']);
        $this->assertSame('job', $result['res_type']);
        $this->assertSame($this->jobId(self::BASE), $result['res_id']);
        $this->assertSame(self::JOB_PASSWORD, $result['password']);
        $this->assertSame(JobStatus::STATUS_ARCHIVED, $result['new_status']);
    }

    // ─── changeStatus — job branch ───

    /**
     * @throws ReflectionException
     * @throws NotFoundException
     * @throws Exception
     */
    #[Test]
    public function changeStatus_job_branch_returns_ok_payload(): void
    {
        $this->setRequestParams([
            'id'         => (string) $this->jobId(self::BASE),
            'res'        => 'job',
            'password'   => self::JOB_PASSWORD,
            'new_status' => JobStatus::STATUS_ARCHIVED,
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame([], $data['errors']);
                $this->assertSame(1, $data['code']);
                $this->assertSame('OK', $data['data']);
                $this->assertSame(JobStatus::STATUS_ARCHIVED, $data['status']);
                return true;
            }));

        $this->controller->changeStatus();
    }

    /**
     * @throws ReflectionException
     * @throws NotFoundException
     * @throws Exception
     */
    #[Test]
    public function changeStatus_job_branch_throws_not_found_for_wrong_password(): void
    {
        $this->setRequestParams([
            'id'         => (string) $this->jobId(self::BASE),
            'res'        => 'job',
            'password'   => 'wrong_password_xyz',
            'new_status' => JobStatus::STATUS_ARCHIVED,
        ]);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Job not found');

        $this->controller->changeStatus();
    }

    // ─── changeStatus — project branch ───

    /**
     * @throws ReflectionException
     * @throws NotFoundException
     * @throws Exception
     */
    #[Test]
    public function changeStatus_project_branch_returns_ok_payload(): void
    {
        $this->setRequestParams([
            'id'         => (string) $this->projectId(self::BASE),
            'res'        => 'prj',
            'password'   => self::PROJECT_PASSWORD,
            'new_status' => JobStatus::STATUS_CANCELLED,
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame([], $data['errors']);
                $this->assertSame(1, $data['code']);
                $this->assertSame('OK', $data['data']);
                $this->assertSame(JobStatus::STATUS_CANCELLED, $data['status']);
                return true;
            }));

        $this->controller->changeStatus();
    }

    /**
     * @throws ReflectionException
     * @throws NotFoundException
     * @throws Exception
     */
    #[Test]
    public function changeStatus_project_branch_throws_not_found_for_wrong_password(): void
    {
        $this->setRequestParams([
            'id'         => (string) $this->projectId(self::BASE),
            'res'        => 'prj',
            'password'   => 'wrong_project_password',
            'new_status' => JobStatus::STATUS_CANCELLED,
        ]);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Job not found');

        $this->controller->changeStatus();
    }

    // ─── registerValidators ───

    /**
     * @throws ReflectionException
     * @throws PHPUnitException
     */
    #[Test]
    public function registerValidators_appends_the_login_validator(): void
    {
        $controller = $this->reflector->newInstanceWithoutConstructor();

        $reqProp = $this->reflector->getProperty('request');
        $reqProp->setValue($controller, new Request());

        $method = $this->reflector->getMethod('registerValidators');
        $method->invoke($controller);

        /** @var array<int, object> $validators */
        $validators = $this->reflector->getProperty('validators')->getValue($controller);

        $this->assertCount(1, $validators);
        $this->assertInstanceOf(LoginValidator::class, $validators[0]);
    }
}
