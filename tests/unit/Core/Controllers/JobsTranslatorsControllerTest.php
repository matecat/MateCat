<?php

namespace Matecat\Core\Controllers;

use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\V2\JobsTranslatorsController;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\Jobs\JobStruct;
use Model\Outsource\ConfirmationDao;
use Utils\Constants\JobStatus;
use Model\FeaturesBase\FeatureSet;
use Model\Translators\JobsTranslatorsStruct;
use Model\Translators\TranslatorsModel;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Utils\Logger\MatecatLogger;

/**
 * Reserved ID block: base 9033000 (project 9033001, job 9033002, segment 9033003, file 9033004).
 * Owner email: ctrltest_9033000@example.org. Clean ONLY by reserved id (Playbook §4).
 */
class TestableJobsTranslatorsController extends JobsTranslatorsController
{
    public function __construct()
    {
    }

    protected function registerValidators(): void
    {
    }
}

/**
 * Overrides the makeTranslatorsModel() seam so add() never builds a real
 * TranslatorsModel (whose update() dispatches an invite email).
 */
class StubModelJobsTranslatorsController extends JobsTranslatorsController
{
    public ?TranslatorsModel $stubModel = null;

    public function __construct()
    {
    }

    protected function registerValidators(): void
    {
    }

    protected function makeTranslatorsModel(JobStruct $chunk): TranslatorsModel
    {
        return $this->stubModel;
    }
}

#[AllowMockObjectsWithoutExpectations]
class JobsTranslatorsControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9033000;

    /** @var ReflectionClass<JobsTranslatorsController> */
    private ReflectionClass $reflector;
    private TestableJobsTranslatorsController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws ReflectionException
     * @throws \PDOException
     * @throws \Exception
     * @throws \TypeError
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws \PHPUnit\Event\NoPreviousThrowableException
     * @throws \PHPUnit\Framework\InvalidArgumentException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);
        $this->seedConnection()->exec("DELETE FROM outsource_confirmation WHERE id_job = " . $this->jobId(self::BASE));
        $this->seedTestData();
        // Drop any leaked Redis confirmation cache for this job (1h TTL) so each
        // test reads a fresh DB state for the outsource check.
        (new ConfirmationDao(obtainTestDatabase()))->destroyConfirmationCache($this->loadJobStruct());

        $this->controller = new TestableJobsTranslatorsController();
        $this->reflector  = new ReflectionClass(JobsTranslatorsController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);

        $user        = new UserStruct();
        $user->uid   = 1;
        $user->email = $this->ownerEmail(self::BASE);
        $user->first_name = 'Ctrl';
        $user->last_name  = 'Tester';
        $this->setProp('user', $user);

        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));
        $this->setProp('database', obtainTestDatabase());
    }

    /**
     * @throws \PDOException
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        $this->seedConnection()->exec("DELETE FROM outsource_confirmation WHERE id_job = " . $this->jobId(self::BASE));
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    /**
     * @throws ReflectionException
     * @throws \PDOException
     */
    private function seedConfirmation(JobStruct $jStruct): void
    {
        $jobId = $this->jobId(self::BASE);
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO outsource_confirmation (id_job, password, id_vendor, vendor_name, delivery_date) "
            . "VALUES ($jobId, 'jobpw', 1, 'Translated', NOW())"
        );
        // The DAO caches getConfirmation() in Redis with a 1h TTL; drop any stale
        // (empty) cache entry so the freshly-seeded row is read deterministically.
        (new ConfirmationDao(obtainTestDatabase()))->destroyConfirmationCache($jStruct);
    }

    /**
     * @throws \PDOException
     */
    private function seedTestData(): void
    {
        $owner = $this->ownerEmail(self::BASE);
        $this->seedProject(self::BASE, $owner);
        $this->seedFile(self::BASE);
        $this->seedSegment(self::BASE);
        $this->seedJob(self::BASE, $owner);
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
     * @throws ReflectionException
     */
    private function setPropOn(object $controller, string $name, mixed $value): void
    {
        $prop = $this->reflector->getProperty($name);
        $prop->setValue($controller, $value);
    }

    private function loadJobStruct(bool $deleted = false): JobStruct
    {
        $job               = new JobStruct();
        $job->id           = $this->jobId(self::BASE);
        $job->password     = 'jobpw';
        $job->id_project   = $this->projectId(self::BASE);
        $job->source       = 'en-US';
        $job->target       = 'it-IT';
        $job->status_owner = $deleted ? JobStatus::STATUS_DELETED : JobStatus::STATUS_ACTIVE;

        return $job;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws ReflectionException
     */
    private function setParams(array $params): void
    {
        $prop = $this->reflector->getProperty('params');
        $prop->setValue($this->controller, $params);
    }

    // ─── get() happy path ───

    /**
     * @throws ReflectionException
     * @throws \Model\Exceptions\NotFoundException
     * @throws NotFoundException
     * @throws \Exception
     * @throws \TypeError
     */
    #[Test]
    public function get_returns_job_payload_with_null_translator_when_not_outsourced(): void
    {
        $jStruct = $this->loadJobStruct();
        (new ConfirmationDao(obtainTestDatabase()))->destroyConfirmationCache($jStruct);
        $this->setProp('jStruct', $jStruct);
        $this->setParams(['id_job' => (string) $this->jobId(self::BASE), 'password' => 'jobpw']);

        $expectedId = $this->jobId(self::BASE);
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use ($expectedId): bool {
                $this->assertArrayHasKey('job', $data);
                $this->assertSame($expectedId, $data['job']['id']);
                $this->assertSame('jobpw', $data['job']['password']);
                $this->assertNull($data['job']['translator']);
                return true;
            }));

        $this->controller->get();
    }

    // ─── get() failure paths ───

    /**
     * @throws ReflectionException
     * @throws \Model\Exceptions\NotFoundException
     * @throws NotFoundException
     * @throws \Exception
     * @throws \TypeError
     */
    #[Test]
    public function get_throws_invalid_argument_when_job_is_outsourced(): void
    {
        $jStruct = $this->loadJobStruct();
        $this->seedConfirmation($jStruct);

        $this->setProp('jStruct', $jStruct);
        $this->setParams(['id_job' => (string) $this->jobId(self::BASE), 'password' => 'jobpw']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);

        $this->controller->get();
    }

    /**
     * @throws ReflectionException
     * @throws \Model\Exceptions\NotFoundException
     * @throws NotFoundException
     * @throws \Exception
     * @throws \TypeError
     */
    #[Test]
    public function get_throws_not_found_when_job_is_deleted(): void
    {
        $this->setProp('jStruct', $this->loadJobStruct(true));
        $this->setParams(['id_job' => (string) $this->jobId(self::BASE), 'password' => 'jobpw']);

        $this->expectException(NotFoundException::class);

        $this->controller->get();
    }

    // ─── add() failure paths ───

    /**
     * @throws ReflectionException
     * @throws NotFoundException
     * @throws \Exception
     * @throws \TypeError
     */
    #[Test]
    public function add_throws_invalid_argument_when_email_is_empty(): void
    {
        $this->setProp('jStruct', $this->loadJobStruct());
        $this->setParams([
            'email'         => '',
            'delivery_date' => '0',
            'timezone'      => '0',
            'id_job'        => (string) $this->jobId(self::BASE),
            'password'      => 'jobpw',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);

        $this->controller->add();
    }

    /**
     * @throws ReflectionException
     * @throws NotFoundException
     * @throws \Exception
     * @throws \TypeError
     */
    #[Test]
    public function add_throws_not_found_when_job_is_deleted(): void
    {
        $this->setProp('jStruct', $this->loadJobStruct(true));
        $this->setParams([
            'email'         => 'translator@example.org',
            'delivery_date' => '0',
            'timezone'      => '0',
            'id_job'        => (string) $this->jobId(self::BASE),
            'password'      => 'jobpw',
        ]);

        $this->expectException(NotFoundException::class);

        $this->controller->add();
    }

    // ─── add() happy path ───

    /**
     * add() builds the model via the makeTranslatorsModel() seam, calls
     * update() and renders the response. The seam is overridden here so no
     * real invite email is dispatched. Covers lines 63-79.
     *
     * @throws ReflectionException
     * @throws NotFoundException
     * @throws \Exception
     * @throws \TypeError
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    #[Test]
    public function add_renders_translator_payload_from_seam_model(): void
    {
        $jStruct = $this->loadJobStruct();

        $tStruct                        = new JobsTranslatorsStruct();
        $tStruct->id_job                = $this->jobId(self::BASE);
        $tStruct->job_password          = 'jobpw';
        $tStruct->id_translator_profile = null;
        $tStruct->email                 = 'translator@example.org';
        $tStruct->added_by              = 1;
        $tStruct->delivery_date         = '2026-01-01 00:00:00';
        $tStruct->source                = 'en-US';
        $tStruct->target                = 'it-IT';

        $model = $this->createMock(TranslatorsModel::class);
        $model->method('setUserInvite')->willReturnSelf();
        $model->method('setDeliveryDate')->willReturnSelf();
        $model->method('setJobOwnerTimezone')->willReturnSelf();
        $model->method('setEmail')->willReturnSelf();
        $model->method('update')->willReturn($tStruct);

        $controller             = new StubModelJobsTranslatorsController();
        $controller->stubModel  = $model;

        $this->setPropOn($controller, 'request', $this->requestStub);
        $this->setPropOn($controller, 'response', $this->responseMock);
        $this->setPropOn($controller, 'logger', $this->createMock(MatecatLogger::class));
        $this->setPropOn($controller, 'database', obtainTestDatabase());
        $user        = new UserStruct();
        $user->uid   = 1;
        $user->email = $this->ownerEmail(self::BASE);
        $this->setPropOn($controller, 'user', $user);
        $this->setPropOn($controller, 'jStruct', $jStruct);

        $prop = $this->reflector->getProperty('params');
        $prop->setValue($controller, [
            'email'         => 'translator@example.org',
            'delivery_date' => '0',
            'timezone'      => '0',
            'id_job'        => (string) $this->jobId(self::BASE),
            'password'      => 'jobpw',
        ]);

        $expectedId = $this->jobId(self::BASE);
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use ($expectedId): bool {
                $this->assertArrayHasKey('job', $data);
                $this->assertSame($expectedId, $data['job']['id']);
                $this->assertSame('jobpw', $data['job']['password']);
                $this->assertSame('translator@example.org', $data['job']['translator']['email']);
                $this->assertNull($data['job']['translator']['user']);
                return true;
            }));

        $controller->add();
    }

    // ─── registerValidators() ───

    /**
     * Invokes the real registerValidators() (bypassing the Testable override)
     * and drives the JobPasswordValidator so its onSuccess closure runs,
     * covering lines 130-136.
     *
     * @throws ReflectionException
     * @throws \Throwable
     */
    #[Test]
    public function register_validators_wires_login_and_password_validators(): void
    {
        $this->setParams(['id_job' => (string) $this->jobId(self::BASE), 'password' => 'jobpw']);

        $this->reflector->getMethod('registerValidators')->invoke($this->controller);

        $validators = $this->reflector->getProperty('validators')->getValue($this->controller);
        $this->assertCount(2, $validators);
        $this->assertInstanceOf(\Controller\API\Commons\Validators\LoginValidator::class, $validators[0]);
        $this->assertInstanceOf(\Controller\API\Commons\Validators\JobPasswordValidator::class, $validators[1]);

        // Drive the password validator so the registered onSuccess closure runs
        // and assigns $this->jStruct (line 133).
        $validators[1]->validate();

        $jStruct = $this->reflector->getProperty('jStruct')->getValue($this->controller);
        $this->assertInstanceOf(JobStruct::class, $jStruct);
        $this->assertSame($this->jobId(self::BASE), $jStruct->id);
    }
}
