<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\V3\IssueCheckController;
use DomainException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Jobs\JobStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Utils\Constants\JobStatus;
use Utils\Logger\MatecatLogger;

class TestableIssueCheckController extends IssueCheckController
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
 * Mock-seam suite for {@see IssueCheckController}.
 *
 * ID block base = 9053000 (task N=53). Pattern: mock seam — the controller queries jobs +
 * segment_translation_events via DAOs that go straight to PDO (cacheTTL=0, no Redis read).
 * The DB singleton is replaced with a custom {@see IDatabase} stub whose getConnection()->prepare()
 * is routed by query content so the jobs lookup and the modified-segments lookup return distinct
 * pre-built structs. No real-DB rows are seeded; per-suite owner identity ctrltest_9053000@example.org
 * is unused (kept for ID-registry consistency).
 */
#[AllowMockObjectsWithoutExpectations]
class IssueCheckControllerTest extends AbstractTest
{
    /** @var \ReflectionClass<IssueCheckController> */
    private \ReflectionClass $reflector;
    private TestableIssueCheckController $controller;
    private Response&MockObject $responseMock;

    /**
     * @throws \ReflectionException
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws \Exception
     * @throws \TypeError
     * @throws \PHPUnit\Event\NoPreviousThrowableException
     * @throws \PHPUnit\Framework\InvalidArgumentException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new TestableIssueCheckController();
        $this->reflector  = new \ReflectionClass(IssueCheckController::class);

        $this->responseMock = $this->createMock(Response::class);
        $this->setProp('response', $this->responseMock);
        $this->setProp('request', new Request());
        $this->setProp('logger', $this->createMock(MatecatLogger::class));
    }

    protected function tearDown(): void
    {
        // setDatabaseInstance() set databaseMockApplied; parent::tearDown() restores the singleton.
        parent::tearDown();
    }

    /**
     * @throws \ReflectionException
     */
    private function setProp(string $name, mixed $value): void
    {
        $this->reflector->getProperty($name)->setValue($this->controller, $value);
    }

    /**
     * @param array<string, string> $params
     *
     * @throws \ReflectionException
     */
    private function setRequestParams(array $params): void
    {
        $this->setProp('request', new Request($params));
    }

    /**
     * Build a PDOStatement stub whose fetchAll() yields the given rows. setFetchMode() and
     * execute() are no-ops on the stub; the already-typed structs survive the `instanceof`
     * filtering in {@see \Model\DataAccess\AbstractDao::_fetchObjectMap}.
     *
     * @param list<object> $rows
     *
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws \PHPUnit\Event\NoPreviousThrowableException
     * @throws \PHPUnit\Framework\InvalidArgumentException
     */
    private function makeStatement(array $rows): PDOStatement
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->queryString = '';
        $stmt->method('fetchAll')->willReturn($rows);

        return $stmt;
    }

    /**
     * Install a custom DB singleton whose prepare() routes by query content:
     * - the modified-segments query (`segment_translation_events`) -> $segmentRows
     * - everything else (the jobs lookup) -> $jobRows
     *
     * @param list<object> $jobRows
     * @param list<object> $segmentRows
     *
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws \PHPUnit\Event\NoPreviousThrowableException
     * @throws \PHPUnit\Framework\InvalidArgumentException
     * @throws \ReflectionException
     */
    private function installDb(array $jobRows, array $segmentRows): void
    {
        $jobStmt     = $this->makeStatement($jobRows);
        $segmentStmt = $this->makeStatement($segmentRows);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturnCallback(
            function (string $query) use ($jobStmt, $segmentStmt): PDOStatement {
                return str_contains($query, 'segment_translation_events') ? $segmentStmt : $jobStmt;
            }
        );

        $db = $this->createStub(IDatabase::class);
        $db->method('getConnection')->willReturn($pdo);

        $this->setDatabaseInstance($db);
        $this->reflector->getProperty('database')->setValue($this->controller, $db);
    }

    private function makeJob(int $id, string $password, string $statusOwner = JobStatus::STATUS_ACTIVE): JobStruct
    {
        $job               = new JobStruct();
        $job->id           = $id;
        $job->password     = $password;
        $job->status_owner = $statusOwner;

        return $job;
    }

    /**
     * @param array<string, int|string> $data
     */
    private function makeSegment(array $data): ShapelessConcreteStruct
    {
        return new ShapelessConcreteStruct($data);
    }

    // ─── segments() happy path ───

    /**
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws \Exception
     */
    #[Test]
    public function segments_returns_aggregated_modified_segments(): void
    {
        $this->installDb(
            [$this->makeJob(9053002, 'pwd9053xyz')],
            [
                $this->makeSegment(['id_segment' => 41, 'q_count' => 2]),
                $this->makeSegment(['id_segment' => 42, 'q_count' => 3]),
            ]
        );

        $this->setRequestParams([
            'id_job'      => '9053002',
            'password'    => 'pwd9053xyz',
            'source_page' => '2',
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->segments();

        $this->assertIsArray($captured);
        $this->assertSame(2, $captured['modified_segments_count']);
        $this->assertSame(5, $captured['issue_count']);
        $this->assertCount(2, $captured['modified_segments']);
        $this->assertSame(['id_segment' => 41, 'issue_count' => 2], $captured['modified_segments'][0]);
        $this->assertSame(['id_segment' => 42, 'issue_count' => 3], $captured['modified_segments'][1]);
    }

    /**
     * No modified segments -> empty aggregate payload, success branch still emits JSON.
     *
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws \Exception
     */
    #[Test]
    public function segments_returns_empty_payload_when_no_modified_segments(): void
    {
        // Distinct id/password from the aggregated test so the SegmentTranslationDao
        // (setCacheTTL=300) cache key does not collide across tests in the same process.
        $this->installDb(
            [$this->makeJob(9053012, 'pwd9053empty')],
            []
        );

        $this->setRequestParams([
            'id_job'   => '9053012',
            'password' => 'pwd9053empty',
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->segments();

        $this->assertIsArray($captured);
        $this->assertSame(0, $captured['modified_segments_count']);
        $this->assertSame(0, $captured['issue_count']);
        $this->assertSame([], $captured['modified_segments']);
    }

    // ─── segments() failure paths ───

    /**
     * No job row + no chunk-review row -> getJob() returns null -> NotFoundException.
     *
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws \Exception
     */
    #[Test]
    public function segments_throws_not_found_when_job_missing(): void
    {
        $this->installDb([], []);

        $this->setRequestParams([
            'id_job'   => '9053002',
            'password' => 'wrongpwd',
        ]);

        $this->responseMock->expects($this->never())->method('json');

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Job not found.');

        $this->controller->segments();
    }

    // ─── segments() return404IfTheJobWasDeleted path ───
    // A deleted job calls $this->response->status()->setCode(404) then exit(); the exit()
    // terminates the worker, so the deleted-job branch is not unit-testable in-process.
    // Covered by assertion: a non-deleted job (status_owner=active) bypasses that branch,
    // exercised by the happy-path tests above.
}
