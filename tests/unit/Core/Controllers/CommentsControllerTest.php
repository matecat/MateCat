<?php

namespace Matecat\Core\Controllers;

use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\V2\CommentsController;
use Exception;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;

/**
 * Real-DB suite for {@see CommentsController} (API/V2).
 *
 * Reserved ID block (Playbook §4): base = 9_048_000 (task N=48).
 *   base+1 project (9048001), base+2 job (9048002),
 *   base+3 segment (9048003), base+4 file (9048004),
 *   base+10 comment (9048010).
 * Clean ONLY by reserved id; per-suite owner ctrltest_9048000@example.org.
 */
class TestableCommentsController extends CommentsController
{
    public function __construct()
    {
    }

    protected function registerValidators(): void
    {
    }
}

/**
 * Variant that does NOT override registerValidators(), so invoking it via
 * reflection exercises the real CommentsController::registerValidators body
 * (validator wiring + onSuccess closure registration).
 */
class RealValidatorsCommentsController extends CommentsController
{
    public function __construct()
    {
    }
}

#[AllowMockObjectsWithoutExpectations]
class CommentsControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_048_000;

    /** @var ReflectionClass<CommentsController> */
    private ReflectionClass $reflector;
    private TestableCommentsController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws \TypeError
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws \PHPUnit\Framework\InvalidArgumentException
     * @throws \PHPUnit\Event\NoPreviousThrowableException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);

        $owner = $this->ownerEmail(self::BASE);
        $this->seedProject(self::BASE, $owner);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $owner);
        $this->seedSegment(self::BASE);
        // Two comments of message_type IN (1,2) so getCommentsForChunk returns them.
        $this->seedComment(self::BASE, 'First comment', 1);
        $this->seedExtraComment('Second comment', 2);

        $this->controller = new TestableCommentsController();
        $this->reflector = new ReflectionClass(CommentsController::class);

        $this->requestStub = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->responseMock);
        $this->reflector->getProperty('database')->setValue($this->controller, obtainTestDatabase());
        $this->reflector->getProperty('chunk')->setValue($this->controller, $this->loadChunk());
    }

    /**
     * @throws \PDOException
     */
    protected function tearDown(): void
    {
        $conn = $this->seedConnection();
        $conn->exec("DELETE FROM comments WHERE id = " . ($this->commentId(self::BASE) + 1));
        $this->cleanFragments(self::BASE);

        parent::tearDown();
    }

    /**
     * @throws \PDOException
     */
    private function seedExtraComment(string $message, int $messageType): void
    {
        $id        = $this->commentId(self::BASE) + 1;
        $jobId     = $this->jobId(self::BASE);
        $segmentId = $this->segmentId(self::BASE);
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO comments (id, id_job, id_segment, create_date, email, full_name, uid, source_page, is_anonymous, message_type, message) "
            . "VALUES ($id, $jobId, $segmentId, NOW(), 'ctrluser2_9048000@example.org', 'Ctrl Tester2', " . $this->userId(self::BASE) . ", 1, 0, $messageType, '$message')"
        );
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\InvalidArgumentException
     * @throws \PHPUnit\Event\NoPreviousThrowableException
     */
    private function loadChunk(): JobStruct
    {
        $chunk = (new JobDao(obtainTestDatabase()))->getByIdAndPassword($this->jobId(self::BASE), 'jobpw');
        $this->assertInstanceOf(JobStruct::class, $chunk);

        return $chunk;
    }

    /**
     * @param array<string, string> $params
     *
     * @throws ReflectionException
     */
    private function setRequestParams(array $params): void
    {
        $serverParams = ['REQUEST_URI' => '/api/v2/jobs/comments', 'REQUEST_METHOD' => 'GET'];
        $this->requestStub = new Request($params, [], [], $serverParams);
        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws \TypeError
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws \PHPUnit\Framework\InvalidArgumentException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Event\NoPreviousThrowableException
     */
    #[Test]
    public function registerValidators_appends_login_and_chunk_password_validators(): void
    {
        $controller = new RealValidatorsCommentsController();

        $reflector = new ReflectionClass(CommentsController::class);
        $reflector->getProperty('request')->setValue($controller, new Request([
            'id_job' => (string) $this->jobId(self::BASE),
            'password' => 'jobpw',
        ], [], [], [
            'REQUEST_URI' => '/api/v2/jobs/comments',
            'REQUEST_METHOD' => 'GET',
        ]));
        $reflector->getProperty('response')->setValue($controller, $this->createMock(Response::class));
        $reflector->getProperty('params')->setValue($controller, [
            'id_job' => (string) $this->jobId(self::BASE),
            'password' => 'jobpw',
        ]);

        $method = $reflector->getMethod('registerValidators');
        $method->invoke($controller);

        $validatorsProp = $reflector->getProperty('validators');
        /** @var array<int, object> $validators */
        $validators = $validatorsProp->getValue($controller);

        $this->assertCount(2, $validators);
        $this->assertInstanceOf(LoginValidator::class, $validators[0]);
        $this->assertInstanceOf(ChunkPasswordValidator::class, $validators[1]);
    }

    /**
     * @throws Exception
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws \PHPUnit\Framework\InvalidArgumentException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Event\NoPreviousThrowableException
     */
    #[Test]
    public function index_returns_comments_for_the_chunk(): void
    {
        $this->setRequestParams([]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('comments', $data);
                $this->assertCount(2, $data['comments']);
                $messages = array_map(static fn($c): string => (string) $c->message, $data['comments']);
                $this->assertContains('First comment', $messages);
                $this->assertContains('Second comment', $messages);

                return true;
            }));

        $this->controller->index();
    }

    /**
     * @throws Exception
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws \PHPUnit\Framework\InvalidArgumentException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Event\NoPreviousThrowableException
     */
    #[Test]
    public function index_passes_from_id_param_through_to_the_dao(): void
    {
        // Exercises the controller branch that reads the `from_id` request param
        // and forwards it to CommentDao::getCommentsForChunk.
        $this->setRequestParams(['from_id' => (string) $this->commentId(self::BASE)]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('comments', $data);
                $messages = array_map(static fn($c): string => (string) $c->message, $data['comments']);
                $this->assertContains('Second comment', $messages);

                return true;
            }));

        $this->controller->index();
    }

    /**
     * @throws Exception
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws \PHPUnit\Framework\InvalidArgumentException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Event\NoPreviousThrowableException
     */
    #[Test]
    public function index_returns_empty_comments_for_chunk_without_comments(): void
    {
        $conn = $this->seedConnection();
        $conn->exec("DELETE FROM comments WHERE id = " . $this->commentId(self::BASE));
        $conn->exec("DELETE FROM comments WHERE id = " . ($this->commentId(self::BASE) + 1));

        $this->setRequestParams([]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('comments', $data);
                $this->assertSame([], $data['comments']);

                return true;
            }));

        $this->controller->index();
    }
}
