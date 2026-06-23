<?php

namespace Matecat\Core\Controllers;

use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\V2\ChunkTranslationIssueController;
use Exception;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\Jobs\JobStruct;
use PDOException;
use PHPUnit\Event\NoPreviousThrowableException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Exception as PHPUnitException;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\InvalidArgumentException as PHPUnitInvalidArgumentException;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use TypeError;
use Utils\Logger\MatecatLogger;

/**
 * Real-DB suite for {@see ChunkTranslationIssueController}.
 *
 * Reserved ID block (Playbook §4): base = 9_047_000 (task N=47).
 *   9047001 project, 9047002 job, 9047003 segment, 9047004 file.
 *   9047020 qa_category, 9047021 qa_entry (suite-local extras).
 * Owner email: ctrltest_9047000@example.org (never the shared test@example.org).
 * Clean ONLY by reserved id; clean-then-seed in setUp(); parent::tearDown() last.
 */
class TestableChunkTranslationIssueController extends ChunkTranslationIssueController
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
class ChunkTranslationIssueControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_047_000;
    private const int QA_CATEGORY_ID = 9_047_020;
    private const int QA_ENTRY_ID    = 9_047_021;
    private const string JOB_PASSWORD = 'jobpw';

    /** @var ReflectionClass<ChunkTranslationIssueController> */
    private ReflectionClass $reflector;
    private TestableChunkTranslationIssueController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws NoPreviousThrowableException
     * @throws PHPUnitInvalidArgumentException
     * @throws MockObjectException
     * @throws PDOException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanTestData();
        $this->seedTestData();

        $this->controller = new TestableChunkTranslationIssueController();
        $this->reflector  = new ReflectionClass(ChunkTranslationIssueController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);
        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('database', Database::obtain());
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
        $owner = $this->ownerEmail(self::BASE);

        $this->seedProject(self::BASE, $owner);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $owner, self::JOB_PASSWORD);
        $this->seedSegment(self::BASE);
        $this->seedSegmentTranslation(self::BASE);
    }

    /**
     * @throws PDOException
     */
    private function cleanTestData(): void
    {
        $conn = $this->seedConnection();
        $conn->exec('DELETE FROM qa_entries WHERE id = ' . self::QA_ENTRY_ID);
        $conn->exec('DELETE FROM qa_categories WHERE id = ' . self::QA_CATEGORY_ID);
        $this->cleanFragments(self::BASE);
    }

    /**
     * @throws PDOException
     */
    private function seedQaEntry(): void
    {
        $conn      = $this->seedConnection();
        $segmentId = $this->segmentId(self::BASE);
        $jobId     = $this->jobId(self::BASE);

        $conn->exec(
            'INSERT IGNORE INTO qa_categories (id, id_model, label) '
            . "VALUES (" . self::QA_CATEGORY_ID . ", 1, 'CtrlTestCategory')"
        );
        $conn->exec(
            'INSERT IGNORE INTO qa_entries '
            . '(id, uid, id_segment, id_job, id_category, severity, translation_version, '
            . 'start_node, start_offset, end_node, end_offset, target_text, is_full_segment, '
            . 'penalty_points, source_page, create_date) '
            . 'VALUES (' . self::QA_ENTRY_ID . ', 1, ' . $segmentId . ', ' . $jobId . ', '
            . self::QA_CATEGORY_ID . ", 'low', 0, 0, 0, 0, 5, 'Ciao mondo', 1, 1.00, 2, NOW())"
        );
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
     * @throws ReflectionException
     */
    private function setChunk(string $password = self::JOB_PASSWORD): void
    {
        $chunk           = new JobStruct();
        $chunk->id       = $this->jobId(self::BASE);
        $chunk->password = $password;
        $this->setProp('chunk', $chunk);
    }

    // ─── index() ───

    /**
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws PHPUnitException
     * @throws ExpectationFailedException
     */
    #[Test]
    public function index_returns_empty_issues_when_no_qa_entries(): void
    {
        $this->setChunk();

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('issues', $data);
                $this->assertSame([], $data['issues']);
                return true;
            }));

        $this->controller->index();
    }

    /**
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws PDOException
     * @throws PHPUnitException
     * @throws ExpectationFailedException
     */
    #[Test]
    public function index_returns_rendered_issue_for_seeded_qa_entry(): void
    {
        $this->seedQaEntry();
        $this->setChunk();

        $expectedEntryId   = self::QA_ENTRY_ID;
        $expectedSegmentId = $this->segmentId(self::BASE);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use ($expectedEntryId, $expectedSegmentId): bool {
                $this->assertArrayHasKey('issues', $data);
                $this->assertCount(1, $data['issues']);

                $issue = $data['issues'][0];
                $this->assertSame($expectedEntryId, (int) $issue['id']);
                $this->assertSame($expectedSegmentId, (int) $issue['id_segment']);
                $this->assertSame('low', $issue['severity']);
                $this->assertSame('Ciao mondo', $issue['target_text']);
                return true;
            }));

        $this->controller->index();
    }

    /**
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws PDOException
     * @throws PHPUnitException
     * @throws ExpectationFailedException
     */
    #[Test]
    public function index_emits_no_issue_for_mismatched_password(): void
    {
        $this->seedQaEntry();
        $this->setChunk('wrong_password_xyz');

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('issues', $data);
                $this->assertSame([], $data['issues']);
                return true;
            }));

        $this->controller->index();
    }

    // ─── registerValidators() ───

    /**
     * @throws ReflectionException
     * @throws PHPUnitException
     * @throws ExpectationFailedException
     */
    #[Test]
    public function registerValidators_appends_login_and_chunk_password_validators(): void
    {
        $controller = $this->reflector->newInstanceWithoutConstructor();

        $reqProp = $this->reflector->getProperty('request');
        $reqProp->setValue($controller, new Request());

        $paramsProp = $this->reflector->getProperty('params');
        $paramsProp->setValue($controller, [
            'id_job'   => (string) $this->jobId(self::BASE),
            'password' => self::JOB_PASSWORD,
        ]);

        $method = $this->reflector->getMethod('registerValidators');
        $method->invoke($controller);

        /** @var array<int, object> $validators */
        $validators = $this->reflector->getProperty('validators')->getValue($controller);

        $this->assertCount(2, $validators);
        $this->assertInstanceOf(LoginValidator::class, $validators[0]);
        $this->assertInstanceOf(ChunkPasswordValidator::class, $validators[1]);
    }
}
