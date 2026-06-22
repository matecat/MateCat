<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

/**
 * Real-DB suite for {@see \Controller\API\V2\JobsController} (plan Wave 7, N=43).
 *
 * Reserved ID block (Playbook §4): base = 9_000_000 + (43 * 1000) = 9_043_000.
 *   base+1 project (9043001), base+2 job (9043002), base+3 segment (9043003),
 *   base+4 file (9043004), base+5 team (9043005), base+6 user (9043006).
 * Cleaned ONLY by reserved id; per-suite owner email ctrltest_9043000@example.org.
 */

use Controller\API\V2\JobsController;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Translations\SegmentTranslationDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Utils\Constants\JobStatus;
use Utils\Logger\MatecatLogger;

class TestableJobsController extends JobsController
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
class JobsControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_000_000 + (43 * 1000);
    private const string JOB_PASSWORD = 'jobpw';

    /** @var ReflectionClass<JobsController> */
    private ReflectionClass $reflector;
    private TestableJobsController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws \Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);
        $this->seedFixtures();

        $this->controller = new TestableJobsController();
        $this->reflector  = new ReflectionClass(JobsController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);

        $user        = new UserStruct();
        $user->uid   = $this->userId(self::BASE);
        $user->email = $this->ownerEmail(self::BASE);
        $this->setProp('user', $user);

        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new \Model\FeaturesBase\FeatureSet(Database::obtain()));

        // Wire the chunk + DAOs the action path reads (normally set in the
        // ChunkPasswordValidator onSuccess closure at validation time).
        $chunk = $this->loadChunk();
        $this->setProp('chunk', $chunk);
        $this->setProp('project', $chunk->getProject());
        $this->setProp('jobDao', new JobDao(Database::obtain()));
        $this->setProp('segmentTranslationDao', new SegmentTranslationDao(Database::obtain()));
    }

    protected function tearDown(): void
    {
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    private function seedFixtures(): void
    {
        $owner = $this->ownerEmail(self::BASE);
        $this->seedProject(self::BASE, $owner);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $owner, self::JOB_PASSWORD, JobStatus::STATUS_ACTIVE);
        $this->seedSegment(self::BASE);
        $this->seedSegmentTranslation(self::BASE);
    }

    /**
     * @throws ReflectionException
     */
    private function setProp(string $name, mixed $value): void
    {
        $this->reflector->getProperty($name)->setValue($this->controller, $value);
    }

    /**
     * @throws \Throwable
     */
    private function loadChunk(): JobStruct
    {
        return (new JobDao(Database::obtain()))
            ->getByIdAndPasswordOrFail($this->jobId(self::BASE), self::JOB_PASSWORD);
    }

    /**
     * Force the wired chunk into a deleted state (status_owner = 'deleted')
     * to exercise the return404IfTheJobWasDeleted() failure branch.
     *
     * @throws ReflectionException
     */
    private function markChunkDeleted(): void
    {
        $chunkProp = $this->reflector->getProperty('chunk');
        /** @var JobStruct $chunk */
        $chunk = $chunkProp->getValue($this->controller);
        $chunk->status_owner = JobStatus::STATUS_DELETED;
    }

    // ─── return404IfTheJobWasDeleted ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function return404_passes_silently_for_active_job(): void
    {
        $m = $this->reflector->getMethod('return404IfTheJobWasDeleted');
        $m->invoke($this->controller);

        $this->addToAssertionCount(1);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function return404_throws_not_found_for_deleted_job(): void
    {
        $this->markChunkDeleted();

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('No job found.');

        $this->reflector->getMethod('return404IfTheJobWasDeleted')->invoke($this->controller);
    }

    // ─── changeStatus (via the public actions) ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function delete_sets_status_deleted_and_returns_success_payload(): void
    {
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame(1, $data['code']);
                $this->assertSame('OK', $data['data']);
                $this->assertSame(JobStatus::STATUS_DELETED, $data['status']);

                return true;
            }));

        $this->controller->delete();

        $this->assertJobStatusOwnerInDb(JobStatus::STATUS_DELETED);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function cancel_sets_status_cancelled_and_returns_success_payload(): void
    {
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame(JobStatus::STATUS_CANCELLED, $data['status']);

                return true;
            }));

        $this->controller->cancel();

        $this->assertJobStatusOwnerInDb(JobStatus::STATUS_CANCELLED);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function archive_sets_status_archived_and_returns_success_payload(): void
    {
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame(JobStatus::STATUS_ARCHIVED, $data['status']);

                return true;
            }));

        $this->controller->archive();

        $this->assertJobStatusOwnerInDb(JobStatus::STATUS_ARCHIVED);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function active_sets_status_active_and_returns_success_payload(): void
    {
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame(JobStatus::STATUS_ACTIVE, $data['status']);

                return true;
            }));

        $this->controller->active();

        $this->assertJobStatusOwnerInDb(JobStatus::STATUS_ACTIVE);
    }

    // ─── failure branches: each action 404s on a deleted job ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function delete_throws_not_found_for_deleted_job(): void
    {
        $this->markChunkDeleted();

        $this->expectException(NotFoundException::class);

        $this->controller->delete();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function cancel_throws_not_found_for_deleted_job(): void
    {
        $this->markChunkDeleted();

        $this->expectException(NotFoundException::class);

        $this->controller->cancel();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function archive_throws_not_found_for_deleted_job(): void
    {
        $this->markChunkDeleted();

        $this->expectException(NotFoundException::class);

        $this->controller->archive();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function active_throws_not_found_for_deleted_job(): void
    {
        $this->markChunkDeleted();

        $this->expectException(NotFoundException::class);

        $this->controller->active();
    }

    // ─── show() ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function show_returns_json_with_job_id_for_active_job(): void
    {
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('job', $data);
                $this->assertSame($this->jobId(self::BASE), $data['job']['id']);
                $this->assertArrayHasKey('chunks', $data['job']);
                $this->assertCount(1, $data['job']['chunks']);

                return true;
            }));

        $this->controller->show();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function show_throws_not_found_for_deleted_job(): void
    {
        $this->markChunkDeleted();

        $this->expectException(NotFoundException::class);

        $this->controller->show();
    }

    // ─── registerValidators() (exercise the real hook, not the empty override) ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function registerValidators_appends_login_and_chunk_password_validators(): void
    {
        $real = $this->reflector->newInstanceWithoutConstructor();

        $params = ['id_job' => (string) $this->jobId(self::BASE), 'password' => self::JOB_PASSWORD];

        $reqProp = $this->reflector->getProperty('request');
        $reqProp->setValue($real, new Request(
            $params,
            [],
            [],
            ['REQUEST_URI' => '/api/v2/jobs', 'REQUEST_METHOD' => 'POST']
        ));

        $paramsProp = $this->reflector->getProperty('params');
        $paramsProp->setValue($real, $params);

        $this->reflector->getMethod('registerValidators')->invoke($real);

        $validatorsProp = $this->reflector->getProperty('validators');
        /** @var array<\Controller\API\Commons\Validators\Base> $validators */
        $validators = $validatorsProp->getValue($real);

        $this->assertCount(2, $validators);
        $this->assertInstanceOf(\Controller\API\Commons\Validators\LoginValidator::class, $validators[0]);
        $this->assertInstanceOf(\Controller\API\Commons\Validators\ChunkPasswordValidator::class, $validators[1]);

        // Trigger the ChunkPasswordValidator success path so the onSuccess
        // closure (chunk/project/DAO wiring + ProjectAccessValidator append)
        // executes against the seeded job.
        $validators[1]->validate();

        $chunk = $this->reflector->getProperty('chunk')->getValue($real);
        $this->assertInstanceOf(JobStruct::class, $chunk);
        $this->assertSame($this->jobId(self::BASE), (int) $chunk->id);

        $project = $this->reflector->getProperty('project')->getValue($real);
        $this->assertInstanceOf(ProjectStruct::class, $project);
        $this->assertSame($this->projectId(self::BASE), (int) $project->id);

        // The closure appends a third validator (ProjectAccessValidator).
        $after = $validatorsProp->getValue($real);
        $this->assertCount(3, $after);
        $this->assertInstanceOf(\Controller\API\Commons\Validators\ProjectAccessValidator::class, $after[2]);
    }

    /**
     * @throws \Throwable
     */
    private function assertJobStatusOwnerInDb(string $expectedStatus): void
    {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare('SELECT status_owner FROM jobs WHERE id = :id');
        $stmt->execute(['id' => $this->jobId(self::BASE)]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame($expectedStatus, $row['status_owner']);
    }
}
