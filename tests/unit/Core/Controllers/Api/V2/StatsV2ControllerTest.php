<?php

namespace Matecat\Core\Controllers\Api\V2;

use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\V2\StatsController;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Throwable;

/**
 * Real-DB suite for {@see StatsController} (API/V2).
 *
 * Reserved ID block: base = 9_068_000 (range 9068000-9068099).
 *   base+1 project, base+2 job. Clean ONLY by reserved id; owner email
 *   ctrltest_9068000@example.org.
 *
 * stats() is exercised with a chunk (JobStruct) injected directly, seeded
 * with a project row whose status_analysis is DONE so analysisComplete()
 * resolves true; no segment_translations rows are seeded, so
 * CatUtils::getFastStatsForJob() short-circuits its performance-estimation
 * branch (getLast10TranslatedSegmentIDsInLastHour() returns empty).
 *
 * registerValidators() is exercised end-to-end through a real Klein Request
 * carrying valid id_job/password params, running LoginValidator and
 * ChunkPasswordValidator's real validate() so the onSuccess closure that
 * assigns $this->chunk actually executes.
 */
class TestableStatsV2Controller extends StatsController
{
    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }
}

#[AllowMockObjectsWithoutExpectations]
class StatsV2ControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_068_000;
    private const string JOB_PASSWORD = 'jobpw';

    /** @var ReflectionClass<StatsController> */
    private ReflectionClass $reflector;
    private TestableStatsV2Controller $controller;
    private Response&MockObject $responseMock;

    /**
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);

        $owner = $this->ownerEmail(self::BASE);
        $this->seedProject(self::BASE, $owner);
        $this->seedJob(self::BASE, $owner, self::JOB_PASSWORD);

        $this->controller = new TestableStatsV2Controller();
        $this->reflector  = new ReflectionClass(StatsController::class);

        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('database', obtainTestDatabase());
    }

    /**
     * @throws Throwable
     */
    protected function tearDown(): void
    {
        $this->cleanFragments(self::BASE);
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

    // ─── stats() ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function stats_returns_word_count_stats_with_analysis_complete_true(): void
    {
        $chunk = (new JobDao(obtainTestDatabase()))->getByIdAndPassword($this->jobId(self::BASE), self::JOB_PASSWORD);
        $this->assertInstanceOf(JobStruct::class, $chunk);

        $this->setProp('chunk', $chunk);
        $this->setProp('response', $this->responseMock);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $payload) use (&$captured): bool {
                $captured = $payload;
                return true;
            }));

        $this->controller->stats();

        $this->assertIsArray($captured);
        $this->assertArrayHasKey('analysis_complete', $captured);
        $this->assertTrue($captured['analysis_complete']);
    }

    // ─── registerValidators() ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function registerValidators_resolves_chunk_via_valid_job_and_password_request(): void
    {
        $this->setProp('request', new Request([
            'id_job'   => (string)$this->jobId(self::BASE),
            'password' => self::JOB_PASSWORD,
        ]));

        $this->reflector->getProperty('userIsLogged')->setValue($this->controller, true);

        $this->reflector->getMethod('registerValidators')->invoke($this->controller);

        /** @var list<object> $validators */
        $validators = $this->reflector->getProperty('validators')->getValue($this->controller);
        $this->assertCount(2, $validators);
        $this->assertInstanceOf(LoginValidator::class, $validators[0]);
        $this->assertInstanceOf(ChunkPasswordValidator::class, $validators[1]);

        $this->reflector->getMethod('validateRequest')->invoke($this->controller);

        $chunk = $this->reflector->getProperty('chunk')->getValue($this->controller);
        $this->assertInstanceOf(JobStruct::class, $chunk);
        $this->assertSame($this->jobId(self::BASE), $chunk->id);
    }
}
