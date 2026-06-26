<?php

namespace Matecat\Core\Controllers;

/**
 * Real-DB suite for {@see \Controller\API\V3\MetaDataController}.
 *
 * Reserved ID block (Playbook §4): base = 9052000.
 *   base+1 (9052001) project, base+2 (9052002) job, base+3 (9052003) segment,
 *   base+4 (9052004) file. Per-suite owner email: ctrltest_9052000@example.org.
 * Clean ONLY by reserved id; never by shared keys.
 */

use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\V3\MetaDataController;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao as FileMetadataDao;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao as JobMetadataDao;
use Model\Projects\ProjectDao;
use Model\Projects\MetadataDao as ProjectMetadataDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use stdClass;
use Utils\Logger\MatecatLogger;

class TestableMetaDataController extends MetaDataController
{
    public function __construct()
    {
    }

    protected function registerValidators(): void
    {
    }
}

#[AllowMockObjectsWithoutExpectations]
class MetaDataControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9052000;
    private const string JOB_PASSWORD = 'jobpw';

    /** @var ReflectionClass<MetaDataController> */
    private ReflectionClass $reflector;
    private TestableMetaDataController $controller;
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

        $this->controller = new TestableMetaDataController();
        $this->reflector  = new ReflectionClass(MetaDataController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);

        $user            = new UserStruct();
        $user->uid       = 1;
        $user->email     = $this->ownerEmail(self::BASE);
        $user->first_name = 'Ctrl';
        $user->last_name  = 'Tester';
        $this->setProp('user', $user);

        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));
        $this->setProp('database', Database::obtain());
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
     * @throws ReflectionException
     */
    private function setProp(string $name, mixed $value): void
    {
        $prop = $this->reflector->getProperty($name);
        $prop->setValue($this->controller, $value);
    }

    /**
     * @throws \PDOException
     * @throws ReflectionException
     */
    private function seedTestData(): void
    {
        $owner = $this->ownerEmail(self::BASE);

        $this->seedProject(self::BASE, $owner);
        $this->seedJob(self::BASE, $owner, self::JOB_PASSWORD);
        $this->seedSegment(self::BASE);

        $conn      = $this->seedConnection();
        $projectId = $this->projectId(self::BASE);
        $jobId     = $this->jobId(self::BASE);
        $fileId    = $this->fileId(self::BASE);

        // file row (FileStruct requires non-null sha1_original_file)
        $conn->exec("INSERT IGNORE INTO files (id, id_project, filename, source_language, mime_type, sha1_original_file, is_converted) "
            . "VALUES ($fileId, $projectId, 'ctrltest_" . self::BASE . ".xliff', 'en-US', 'application/xliff+xml', 'ctrlsha_" . self::BASE . "', 0)");

        // link file <-> job so JobStruct::getFiles() resolves it
        $conn->exec("INSERT IGNORE INTO files_job (id_job, id_file) VALUES ($jobId, $fileId)");

        // project_metadata: one engine config key (-> mt_extra) + one plain key
        $conn->exec("INSERT IGNORE INTO project_metadata (id_project, `key`, value) VALUES ($projectId, 'mmt_glossaries', '[101]')");
        $conn->exec("INSERT IGNORE INTO project_metadata (id_project, `key`, value) VALUES ($projectId, 'ctrl_plain_key', 'plain_value')");

        // job_metadata keyed by (id_job, password)
        $conn->exec("INSERT IGNORE INTO job_metadata (id_job, password, `key`, value) VALUES ($jobId, '" . self::JOB_PASSWORD . "', 'tag_projection', 'enabled')");

        // file_metadata keyed by (id_project, id_file)
        $conn->exec("INSERT IGNORE INTO file_metadata (id_project, id_file, `key`, value) VALUES ($projectId, $fileId, 'original_filename', 'real_name.docx')");

        // metadata DAOs are Redis-cached (TTL); drop any stale cache for the seeded ids
        (new ProjectMetadataDao(\Model\DataAccess\Database::obtain()))->destroyMetadataCache($projectId);
        (new JobMetadataDao(\Model\DataAccess\Database::obtain()))->destroyCacheByJobAndPassword($jobId, self::JOB_PASSWORD);
        (new FileMetadataDao(\Model\DataAccess\Database::obtain()))->destroyCacheByJobIdProjectAndIdFile($projectId, $fileId);
    }

    /**
     * @throws \PDOException
     */
    private function cleanTestData(): void
    {
        $conn      = $this->seedConnection();
        $projectId = $this->projectId(self::BASE);
        $jobId     = $this->jobId(self::BASE);
        $fileId    = $this->fileId(self::BASE);

        $conn->exec("DELETE FROM file_metadata WHERE id_project = $projectId");
        $conn->exec("DELETE FROM job_metadata WHERE id_job = $jobId");
        $conn->exec("DELETE FROM project_metadata WHERE id_project = $projectId");
        $conn->exec("DELETE FROM files_job WHERE id_job = $jobId");
        $this->cleanFragments(self::BASE);
    }

    /**
     * @param array<string,mixed> $params
     *
     * @throws ReflectionException
     */
    private function setRequestParams(array $params): void
    {
        $serverParams      = ['REQUEST_URI' => '/api/v3/jobs/metadata', 'REQUEST_METHOD' => 'GET'];
        $this->requestStub = new Request($params, [], [], $serverParams);
        $this->setProp('request', $this->requestStub);
    }

    /**
     * @param array<int,mixed> $args
     *
     * @throws ReflectionException
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        return $this->reflector->getMethod($method)->invoke($this->controller, ...$args);
    }

    /**
     * @throws ReflectionException
     */
    private function loadSeededJob(): JobStruct
    {
        return $this->invokePrivate('getJob', [$this->jobId(self::BASE), self::JOB_PASSWORD]);
    }

    // ─── index() happy path ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function index_returns_metadata_with_project_job_and_files_for_valid_job(): void
    {
        $this->setRequestParams([
            'id_job'   => (string) $this->jobId(self::BASE),
            'password' => self::JOB_PASSWORD,
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (stdClass $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->index();

        $this->assertInstanceOf(stdClass::class, $captured);
        $this->assertObjectHasProperty('project', $captured);
        $this->assertObjectHasProperty('job', $captured);
        $this->assertObjectHasProperty('files', $captured);

        // project: engine-config key routed to mt_extra, plain key stays top-level
        $this->assertObjectHasProperty('mt_extra', $captured->project);
        $this->assertSame('[101]', $captured->project->mt_extra->mmt_glossaries);
        $this->assertSame('plain_value', $captured->project->ctrl_plain_key);

        // job metadata key present + marshaller default subfiltering_handlers
        $this->assertSame('enabled', $captured->job->tag_projection);
        $this->assertObjectHasProperty('subfiltering_handlers', $captured->job);
        $this->assertSame([], $captured->job->subfiltering_handlers);

        // files: one seeded file with its file_metadata
        $this->assertIsArray($captured->files);
        $this->assertCount(1, $captured->files);
        $this->assertSame($this->fileId(self::BASE), $captured->files[0]->id);
        $this->assertSame('real_name.docx', $captured->files[0]->data->original_filename);
    }

    // ─── index() failure path ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function index_throws_not_found_for_wrong_password(): void
    {
        $this->setRequestParams([
            'id_job'   => (string) $this->jobId(self::BASE),
            'password' => 'wrong_password_zzz',
        ]);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Job not found.');

        $this->controller->index();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function index_throws_not_found_for_nonexistent_job(): void
    {
        $this->setRequestParams([
            'id_job'   => '88888888',
            'password' => 'any_password_here',
        ]);

        $this->expectException(NotFoundException::class);

        $this->controller->index();
    }

    // ─── getProjectInfo() ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function getProjectInfo_routes_engine_keys_to_mt_extra_and_keeps_other_keys_top_level(): void
    {
        $job     = $this->loadSeededJob();
        $project = $job->getProject(new ProjectDao(Database::obtain()));

        /** @var stdClass $result */
        $result = $this->invokePrivate('getProjectInfo', [$project]);

        $this->assertObjectHasProperty('mt_extra', $result);
        $this->assertSame('[101]', $result->mt_extra->mmt_glossaries);
        $this->assertSame('plain_value', $result->ctrl_plain_key);
        $this->assertObjectNotHasProperty('mmt_glossaries', $result);
    }

    // ─── getJobMetaData() ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function getJobMetaData_returns_seeded_keys_and_default_subfiltering_handlers(): void
    {
        $job = $this->loadSeededJob();

        /** @var stdClass $result */
        $result = $this->invokePrivate('getJobMetaData', [$job]);

        $this->assertSame('enabled', $result->tag_projection);
        $this->assertObjectHasProperty('subfiltering_handlers', $result);
        $this->assertSame([], $result->subfiltering_handlers);
    }

    // ─── getJobFilesMetaData() ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function getJobFilesMetaData_returns_file_entries_with_metadata(): void
    {
        $job = $this->loadSeededJob();

        /** @var array<int,stdClass> $result */
        $result = $this->invokePrivate('getJobFilesMetaData', [$job]);

        $this->assertCount(1, $result);
        $this->assertSame($this->fileId(self::BASE), $result[0]->id);
        $this->assertSame('ctrltest_' . self::BASE . '.xliff', $result[0]->filename);
        $this->assertSame('real_name.docx', $result[0]->data->original_filename);
    }

    // ─── getJob() ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function getJob_returns_struct_for_valid_credentials(): void
    {
        $job = $this->loadSeededJob();

        $this->assertInstanceOf(JobStruct::class, $job);
        $this->assertSame($this->jobId(self::BASE), $job->id);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function getJob_returns_null_for_unknown_job(): void
    {
        $job = $this->invokePrivate('getJob', [88888888, 'nope']);

        $this->assertNull($job);
    }
}
