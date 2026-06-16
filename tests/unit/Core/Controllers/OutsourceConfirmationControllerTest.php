<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\OutsourceConfirmationController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Exception;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
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
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;
use Utils\Tools\SimpleJWT;

/**
 * Real-DB suite for {@see OutsourceConfirmationController}.
 *
 * Reserved ID block (Playbook §4): base = 9_015_000 (task N=15).
 *   9015001 project, 9015002 job, 9015003 segment, 9015004 file.
 * Owner email: ctrltest_9015000@example.org (never the shared test@example.org).
 * Clean ONLY by reserved id; clean-then-seed in setUp(); parent::tearDown() last.
 *
 * The controller has NO afterConstruct() override; the Testable subclass only
 * neuters the constructor and the (empty) base init/register hooks.
 */
class TestableOutsourceConfirmationController extends OutsourceConfirmationController
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
class OutsourceConfirmationControllerTest extends AbstractTest
{
    use ControllerSeedFragments {
        cleanFragments as private cleanReservedFragments;
    }

    private const int    BASE         = 9_015_000;
    private const string JOB_PASSWORD = 'jobpw';

    /** @var ReflectionClass<OutsourceConfirmationController> */
    private ReflectionClass $reflector;
    private TestableOutsourceConfirmationController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws PDOException
     * @throws NoPreviousThrowableException
     * @throws PHPUnitInvalidArgumentException
     * @throws MockObjectException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);
        $this->seedTestData();

        $this->controller = new TestableOutsourceConfirmationController();
        $this->reflector  = new ReflectionClass(OutsourceConfirmationController::class);

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

        $this->seedProject(self::BASE, $owner);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $owner, self::JOB_PASSWORD);
        $this->seedSegment(self::BASE);
        $this->seedSegmentTranslation(self::BASE);
    }

    /**
     * @throws PDOException
     */
    private function cleanFragments(int $base): void
    {
        $this->seedConnection()->exec(
            "DELETE FROM outsource_confirmation WHERE id_job = " . $this->jobId($base)
        );
        $this->cleanReservedFragments($base);
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
        $serverParams      = ['REQUEST_URI' => '/api/app/outsource/confirm', 'REQUEST_METHOD' => 'POST'];
        $this->requestStub = new Request($params, [], [], $serverParams);
        $this->setProp('request', $this->requestStub);
    }

    /**
     * Build a signed SimpleJWT whose custom claims carry id_job/password (the
     * shape {@see OutsourceConfirmationController::confirm()} reads via
     * SimpleJWT::getPayload()).
     *
     * @param array<string, mixed> $claims
     *
     * @throws TypeError
     * @throws \UnexpectedValueException
     */
    private function makeToken(array $claims): string
    {
        $jwt = new SimpleJWT($claims, 'simple.jwt.claims', AppConfig::$AUTHSECRET);

        return (string) $jwt;
    }

    // ─── confirm() happy path ───

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws PHPUnitException
     * @throws ExpectationFailedException
     */
    #[Test]
    public function confirm_returns_confirmation_payload_for_valid_job(): void
    {
        $jobId = $this->jobId(self::BASE);
        $token = $this->makeToken([
            'id_job'   => (string) $jobId,
            'password' => self::JOB_PASSWORD,
        ]);

        $this->setRequestParams([
            'id_job'   => (string) $jobId,
            'password' => self::JOB_PASSWORD,
            'payload'  => $token,
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('errors', $data);
                $this->assertArrayHasKey('confirm', $data);
                $this->assertSame([], $data['errors']);
                $this->assertIsArray($data['confirm']);
                // confirm() unsets the auto-increment id before responding.
                $this->assertArrayNotHasKey('id', $data['confirm']);
                $this->assertSame($this->jobId(self::BASE), (int) $data['confirm']['id_job']);
                $this->assertArrayHasKey('vendor_name', $data['confirm']);
                $this->assertNotEmpty($data['confirm']['create_date']);
                return true;
            }));

        $this->controller->confirm();
    }

    /**
     * The id_job/password carried by the payload override the request couple in
     * the persisted confirmation, so the response echoes the seeded job id even
     * when the request params merely match it.
     *
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws PHPUnitException
     * @throws ExpectationFailedException
     */
    #[Test]
    public function confirm_echoes_payload_id_job_in_confirmation(): void
    {
        $jobId = $this->jobId(self::BASE);
        $token = $this->makeToken([
            'id_job'   => (string) $jobId,
            'password' => self::JOB_PASSWORD,
        ]);

        $this->setRequestParams([
            'id_job'   => (string) $jobId,
            'password' => self::JOB_PASSWORD,
            'payload'  => $token,
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame($this->jobId(self::BASE), (int) $data['confirm']['id_job']);
                return true;
            }));

        $this->controller->confirm();
    }

    // ─── confirm() failure paths ───

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function confirm_throws_authorization_error_when_request_does_not_match_payload(): void
    {
        $jobId = $this->jobId(self::BASE);
        // Payload says one id_job, the request claims a different one.
        $token = $this->makeToken([
            'id_job'   => (string) $jobId,
            'password' => self::JOB_PASSWORD,
        ]);

        $this->setRequestParams([
            'id_job'   => (string) ($jobId + 999),
            'password' => self::JOB_PASSWORD,
            'payload'  => $token,
        ]);

        $this->expectException(AuthorizationError::class);
        $this->expectExceptionMessage('Invalid Job');

        $this->controller->confirm();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function confirm_throws_authorization_error_when_password_does_not_match_payload(): void
    {
        $jobId = $this->jobId(self::BASE);
        $token = $this->makeToken([
            'id_job'   => (string) $jobId,
            'password' => self::JOB_PASSWORD,
        ]);

        $this->setRequestParams([
            'id_job'   => (string) $jobId,
            'password' => 'a_different_password',
            'payload'  => $token,
        ]);

        $this->expectException(AuthorizationError::class);
        $this->expectExceptionMessage('Invalid Job');

        $this->controller->confirm();
    }

    /**
     * Payload and request agree, but no job row matches the id+password couple.
     *
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function confirm_throws_authorization_error_when_job_not_found(): void
    {
        $missingId = $this->jobId(self::BASE) + 50_000;
        $token     = $this->makeToken([
            'id_job'   => (string) $missingId,
            'password' => 'no_such_password',
        ]);

        $this->setRequestParams([
            'id_job'   => (string) $missingId,
            'password' => 'no_such_password',
            'payload'  => $token,
        ]);

        $this->expectException(AuthorizationError::class);
        $this->expectExceptionMessage('Job not found');

        $this->controller->confirm();
    }
}
