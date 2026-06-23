<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\JobMetadataController;
use Controller\API\Commons\Exceptions\NotFoundException;
use Exception;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\MetadataDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\ExpectationFailedException;
use ReflectionClass;
use ReflectionException;
use Throwable;
use Utils\Logger\MatecatLogger;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;

class TestableJobMetadataController extends JobMetadataController
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

/**
 * Real-DB controller suite (Playbook §1/§4).
 * Reserved ID block base = 9_000_000 + (14 * 1000) = 9014000.
 *   base+1 project 9014001, base+2 job 9014002, base+3 segment 9014003, base+4 file 9014004.
 * Per-suite owner email: ctrltest_9014000@example.org.
 * Clean ONLY by reserved id.
 */
#[AllowMockObjectsWithoutExpectations]
class JobMetadataControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_000_000 + (14 * 1000);
    private const string JOB_PASSWORD = 'jobpw';

    /** @var ReflectionClass<JobMetadataController> */
    private ReflectionClass $reflector;
    private TestableJobMetadataController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;
    private string $owner;

    /**
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanTestData();
        $this->seedTestData();

        $this->controller = new TestableJobMetadataController();
        $this->reflector = new ReflectionClass(JobMetadataController::class);

        $this->requestStub = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->responseMock);

        $user = new UserStruct();
        $user->uid = $this->userId(self::BASE);
        $user->email = $this->owner;
        $user->first_name = 'Ctrl';
        $user->last_name = 'Tester';
        $this->reflector->getProperty('user')->setValue($this->controller, $user);

        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createMock(MatecatLogger::class));
        $this->reflector->getProperty('featureSet')->setValue($this->controller, new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));
        $this->reflector->getProperty('database')->setValue($this->controller, Database::obtain());
    }

    /**
     * @throws Throwable
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
        $this->owner = $this->ownerEmail(self::BASE);
        $this->seedProject(self::BASE, $this->owner);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $this->owner, self::JOB_PASSWORD);
    }

    /**
     * @throws \PDOException
     */
    private function cleanTestData(): void
    {
        $this->owner = $this->ownerEmail(self::BASE);
        $conn = $this->seedConnection();
        $conn->exec("DELETE FROM job_metadata WHERE id_job = " . $this->jobId(self::BASE));
        $this->cleanFragments(self::BASE);
    }

    /**
     * Build a request carrying both params and a JSON body with the correct content type.
     *
     * @param array<string, string> $params
     *
     * @throws ReflectionException
     */
    private function setRequest(array $params, ?string $body = null, bool $jsonContentType = false): void
    {
        $server = ['REQUEST_URI' => '/api/app/job/metadata', 'REQUEST_METHOD' => 'POST'];
        $server['CONTENT_TYPE'] = $jsonContentType ? 'application/json' : 'text/plain';
        $this->requestStub = new Request($params, [], [], $server, [], $body);
        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
    }

    /**
     * @throws Exception
     */
    private function insertMetadata(string $key, string $value): void
    {
        $dao = new MetadataDao(Database::obtain());
        $dao->set($this->jobId(self::BASE), self::JOB_PASSWORD, $key, $value);
    }

    // ─── delete() ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function delete_returns_json_with_deleted_struct_id(): void
    {
        $this->insertMetadata('tm_prioritization', 'true');

        $existing = (new MetadataDao(Database::obtain()))
            ->get($this->jobId(self::BASE), self::JOB_PASSWORD, 'tm_prioritization');
        $this->assertNotNull($existing);
        $expectedId = $existing->id;

        $this->setRequest([
            'id_job' => (string)$this->jobId(self::BASE),
            'password' => self::JOB_PASSWORD,
            'key' => 'tm_prioritization',
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use ($expectedId): bool {
                $this->assertArrayHasKey('id', $data);
                $this->assertSame($expectedId, $data['id']);
                return true;
            }));

        $this->controller->delete();

        // row really removed
        $after = (new MetadataDao(Database::obtain()))
            ->get($this->jobId(self::BASE), self::JOB_PASSWORD, 'tm_prioritization');
        $this->assertNull($after);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function delete_throws_not_found_when_metadata_absent(): void
    {
        $this->setRequest([
            'id_job' => (string)$this->jobId(self::BASE),
            'password' => self::JOB_PASSWORD,
            'key' => 'tm_prioritization',
        ]);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionCode(404);

        $this->controller->delete();
    }

    // ─── save() ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function save_throws_when_request_is_not_json(): void
    {
        $this->setRequest([
            'id_job' => (string)$this->jobId(self::BASE),
            'password' => self::JOB_PASSWORD,
        ], '[]', false);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);

        $this->controller->save();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function save_upserts_metadata_and_returns_json_with_persisted_struct(): void
    {
        $body = (string)json_encode([
            ['key' => 'tm_prioritization', 'value' => true],
        ]);

        $this->setRequest([
            'id_job' => (string)$this->jobId(self::BASE),
            'password' => self::JOB_PASSWORD,
        ], $body, true);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertCount(1, $data);
                $struct = $data[0];
                $this->assertInstanceOf(\Model\Jobs\MetadataStruct::class, $struct);
                $this->assertSame('tm_prioritization', $struct->key);
                $this->assertSame($this->jobId(self::BASE), $struct->id_job);
                return true;
            }));

        $this->controller->save();

        // persisted in DB
        $stored = (new MetadataDao(Database::obtain()))
            ->get($this->jobId(self::BASE), self::JOB_PASSWORD, 'tm_prioritization');
        $this->assertNotNull($stored);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function save_persists_array_value_as_json_encoded_string(): void
    {
        $body = (string)json_encode([
            ['key' => 'subfiltering_handlers', 'value' => []],
        ]);

        $this->setRequest([
            'id_job' => (string)$this->jobId(self::BASE),
            'password' => self::JOB_PASSWORD,
        ], $body, true);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertCount(1, $data);
                $this->assertSame('subfiltering_handlers', $data[0]->key);
                return true;
            }));

        $this->controller->save();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function save_throws_validation_exception_for_invalid_payload(): void
    {
        $body = (string)json_encode([
            ['key' => 'not_a_valid_key', 'value' => 'whatever'],
        ]);

        $this->setRequest([
            'id_job' => (string)$this->jobId(self::BASE),
            'password' => self::JOB_PASSWORD,
        ], $body, true);

        $this->expectException(JSONValidatorException::class);

        $this->controller->save();
    }

    // ─── sanitizeRequestParams() ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function sanitizeRequestParams_returns_expected_keys(): void
    {
        $this->setRequest([
            'id_job' => '  9014002  ',
            'password' => 'pw',
            'key' => 'tm_prioritization',
        ]);

        $m = $this->reflector->getMethod('sanitizeRequestParams');
        $result = $m->invoke($this->controller);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id_job', $result);
        $this->assertArrayHasKey('password', $result);
        $this->assertArrayHasKey('key', $result);
        $this->assertSame('tm_prioritization', $result['key']);
    }
}
