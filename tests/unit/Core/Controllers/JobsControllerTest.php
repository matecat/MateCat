<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

use Controller\API\V2\JobsController;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Exceptions\NotFoundException;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Translations\SegmentTranslationDao;
use PDO;
use PDOStatement;
use ReflectionClass;
use Utils\Registry\AppConfig;

class TestableJobsController extends JobsController
{
    public function __construct()
    {
    }

    public function initForTest(
        Response $response,
        JobStruct $chunk,
        ProjectStruct $project,
        JobDao $jobDao,
        SegmentTranslationDao $segmentTranslationDao
    ): void {
        $ref = new ReflectionClass(JobsController::class);
        $ref->getProperty('response')->setValue($this, $response);
        $ref->getProperty('chunk')->setValue($this, $chunk);
        $ref->getProperty('project')->setValue($this, $project);
        $ref->getProperty('jobDao')->setValue($this, $jobDao);
        $ref->getProperty('segmentTranslationDao')->setValue($this, $segmentTranslationDao);
    }
}

class JobsControllerTest extends AbstractTest
{
    private \PHPUnit\Framework\MockObject\Stub&IDatabase $dbStub;
    private \PHPUnit\Framework\MockObject\Stub&PDO $pdoStub;
    private \PHPUnit\Framework\MockObject\Stub&PDOStatement $stmtStub;

    protected function setUp(): void
    {
        parent::setUp();
        AppConfig::$SKIP_SQL_CACHE = true;
        [$this->dbStub, $this->pdoStub, $this->stmtStub] = $this->createDatabaseMock();
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
    }

    private function makeChunk(array $overrides = []): JobStruct
    {
        $job = new JobStruct();
        $job->id = $overrides['id'] ?? 1;
        $job->password = $overrides['password'] ?? 'abc123';
        $job->id_project = $overrides['id_project'] ?? 10;
        $job->job_first_segment = $overrides['job_first_segment'] ?? 100;
        $job->job_last_segment = $overrides['job_last_segment'] ?? 200;
        $job->source = $overrides['source'] ?? 'en-US';
        $job->target = $overrides['target'] ?? 'it-IT';
        $job->status_owner = $overrides['status_owner'] ?? 'active';
        $job->status = $overrides['status'] ?? 'active';

        return $job;
    }

    private function makeDeletedChunk(): JobStruct
    {
        return $this->makeChunk(['status_owner' => 'deleted']);
    }

    private function makeProject(): ProjectStruct
    {
        $project = new ProjectStruct();
        $project->id = 10;
        $project->id_team = 1;

        return $project;
    }

    private function createController(JobStruct $chunk, ?Response $response = null): TestableJobsController
    {
        $jobDao = new JobDao($this->dbStub);
        $segmentTranslationDao = new SegmentTranslationDao($this->dbStub);
        $response = $response ?? $this->createStub(Response::class);

        $controller = new TestableJobsController();
        $controller->initForTest($response, $chunk, $this->makeProject(), $jobDao, $segmentTranslationDao);

        return $controller;
    }

    private function setupDbForChangeStatus(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([[500]]);
    }

    public function testReturn404ThrowsOnDeletedJob(): void
    {
        $controller = $this->createController($this->makeDeletedChunk());

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('No job found.');

        $controller->delete();
    }

    public function testReturn404PassesOnActiveJob(): void
    {
        $this->setupDbForChangeStatus();

        $response = $this->createMock(Response::class);
        $response->expects($this->once())->method('json');

        $controller = $this->createController($this->makeChunk(), $response);
        $controller->delete();

        $this->addToAssertionCount(1);
    }

    public function testDeleteThrowsOnDeletedJob(): void
    {
        $controller = $this->createController($this->makeDeletedChunk());

        $this->expectException(NotFoundException::class);
        $controller->delete();
    }

    public function testCancelThrowsOnDeletedJob(): void
    {
        $controller = $this->createController($this->makeDeletedChunk());

        $this->expectException(NotFoundException::class);
        $controller->cancel();
    }

    public function testArchiveThrowsOnDeletedJob(): void
    {
        $controller = $this->createController($this->makeDeletedChunk());

        $this->expectException(NotFoundException::class);
        $controller->archive();
    }

    public function testActiveThrowsOnDeletedJob(): void
    {
        $controller = $this->createController($this->makeDeletedChunk());

        $this->expectException(NotFoundException::class);
        $controller->active();
    }

    public function testDeleteOnActiveJobReturnsSuccess(): void
    {
        $this->setupDbForChangeStatus();

        $response = $this->createMock(Response::class);
        $response->expects($this->once())
            ->method('json')
            ->with($this->callback(fn($arg) => $arg['code'] === 1 && $arg['status'] === 'deleted'));

        $controller = $this->createController($this->makeChunk(), $response);
        $controller->delete();
    }

    public function testCancelOnActiveJobReturnsSuccess(): void
    {
        $this->setupDbForChangeStatus();

        $response = $this->createMock(Response::class);
        $response->expects($this->once())
            ->method('json')
            ->with($this->callback(fn($arg) => $arg['code'] === 1 && $arg['status'] === 'cancelled'));

        $controller = $this->createController($this->makeChunk(), $response);
        $controller->cancel();
    }

    public function testArchiveOnActiveJobReturnsSuccess(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $response = $this->createMock(Response::class);
        $response->expects($this->once())
            ->method('json')
            ->with($this->callback(fn($arg) => $arg['code'] === 1 && $arg['status'] === 'archived'));

        $controller = $this->createController($this->makeChunk(), $response);
        $controller->archive();
    }

    public function testActiveOnActiveJobReturnsSuccess(): void
    {
        $this->setupDbForChangeStatus();

        $response = $this->createMock(Response::class);
        $response->expects($this->once())
            ->method('json')
            ->with($this->callback(fn($arg) => $arg['code'] === 1 && $arg['status'] === 'active'));

        $controller = $this->createController($this->makeChunk(), $response);
        $controller->active();
    }
}
