<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\CompletionEventController;
use Controller\API\Commons\Exceptions\ValidationError;
use JsonException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\ChunksCompletion\ChunkCompletionEventDao;
use Model\ChunksCompletion\ChunkCompletionEventStruct;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\FeaturesBase\FeatureSet;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Exception as PHPUnitException;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Throwable;
use Utils\Logger\MatecatLogger;

/**
 * Real-DB suite for {@see CompletionEventController}.
 *
 * Reserved ID block (Playbook §4): base = 9_059_000 (task N=59).
 *   base+1 project (9059001), base+2 job (9059002), base+3 segment (9059003),
 *   base+4 file (9059004), base+5 team (9059005), base+6 user (9059006).
 * Owner email: ctrltest_9059000@example.org (never the shared test@example.org).
 * The chunk_completion_events row uses a dedicated reserved id (base+20).
 * The qa_chunk_reviews row uses the fragment id (base+8), carrying undo_data for EVENT_ID.
 * Clean ONLY by reserved id.
 */
class TestableCompletionEventController extends CompletionEventController
{
    public function __construct()
    {
    }

    protected function registerValidators(): void
    {
    }
}

#[AllowMockObjectsWithoutExpectations]
class CompletionEventControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE     = 9_059_000;
    private const int EVENT_ID = 9_059_020;

    private const float UNDO_PENALTY_POINTS       = 12.5;
    private const int   UNDO_REVIEWED_WORDS_COUNT = 42;
    private const int   UNDO_IS_PASS              = 1;

    /** @var ReflectionClass<CompletionEventController> */
    private ReflectionClass $reflector;
    private TestableCompletionEventController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanTestData();
        $this->seedTestData();

        $this->controller = new TestableCompletionEventController();
        $this->reflector  = new ReflectionClass(CompletionEventController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);
        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet(obtainTestDatabase()));
        $this->setProp('database', obtainTestDatabase());

        $event           = new ChunkCompletionEventStruct();
        $event->id       = self::EVENT_ID;
        $event->id_job   = $this->jobId(self::BASE);
        $event->password = 'jobpw';
        $this->setProp('event', $event);
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
     * @throws ReflectionException
     */
    private function setProp(string $name, mixed $value): void
    {
        $prop = $this->reflector->getProperty($name);
        $prop->setValue($this->controller, $value);
    }

    /**
     * @throws PDOException
     * @throws JsonException
     */
    private function seedTestData(): void
    {
        $owner = $this->ownerEmail(self::BASE);
        $this->seedProject(self::BASE, $owner);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $owner);
        $this->seedSegment(self::BASE);

        $this->seedConnection()->exec(
            "INSERT IGNORE INTO chunk_completion_events "
            . "(id, id_project, id_job, uid, job_first_segment, job_last_segment, password, source, create_date, remote_ip_address, is_review) "
            . "VALUES (" . self::EVENT_ID . ", " . $this->projectId(self::BASE) . ", " . $this->jobId(self::BASE) . ", "
            . "NULL, " . $this->segmentId(self::BASE) . ", " . $this->segmentId(self::BASE) . ", "
            . "'jobpw', 'user', NOW(), '127.0.0.1', 0)"
        );

        // The review as the completion event left it: score reset, the previous one kept as undo_data.
        $this->seedChunkReview(self::BASE);
        $stmt = $this->seedConnection()->prepare(
            "UPDATE qa_chunk_reviews SET penalty_points = 0, reviewed_words_count = 0, is_pass = 0, undo_data = :undo WHERE id = :id"
        );
        $stmt->execute([
            'undo' => json_encode([
                'reset_by_event_id'    => (string) self::EVENT_ID,
                'penalty_points'       => self::UNDO_PENALTY_POINTS,
                'reviewed_words_count' => self::UNDO_REVIEWED_WORDS_COUNT,
                'is_pass'              => self::UNDO_IS_PASS,
            ], JSON_THROW_ON_ERROR),
            'id'   => $this->chunkReviewId(self::BASE),
        ]);
    }

    /**
     * @throws PDOException
     */
    private function cleanTestData(): void
    {
        $this->seedConnection()->exec("DELETE FROM chunk_completion_events WHERE id = " . self::EVENT_ID);
        $this->cleanFragments(self::BASE);
    }

    /**
     * @throws PDOException
     */
    private function eventRowCount(): int
    {
        $stmt = $this->seedConnection()->prepare(
            "SELECT COUNT(*) AS c FROM chunk_completion_events WHERE id = :id"
        );
        $stmt->execute(['id' => self::EVENT_ID]);
        $row = $stmt->fetch();

        return (int) ($row['c'] ?? 0);
    }

    /**
     * @return array<string, mixed>
     * @throws PDOException
     * @throws PHPUnitException
     */
    private function chunkReviewRow(): array
    {
        $stmt = $this->seedConnection()->prepare(
            "SELECT penalty_points, reviewed_words_count, is_pass, undo_data FROM qa_chunk_reviews WHERE id = :id"
        );
        $stmt->execute(['id' => $this->chunkReviewId(self::BASE)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row, 'seeded chunk review row exists');

        return $row;
    }

    /**
     * @throws PDOException
     * @throws PHPUnitException
     */
    private function assertChunkReviewRestoredFromUndoData(): void
    {
        $row = $this->chunkReviewRow();
        $this->assertEquals(self::UNDO_PENALTY_POINTS, $row['penalty_points']);
        $this->assertEquals(self::UNDO_REVIEWED_WORDS_COUNT, $row['reviewed_words_count']);
        $this->assertEquals(self::UNDO_IS_PASS, $row['is_pass']);
        $this->assertNull($row['undo_data']);
    }

    // ─── delete() public action ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function delete_removes_event_row_and_sends_response(): void
    {
        $this->assertSame(1, $this->eventRowCount(), 'precondition: seeded event row exists');

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with($this->callback(function (int $code): bool {
                $this->assertSame(200, $code);
                return true;
            }));
        $this->responseMock->expects($this->once())->method('send');

        $this->controller->delete();

        $this->assertSame(0, $this->eventRowCount(), 'event row deleted by __performUndo');
        $this->assertChunkReviewRestoredFromUndoData();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function delete_dispatches_alter_chunk_review_event_with_seeded_event(): void
    {
        $this->controller->delete();

        // The dispatch + DAO delete both ran against the real DB; the row is gone.
        $this->assertSame(0, $this->eventRowCount());
        $this->assertChunkReviewRestoredFromUndoData();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function delete_without_undo_data_throws_and_keeps_the_event(): void
    {
        $this->seedConnection()->exec("UPDATE qa_chunk_reviews SET undo_data = NULL WHERE id = " . $this->chunkReviewId(self::BASE));

        $this->responseMock->expects($this->never())->method('send');

        try {
            $this->controller->delete();
            $this->fail('ValidationError expected when the review has no undo data');
        } catch (ValidationError $e) {
            $this->assertSame('Undo data is not available', $e->getMessage());
        }

        // The transaction was rolled back: the event is still there and the review untouched.
        $this->assertSame(1, $this->eventRowCount());
        $row = $this->chunkReviewRow();
        $this->assertEquals(0, $row['penalty_points']);
        $this->assertNull($row['undo_data']);
    }

    // ─── __performUndo() private helper (direct) ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function performUndo_commits_deletion_of_the_event(): void
    {
        $this->assertSame(1, $this->eventRowCount());

        $m = $this->reflector->getMethod('__performUndo');
        $m->invoke($this->controller);

        $this->assertSame(0, $this->eventRowCount());
        $this->assertChunkReviewRestoredFromUndoData();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function performUndo_is_noop_count_when_event_already_absent(): void
    {
        // Remove the seeded row first; undo_data still points at the event struct's id, so only the delete is a no-op.
        $this->seedConnection()->exec("DELETE FROM chunk_completion_events WHERE id = " . self::EVENT_ID);
        $this->assertSame(0, $this->eventRowCount());

        $m = $this->reflector->getMethod('__performUndo');
        $m->invoke($this->controller);

        $this->assertSame(0, $this->eventRowCount());
    }

    // ─── registerValidators() + ChunkPasswordValidator onSuccess closure ───

    /**
     * Drives the closure body registered in registerValidators(): it loads the
     * event via ChunkCompletionEventDao, assigns chunk/event, and loads project
     * features. Covers the happy path of the validator success callback.
     *
     * @throws Throwable
     */
    #[Test]
    public function registerValidators_onSuccess_loads_event_and_chunk(): void
    {
        $controller = (new ReflectionClass(CompletionEventController::class))->newInstanceWithoutConstructor();
        $ref        = new ReflectionClass(CompletionEventController::class);

        $params       = ['id_job' => (string) $this->jobId(self::BASE), 'password' => 'jobpw', 'id_event' => (string) self::EVENT_ID];
        $serverParams = ['REQUEST_URI' => '/api/app/completion', 'REQUEST_METHOD' => 'POST'];
        $request      = new Request($params, [], [], $serverParams);

        $ref->getProperty('request')->setValue($controller, $request);
        $ref->getProperty('response')->setValue($controller, $this->createMock(Response::class));
        $ref->getProperty('logger')->setValue($controller, $this->createMock(MatecatLogger::class));
        $ref->getProperty('featureSet')->setValue($controller, new FeatureSet(obtainTestDatabase()));
        $ref->getProperty('database')->setValue($controller, obtainTestDatabase());

        $paramsProp = $ref->getProperty('params');
        $paramsProp->setValue($controller, $params);

        $register = $ref->getMethod('registerValidators');
        $register->invoke($controller);

        $validatorsProp = $ref->getProperty('validators');
        $validators     = $validatorsProp->getValue($controller);
        $this->assertCount(2, $validators, 'LoginValidator + ChunkPasswordValidator registered');

        // Run the ChunkPasswordValidator (index 1); its onSuccess closure assigns chunk + event.
        $validators[1]->validate();

        $eventProp = $ref->getProperty('event');
        $event     = $eventProp->getValue($controller);
        $this->assertInstanceOf(ChunkCompletionEventStruct::class, $event);
        $this->assertSame(self::EVENT_ID, (int) $event->id);

        $chunkProp = $ref->getProperty('chunk');
        $this->assertSame($this->jobId(self::BASE), (int) $chunkProp->getValue($controller)->id);
    }

    /**
     * The onSuccess closure throws NotFoundException when the event row is absent
     * for the resolved chunk (covers lines 46-48).
     *
     * @throws Throwable
     */
    #[Test]
    public function registerValidators_onSuccess_throws_not_found_when_event_missing(): void
    {
        $this->seedConnection()->exec("DELETE FROM chunk_completion_events WHERE id = " . self::EVENT_ID);

        $controller = (new ReflectionClass(CompletionEventController::class))->newInstanceWithoutConstructor();
        $ref        = new ReflectionClass(CompletionEventController::class);

        $params       = ['id_job' => (string) $this->jobId(self::BASE), 'password' => 'jobpw', 'id_event' => (string) self::EVENT_ID];
        $serverParams = ['REQUEST_URI' => '/api/app/completion', 'REQUEST_METHOD' => 'POST'];
        $request      = new Request($params, [], [], $serverParams);

        $ref->getProperty('request')->setValue($controller, $request);
        $ref->getProperty('response')->setValue($controller, $this->createMock(Response::class));
        $ref->getProperty('logger')->setValue($controller, $this->createMock(MatecatLogger::class));
        $ref->getProperty('featureSet')->setValue($controller, new FeatureSet(obtainTestDatabase()));
        $ref->getProperty('database')->setValue($controller, obtainTestDatabase());
        $ref->getProperty('params')->setValue($controller, $params);

        $ref->getMethod('registerValidators')->invoke($controller);

        $validators = $ref->getProperty('validators')->getValue($controller);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionCode(404);

        $validators[1]->validate();
    }

    /**
     * Sanity: the DAO used by the controller fetches the seeded event by id+chunk.
     *
     * @throws Throwable
     */
    #[Test]
    public function dao_getByIdAndChunk_returns_seeded_event(): void
    {
        $chunkRef = new ChunkCompletionEventStruct();

        $job          = new \Model\Jobs\JobStruct();
        $job->id      = $this->jobId(self::BASE);
        $job->password = 'jobpw';

        $dao   = new ChunkCompletionEventDao(obtainTestDatabase());
        $event = $dao->getByIdAndChunk(self::EVENT_ID, $job);

        $this->assertInstanceOf(ChunkCompletionEventStruct::class, $event);
        $this->assertSame(self::EVENT_ID, (int) $event->id);
        $this->assertSame((int) $this->jobId(self::BASE), (int) $event->id_job);

        unset($chunkRef);
    }
}
