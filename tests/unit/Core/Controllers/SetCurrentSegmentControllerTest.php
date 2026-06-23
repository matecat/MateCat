<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\SetCurrentSegmentController;
use Exception;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;
use PDOException;
use PHPUnit\Event\NoPreviousThrowableException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Exception as FrameworkException;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\InvalidArgumentException as FrameworkInvalidArgumentException;
use PHPUnit\Framework\MockObject\Exception as MockException;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use TypeError;
use Utils\Logger\MatecatLogger;

/**
 * Reserved ID block (Playbook §4): base = 9_000_000 + (8 * 1000) = 9008000
 *   9008001 project, 9008002 job, 9008003 current segment, 9008004 file,
 *   9008010 next (draft) segment.
 * Owner email: ctrltest_9008000@example.org (per-suite unique).
 * Clean ONLY by reserved id.
 */
class TestableSetCurrentSegmentController extends SetCurrentSegmentController
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
class SetCurrentSegmentControllerTest extends AbstractTest
{
    private const int BASE = 9008000;
    private const int TEST_PROJECT_ID = self::BASE + 1;
    private const int TEST_JOB_ID = self::BASE + 2;
    private const int TEST_SEGMENT_ID = self::BASE + 3;
    private const int TEST_FILE_ID = self::BASE + 4;
    private const int TEST_NEXT_SEGMENT_ID = self::BASE + 10;
    private const string TEST_JOB_PASSWORD = 'curseg_test_pw';
    private const string OWNER = 'ctrltest_9008000@example.org';

    /** @var ReflectionClass<SetCurrentSegmentController> */
    private ReflectionClass $reflector;
    private TestableSetCurrentSegmentController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws NoPreviousThrowableException
     * @throws FrameworkInvalidArgumentException
     * @throws MockException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanTestData();
        $this->seedTestData();

        $this->controller = new TestableSetCurrentSegmentController();
        $this->reflector = new ReflectionClass(SetCurrentSegmentController::class);

        $this->requestStub = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $reqProp = $this->reflector->getProperty('request');
        $reqProp->setValue($this->controller, $this->requestStub);

        $resProp = $this->reflector->getProperty('response');
        $resProp->setValue($this->controller, $this->responseMock);

        $user = new UserStruct();
        $user->uid = 1;
        $user->email = self::OWNER;
        $user->first_name = 'Test';
        $user->last_name = 'User';

        $userProp = $this->reflector->getProperty('user');
        $userProp->setValue($this->controller, $user);

        $logProp = $this->reflector->getProperty('logger');
        $logProp->setValue($this->controller, $this->createMock(MatecatLogger::class));

        $fsProp = $this->reflector->getProperty('featureSet');
        $fsProp->setValue($this->controller, new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));

        $this->reflector->getProperty('database')->setValue($this->controller, Database::obtain());
    }

    /**
     * @throws PDOException
     */
    protected function tearDown(): void
    {
        $this->cleanTestData();
        parent::tearDown();
    }

    /**
     * @throws PDOException
     */
    private function seedTestData(): void
    {
        $conn = Database::obtain()->getConnection();

        $conn->exec("INSERT INTO projects (id, id_customer, password, name, create_date, status_analysis) VALUES (" . self::TEST_PROJECT_ID . ", '" . self::OWNER . "', 'projpw', 'CurSegProject', NOW(), 'DONE')");

        $conn->exec("INSERT INTO files (id, id_project, filename, source_language, mime_type) VALUES (" . self::TEST_FILE_ID . ", " . self::TEST_PROJECT_ID . ", 'cs.xliff', 'en-US', 'application/xliff+xml')");

        // job spans both seeded segments so getNextSegment's BETWEEN clause matches
        $conn->exec("INSERT INTO jobs (id, password, id_project, source, target, job_first_segment, job_last_segment, owner, tm_keys, create_date, disabled) VALUES (" . self::TEST_JOB_ID . ", '" . self::TEST_JOB_PASSWORD . "', " . self::TEST_PROJECT_ID . ", 'en-US', 'it-IT', " . self::TEST_SEGMENT_ID . ", " . self::TEST_NEXT_SEGMENT_ID . ", '" . self::OWNER . "', '[]', NOW(), 0)");

        // current segment
        $conn->exec("INSERT INTO segments (id, id_file, internal_id, segment, segment_hash, raw_word_count, show_in_cattool) VALUES (" . self::TEST_SEGMENT_ID . ", " . self::TEST_FILE_ID . ", '1', 'Hello world', 'cs_hash_cur', 2, 1)");
        $conn->exec("INSERT INTO segment_translations (id_segment, id_job, segment_hash, translation, status, version_number, translation_date) VALUES (" . self::TEST_SEGMENT_ID . ", " . self::TEST_JOB_ID . ", 'cs_hash_cur', 'Ciao mondo', 'DRAFT', 0, NOW())");

        // next (draft) segment that getNextSegment should return
        $conn->exec("INSERT INTO segments (id, id_file, internal_id, segment, segment_hash, raw_word_count, show_in_cattool) VALUES (" . self::TEST_NEXT_SEGMENT_ID . ", " . self::TEST_FILE_ID . ", '2', 'Second segment', 'cs_hash_next', 2, 1)");
        $conn->exec("INSERT INTO segment_translations (id_segment, id_job, segment_hash, translation, status, version_number, translation_date) VALUES (" . self::TEST_NEXT_SEGMENT_ID . ", " . self::TEST_JOB_ID . ", 'cs_hash_next', '', 'DRAFT', 0, NOW())");
    }

    /**
     * @throws PDOException
     */
    private function cleanTestData(): void
    {
        $conn = Database::obtain()->getConnection();

        $conn->exec("DELETE FROM segment_translations_splits WHERE id_job = " . self::TEST_JOB_ID);
        $conn->exec("DELETE FROM segment_translations WHERE id_job = " . self::TEST_JOB_ID);
        $conn->exec("DELETE FROM segments WHERE id_file = " . self::TEST_FILE_ID);
        $conn->exec("DELETE FROM jobs WHERE id = " . self::TEST_JOB_ID);
        $conn->exec("DELETE FROM files WHERE id = " . self::TEST_FILE_ID);
        $conn->exec("DELETE FROM projects WHERE id = " . self::TEST_PROJECT_ID);
    }

    /**
     * @param array<string, string> $params
     *
     * @throws ReflectionException
     */
    private function setRequestParams(array $params): void
    {
        $serverParams = ['REQUEST_URI' => '/api/app/setcurrentsegment', 'REQUEST_METHOD' => 'POST'];
        $this->requestStub = new Request($params, [], [], $serverParams);
        $reqProp = $this->reflector->getProperty('request');
        $reqProp->setValue($this->controller, $this->requestStub);
    }

    /**
     * @param array<int, mixed> $args
     *
     * @throws ReflectionException
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        $m = $this->reflector->getMethod($method);

        return $m->invoke($this->controller, ...$args);
    }

    // ─── validateTheRequest ───

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateTheRequest_throws_when_id_job_is_empty(): void
    {
        $this->setRequestParams(['password' => 'pw', 'id_segment' => '5']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-1);

        $this->invokePrivate('validateTheRequest');
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateTheRequest_throws_when_password_is_empty(): void
    {
        $this->setRequestParams(['id_job' => '100', 'id_segment' => '5']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-2);

        $this->invokePrivate('validateTheRequest');
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateTheRequest_throws_when_id_segment_is_empty(): void
    {
        $this->setRequestParams(['id_job' => '100', 'password' => 'pw']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-3);

        $this->invokePrivate('validateTheRequest');
    }

    /**
     * @throws ReflectionException
     * @throws FrameworkException
     * @throws ExpectationFailedException
     */
    #[Test]
    public function validateTheRequest_returns_expected_structure(): void
    {
        $this->setRequestParams([
            'id_job' => '42',
            'password' => 'mypass',
            'id_segment' => '7',
            'revision_number' => '1',
        ]);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertIsArray($result);
        $this->assertSame('42', $result['id_job']);
        $this->assertSame('mypass', $result['password']);
        $this->assertSame('7', $result['id_segment']);
        $this->assertSame('1', $result['revision_number']);
        $this->assertNull($result['split_num']);
    }

    /**
     * @throws ReflectionException
     * @throws ExpectationFailedException
     */
    #[Test]
    public function validateTheRequest_parses_split_num_from_id_segment(): void
    {
        $this->setRequestParams([
            'id_job' => '42',
            'password' => 'mypass',
            'id_segment' => '7-2',
        ]);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertSame('7', $result['id_segment']);
        $this->assertSame('2', $result['split_num']);
    }

    // ─── set() public action ───

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function set_returns_next_segment_id_on_success(): void
    {
        $this->setRequestParams([
            'id_job' => (string) self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
            'id_segment' => (string) self::TEST_SEGMENT_ID,
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame(1, $data['code']);
                $this->assertSame([], $data['errors']);
                $this->assertSame(self::TEST_NEXT_SEGMENT_ID, (int) $data['nextSegmentId']);
                return true;
            }));

        $this->controller->set();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws PDOException
     */
    #[Test]
    public function set_returns_translated_next_segment_when_revision_number_set(): void
    {
        // mark the next segment TRANSLATED so the revision branch finds it
        Database::obtain()->getConnection()->exec(
            "UPDATE segment_translations SET status = 'TRANSLATED' WHERE id_segment = " . self::TEST_NEXT_SEGMENT_ID . " AND id_job = " . self::TEST_JOB_ID
        );

        $this->setRequestParams([
            'id_job' => (string) self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
            'id_segment' => (string) self::TEST_SEGMENT_ID,
            'revision_number' => '1',
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame(1, $data['code']);
                $this->assertSame(self::TEST_NEXT_SEGMENT_ID, (int) $data['nextSegmentId']);
                return true;
            }));

        $this->controller->set();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function set_returns_null_next_segment_when_revision_number_set_and_none_translated(): void
    {
        $this->setRequestParams([
            'id_job' => (string) self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
            'id_segment' => (string) self::TEST_SEGMENT_ID,
            'revision_number' => '1',
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame(1, $data['code']);
                $this->assertNull($data['nextSegmentId']);
                return true;
            }));

        $this->controller->set();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function set_throws_not_found_for_wrong_password(): void
    {
        $this->setRequestParams([
            'id_job' => (string) self::TEST_JOB_ID,
            'password' => 'wrong_password_xyz',
            'id_segment' => (string) self::TEST_SEGMENT_ID,
        ]);

        $this->expectException(NotFoundException::class);

        $this->controller->set();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws PDOException
     */
    #[Test]
    public function set_returns_next_chunk_id_for_non_last_split_chunk(): void
    {
        // two source chunks => index 1 is last; split_num 0 is NOT last
        Database::obtain()->getConnection()->exec(
            "INSERT INTO segment_translations_splits (id_segment, id_job, source_chunk_lengths, target_chunk_lengths) "
            . "VALUES (" . self::TEST_SEGMENT_ID . ", " . self::TEST_JOB_ID . ", '[5,5]', '{\"len\":[0],\"statuses\":[\"DRAFT\"]}')"
        );

        $this->setRequestParams([
            'id_job' => (string) self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
            'id_segment' => (string) self::TEST_SEGMENT_ID . '-0',
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame(1, $data['code']);
                $this->assertSame(self::TEST_SEGMENT_ID . '-1', $data['nextSegmentId']);
                return true;
            }));

        $this->controller->set();

        Database::obtain()->getConnection()->exec(
            "DELETE FROM segment_translations_splits WHERE id_segment = " . self::TEST_SEGMENT_ID . " AND id_job = " . self::TEST_JOB_ID
        );
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function set_throws_invalid_argument_when_id_job_missing(): void
    {
        $this->setRequestParams([
            'password' => self::TEST_JOB_PASSWORD,
            'id_segment' => (string) self::TEST_SEGMENT_ID,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->controller->set();
    }
}
