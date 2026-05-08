<?php

namespace unit\Controllers;

use Controller\API\App\GetWarningController;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
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
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;

class TestableGetWarningController extends GetWarningController
{
    public function __construct()
    {
    }

    protected function afterConstruct(): void
    {
    }
}

#[AllowMockObjectsWithoutExpectations]
class GetWarningControllerTest extends AbstractTest
{
    private const int TEST_PROJECT_ID = 8888001;
    private const int TEST_JOB_ID = 8888002;
    private const string TEST_JOB_PASSWORD = 'warn_test_pw';
    private const int TEST_SEGMENT_ID = 8888003;
    private const int TEST_FILE_ID = 8888004;

    private ReflectionClass $reflector;
    private TestableGetWarningController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws ReflectionException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedTestData();

        $this->controller = new TestableGetWarningController();
        $this->reflector = new ReflectionClass(GetWarningController::class);

        $this->requestStub = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $reqProp = $this->reflector->getProperty('request');
        $reqProp->setValue($this->controller, $this->requestStub);

        $resProp = $this->reflector->getProperty('response');
        $resProp->setValue($this->controller, $this->responseMock);

        $user = new UserStruct();
        $user->uid = 1;
        $user->email = 'test@example.org';
        $user->first_name = 'Test';
        $user->last_name = 'User';

        $userProp = $this->reflector->getProperty('user');
        $userProp->setValue($this->controller, $user);

        $logProp = $this->reflector->getProperty('logger');
        $logProp->setValue($this->controller, $this->createMock(MatecatLogger::class));

        $fsProp = $this->reflector->getProperty('featureSet');
        $fsProp->setValue($this->controller, new FeatureSet());
    }

    protected function tearDown(): void
    {
        $this->cleanTestData();
        parent::tearDown();
    }

    private function seedTestData(): void
    {
        $db = Database::obtain();
        $conn = $db->getConnection();

        $this->cleanTestData();

        $conn->exec("INSERT INTO projects (id, id_customer, password, name, create_date, status_analysis) VALUES (" . self::TEST_PROJECT_ID . ", 'test@example.org', 'projpw', 'TestWarningProject', NOW(), 'DONE')");

        $conn->exec("INSERT INTO files (id, id_project, filename, source_language, mime_type) VALUES (" . self::TEST_FILE_ID . ", " . self::TEST_PROJECT_ID . ", 'test.xliff', 'en-US', 'application/xliff+xml')");

        $conn->exec("INSERT INTO jobs (id, password, id_project, source, target, job_first_segment, job_last_segment, owner, tm_keys, create_date, disabled) VALUES (" . self::TEST_JOB_ID . ", '" . self::TEST_JOB_PASSWORD . "', " . self::TEST_PROJECT_ID . ", 'en-US', 'it-IT', " . self::TEST_SEGMENT_ID . ", " . self::TEST_SEGMENT_ID . ", 'test@example.org', '[]', NOW(), 0)");

        $conn->exec("INSERT INTO segments (id, id_file, internal_id, segment, segment_hash, raw_word_count) VALUES (" . self::TEST_SEGMENT_ID . ", " . self::TEST_FILE_ID . ", '1', 'Hello world', 'hash1_test_warn', 2)");

        $conn->exec("INSERT INTO segment_translations (id_segment, id_job, segment_hash, translation, status, version_number, translation_date) VALUES (" . self::TEST_SEGMENT_ID . ", " . self::TEST_JOB_ID . ", 'hash1_test_warn', 'Ciao mondo', 'TRANSLATED', 0, NOW())");
    }

    private function cleanTestData(): void
    {
        $db = Database::obtain();
        $conn = $db->getConnection();

        $conn->exec("DELETE FROM segment_translations WHERE id_job = " . self::TEST_JOB_ID);
        $conn->exec("DELETE FROM segments WHERE id_file = " . self::TEST_FILE_ID);
        $conn->exec("DELETE FROM jobs WHERE id = " . self::TEST_JOB_ID);
        $conn->exec("DELETE FROM files WHERE id = " . self::TEST_FILE_ID);
        $conn->exec("DELETE FROM projects WHERE id = " . self::TEST_PROJECT_ID);
    }

    /**
     * @throws ReflectionException
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        $m = $this->reflector->getMethod($method);

        return $m->invoke($this->controller, ...$args);
    }

    private function setRequestParams(array $params): void
    {
        $serverParams = ['REQUEST_URI' => '/api/app/getwarning', 'REQUEST_METHOD' => 'GET'];
        $this->requestStub = new Request($params, [], [], $serverParams);
        $reqProp = $this->reflector->getProperty('request');
        $reqProp->setValue($this->controller, $this->requestStub);
    }

    // ─── validateTheGlobalRequest ───

    #[Test]
    public function validateTheGlobalRequest_throws_when_id_job_is_empty(): void
    {
        $this->setRequestParams(['password' => 'somepass']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-1);

        $this->invokePrivate('validateTheGlobalRequest');
    }

    #[Test]
    public function validateTheGlobalRequest_throws_when_password_is_empty(): void
    {
        $this->setRequestParams(['id_job' => '123']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-2);

        $this->invokePrivate('validateTheGlobalRequest');
    }

    #[Test]
    public function validateTheGlobalRequest_returns_expected_structure(): void
    {
        $this->setRequestParams([
            'id_job' => '42',
            'password' => 'mypassword',
        ]);

        $result = $this->invokePrivate('validateTheGlobalRequest');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id_job', $result);
        $this->assertArrayHasKey('password', $result);
        $this->assertSame('42', $result['id_job']);
        $this->assertSame('mypassword', $result['password']);
    }

    #[Test]
    public function validateTheGlobalRequest_sanitizes_id_job_to_numeric_string(): void
    {
        $this->setRequestParams([
            'id_job' => '  8888002  ',
            'password' => 'pass',
        ]);

        $result = $this->invokePrivate('validateTheGlobalRequest');

        $this->assertSame('8888002', $result['id_job']);
    }

    #[Test]
    public function validateTheGlobalRequest_throws_code_minus_one_when_both_params_empty(): void
    {
        $this->setRequestParams([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-1);

        $this->invokePrivate('validateTheGlobalRequest');
    }

    // ─── validateTheLocalRequest ───

    #[Test]
    public function validateTheLocalRequest_throws_when_id_job_is_empty(): void
    {
        $this->setRequestParams(['password' => 'somepass', 'src_content' => 'hello', 'trg_content' => 'ciao']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-1);

        $this->invokePrivate('validateTheLocalRequest');
    }

    #[Test]
    public function validateTheLocalRequest_throws_when_password_is_empty(): void
    {
        $this->setRequestParams(['id_job' => '100', 'src_content' => 'hello', 'trg_content' => 'ciao']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-2);

        $this->invokePrivate('validateTheLocalRequest');
    }

    #[Test]
    public function validateTheLocalRequest_defaults_segment_status_to_draft_when_empty(): void
    {
        $this->setRequestParams([
            'id_job' => '100',
            'password' => 'pass',
            'src_content' => 'source',
            'trg_content' => 'target',
        ]);

        $result = $this->invokePrivate('validateTheLocalRequest');

        $this->assertSame('draft', $result['segment_status']);
    }

    #[Test]
    public function validateTheLocalRequest_returns_correct_shape_with_all_fields(): void
    {
        $this->setRequestParams([
            'id' => '5',
            'id_job' => '100',
            'src_content' => 'source text',
            'trg_content' => 'target text',
            'password' => 'mypass',
            'token' => 'tok123',
            'logs' => 'some logs',
            'segment_status' => 'translated',
            'characters_counter' => '200',
        ]);

        $result = $this->invokePrivate('validateTheLocalRequest');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('id_job', $result);
        $this->assertArrayHasKey('src_content', $result);
        $this->assertArrayHasKey('trg_content', $result);
        $this->assertArrayHasKey('password', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('logs', $result);
        $this->assertArrayHasKey('segment_status', $result);
        $this->assertArrayHasKey('characters_counter', $result);

        $this->assertSame(5, $result['id']);
        $this->assertSame('translated', $result['segment_status']);
        $this->assertSame('tok123', $result['token']);
    }

    #[Test]
    public function validateTheLocalRequest_returns_string_for_src_and_trg_content(): void
    {
        $this->setRequestParams([
            'id_job' => '100',
            'password' => 'mypass',
            'src_content' => 'source text',
            'trg_content' => 'target text',
        ]);

        $result = $this->invokePrivate('validateTheLocalRequest');

        $this->assertIsString($result['src_content']);
        $this->assertIsString($result['trg_content']);
        $this->assertSame('source text', $result['src_content']);
        $this->assertSame('target text', $result['trg_content']);
    }

    #[Test]
    public function validateTheLocalRequest_characters_counter_is_always_returned_as_string(): void
    {
        $this->setRequestParams([
            'id_job' => '100',
            'password' => 'mypass',
            'characters_counter' => '150',
        ]);

        $result = $this->invokePrivate('validateTheLocalRequest');

        $this->assertIsString($result['characters_counter']);
        $this->assertSame('150', $result['characters_counter']);
    }

    #[Test]
    public function validateTheLocalRequest_returns_empty_string_for_characters_counter_when_not_provided(): void
    {
        $this->setRequestParams([
            'id_job' => '100',
            'password' => 'mypass',
        ]);

        $result = $this->invokePrivate('validateTheLocalRequest');

        $this->assertIsString($result['characters_counter']);
    }

    // ─── getChunkAndLoadProjectFeatures ───

    #[Test]
    public function getChunkAndLoadProjectFeatures_returns_job_struct_for_valid_job(): void
    {
        $chunk = $this->invokePrivate(
            'getChunkAndLoadProjectFeatures',
            [(string) self::TEST_JOB_ID, self::TEST_JOB_PASSWORD]
        );

        $this->assertInstanceOf(JobStruct::class, $chunk);
        $this->assertSame(self::TEST_JOB_ID, $chunk->id);
    }

    #[Test]
    public function getChunkAndLoadProjectFeatures_returned_chunk_has_non_null_id(): void
    {
        $chunk = $this->invokePrivate(
            'getChunkAndLoadProjectFeatures',
            [(string) self::TEST_JOB_ID, self::TEST_JOB_PASSWORD]
        );

        $this->assertInstanceOf(JobStruct::class, $chunk);
        $this->assertNotNull($chunk->id);
        $this->assertSame(self::TEST_JOB_ID, (int) $chunk->id);
    }

    #[Test]
    public function getChunkAndLoadProjectFeatures_throws_not_found_for_wrong_password(): void
    {
        $this->expectException(NotFoundException::class);

        $this->invokePrivate(
            'getChunkAndLoadProjectFeatures',
            [(string) self::TEST_JOB_ID, 'wrong_password_xyz_999']
        );
    }

    #[Test]
    public function getChunkAndLoadProjectFeatures_throws_not_found_for_nonexistent_job(): void
    {
        $this->expectException(NotFoundException::class);

        $this->invokePrivate(
            'getChunkAndLoadProjectFeatures',
            ['99999999', 'any_password_here']
        );
    }

    // ─── global() public action ───

    #[Test]
    public function global_returns_json_with_data_and_errors_keys_on_success(): void
    {
        $this->setRequestParams([
            'id_job' => (string) self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('data', $data);
                $this->assertArrayHasKey('errors', $data);
                return true;
            }));

        $this->controller->global();
    }

    #[Test]
    public function global_catches_exception_and_returns_details_empty_array(): void
    {
        $this->setRequestParams([
            'id_job' => '9999999',
            'password' => 'nonexistent_job_password',
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('details', $data);
                $this->assertSame([], $data['details']);
                return true;
            }));

        $this->controller->global();
    }
}
