<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

use Controller\API\App\ContextUrlController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\Services\RateLimiterService;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\Exceptions\NotFoundException;
use Model\Files\FileDao;
use Model\Files\FileStruct;
use Model\Files\MetadataDao as FilesMetadataDao;
use Model\Files\MetadataStruct as FilesMetadataStruct;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use Model\Projects\ProjectStruct;
use Model\Segments\SegmentDao;
use Model\Segments\SegmentMetadataDao;
use Model\Segments\SegmentStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use ReflectionClass;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;

class TestableContextUrlController extends ContextUrlController
{
    public function __construct()
    {
    }

    protected function afterConstruct(): void
    {
    }
}

class ContextUrlControllerTest extends AbstractTest
{
    private ReflectionClass $reflector;
    private TestableContextUrlController $controller;
    private Stub&Response $responseStub;
    private Stub&RateLimiterService $rateLimiterStub;
    private Stub&FileDao $fileDaoStub;
    private Stub&SegmentDao $segmentDaoStub;
    private Stub&ProjectsMetadataDao $projectsMetadataDaoStub;
    private Stub&FilesMetadataDao $filesMetadataDaoStub;
    private Stub&SegmentMetadataDao $segmentMetadataDaoStub;
    private ProjectStruct $project;

    protected function setUp(): void
    {
        parent::setUp();

        AppConfig::$SKIP_SQL_CACHE = true;
        $this->createDatabaseMock();

        $this->controller = new TestableContextUrlController();
        $this->reflector  = new ReflectionClass(ContextUrlController::class);

        $this->responseStub = $this->createStub(Response::class);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->responseStub);

        $user = new UserStruct();
        $user->uid        = 1;
        $user->email      = 'test@example.org';
        $user->first_name = 'Test';
        $user->last_name  = 'User';
        $this->reflector->getProperty('user')->setValue($this->controller, $user);
        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createStub(MatecatLogger::class));

        $this->project     = new ProjectStruct();
        $this->project->id = 42;
        $this->reflector->getProperty('project')->setValue($this->controller, $this->project);

        $this->rateLimiterStub         = $this->createStub(RateLimiterService::class);
        $this->fileDaoStub             = $this->createStub(FileDao::class);
        $this->segmentDaoStub          = $this->createStub(SegmentDao::class);
        $this->projectsMetadataDaoStub = $this->createStub(ProjectsMetadataDao::class);
        $this->filesMetadataDaoStub    = $this->createStub(FilesMetadataDao::class);
        $this->segmentMetadataDaoStub  = $this->createStub(SegmentMetadataDao::class);

        $this->reflector->getProperty('rateLimiterService')->setValue($this->controller, $this->rateLimiterStub);
        $this->reflector->getProperty('fileDao')->setValue($this->controller, $this->fileDaoStub);
        $this->reflector->getProperty('segmentDao')->setValue($this->controller, $this->segmentDaoStub);
        $this->reflector->getProperty('projectsMetadataDao')->setValue($this->controller, $this->projectsMetadataDaoStub);
        $this->reflector->getProperty('filesMetadataDao')->setValue($this->controller, $this->filesMetadataDaoStub);
        $this->reflector->getProperty('segmentMetadataDao')->setValue($this->controller, $this->segmentMetadataDaoStub);

        $this->rateLimiterStub->method('checkAndIncrement')->willReturn(null);
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
    }

    private function setRequestBody(string $body): void
    {
        $request = $this->createStub(Request::class);
        $request->method('body')->willReturn($body);
        $request->method('param')->willReturn(null);
        $this->reflector->getProperty('request')->setValue($this->controller, $request);
    }

    private function setNullBody(): void
    {
        $request = $this->createStub(Request::class);
        $request->method('body')->willReturn(null);
        $request->method('param')->willReturn(null);
        $this->reflector->getProperty('request')->setValue($this->controller, $request);
    }

    private function injectResponseMock(): Response&\PHPUnit\Framework\MockObject\MockObject
    {
        $mock = $this->createMock(Response::class);
        $this->reflector->getProperty('response')->setValue($this->controller, $mock);
        return $mock;
    }

    private function createFileStruct(int $idProject, int $idFile = 5): FileStruct
    {
        $file = new FileStruct();
        $file->id         = $idFile;
        $file->id_project = $idProject;
        return $file;
    }

    private function createSegmentStruct(int $idFile, int $idSegment = 100): SegmentStruct
    {
        $segment          = new SegmentStruct();
        $segment->id      = $idSegment;
        $segment->id_file = $idFile;
        return $segment;
    }


    // ── Rate Limiting ────────────────────────────────────────────────────

    #[Test]
    public function setForProject_returns_429_when_rate_limited(): void
    {
        $rateLimitedResponse = $this->createStub(Response::class);
        $rateLimitedResponse->method('code')->willReturn(429);

        $rateLimiter = $this->createStub(RateLimiterService::class);
        $rateLimiter->method('checkAndIncrement')->willReturn($rateLimitedResponse);
        $this->reflector->getProperty('rateLimiterService')->setValue($this->controller, $rateLimiter);

        $this->setRequestBody('{"context_url": "https://example.com"}');

        $responseMock = $this->injectResponseMock();
        $responseMock->expects(self::never())->method('json');

        $this->controller->setForProject();
    }

    #[Test]
    public function setForFile_returns_429_when_rate_limited(): void
    {
        $rateLimitedResponse = $this->createStub(Response::class);
        $rateLimitedResponse->method('code')->willReturn(429);

        $rateLimiter = $this->createStub(RateLimiterService::class);
        $rateLimiter->method('checkAndIncrement')->willReturn($rateLimitedResponse);
        $this->reflector->getProperty('rateLimiterService')->setValue($this->controller, $rateLimiter);

        $this->setRequestBody('{"id_file": 5, "context_url": "https://example.com"}');

        $responseMock = $this->injectResponseMock();
        $responseMock->expects(self::never())->method('json');

        $this->controller->setForFile();
    }


    // ── Authorization ────────────────────────────────────────────────────

    #[Test]
    public function setForProject_throws_when_project_not_found(): void
    {
        $this->reflector->getProperty('project')->setValue($this->controller, null);
        $this->setRequestBody('{"context_url": "https://example.com"}');

        $this->expectException(NotFoundException::class);

        $this->controller->setForProject();
    }

    // ── JSON Schema Validation ───────────────────────────────────────────

    #[Test]
    public function setForProject_throws_when_url_invalid(): void
    {
        $this->setRequestBody('{"context_url": "not-a-valid-url"}');

        $this->expectException(JSONValidatorException::class);

        $this->controller->setForProject();
    }

    #[Test]
    public function setForFile_throws_when_url_invalid(): void
    {
        $this->setRequestBody('{"id_file": 5, "context_url": "not-a-valid-url"}');

        $this->expectException(JSONValidatorException::class);

        $this->controller->setForFile();
    }

    #[Test]
    public function setForProject_throws_when_context_url_missing(): void
    {
        $this->setRequestBody('{}');

        $this->expectException(JSONValidatorException::class);

        $this->controller->setForProject();
    }


    // ── Missing Body / Params ────────────────────────────────────────────

    #[Test]
    public function setForProject_throws_when_body_missing(): void
    {
        $this->setNullBody();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);

        $this->controller->setForProject();
    }

    #[Test]
    public function setForFile_throws_when_body_missing(): void
    {
        $this->setNullBody();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);

        $this->controller->setForFile();
    }

    #[Test]
    public function setForFile_throws_when_id_file_missing(): void
    {
        $this->setRequestBody('{"context_url": "https://example.com"}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);

        $this->controller->setForFile();
    }

    #[Test]
    public function setForSegment_throws_when_body_missing(): void
    {
        $this->setNullBody();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);

        $this->controller->setForSegment();
    }

    #[Test]
    public function setForSegment_throws_when_id_segment_missing(): void
    {
        $this->setRequestBody('{"context_url": "https://example.com/seg"}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);

        $this->controller->setForSegment();
    }


    // ── Entity Existence ─────────────────────────────────────────────────

    #[Test]
    public function setForFile_throws_when_file_not_found(): void
    {
        $this->fileDaoStub->method('getById')->willReturn(null);
        $this->setRequestBody('{"id_file": 5, "context_url": "https://example.com/file"}');

        $this->expectException(NotFoundException::class);

        $this->controller->setForFile();
    }

    #[Test]
    public function setForSegment_throws_when_segment_not_found(): void
    {
        $this->segmentDaoStub->method('fetchById')->willReturn(null);
        $this->setRequestBody('{"id_segment": 100, "context_url": "https://example.com/seg"}');

        $this->expectException(NotFoundException::class);

        $this->controller->setForSegment();
    }


    // ── Domain Validation ────────────────────────────────────────────────

    #[Test]
    public function setForFile_throws_when_file_not_in_project(): void
    {
        $this->fileDaoStub->method('getById')->willReturn($this->createFileStruct(999));
        $this->setRequestBody('{"id_file": 5, "context_url": "https://example.com/file"}');

        $this->expectException(AuthorizationError::class);

        $this->controller->setForFile();
    }

    #[Test]
    public function setForSegment_throws_when_segment_not_in_project(): void
    {
        $segment = $this->createSegmentStruct(5);
        $this->segmentDaoStub->method('fetchById')->willReturn($segment);
        $this->fileDaoStub->method('getById')->willReturn($this->createFileStruct(999));

        $this->setRequestBody('{"id_segment": 100, "context_url": "https://example.com/seg"}');

        $this->expectException(AuthorizationError::class);

        $this->controller->setForSegment();
    }


    // ── Happy Path ───────────────────────────────────────────────────────

    #[Test]
    public function setForProject_returns_project_level_json(): void
    {
        $this->setRequestBody('{"context_url": "https://example.com/proj"}');

        $projectsMetadataDao = $this->createMock(ProjectsMetadataDao::class);
        $projectsMetadataDao->expects(self::once())->method('set');
        $this->reflector->getProperty('projectsMetadataDao')->setValue($this->controller, $projectsMetadataDao);

        $responseMock = $this->injectResponseMock();
        $responseMock->expects(self::once())
            ->method('json')
            ->with(self::callback(function (array $data): bool {
                return $data['level'] === 'project'
                    && $data['id_project'] === 42
                    && $data['context_url'] === 'https://example.com/proj';
            }));

        $this->controller->setForProject();
    }

    #[Test]
    public function setForFile_inserts_when_no_existing_record(): void
    {
        $this->fileDaoStub->method('getById')->willReturn($this->createFileStruct(42));

        $filesMetadataDao = $this->createMock(FilesMetadataDao::class);
        $filesMetadataDao->method('get')->willReturn(null);
        $filesMetadataDao->expects(self::once())->method('insert');
        $filesMetadataDao->expects(self::never())->method('update');
        $this->reflector->getProperty('filesMetadataDao')->setValue($this->controller, $filesMetadataDao);

        $this->setRequestBody('{"id_file": 5, "context_url": "https://example.com/file"}');

        $responseMock = $this->injectResponseMock();
        $responseMock->expects(self::once())
            ->method('json')
            ->with(self::callback(function (array $data): bool {
                return $data['level'] === 'file'
                    && $data['id_project'] === 42
                    && $data['id_file'] === 5
                    && $data['context_url'] === 'https://example.com/file';
            }));

        $this->controller->setForFile();
    }

    #[Test]
    public function setForFile_updates_when_existing_record_found(): void
    {
        $existing = new FilesMetadataStruct();
        $existing->id_project = 42;
        $existing->id_file    = 5;
        $existing->key        = 'context-url';
        $existing->value      = 'https://old.com';

        $this->fileDaoStub->method('getById')->willReturn($this->createFileStruct(42));

        $filesMetadataDao = $this->createMock(FilesMetadataDao::class);
        $filesMetadataDao->method('get')->willReturn($existing);
        $filesMetadataDao->expects(self::once())->method('update');
        $filesMetadataDao->expects(self::never())->method('insert');
        $this->reflector->getProperty('filesMetadataDao')->setValue($this->controller, $filesMetadataDao);

        $this->setRequestBody('{"id_file": 5, "context_url": "https://example.com/file-updated"}');

        $responseMock = $this->injectResponseMock();
        $responseMock->expects(self::once())
            ->method('json')
            ->with(self::callback(function (array $data): bool {
                return $data['level'] === 'file' && $data['id_file'] === 5;
            }));

        $this->controller->setForFile();
    }

    #[Test]
    public function setForSegment_returns_segment_level_json(): void
    {
        $segment = $this->createSegmentStruct(5);
        $this->segmentDaoStub->method('fetchById')->willReturn($segment);
        $this->fileDaoStub->method('getById')->willReturn($this->createFileStruct(42));

        $segmentMetadataDao = $this->createMock(SegmentMetadataDao::class);
        $segmentMetadataDao->expects(self::once())->method('upsert');
        $this->reflector->getProperty('segmentMetadataDao')->setValue($this->controller, $segmentMetadataDao);

        $this->setRequestBody('{"id_segment": 100, "context_url": "https://example.com/seg"}');

        $responseMock = $this->injectResponseMock();
        $responseMock->expects(self::once())
            ->method('json')
            ->with(self::callback(function (array $data): bool {
                return $data['level'] === 'segment'
                    && $data['id_segment'] === 100
                    && $data['context_url'] === 'https://example.com/seg';
            }));

        $this->controller->setForSegment();
    }
}
