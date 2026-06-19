<?php

namespace Matecat\Core\Controllers;

use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\V3\ChunkController;
use Exception;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Throwable;
use Utils\Logger\MatecatLogger;

/**
 * Reserved ID block (Playbook §4): base = 9_055_000 (task N=55).
 *   base+1 project, base+2 job, base+3 segment, base+4 file, base+5 team,
 *   base+6 user/uid. Clean ONLY by reserved id; per-suite owner email
 *   ctrltest_9055000@example.org.
 */
class TestableChunkV3Controller extends ChunkController
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
class ChunkV3ControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_055_000;

    /** @var ReflectionClass<ChunkController> */
    private ReflectionClass $reflector;
    private TestableChunkV3Controller $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);
        $this->seedTestData();

        $this->controller = new TestableChunkV3Controller();
        $this->reflector  = new ReflectionClass(ChunkController::class);

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
        $this->setProp('featureSet', new FeatureSet());
    }

    protected function tearDown(): void
    {
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    private function seedTestData(): void
    {
        $owner = $this->ownerEmail(self::BASE);
        $this->seedProject(self::BASE, $owner);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $owner, 'jobpw', 'active');
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
     * @throws Throwable
     */
    private function loadChunk(): JobStruct
    {
        $job = (new JobDao())->getByIdAndPassword($this->jobId(self::BASE), 'jobpw');
        $this->assertInstanceOf(JobStruct::class, $job);

        return $job;
    }

    /**
     * @throws Throwable
     */
    private function injectChunkState(JobStruct $chunk): void
    {
        $this->setProp('chunk', $chunk);
        $this->setProp('project', $chunk->getProject());
        $this->setProp('featureSet', FeatureSet::forProject($chunk->getProject(), Database::obtain()));
        $this->setProp('chunk_reviews', []);
    }

    // ─── show() happy path ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function show_returns_json_with_job_id_for_seeded_chunk(): void
    {
        $chunk = $this->loadChunk();
        $this->injectChunkState($chunk);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $payload): bool {
                $this->assertArrayHasKey('job', $payload);
                $this->assertArrayHasKey('id', $payload['job']);
                $this->assertSame($this->jobId(self::BASE), $payload['job']['id']);
                $this->assertArrayHasKey('chunks', $payload['job']);
                $this->assertCount(1, $payload['job']['chunks']);
                return true;
            }));

        $this->controller->show();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function show_rendered_chunk_carries_seeded_languages_and_password(): void
    {
        $chunk = $this->loadChunk();
        $this->injectChunkState($chunk);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $payload): bool {
                $rendered = $payload['job']['chunks'][0];
                $this->assertSame($this->jobId(self::BASE), $rendered['id']);
                $this->assertSame('jobpw', $rendered['password']);
                $this->assertSame('en-US', $rendered['source']);
                $this->assertSame('it-IT', $rendered['target']);
                return true;
            }));

        $this->controller->show();
    }

    // ─── chunk loading sanity (DAO seam used by validator closure) ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function seeded_job_is_loadable_and_not_deleted(): void
    {
        $chunk = $this->loadChunk();

        $this->assertSame($this->jobId(self::BASE), (int) $chunk->id);
        $this->assertFalse($chunk->isDeleted());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function wrong_password_does_not_load_the_seeded_job(): void
    {
        $job = (new JobDao())->getByIdAndPassword($this->jobId(self::BASE), 'wrong_pw_xyz');

        $this->assertNull($job);
    }

    // ─── registerValidators() wiring + onSuccess closure ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function registerValidators_appends_two_validators_and_closure_populates_state(): void
    {
        // Build a REAL controller (not the Testable subclass) so the production
        // registerValidators() body runs.
        $realRef = new ReflectionClass(ChunkController::class);
        $real    = $realRef->newInstanceWithoutConstructor();

        $realRef->getProperty('request')->setValue($real, new Request());

        $realRef->getProperty('params')->setValue($real, [
            'id_job'   => (string) $this->jobId(self::BASE),
            'password' => 'jobpw',
        ]);
        $realRef->getProperty('response')->setValue($real, $this->responseMock);

        $realRef->getMethod('registerValidators')->invoke($real);

        $validators = $realRef->getProperty('validators')->getValue($real);
        $this->assertIsArray($validators);
        $this->assertCount(2, $validators);

        $chunkValidator = $validators[1];
        $this->assertInstanceOf(ChunkPasswordValidator::class, $chunkValidator);

        // Drive the onSuccess closure: set the validator's chunk to a real
        // seeded JobStruct, then execute the registered callback.
        $chunk = $this->loadChunk();
        (new ReflectionClass(ChunkPasswordValidator::class))
            ->getProperty('chunk')
            ->setValue($chunkValidator, $chunk);

        $callbacks = (new ReflectionClass($chunkValidator))
            ->getProperty('_validationCallbacks')
            ->getValue($chunkValidator);
        $this->assertIsArray($callbacks);
        $this->assertNotEmpty($callbacks);

        $callback = $callbacks[0];
        $this->assertIsCallable($callback);
        $callback();

        // The closure assigned controller state from the validated chunk.
        $assignedChunk = $realRef->getProperty('chunk')->getValue($real);
        $this->assertInstanceOf(JobStruct::class, $assignedChunk);
        $this->assertSame($this->jobId(self::BASE), (int) $assignedChunk->id);

        $project = $realRef->getProperty('project')->getValue($real);
        $this->assertInstanceOf(ProjectStruct::class, $project);
        $this->assertSame($this->projectId(self::BASE), (int) $project->id);
    }
}
