<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\FilesController;
use Controller\API\Commons\Exceptions\NotFoundException;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
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
 * Reserved ID block: base = 9_000_000 + (12 * 1000) = 9012000.
 *   project = 9012001, job = 9012002, segment = 9012003, file = 9012004,
 *   file_part = 9012020 (suite-local offset).
 * Clean ONLY by reserved id. Per-suite owner: ctrltest_9012000@example.org.
 */
class TestableFilesController extends FilesController
{
    public function __construct()
    {
    }

    protected function registerValidators(): void
    {
    }

    protected function initDependencies(): void
    {
    }

    public function setChunk(JobStruct $chunk): void
    {
        $this->chunk = $chunk;
    }
}

#[AllowMockObjectsWithoutExpectations]
class FilesControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE         = 9012000;
    private const int FILE_PART_ID = 9012020;

    /** @var ReflectionClass<FilesController> */
    private ReflectionClass $reflector;
    private TestableFilesController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws \Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanTestData();
        $this->seedTestData();

        $this->controller = new TestableFilesController();
        $this->reflector  = new ReflectionClass(FilesController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);

        $user            = new UserStruct();
        $user->uid       = $this->userId(self::BASE);
        $user->email     = $this->ownerEmail(self::BASE);
        $user->first_name = 'Ctrl';
        $user->last_name  = 'Tester';
        $this->setProp('user', $user);

        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));
    }

    /**
     * @throws \PDOException
     */
    protected function tearDown(): void
    {
        $this->cleanTestData();
        parent::tearDown();
    }

    /**
     * @throws \PDOException
     */
    private function seedTestData(): void
    {
        $owner = $this->ownerEmail(self::BASE);

        $this->seedProject(self::BASE, $owner);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $owner);
        $this->seedSegment(self::BASE);
        $this->seedSegmentTranslation(self::BASE);

        $conn      = $this->seedConnection();
        $fileId    = $this->fileId(self::BASE);
        $jobId     = $this->jobId(self::BASE);
        $segmentId = $this->segmentId(self::BASE);

        // files_parts row (file_part_id path)
        $conn->exec(
            "INSERT IGNORE INTO files_parts (id, id_file, tag_key, tag_value) "
            . "VALUES (" . self::FILE_PART_ID . ", $fileId, 'k', 'v')"
        );

        // link the seeded segment to the file part
        $conn->exec("UPDATE segments SET id_file_part = " . self::FILE_PART_ID . " WHERE id = $segmentId");

        // files_job row (file_id path)
        $conn->exec("INSERT IGNORE INTO files_job (id_job, id_file) VALUES ($jobId, $fileId)");
    }

    /**
     * @throws \PDOException
     */
    private function cleanTestData(): void
    {
        $conn = $this->seedConnection();
        $conn->exec("DELETE FROM files_parts WHERE id = " . self::FILE_PART_ID);
        $conn->exec("DELETE FROM files_job WHERE id_job = " . $this->jobId(self::BASE));
        $this->cleanFragments(self::BASE);
    }

    /**
     * @throws ReflectionException
     */
    private function setProp(string $name, mixed $value): void
    {
        $p = $this->reflector->getProperty($name);
        $p->setValue($this->controller, $value);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function setParams(array $params): void
    {
        $this->controller->params = $params;
    }

    private function makeChunk(): JobStruct
    {
        $chunk     = new JobStruct();
        $chunk->id = $this->jobId(self::BASE);
        return $chunk;
    }

    /**
     * @param list<mixed> $args
     *
     * @throws ReflectionException
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        $m = $this->reflector->getMethod($method);
        return $m->invoke($this->controller, ...$args);
    }

    // ─── validateInteger ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function validateInteger_passes_for_valid_integer(): void
    {
        $this->expectNotToPerformAssertions();
        $this->invokePrivate('validateInteger', ['42']);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function validateInteger_throws_for_non_integer(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('`file_part_id` is not an integer');
        $this->invokePrivate('validateInteger', ['not-a-number']);
    }

    // ─── getFirstAndLastSegmentFromFilePartId ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function getFirstAndLastSegmentFromFilePartId_returns_segment_bounds(): void
    {
        $segmentId = $this->segmentId(self::BASE);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use ($segmentId): bool {
                $this->assertArrayHasKey('first_segment', $data);
                $this->assertArrayHasKey('last_segment', $data);
                $this->assertSame($segmentId, $data['first_segment']);
                $this->assertSame($segmentId, $data['last_segment']);
                return true;
            }));

        $this->invokePrivate('getFirstAndLastSegmentFromFilePartId', [self::FILE_PART_ID]);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function getFirstAndLastSegmentFromFilePartId_throws_not_found_for_unknown_part(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('File part id 99999999 was not found');
        $this->invokePrivate('getFirstAndLastSegmentFromFilePartId', [99999999]);
    }

    // ─── getFirstAndLastSegmentFromFileId ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function getFirstAndLastSegmentFromFileId_returns_segment_bounds(): void
    {
        $this->controller->setChunk($this->makeChunk());
        $fileId    = $this->fileId(self::BASE);
        $segmentId = $this->segmentId(self::BASE);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use ($segmentId): bool {
                $this->assertArrayHasKey('fist_segment', $data);
                $this->assertArrayHasKey('last_segment', $data);
                $this->assertSame($segmentId, $data['fist_segment']);
                $this->assertSame($segmentId, $data['last_segment']);
                return true;
            }));

        $this->invokePrivate('getFirstAndLastSegmentFromFileId', [$fileId]);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function getFirstAndLastSegmentFromFileId_throws_not_found_when_job_has_no_files(): void
    {
        $emptyChunk     = new JobStruct();
        $emptyChunk->id = 99999998;
        $this->controller->setChunk($emptyChunk);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('File id 12345 was not found');
        $this->invokePrivate('getFirstAndLastSegmentFromFileId', [12345]);
    }

    // ─── segments() public action ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function segments_dispatches_to_file_part_id_branch(): void
    {
        $segmentId = $this->segmentId(self::BASE);
        $this->setParams(['file_part_id' => (string) self::FILE_PART_ID]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use ($segmentId): bool {
                $this->assertSame($segmentId, $data['first_segment']);
                return true;
            }));

        $this->controller->segments();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function segments_dispatches_to_file_id_branch(): void
    {
        $this->controller->setChunk($this->makeChunk());
        $segmentId = $this->segmentId(self::BASE);
        $fileId    = $this->fileId(self::BASE);
        $this->setParams(['file_id' => (string) $fileId]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use ($segmentId): bool {
                $this->assertSame($segmentId, $data['fist_segment']);
                return true;
            }));

        $this->controller->segments();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function segments_returns_error_when_no_params_provided(): void
    {
        $this->setParams([]);

        $statusMock = $this->createMock(\Klein\HttpStatus::class);
        $statusMock->expects($this->once())->method('setCode')->with(500);

        $this->responseMock->expects($this->once())->method('status')->willReturn($statusMock);
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('error', $data);
                $this->assertStringContainsString('Missing parameters', $data['error']);
                return true;
            }));

        $this->controller->segments();
    }

    // ─── registerValidators (real validator chain) ───

    /**
     * Exercises the real registerValidators() body and the ChunkPasswordValidator
     * onSuccess closure that assigns $this->chunk.
     *
     * @throws \Throwable
     */
    #[Test]
    public function registerValidators_runs_chunk_password_validator_and_sets_chunk(): void
    {
        $controller = $this->reflector->newInstanceWithoutConstructor();

        $params = [
            'id_job'   => (string) $this->jobId(self::BASE),
            'password' => 'jobpw',
        ];

        $serverParams = ['REQUEST_URI' => '/api/app/files/segments', 'REQUEST_METHOD' => 'GET'];
        $request      = new Request($params, [], [], $serverParams);

        $this->reflector->getProperty('request')->setValue($controller, $request);
        $this->reflector->getProperty('response')->setValue($controller, $this->createMock(Response::class));
        $this->reflector->getProperty('logger')->setValue($controller, $this->createMock(MatecatLogger::class));
        $this->reflector->getProperty('featureSet')->setValue($controller, new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));
        $this->reflector->getProperty('params')->setValue($controller, $params);

        $this->reflector->getMethod('registerValidators')->invoke($controller);
        $this->reflector->getMethod('validateRequest')->invoke($controller);

        $chunk = $this->reflector->getProperty('chunk')->getValue($controller);
        $this->assertInstanceOf(JobStruct::class, $chunk);
        $this->assertSame($this->jobId(self::BASE), (int) $chunk->id);
    }
}
