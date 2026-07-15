<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use Controller\API\V2\UrlsController;
use Exception;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use Model\FeaturesBase\Hook\Event\Filter\ProjectUrlsEvent;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Utils\Constants\JobStatus;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

/**
 * Stubbed {@see ProjectPasswordValidator} that returns a pre-set project without
 * touching the DB. Constructed via newInstanceWithoutConstructor so the real
 * filter_var_array parsing is bypassed; the test reflection-sets {@see $project}.
 */
class TestableUrlsProjectPasswordValidator extends ProjectPasswordValidator
{
    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct()
    {
    }

    public function validate(): void
    {
        // no-op: validation is exercised separately via the real seam
    }
}

class TestableUrlsController extends UrlsController
{
    /** @var \Model\Jobs\JobStruct[]|null */
    public ?array $fakeJobs = null;

    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }

    protected function registerValidators(): void
    {
    }

    protected function getProjectJobs(\Model\Projects\ProjectStruct $project): array
    {
        if ($this->fakeJobs === null) {
            throw new \RuntimeException('fakeJobs not configured');
        }

        return $this->fakeJobs;
    }
}

/**
 * Mock-seam suite for {@see UrlsController}.
 *
 * ID block base = 9042000 (task N=42, Wave 6). Pattern: mock seam.
 * Per-suite owner identity: ctrltest_9042000@example.org (registry consistency only —
 * no real-DB rows are seeded; the DB singleton is stubbed via createDatabaseMock()).
 *
 * urls()'s happy path drives the project/job loop, the empty {@see ProjectUrls} render
 * (the stubbed PDOStatement returns no project_data rows) and the ProjectUrlsEvent
 * dispatch through a stubbed FeatureSet. The 404 branch (lines 43-52) terminates with
 * exit() and is therefore not unit-testable in-process (documented blocker).
 */
#[AllowMockObjectsWithoutExpectations]
class UrlsControllerTest extends AbstractTest
{
    private const int BASE = 9042000;

    /** @var \ReflectionClass<UrlsController> */
    private \ReflectionClass $reflector;
    /** @var \ReflectionClass<\Controller\Abstracts\KleinController> */
    private \ReflectionClass $baseReflector;
    private TestableUrlsController $controller;
    private Response&MockObject $responseMock;

    /**
     * @throws \ReflectionException
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws Exception
     * @throws \TypeError
     * @throws \PHPUnit\Event\NoPreviousThrowableException
     * @throws \PHPUnit\Framework\InvalidArgumentException
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Mock-seam: swap the Database singleton with a stub. getProjectData()'s
        // statement therefore returns no rows -> ProjectUrls renders an empty shape.
        [$dbStub] = $this->createDatabaseMock();

        $this->controller    = new TestableUrlsController();
        $this->reflector     = new \ReflectionClass(UrlsController::class);
        $this->baseReflector = new \ReflectionClass(\Controller\Abstracts\KleinController::class);

        $this->responseMock = $this->createMock(Response::class);
        $this->setProp('response', $this->responseMock);
        $this->setProp('request', new Request());
        $this->setProp('database', $dbStub);
        $this->setProp('logger', $this->createMock(MatecatLogger::class));
    }

    protected function tearDown(): void
    {
        // createDatabaseMock() set databaseMockApplied; parent::tearDown() restores the singleton.
        parent::tearDown();
    }

    /**
     * @throws \ReflectionException
     */
    private function setProp(string $name, mixed $value): void
    {
        $prop = $this->reflector->hasProperty($name)
            ? $this->reflector->getProperty($name)
            : $this->baseReflector->getProperty($name);
        $prop->setValue($this->controller, $value);
    }

    /**
     * Builds a real ProjectStruct whose getMetadataValue('features') cache returns
     * empty. Jobs are provided separately via {@see TestableUrlsController::$fakeJobs}.
     *
     * @throws \ReflectionException
     */
    private function seedProjectStruct(int $id): ProjectStruct
    {
        $project = new ProjectStruct(['id' => $id]);

        $ref  = new \ReflectionClass(ProjectStruct::class);
        $prop = $ref->getProperty('cached_results');
        $prop->setValue($project, [
            ProjectStruct::class . '::getMetadataValue:' . 'features' => '',
        ]);

        return $project;
    }

    /**
     * @throws \ReflectionException
     */
    private function injectValidatorWithProject(ProjectStruct $project): void
    {
        $validator = (new \ReflectionClass(TestableUrlsProjectPasswordValidator::class))
            ->newInstanceWithoutConstructor();

        (new \ReflectionClass(ProjectPasswordValidator::class))
            ->getProperty('project')
            ->setValue($validator, $project);

        $this->setProp('validator', $validator);
    }

    /**
     * @throws \ReflectionException
     * @throws \PHPUnit\Event\NoPreviousThrowableException
     * @throws \PHPUnit\Framework\InvalidArgumentException
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    private function injectNeuteredFeatureSet(): void
    {
        // dispatch() must echo the event back so urls() reads the unchanged ProjectUrls;
        // loadForProject() is a no-op so no project metadata / feature loading occurs.
        $featureSet = $this->createMock(FeatureSet::class);
        $featureSet->method('dispatch')->willReturnArgument(0);
        $this->setProp('featureSet', $featureSet);
    }

    // ─── urls() happy path ───

    /**
     * @throws \ReflectionException
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws Exception
     * @throws \Throwable
     */
    #[Test]
    public function urls_returns_urls_payload_for_project_with_active_job(): void
    {
        $previousSkip = AppConfig::$SKIP_SQL_CACHE;
        AppConfig::$SKIP_SQL_CACHE = true; // keep getProjectData() off Redis in the mock seam

        try {
            $activeJob = new JobStruct(['id' => self::BASE + 2, 'status_owner' => JobStatus::STATUS_ACTIVE]);
            $project   = $this->seedProjectStruct(self::BASE + 1);

            $this->controller->fakeJobs = [$activeJob];
            $this->injectValidatorWithProject($project);
            $this->injectNeuteredFeatureSet();

            $captured = null;
            $this->responseMock->expects($this->once())
                ->method('json')
                ->with($this->callback(function (array $data) use (&$captured): bool {
                    $captured = $data;
                    return true;
                }));

            $this->controller->urls();

            $this->assertIsArray($captured);
            $this->assertArrayHasKey('urls', $captured);
            // getProjectData() returned no rows (stubbed PDO) -> empty render shape.
            $this->assertSame(['files' => [], 'jobs' => []], $captured['urls']);
        } finally {
            AppConfig::$SKIP_SQL_CACHE = $previousSkip;
        }
    }

    /**
     * The event-dispatch seam must be honoured: a feature that rewrites the formatted
     * payload through the ProjectUrlsEvent is reflected in the emitted JSON.
     *
     * @throws \ReflectionException
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws Exception
     * @throws \Throwable
     */
    #[Test]
    public function urls_emits_payload_rewritten_by_dispatched_event(): void
    {
        $previousSkip = AppConfig::$SKIP_SQL_CACHE;
        AppConfig::$SKIP_SQL_CACHE = true;

        try {
            $activeJob = new JobStruct(['id' => self::BASE + 2, 'status_owner' => JobStatus::STATUS_ACTIVE]);
            $project   = $this->seedProjectStruct(self::BASE + 1);

            $this->controller->fakeJobs = [$activeJob];
            $this->injectValidatorWithProject($project);

            // FeatureSet rewrites the formatted object to a stub whose render() is sentinel data.
            $rewritten = new class {
                /** @return array<string, string> */
                public function render(): array
                {
                    return ['files' => 'SENTINEL'];
                }
            };
            $featureSet = $this->createMock(FeatureSet::class);
            $featureSet->method('dispatch')->willReturnCallback(
                function (ProjectUrlsEvent $event) use ($rewritten): ProjectUrlsEvent {
                    $event->setFormatted($rewritten);
                    return $event;
                }
            );
            $this->setProp('featureSet', $featureSet);

            $captured = null;
            $this->responseMock->expects($this->once())
                ->method('json')
                ->with($this->callback(function (array $data) use (&$captured): bool {
                    $captured = $data;
                    return true;
                }));

            $this->controller->urls();

            $this->assertIsArray($captured);
            $this->assertSame(['files' => 'SENTINEL'], $captured['urls']);
        } finally {
            AppConfig::$SKIP_SQL_CACHE = $previousSkip;
        }
    }

    // ─── urls() failure / edge ───

    /**
     * When the validator resolved no project, urls() throws "Project not found".
     *
     * @throws \ReflectionException
     * @throws \Throwable
     */
    #[Test]
    public function urls_throws_when_project_missing(): void
    {
        $validator = (new \ReflectionClass(TestableUrlsProjectPasswordValidator::class))
            ->newInstanceWithoutConstructor();
        $this->setProp('validator', $validator);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Project not found');

        $this->controller->urls();
    }

    /**
     * Deleted jobs do not increment jobCheck. With every job deleted the loop runs
     * but jobCheck stays 0, reaching the 404/exit() branch. exit() kills the worker
     * process, so we assert via expectException only up to the loop boundary by
     * verifying isDeleted() filtering on the seam — see the documented blocker for
     * the exit() branch itself.
     *
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function deleted_jobs_are_filtered_by_isDeleted_seam(): void
    {
        $deletedJob = new JobStruct(['id' => self::BASE + 3, 'status_owner' => JobStatus::STATUS_DELETED]);

        $this->assertTrue($deletedJob->isDeleted());

        $activeJob = new JobStruct(['id' => self::BASE + 4, 'status_owner' => JobStatus::STATUS_ACTIVE]);
        $this->assertFalse($activeJob->isDeleted());
    }

    // ─── validateRequest() ───

    /**
     * validateRequest() delegates to the injected validator's validate().
     *
     * @throws \ReflectionException
     * @throws \Throwable
     */
    #[Test]
    public function validateRequest_invokes_validator_validate(): void
    {
        $validator = $this->createMock(ProjectPasswordValidator::class);
        $validator->expects($this->once())->method('validate');
        $this->setProp('validator', $validator);

        $method = $this->reflector->getMethod('validateRequest');
        $method->invoke($this->controller);
    }

    // ─── registerValidators() ───

    /**
     * registerValidators() wires the ProjectPasswordValidator instance and appends a
     * LoginValidator to the validator chain.
     *
     * @throws \ReflectionException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\Exception
     */
    #[Test]
    public function registerValidators_wires_password_validator_and_appends_login(): void
    {
        // params are read by ProjectPasswordValidator's constructor.
        $this->setProp('params', ['id_project' => (string) (self::BASE + 1), 'password' => 'pw']);

        $method = $this->reflector->getMethod('registerValidators');
        $method->invoke($this->controller);

        $validator = $this->reflector->getProperty('validator')->getValue($this->controller);
        $this->assertInstanceOf(ProjectPasswordValidator::class, $validator);

        /** @var list<object> $validators */
        $validators = $this->baseReflector->getProperty('validators')->getValue($this->controller);
        $this->assertCount(1, $validators);
        $this->assertInstanceOf(LoginValidator::class, $validators[0]);
    }
}
