<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\ProjectAccessValidator;
use Controller\API\V3\FileInfoController;
use InvalidArgumentException;
use Klein\HttpStatus;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\FeaturesBase\FeatureSet;
use Model\Files\FilesInfoUtility;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use ReflectionClass;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

class TestableFileInfoController extends FileInfoController
{
    public FilesInfoUtility $stubUtility;

    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }

    protected function registerValidators(): void
    {
    }

    protected function createFilesInfoUtility(JobStruct $chunk): FilesInfoUtility
    {
        return $this->stubUtility;
    }
}

class ValidatorTestableFileInfoController extends FileInfoController
{
    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }
}

class FileInfoControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int    BASE         = 9_000_000 + (73 * 1000);
    private const string JOB_PASSWORD = 'jobpw';

    private ReflectionClass $reflector;
    private TestableFileInfoController $controller;
    private Stub&FilesInfoUtility $utilityStub;

    protected function setUp(): void
    {
        parent::setUp();

        AppConfig::$SKIP_SQL_CACHE = true;

        $this->controller = new TestableFileInfoController();
        $this->reflector  = new ReflectionClass(FileInfoController::class);

        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createStub(MatecatLogger::class));

        $chunk = $this->createStub(JobStruct::class);
        $chunk->method('isDeleted')->willReturn(false);
        $this->reflector->getProperty('chunk')->setValue($this->controller, $chunk);

        $this->utilityStub = $this->createStub(FilesInfoUtility::class);
        $this->controller->stubUtility = $this->utilityStub;
    }

    protected function tearDown(): void
    {
        AppConfig::$SKIP_SQL_CACHE = false;
        // Id-scoped and safe for the stub-only tests that seed nothing.
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    /**
     * @param array<string, string|null> $params
     */
    private function setRequest(array $params = []): void
    {
        $request = $this->createStub(Request::class);
        $request->method('param')->willReturnCallback(static fn(string $key, $default = null) => $params[$key] ?? $default);
        $this->reflector->getProperty('request')->setValue($this->controller, $request);
    }

    private function responseMock(): Response&MockObject
    {
        $mock = $this->createMock(Response::class);
        $mock->method('status')->willReturn($this->createStub(HttpStatus::class));
        $mock->method('json')->willReturnSelf();
        $this->reflector->getProperty('response')->setValue($this->controller, $mock);
        return $mock;
    }

    private function responseStub(): void
    {
        $stub = $this->createStub(Response::class);
        $stub->method('status')->willReturn($this->createStub(HttpStatus::class));
        $this->reflector->getProperty('response')->setValue($this->controller, $stub);
    }

    // ── getInfo ──────────────────────────────────────────────────────────

    #[Test]
    public function getInfo_returns_json(): void
    {
        $this->setRequest();
        $this->utilityStub->method('getInfo')->willReturn(['files' => []]);

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['files' => []]);

        $this->controller->getInfo();
    }

    // ── getInstructions ──────────────────────────────────────────────────

    #[Test]
    public function getInstructions_returns_json(): void
    {
        $this->setRequest(['id_file' => '35']);
        $this->utilityStub->method('getInstructions')->willReturn(['instructions' => 'do this']);

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['instructions' => 'do this']);

        $this->controller->getInstructions();
    }

    #[Test]
    public function getInstructions_throws_404_when_empty(): void
    {
        $this->setRequest(['id_file' => '35']);
        $this->utilityStub->method('getInstructions')->willReturn(null);
        $this->responseStub();

        $this->expectException(NotFoundException::class);

        $this->controller->getInstructions();
    }

    // ── getInstructionsByFilePartsId ─────────────────────────────────────

    #[Test]
    public function getInstructionsByFilePartsId_returns_json(): void
    {
        $this->setRequest(['id_file' => '35', 'id_file_parts' => '7']);
        $this->utilityStub->method('getInstructions')->willReturn(['instructions' => 'part']);

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['instructions' => 'part']);

        $this->controller->getInstructionsByFilePartsId();
    }

    #[Test]
    public function getInstructionsByFilePartsId_throws_404_when_empty(): void
    {
        $this->setRequest(['id_file' => '35', 'id_file_parts' => '7']);
        $this->utilityStub->method('getInstructions')->willReturn(null);
        $this->responseStub();

        $this->expectException(NotFoundException::class);

        $this->controller->getInstructionsByFilePartsId();
    }

    // ── setInstructions ──────────────────────────────────────────────────

    private function setFeatureSet(): void
    {
        $this->reflector->getProperty('featureSet')->setValue($this->controller, $this->createStub(FeatureSet::class));
    }

    #[Test]
    public function setInstructions_returns_success(): void
    {
        $this->setRequest(['id_file' => '35', 'instructions' => 'do this']);
        $this->setFeatureSet();
        $this->utilityStub->method('setInstructions')->willReturn(true);

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['success' => true]);

        $this->controller->setInstructions();
    }

    #[Test]
    public function setInstructions_throws_when_instructions_empty(): void
    {
        $this->setRequest(['id_file' => '35', 'instructions' => null]);
        $this->setFeatureSet();
        $this->responseStub();

        $this->expectException(InvalidArgumentException::class);

        $this->controller->setInstructions();
    }

    #[Test]
    public function setInstructions_throws_404_when_not_saved(): void
    {
        $this->setRequest(['id_file' => '35', 'instructions' => 'do this']);
        $this->setFeatureSet();
        $this->utilityStub->method('setInstructions')->willReturn(false);
        $this->responseStub();

        $this->expectException(NotFoundException::class);

        $this->controller->setInstructions();
    }

    // ── registerValidators ───────────────────────────────────────────────

    #[Test]
    public function registerValidators_registers_login_and_chunk_validators(): void
    {
        $controller = new ValidatorTestableFileInfoController();
        $ref = new ReflectionClass(FileInfoController::class);

        $ref->getProperty('request')->setValue($controller, $this->createStub(Request::class));
        $ref->getProperty('response')->setValue($controller, $this->createStub(Response::class));
        $ref->getProperty('params')->setValue($controller, ['id_job' => '1', 'password' => 'abc']);

        $ref->getMethod('registerValidators')->invoke($controller);

        $validators = $ref->getProperty('validators')->getValue($controller);
        self::assertCount(2, $validators);
    }

    /**
     * The ProjectAccessValidator is built inside the ChunkPasswordValidator's onSuccess closure, so
     * registering the validators is not enough to reach it — the chunk password has to actually
     * validate against a real job. Hence the seeded rows: this is the only path that runs the line
     * threading the acting user into the access check.
     */
    #[Test]
    public function registerValidators_appends_a_project_access_validator_for_the_acting_user(): void
    {
        $owner = $this->ownerEmail(self::BASE);
        $this->cleanFragments(self::BASE);
        $this->seedProject(self::BASE, $owner);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $owner, self::JOB_PASSWORD);

        $controller = new ValidatorTestableFileInfoController();
        $ref        = new ReflectionClass(FileInfoController::class);
        $params     = ['id_job' => (string)$this->jobId(self::BASE), 'password' => self::JOB_PASSWORD];

        $ref->getProperty('request')->setValue($controller, new Request(
            $params,
            [],
            [],
            ['REQUEST_URI' => '/api/v3/file-info', 'REQUEST_METHOD' => 'GET']
        ));
        $ref->getProperty('response')->setValue($controller, $this->createStub(Response::class));
        $ref->getProperty('params')->setValue($controller, $params);
        $ref->getProperty('database')->setValue($controller, obtainTestDatabase());
        // $user is typed with no default. LoginValidator, appended before this closure runs, always
        // initializes it in the real pipeline; invoking the closure directly has to stand in for that.
        $ref->getProperty('user')->setValue($controller, new UserStruct());

        $ref->getMethod('registerValidators')->invoke($controller);

        $validatorsProp = $ref->getProperty('validators');
        $validators     = $validatorsProp->getValue($controller);

        self::assertCount(2, $validators);
        self::assertInstanceOf(LoginValidator::class, $validators[0]);
        self::assertInstanceOf(ChunkPasswordValidator::class, $validators[1]);

        $validators[1]->validate();

        $chunk = $ref->getProperty('chunk')->getValue($controller);
        self::assertInstanceOf(JobStruct::class, $chunk);
        self::assertSame($this->jobId(self::BASE), (int)$chunk->id);

        $project = $ref->getProperty('project')->getValue($controller);
        self::assertInstanceOf(ProjectStruct::class, $project);
        self::assertSame($this->projectId(self::BASE), (int)$project->id);

        $after = $validatorsProp->getValue($controller);
        self::assertCount(3, $after);
        self::assertInstanceOf(ProjectAccessValidator::class, $after[2]);
    }
}
