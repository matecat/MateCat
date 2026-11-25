<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\SetChunkCompletedController;
use Exception;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobDao;
use Model\Users\UserStruct;
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
use TypeError;
use Utils\Constants\SourcePages;
use Utils\Logger\MatecatLogger;

class TestableSetChunkCompletedController extends SetChunkCompletedController
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
 * Real-DB suite for {@see SetChunkCompletedController}.
 *
 * ID block base = 9013000 (task N=13). Reserved offsets via ControllerSeedFragments:
 *   base+1 project, base+2 job, base+3 segment, base+4 file, base+5 team.
 * Cleans ONLY by reserved id; per-suite owner email ctrltest_9013000@example.org.
 */
#[AllowMockObjectsWithoutExpectations]
class SetChunkCompletedControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_013_000;

    /** @var ReflectionClass<SetChunkCompletedController> */
    private ReflectionClass $reflector;
    private TestableSetChunkCompletedController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws NoPreviousThrowableException
     * @throws PHPUnitInvalidArgumentException
     * @throws MockObjectException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);
        $this->cleanCompletionRows();
        $this->seedTestData();

        $this->controller = new TestableSetChunkCompletedController();
        $this->reflector  = new ReflectionClass(SetChunkCompletedController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);

        $user            = new UserStruct();
        $user->uid       = $this->userId(self::BASE);
        $user->email     = $this->ownerEmail(self::BASE);
        $user->first_name = 'Ctrl';
        $user->last_name  = 'Tester';
        $this->setProp('user', $user);

        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));
        $this->setProp('database', obtainTestDatabase());
    }

    /**
     * @throws PDOException
     */
    protected function tearDown(): void
    {
        $this->cleanCompletionRows();
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    /**
     * Completion-event tables are not part of the shared seed fragments; clean
     * by the reserved job id so currentPhase() is deterministic (no stale
     * record => TRANSLATE phase, matching a non-review completion).
     *
     * @throws PDOException
     */
    private function cleanCompletionRows(): void
    {
        $conn  = $this->seedConnection();
        $jobId = $this->jobId(self::BASE);
        $conn->exec("DELETE FROM chunk_completion_events WHERE id_job = $jobId");
        $conn->exec("DELETE FROM chunk_completion_updates WHERE id_job = $jobId");
    }

    private function seedTestData(): void
    {
        $owner = $this->ownerEmail(self::BASE);
        $this->seedProject(self::BASE, $owner);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $owner, 'chunkpw');
        $this->seedSegment(self::BASE);
        $this->seedSegmentTranslation(self::BASE);
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
     * @param list<mixed> $args
     *
     * @throws ReflectionException
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        $m = $this->reflector->getMethod($method);

        return $m->invoke($this->controller, ...$args);
    }

    /**
     * ChunkPasswordValidator runs before the action in production; these tests call the action
     * (and isRevision) directly, so seed the credential-resolved chunk the controller now reads.
     *
     * @throws ReflectionException
     */
    private function seedChunk(int $sourcePage): void
    {
        $chunk = (new JobDao(obtainTestDatabase()))->getByIdAndPassword($this->jobId(self::BASE), 'chunkpw');
        self::assertNotNull($chunk);
        $chunk->setSourcePage($sourcePage);
        $this->setProp('chunk', $chunk);
    }

    // ─── isRevision() phase derivation (credential-resolved source_page, never the Referer) ───

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function isRevision_is_false_when_chunk_source_page_is_translate(): void
    {
        $this->seedChunk(SourcePages::SOURCE_PAGE_TRANSLATE);

        $this->assertFalse($this->invokePrivate('isRevision'));
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function isRevision_is_true_when_chunk_source_page_is_revision(): void
    {
        $this->seedChunk(SourcePages::SOURCE_PAGE_REVISION);

        $this->assertTrue($this->invokePrivate('isRevision'));
    }

    // ─── complete() public action ───

    /**
     * @throws Exception
     * @throws TypeError
     * @throws PHPUnitException
     * @throws ExpectationFailedException
     * @throws ReflectionException
     */
    #[Test]
    public function complete_returns_event_id_on_success(): void
    {
        $this->seedChunk(SourcePages::SOURCE_PAGE_TRANSLATE);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->complete();

        $this->assertIsArray($captured);
        $this->assertArrayHasKey('data', $captured);
        $this->assertArrayHasKey('event', $captured['data']);
        $this->assertArrayHasKey('id', $captured['data']['event']);
        $this->assertIsInt($captured['data']['event']['id']);
        $this->assertGreaterThan(0, $captured['data']['event']['id']);
    }
}
