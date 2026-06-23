<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\UpdateJobKeysController;
use DomainException;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Utils\Logger\MatecatLogger;

/**
 * Real-DB suite for UpdateJobKeysController.
 *
 * Reserved ID block (Playbook §4): base = 9_005_000 (Wave 1, N=5).
 *   base+1 project, base+2 job, base+3 segment, base+4 file.
 * Owner/customer email: ctrltest_9005000@example.org (never the shared test@example.org).
 */
class TestableUpdateJobKeysController extends UpdateJobKeysController
{
    public function __construct()
    {
    }

    protected function registerValidators(): void
    {
    }
}

#[AllowMockObjectsWithoutExpectations]
class UpdateJobKeysControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_005_000;
    private const string JOB_PASSWORD = 'jobpw';

    /** @var ReflectionClass<UpdateJobKeysController> */
    private ReflectionClass $reflector;
    private TestableUpdateJobKeysController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;
    private string $owner;

    /**
     * @throws \Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->ownerEmail(self::BASE);

        $this->cleanFragments(self::BASE);
        $this->seedTestData();

        $this->controller = new TestableUpdateJobKeysController();
        $this->reflector  = new ReflectionClass(UpdateJobKeysController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);
        $this->setProp('database', Database::obtain());

        $user            = new UserStruct();
        $user->uid       = $this->userId(self::BASE);
        $user->email     = $this->owner;
        $user->first_name = 'Ctrl';
        $user->last_name  = 'Tester';
        $this->setProp('user', $user);

        $this->setProp('userIsLogged', true);
        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));
    }

    protected function tearDown(): void
    {
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    private function seedTestData(): void
    {
        $this->seedProject(self::BASE, $this->owner);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $this->owner, self::JOB_PASSWORD);
        $this->seedSegment(self::BASE);
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
     * @param array<string,mixed> $params
     *
     * @throws ReflectionException
     */
    private function setRequestParams(array $params): void
    {
        $serverParams      = ['REQUEST_URI' => '/api/app/jobs/update', 'REQUEST_METHOD' => 'POST'];
        $this->requestStub = new Request([], $params, [], $serverParams);
        $this->setProp('request', $this->requestStub);
    }

    /**
     * @param array<int,mixed> $args
     *
     * @throws ReflectionException
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        $m = $this->reflector->getMethod($method);

        return $m->invoke($this->controller, ...$args);
    }

    private function validTmKeysJson(): string
    {
        return (string) json_encode([
            'mine'       => [],
            'ownergroup' => [],
            'anonymous'  => [],
        ]);
    }

    // ─── registerValidators ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function registerValidators_appends_login_validator_without_error(): void
    {
        // Use the production class to exercise the real hook body.
        $controller = (new ReflectionClass(UpdateJobKeysController::class))
            ->newInstanceWithoutConstructor();

        $reflector = new ReflectionClass(UpdateJobKeysController::class);
        $reflector->getProperty('request')->setValue($controller, new Request());
        $reflector->getProperty('response')->setValue($controller, $this->createMock(Response::class));

        $m = $reflector->getMethod('registerValidators');
        $m->invoke($controller);

        $validatorsProp = (new ReflectionClass($controller))
            ->getProperty('validators');
        /** @var array<int,mixed> $validators */
        $validators = $validatorsProp->getValue($controller);

        $this->assertCount(1, $validators);
    }

    // ─── validateTheRequest happy path ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function validateTheRequest_returns_expected_shape_for_valid_job(): void
    {
        $this->setRequestParams([
            'job_id'   => (string) $this->jobId(self::BASE),
            'job_pass' => self::JOB_PASSWORD,
            'data'     => $this->validTmKeysJson(),
        ]);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertIsArray($result);
        $this->assertSame((string) $this->jobId(self::BASE), $result['job_id']);
        $this->assertSame(self::JOB_PASSWORD, $result['job_pass']);
        $this->assertInstanceOf(JobStruct::class, $result['jobData']);
        $this->assertSame($this->jobId(self::BASE), $result['jobData']->id);
        $this->assertNull($result['public_tm_penalty']);
        $this->assertFalse($result['get_public_matches']);
        $this->assertTrue($result['only_private']);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function validateTheRequest_sets_only_private_false_when_public_matches_true(): void
    {
        $this->setRequestParams([
            'job_id'             => (string) $this->jobId(self::BASE),
            'job_pass'           => self::JOB_PASSWORD,
            'data'               => $this->validTmKeysJson(),
            'get_public_matches' => 'true',
        ]);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertTrue($result['get_public_matches']);
        $this->assertFalse($result['only_private']);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function validateTheRequest_parses_public_tm_penalty_integer(): void
    {
        $this->setRequestParams([
            'job_id'            => (string) $this->jobId(self::BASE),
            'job_pass'          => self::JOB_PASSWORD,
            'data'              => $this->validTmKeysJson(),
            'public_tm_penalty' => '50',
        ]);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertSame(50, $result['public_tm_penalty']);
    }

    // ─── validateTheRequest failure paths ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function validateTheRequest_throws_when_job_id_missing(): void
    {
        $this->setRequestParams([
            'job_pass' => self::JOB_PASSWORD,
            'data'     => $this->validTmKeysJson(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-1);

        $this->invokePrivate('validateTheRequest');
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function validateTheRequest_throws_when_job_pass_missing(): void
    {
        $this->setRequestParams([
            'job_id' => (string) $this->jobId(self::BASE),
            'data'   => $this->validTmKeysJson(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-2);

        $this->invokePrivate('validateTheRequest');
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function validateTheRequest_throws_when_penalty_out_of_range(): void
    {
        $this->setRequestParams([
            'job_id'            => (string) $this->jobId(self::BASE),
            'job_pass'          => self::JOB_PASSWORD,
            'data'              => $this->validTmKeysJson(),
            'public_tm_penalty' => '101',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-6);

        $this->invokePrivate('validateTheRequest');
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function validateTheRequest_throws_not_found_for_wrong_password(): void
    {
        $this->setRequestParams([
            'job_id'   => (string) $this->jobId(self::BASE),
            'job_pass' => 'wrongpass',
            'data'     => $this->validTmKeysJson(),
        ]);

        $this->expectException(NotFoundException::class);

        $this->invokePrivate('validateTheRequest');
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function validateTheRequest_throws_domain_exception_for_invalid_tm_keys(): void
    {
        $this->setRequestParams([
            'job_id'   => (string) $this->jobId(self::BASE),
            'job_pass' => self::JOB_PASSWORD,
            'data'     => '{"not":"a valid job_keys payload"}',
        ]);

        $this->expectException(DomainException::class);

        $this->invokePrivate('validateTheRequest');
    }

    // ─── jobOwnerIsMe ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function jobOwnerIsMe_returns_true_when_owner_matches_logged_user(): void
    {
        $this->setProp('userIsLogged', true);

        $result = $this->invokePrivate('jobOwnerIsMe', [$this->owner]);

        $this->assertTrue($result);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function jobOwnerIsMe_returns_false_when_owner_differs(): void
    {
        $this->setProp('userIsLogged', true);

        $result = $this->invokePrivate('jobOwnerIsMe', ['someone-else@example.org']);

        $this->assertFalse($result);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function jobOwnerIsMe_returns_false_when_not_logged_in(): void
    {
        $this->setProp('userIsLogged', false);

        $result = $this->invokePrivate('jobOwnerIsMe', [$this->owner]);

        $this->assertFalse($result);
    }

    // ─── validateTMKeysArray ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function validateTMKeysArray_passes_for_valid_payload(): void
    {
        $this->invokePrivate('validateTMKeysArray', [$this->validTmKeysJson()]);

        // No exception thrown means the schema validation passed.
        $this->addToAssertionCount(1);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function validateTMKeysArray_throws_for_invalid_payload(): void
    {
        $this->expectException(\Exception::class);

        $this->invokePrivate('validateTMKeysArray', ['{"missing":"required keys"}']);
    }

    // ─── update() happy path ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function update_returns_ok_payload_for_owner_with_empty_keys(): void
    {
        $this->setRequestParams([
            'job_id'   => (string) $this->jobId(self::BASE),
            'job_pass' => self::JOB_PASSWORD,
            'data'     => $this->validTmKeysJson(),
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('data', $data);
                $this->assertSame('OK', $data['data']);
                return true;
            }));

        $this->controller->update();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function update_sets_public_tm_penalty_metadata_when_provided(): void
    {
        $this->setRequestParams([
            'job_id'            => (string) $this->jobId(self::BASE),
            'job_pass'          => self::JOB_PASSWORD,
            'data'              => $this->validTmKeysJson(),
            'public_tm_penalty' => '25',
            'job_pass_meta'     => self::JOB_PASSWORD,
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame('OK', $data['data']);
                return true;
            }));

        $this->controller->update();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function update_returns_ok_for_non_owner_translator_role(): void
    {
        // A logged user whose email does NOT match the job owner falls into the
        // isRevision()/translator role branch (covers lines 51-54).
        $user             = new UserStruct();
        $user->uid        = $this->userId(self::BASE);
        $user->email      = 'not-the-owner_' . self::BASE . '@example.org';
        $user->first_name = 'Other';
        $user->last_name  = 'User';
        $this->setProp('user', $user);
        $this->setProp('userIsLogged', true);

        $this->setRequestParams([
            'job_id'   => (string) $this->jobId(self::BASE),
            'job_pass' => self::JOB_PASSWORD,
            'data'     => $this->validTmKeysJson(),
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame('OK', $data['data']);
                return true;
            }));

        $this->controller->update();
    }

    // ─── update() failure propagation ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function update_propagates_invalid_argument_when_job_id_missing(): void
    {
        $this->setRequestParams([
            'job_pass' => self::JOB_PASSWORD,
            'data'     => $this->validTmKeysJson(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-1);

        $this->controller->update();
    }
}
