<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\SplitSegmentController;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\FeaturesBase\FeatureSet;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Utils\Logger\MatecatLogger;

/**
 * Real-DB suite for {@see SplitSegmentController}.
 *
 * Reserved ID block (Playbook §4): base = 9009000
 *   - 9009001 project, 9009002 job, 9009003 segment, 9009004 file
 * Owner email: ctrltest_9009000@example.org
 */
class TestableSplitSegmentController extends SplitSegmentController
{
    public function __construct()
    {
    }

    protected function registerValidators(): void
    {
    }
}

#[AllowMockObjectsWithoutExpectations]
class SplitSegmentControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9009000;
    private const string JOB_PASSWORD = 'splitpw';

    /** @var ReflectionClass<SplitSegmentController> */
    private ReflectionClass $reflector;
    private TestableSplitSegmentController $controller;
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

        $this->controller = new TestableSplitSegmentController();
        $this->reflector  = new ReflectionClass(SplitSegmentController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->responseMock);
        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createMock(MatecatLogger::class));
        $this->reflector->getProperty('featureSet')->setValue($this->controller, new FeatureSet(Database::obtain()));
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
        $this->seedJob(self::BASE, $owner, self::JOB_PASSWORD);
        $this->seedSegment(self::BASE);
    }

    /**
     * @throws \PDOException
     */
    private function cleanTestData(): void
    {
        $conn = $this->seedConnection();
        $conn->exec(
            "DELETE FROM segment_translations_splits WHERE id_segment = " . $this->segmentId(self::BASE)
            . " AND id_job = " . $this->jobId(self::BASE)
        );
        $this->cleanFragments(self::BASE);
    }

    /**
     * @param array<string, string> $params
     *
     * @throws ReflectionException
     */
    private function setRequestParams(array $params): void
    {
        $serverParams       = ['REQUEST_URI' => '/api/app/split', 'REQUEST_METHOD' => 'POST'];
        $this->requestStub  = new Request($params, [], [], $serverParams);
        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
    }

    /**
     * @param array<int, mixed> $args
     *
     * @throws ReflectionException
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        return $this->reflector->getMethod($method)->invoke($this->controller, ...$args);
    }

    // ─── registerValidators ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function registerValidators_appends_login_validator(): void
    {
        // Drive the real (non-overridden) registerValidators on a bare instance.
        $real = $this->reflector->newInstanceWithoutConstructor();
        $this->reflector->getProperty('validators')->setValue($real, []);
        $this->reflector->getProperty('request')->setValue($real, new Request());

        $this->reflector->getMethod('registerValidators')->invoke($real);

        $validators = $this->reflector->getProperty('validators')->getValue($real);
        $this->assertCount(1, $validators);
        $this->assertInstanceOf(\Controller\API\Commons\Validators\LoginValidator::class, $validators[0]);
    }

    // ─── validateTheRequest ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function validateTheRequest_throws_when_id_job_is_empty(): void
    {
        $this->setRequestParams(['id_segment' => '5', 'password' => 'pw', 'segment' => 'a']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-3);

        $this->invokePrivate('validateTheRequest');
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function validateTheRequest_throws_when_id_segment_is_empty(): void
    {
        $this->setRequestParams(['id_job' => '100', 'password' => 'pw', 'segment' => 'a']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-4);

        $this->invokePrivate('validateTheRequest');
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function validateTheRequest_throws_when_password_is_empty(): void
    {
        $this->setRequestParams(['id_job' => '100', 'id_segment' => '5', 'segment' => 'a']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-5);

        $this->invokePrivate('validateTheRequest');
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function validateTheRequest_throws_not_found_for_wrong_password(): void
    {
        $this->setRequestParams([
            'id_job'     => (string)$this->jobId(self::BASE),
            'id_segment' => (string)$this->segmentId(self::BASE),
            'password'   => 'wrong_password_xyz',
            'segment'    => 'hello',
        ]);

        $this->expectException(NotFoundException::class);

        $this->invokePrivate('validateTheRequest');
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function validateTheRequest_returns_expected_shape_for_valid_job(): void
    {
        $this->setRequestParams([
            'id_job'     => (string)$this->jobId(self::BASE),
            'id_segment' => (string)$this->segmentId(self::BASE),
            'password'   => self::JOB_PASSWORD,
            'segment'    => 'hello world',
            'target'     => 'ciao mondo',
        ]);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertIsArray($result);
        $this->assertSame((string)$this->jobId(self::BASE), $result['id_job']);
        $this->assertSame((string)$this->segmentId(self::BASE), $result['id_segment']);
        $this->assertSame(self::JOB_PASSWORD, $result['job_pass']);
        $this->assertSame('hello world', $result['segment']);
        $this->assertSame($this->jobId(self::BASE), $result['jobStruct']->id);
    }

    // ─── split() public action ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function split_returns_ok_payload_and_persists_split_row(): void
    {
        $segment = 'Hello##$_SPLIT$##world';
        $this->setRequestParams([
            'id_job'     => (string)$this->jobId(self::BASE),
            'id_segment' => (string)$this->segmentId(self::BASE),
            'password'   => self::JOB_PASSWORD,
            'segment'    => $segment,
            'target'     => '',
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame('OK', $data['data']);
                $this->assertSame([], $data['errors']);
                return true;
            }));

        $this->controller->split();

        // Verify the split row was atomically persisted.
        $conn = $this->seedConnection();
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS c FROM segment_translations_splits WHERE id_segment = ? AND id_job = ?"
        );
        $this->assertNotFalse($stmt);
        $stmt->execute([$this->segmentId(self::BASE), $this->jobId(self::BASE)]);
        $row = $stmt->fetch();
        $this->assertIsArray($row);
        $this->assertSame(1, (int)$row['c']);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function split_throws_runtime_exception_when_update_affects_no_rows(): void
    {
        // Pre-insert the identical split row so ON DUPLICATE KEY UPDATE with the
        // same values yields 0 affected rows -> atomicUpdate() returns null.
        $conn      = $this->seedConnection();
        $segmentId = $this->segmentId(self::BASE);
        $jobId     = $this->jobId(self::BASE);
        $src       = json_encode([0, 5, 5]);
        $trg       = json_encode(['len' => [0], 'statuses' => ['DRAFT', 'DRAFT']]);
        $conn->exec(
            "INSERT INTO segment_translations_splits (id_segment, id_job, source_chunk_lengths, target_chunk_lengths) "
            . "VALUES ($segmentId, $jobId, '$src', '$trg')"
        );

        $this->setRequestParams([
            'id_job'     => (string)$jobId,
            'id_segment' => (string)$segmentId,
            'password'   => self::JOB_PASSWORD,
            'segment'    => 'Hello##$_SPLIT$##world',
            'target'     => '',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed while splitting/merging segment.');

        $this->controller->split();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function split_throws_invalid_argument_for_missing_id_job(): void
    {
        $this->setRequestParams([
            'id_segment' => (string)$this->segmentId(self::BASE),
            'password'   => self::JOB_PASSWORD,
            'segment'    => 'Hello##$_SPLIT$##world',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-3);

        $this->controller->split();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function split_throws_not_found_for_nonexistent_job(): void
    {
        $this->setRequestParams([
            'id_job'     => '99990999',
            'id_segment' => (string)$this->segmentId(self::BASE),
            'password'   => 'any_pw',
            'segment'    => 'Hello##$_SPLIT$##world',
        ]);

        $this->expectException(NotFoundException::class);

        $this->controller->split();
    }
}
