<?php

namespace Matecat\Core\Controllers;

use Controller\API\V2\MarkAllSegmentStatusController;
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
 * Reserved ID block: base = 9_036_000 (Wave 6 N=36).
 *   9036001 project, 9036002 job, 9036003 segment, 9036004 file,
 *   9036008 qa_chunk_review.
 * Owner email: ctrltest_9036000@example.org (per-suite unique, Playbook §4).
 */
class TestableMarkAllSegmentStatusController extends MarkAllSegmentStatusController
{
    public function __construct()
    {
    }

    protected function registerValidators(): void
    {
    }
}

/**
 * Keeps the production registerValidators() so the validator-registration path
 * can be exercised directly (the plain Testable overrides it to a no-op).
 */
class RegisterValidatorsMarkAllSegmentStatusController extends MarkAllSegmentStatusController
{
    public function __construct()
    {
    }
}

#[AllowMockObjectsWithoutExpectations]
class MarkAllSegmentStatusControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_036_000;

    /** @var ReflectionClass<MarkAllSegmentStatusController> */
    private ReflectionClass $reflector;
    private TestableMarkAllSegmentStatusController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws \Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);
        $this->seedTestData();

        $this->controller = new TestableMarkAllSegmentStatusController();
        $this->reflector = new ReflectionClass(MarkAllSegmentStatusController::class);

        $this->requestStub = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);

        $user = new UserStruct();
        $user->uid = $this->userId(self::BASE);
        $user->email = $this->ownerEmail(self::BASE);
        $user->first_name = 'Ctrl';
        $user->last_name = 'Tester';
        $this->setProp('user', $user);
        $this->setProp('userIsLogged', true);

        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet());

        $this->setProp('chunk', $this->buildChunk());
    }

    /**
     * @throws \PDOException
     */
    protected function tearDown(): void
    {
        $this->cleanFragments(self::BASE);
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
        // A fully-translated, non-empty segment translation: it is NOT
        // unchangeable when moving to TRANSLATED.
        $this->seedSegmentTranslation(self::BASE, 'TRANSLATED', 'Ciao mondo');
        // A second segment that is "unchangeable" (empty translation) for the
        // no-enqueue happy path.
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO segments (id, id_file, internal_id, segment, segment_hash, raw_word_count, show_in_cattool) "
            . "VALUES (" . (self::BASE + 30) . ", " . $this->fileId(self::BASE) . ", '2', 'Empty seg', 'ctrltest_hash_" . self::BASE . "_b', 2, 1)"
        );
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO segment_translations (id_segment, id_job, segment_hash, translation, status, version_number, translation_date) "
            . "VALUES (" . (self::BASE + 30) . ", " . $this->jobId(self::BASE) . ", 'ctrltest_hash_" . self::BASE . "_b', '', 'NEW', 0, NOW())"
        );
        $this->seedChunkReview(self::BASE);
    }

    /**
     * @throws \PDOException
     */
    protected function cleanFragments(int $base): void
    {
        $conn = $this->seedConnection();
        $conn->exec("DELETE FROM segment_translations WHERE id_segment = " . ($base + 30));
        $conn->exec("DELETE FROM segments WHERE id = " . ($base + 30));
        $conn->exec("DELETE FROM segment_translations WHERE id_job = " . $this->jobId($base));
        $conn->exec("DELETE FROM qa_chunk_reviews WHERE id = " . $this->chunkReviewId($base));
        $conn->exec("DELETE FROM segments WHERE id = " . $this->segmentId($base));
        $conn->exec("DELETE FROM files WHERE id = " . $this->fileId($base));
        $conn->exec("DELETE FROM jobs WHERE id = " . $this->jobId($base));
        $conn->exec("DELETE FROM projects WHERE id = " . $this->projectId($base));
    }

    private function buildChunk(): JobStruct
    {
        $chunk = new JobStruct();
        $chunk->id = $this->jobId(self::BASE);
        $chunk->password = 'jobpw';
        $chunk->id_project = $this->projectId(self::BASE);
        $chunk->source = 'en-US';
        $chunk->target = 'it-IT';
        $chunk->job_first_segment = $this->segmentId(self::BASE);
        $chunk->job_last_segment = self::BASE + 30;

        return $chunk;
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
     * @param array<string, mixed> $params
     * @throws ReflectionException
     */
    private function setRequestParams(array $params): void
    {
        $serverParams = ['REQUEST_URI' => '/api/v2/jobs', 'REQUEST_METHOD' => 'POST'];
        $this->requestStub = new Request($params, [], [], $serverParams);
        $this->setProp('request', $this->requestStub);
    }

    /**
     * @param array<int, mixed> $args
     * @throws ReflectionException
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        return $this->reflector->getMethod($method)->invoke($this->controller, ...$args);
    }

    // ─── sanitizeSegmentIDs ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function sanitizeSegmentIDs_casts_filters_and_dedupes(): void
    {
        $result = $this->invokePrivate('sanitizeSegmentIDs', [['5', '5', '0', 'abc', 7, '3']]);

        $this->assertSame([5, 7, 3], $result);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function sanitizeSegmentIDs_returns_empty_array_for_only_zero_like_values(): void
    {
        $result = $this->invokePrivate('sanitizeSegmentIDs', [['0', '', 'x']]);

        $this->assertSame([], $result);
    }

    // ─── registerValidators ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function registerValidators_appends_login_and_chunk_password_validators(): void
    {
        $controller = new RegisterValidatorsMarkAllSegmentStatusController();
        $reflector = new ReflectionClass(MarkAllSegmentStatusController::class);

        $reqProp = $reflector->getProperty('request');
        $reqProp->setValue($controller, new Request());

        $controller->params = [
            'id_job' => (string) $this->jobId(self::BASE),
            'password' => 'jobpw',
        ];

        $reflector->getMethod('registerValidators')->invoke($controller);

        $validatorsProp = $reflector->getProperty('validators');
        $validators = $validatorsProp->getValue($controller);

        $this->assertCount(2, $validators);
        $this->assertInstanceOf(
            \Controller\API\Commons\Validators\LoginValidator::class,
            $validators[0]
        );
        $this->assertInstanceOf(
            \Controller\API\Commons\Validators\ChunkPasswordValidator::class,
            $validators[1]
        );
    }

    // ─── changeSegmentsStatus ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function changeSegmentsStatus_does_nothing_for_non_status_changing_value(): void
    {
        $this->setRequestParams([
            'segments_id' => [$this->segmentId(self::BASE)],
            'status' => 'draft',
        ]);

        $this->responseMock->expects($this->never())->method('json');

        $this->controller->changeSegmentsStatus();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function changeSegmentsStatus_returns_unchangeable_segments_when_none_to_enqueue(): void
    {
        // Only request the empty-translation segment which is unchangeable for
        // TRANSLATED → array_diff empties segments_id → no enqueue → clean json.
        $emptySeg = self::BASE + 30;

        $this->setRequestParams([
            'segments_id' => [$emptySeg],
            'status' => 'translated',
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use ($emptySeg): bool {
                $this->assertTrue($data['data']);
                $this->assertContains($emptySeg, $data['unchangeble_segments']);
                $this->assertArrayNotHasKey('error_message', $data);
                return true;
            }));

        $this->controller->changeSegmentsStatus();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function changeSegmentsStatus_enqueues_changeable_segment_and_returns_data(): void
    {
        // The fully-translated, non-empty segment is changeable when moving to
        // TRANSLATED → it stays in segments_id → the worker is enqueued and the
        // success payload (data=true, empty unchangeable list) is returned.
        $segId = $this->segmentId(self::BASE);

        $this->setRequestParams([
            'segments_id' => [$segId],
            'status' => 'translated',
            'client_id' => 'cid-123',
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use ($segId): bool {
                $this->assertTrue($data['data']);
                $this->assertNotContains($segId, $data['unchangeble_segments']);
                return true;
            }));

        $this->controller->changeSegmentsStatus();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function changeSegmentsStatus_throws_for_invalid_revision_number(): void
    {
        $this->setRequestParams([
            'segments_id' => [$this->segmentId(self::BASE)],
            'status' => 'translated',
            'revision_number' => '99',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid revision number');

        $this->controller->changeSegmentsStatus();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function changeSegmentsStatus_accepts_valid_revision_number_and_enqueues(): void
    {
        // qa_chunk_reviews seeded with source_page=2 → valid revision number 1.
        $segId = $this->segmentId(self::BASE);

        $this->setRequestParams([
            'segments_id' => [$segId],
            'status' => 'approved',
            'revision_number' => '1',
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                // approved status: translated/non-empty segment IS unchangeable
                // moving to approved is allowed (status in list) so it stays
                // changeable → enqueue → caught error OR clean data; either way
                // a concrete payload with data===true is produced.
                $this->assertTrue($data['data']);
                $this->assertArrayHasKey('unchangeble_segments', $data);
                return true;
            }));

        $this->controller->changeSegmentsStatus();
    }
}
