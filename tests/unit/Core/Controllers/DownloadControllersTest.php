<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\DownloadAnalysisReportController;
use Controller\API\V2\DownloadController;
use Controller\API\V2\DownloadJobTMXController;
use Controller\API\V2\DownloadOriginalController;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\ActivityLog\ActivityLogStruct;
use Model\Exceptions\NotFoundException;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use ReflectionMethod;
use ReflectionProperty;
use Stomp\Transport\Message;
use TypeError;
use Utils\ActiveMQ\AMQHandler;
use Utils\ActiveMQ\WorkerClient;
use Utils\Logger\MatecatLogger;

/**
 * Shared suite for the AbstractDownloadController concrete subclasses.
 *
 * Reserved ID blocks (Playbook §4 — clean ONLY by reserved id; per-suite owner email):
 *   DownloadOriginalController       → base 9070000
 *   DownloadJobTMXController         → base 9070200
 *   DownloadAnalysisReportController → base 9070400
 *
 * Real-DB cases follow the GetWarningControllerTest pattern: clean-then-seed in
 * setUp(), clean in tearDown(), parent::tearDown() last line. Coverage for the
 * three controllers is bounded by their action methods terminating in
 * AbstractDownloadController::finalize()/exit and Activity::save() (ActiveMQ
 * enqueue), so suites assert the fully-reachable branches that run before any
 * exit / external-service boundary (see blocker notes in the plan deliverable).
 */
#[AllowMockObjectsWithoutExpectations]
#[CoversClass(DownloadController::class)]
#[CoversClass(DownloadOriginalController::class)]
#[CoversClass(DownloadJobTMXController::class)]
#[CoversClass(DownloadAnalysisReportController::class)]
class DownloadControllersTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int ORIGINAL_BASE = 9070000;
    private const int TMX_BASE       = 9070200;
    private const int ANALYSIS_BASE  = 9070400;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::ORIGINAL_BASE);
        $this->cleanFragments(self::TMX_BASE);
        $this->cleanFragments(self::ANALYSIS_BASE);

        // DownloadOriginal: a job that exists so the chunk-review fallback branch
        // is reached only via wrong password (no row), exercising the early return.
        $this->seedProject(self::ORIGINAL_BASE, $this->ownerEmail(self::ORIGINAL_BASE));
        $this->seedFile(self::ORIGINAL_BASE);
        $this->seedJob(self::ORIGINAL_BASE, $this->ownerEmail(self::ORIGINAL_BASE));
        // Review-password fallback row: review_password 'revpw' maps to the seeded
        // job (its job password 'jobpw'), so index() resolves the chunk via the
        // ChunkReviewDao branch and then proceeds into the file-storage lookup.
        $this->seedChunkReview(self::ORIGINAL_BASE, 'jobpw', 'revpw');

        // DownloadAnalysisReport: project + job so getProjectAndJobData / findById resolve.
        $this->seedProject(self::ANALYSIS_BASE, $this->ownerEmail(self::ANALYSIS_BASE));
        $this->seedFile(self::ANALYSIS_BASE);
        $this->seedJob(self::ANALYSIS_BASE, $this->ownerEmail(self::ANALYSIS_BASE));
    }

    protected function tearDown(): void
    {
        $this->cleanFragments(self::ORIGINAL_BASE);
        $this->cleanFragments(self::TMX_BASE);
        $this->cleanFragments(self::ANALYSIS_BASE);

        parent::tearDown();
    }

    // --- DownloadController::pathinfoString ---

    /** @throws \Throwable */
    #[Test]
    public function pathinfoStringReturnsBasename(): void
    {
        $controller = $this->createDownloadController();
        $method = new ReflectionMethod($controller, 'pathinfoString');

        $result = $method->invoke($controller, '/path/to/file.xlf', PATHINFO_BASENAME);
        $this->assertSame('file.xlf', $result);
    }

    /** @throws \Throwable */
    #[Test]
    public function pathinfoStringReturnsExtension(): void
    {
        $controller = $this->createDownloadController();
        $method = new ReflectionMethod($controller, 'pathinfoString');

        $result = $method->invoke($controller, '/path/to/file.sdlxliff', PATHINFO_EXTENSION);
        $this->assertSame('sdlxliff', $result);
    }

    /** @throws \Throwable */
    #[Test]
    public function pathinfoStringReturnsFilename(): void
    {
        $controller = $this->createDownloadController();
        $method = new ReflectionMethod($controller, 'pathinfoString');

        $result = $method->invoke($controller, '/path/to/document.docx', PATHINFO_FILENAME);
        $this->assertSame('document', $result);
    }

    /** @throws \Throwable */
    #[Test]
    public function pathinfoStringReturnsDirname(): void
    {
        $controller = $this->createDownloadController();
        $method = new ReflectionMethod($controller, 'pathinfoString');

        $result = $method->invoke($controller, '/path/to/document.docx', PATHINFO_DIRNAME);
        $this->assertSame('/path/to', $result);
    }

    /** @throws \Throwable */
    #[Test]
    public function pathinfoStringHandlesUnicodeFilenames(): void
    {
        $controller = $this->createDownloadController();
        $method = new ReflectionMethod($controller, 'pathinfoString');

        $result = $method->invoke($controller, '/path/日本語ファイル.txt', PATHINFO_EXTENSION);
        $this->assertSame('txt', $result);
    }

    // --- DownloadAnalysisReportController::validateTheRequest ---

    /** @throws \Throwable */
    #[Test]
    public function validateTheRequestThrowsInvalidArgumentExceptionWithCorrectMessageAndCode(): void
    {
        $controller = $this->createAnalysisReportController(['id_project' => '999999']);
        $method = new ReflectionMethod($controller, 'validateTheRequest');

        try {
            $method->invoke($controller);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            // Verify the bug fix: message is string, code is int
            $this->assertSame("Wrong Id project provided", $e->getMessage());
            $this->assertSame(-10, $e->getCode());
        }
    }

    /** @throws \Throwable */
    #[Test]
    public function validateTheRequestThrowsWhenIdProjectEmpty(): void
    {
        $controller = $this->createAnalysisReportController(['id_project' => '0']);
        $method = new ReflectionMethod($controller, 'validateTheRequest');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Id project not provided");

        $method->invoke($controller);
    }

    /** @throws \Throwable */
    #[Test]
    public function validateTheRequestThrowsWhenIdProjectZero(): void
    {
        // id_project = '0' is treated as empty
        $controller = $this->createAnalysisReportController(['id_project' => '0']);
        $method = new ReflectionMethod($controller, 'validateTheRequest');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Id project not provided");

        $method->invoke($controller);
    }

    /** @throws \Throwable */
    #[Test]
    public function validateTheRequestReturnsExpectedShapeForSeededProject(): void
    {
        $projectId = $this->projectId(self::ANALYSIS_BASE);
        $controller = $this->createAnalysisReportController([
            'id_project'    => (string)$projectId,
            'password'      => 'projpw',
            'download_type' => 'all',
        ]);
        $method = new ReflectionMethod($controller, 'validateTheRequest');

        /** @var array<string, mixed> $result */
        $result = $method->invoke($controller);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('project', $result);
        $this->assertArrayHasKey('id_project', $result);
        $this->assertArrayHasKey('password', $result);
        $this->assertArrayHasKey('download_type', $result);
        $this->assertSame((string)$projectId, $result['id_project']);
        $this->assertSame('all', $result['download_type']);
        // concrete payload value: the resolved ProjectStruct carries the seeded id
        $this->assertSame($projectId, (int)$result['project']->id);
    }

    /** @throws \Throwable */
    #[Test]
    #[WithoutErrorHandler]
    public function downloadActionResolvesProjectJobDataAndComposesAnalysisOutput(): void
    {
        // download() resolves the project+job data from the seeded rows, sets the
        // controller's id_job from that data, loads the feature set, then assembles
        // the analysis output via XTRFStatus + composeZip. With no analysis word-count
        // rows seeded the assembled zip is empty, so composeZip raises the documented
        // "Failed to read zip file" Exception — after the real data-resolution path
        // (controller lines 44-64) has executed. We assert the concrete resolved
        // id_job, proving the project/job lookup returned the seeded job.
        $projectId = $this->projectId(self::ANALYSIS_BASE);
        $jobId     = $this->jobId(self::ANALYSIS_BASE);

        $controller = $this->createAnalysisReportController([
            'id_project'    => (string)$projectId,
            'password'      => 'projpw',
            'download_type' => 'all',
        ]);

        $user = new UserStruct();
        $user->uid   = 1;
        $user->email = $this->ownerEmail(self::ANALYSIS_BASE);
        $this->setControllerProp($controller, 'user', $user);

        try {
            $controller->download();
            $this->fail('Expected the composeZip boundary Exception was not thrown');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Failed to read zip file', $e->getMessage());
        }

        // concrete payload value: id_job was set from the resolved getProjectAndJobData row
        $idJobProp = new ReflectionProperty(\Controller\Abstracts\AbstractDownloadController::class, 'id_job');
        $this->assertSame($jobId, $idJobProp->getValue($controller));
    }

    /** @throws \Throwable */
    #[Test]
    public function analysisReportRegisterValidatorsAppendsTwoValidators(): void
    {
        // After the afterConstruct -> registerValidators migration, the two
        // validators are registered through the new hook. ProjectPasswordValidator
        // reads id_project/password from request params at construction time.
        $_GET['id_project'] = (string)$this->projectId(self::ANALYSIS_BASE);
        $_GET['password']   = 'projpw';
        $request  = Request::createFromGlobals();
        $response = new Response();

        $controller = new class ($request, $response) extends DownloadAnalysisReportController {
            protected bool $useSession = false;

            protected function identifyUser(?bool $useSession = true): void
            {
                $this->userIsLogged = false;
            }
        };

        unset($_GET['id_project'], $_GET['password']);

        $prop = new ReflectionProperty(\Controller\Abstracts\KleinController::class, 'validators');
        /** @var array<int, mixed> $validators */
        $validators = $prop->getValue($controller);

        $this->assertCount(2, $validators);
        $this->assertInstanceOf(\Controller\API\Commons\Validators\LoginValidator::class, $validators[0]);
        $this->assertInstanceOf(\Controller\API\Commons\Validators\ProjectPasswordValidator::class, $validators[1]);
    }

    // --- DownloadOriginalController::index ---

    /** @throws \Throwable */
    #[Test]
    public function downloadOriginalControllerIdJobPropertyIsTypedInt(): void
    {
        $controller = $this->createOriginalController();
        $prop = new ReflectionProperty($controller, 'id_job');
        $prop->setValue($controller, 42);

        $this->assertIsInt($prop->getValue($controller));
    }

    /** @throws \Throwable */
    #[Test]
    public function downloadOriginalIndexReturnsEarlyOnWrongPassword(): void
    {
        // Wrong password + no review row -> the controller logs and returns
        // before touching the filesystem. Asserts the debug message payload.
        $jobId = $this->jobId(self::ORIGINAL_BASE);
        $controller = $this->createOriginalController([
            'id_job'   => (string)$jobId,
            'password' => 'definitely_wrong_pw',
        ]);

        $logger = $this->createMock(MatecatLogger::class);
        $captured = null;
        $logger->expects($this->once())
            ->method('debug')
            ->with($this->callback(function (string $msg) use (&$captured): bool {
                $captured = $msg;
                return true;
            }));
        $this->setControllerProp($controller, 'logger', $logger);

        $controller->index();

        $this->assertIsString($captured);
        $this->assertStringContainsString('wrong password provided for download', $captured);
    }

    /** @throws \Throwable */
    #[Test]
    #[WithoutErrorHandler]
    public function downloadOriginalIndexResolvesChunkViaReviewPasswordThenFailsOnMissingFiles(): void
    {
        // A review_password ('revpw') that does NOT match the job password makes the
        // first getByIdAndPassword() return null, driving the ChunkReviewDao fallback
        // branch (getChunk() resolves the real job). With no files_job row seeded, the
        // subsequent file-storage lookup yields no files, so the controller raises a
        // clear Exception via the missing-files guard — exercising the fallback +
        // storage resolution path (controller lines 64,68,69) before the boundary.
        $jobId = $this->jobId(self::ORIGINAL_BASE);
        $controller = $this->createOriginalController([
            'id_job'   => (string)$jobId,
            'password' => 'revpw',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No files found for job');

        $controller->index();
    }

    // --- DownloadJobTMXController ---

    /** @throws \Throwable */
    #[Test]
    public function downloadJobTMXControllerErrorsPropertyIsArray(): void
    {
        $controller = $this->createTMXController();
        $prop = new ReflectionProperty($controller, 'errors');
        $prop->setValue($controller, []);

        $this->assertIsArray($prop->getValue($controller));
    }

    /** @throws \Throwable */
    #[Test]
    public function downloadJobTMXIndexThrowsNotFoundForUnknownJob(): void
    {
        // Valid (non-empty) id_job + password skips the error/exit block and
        // reaches getByIdAndPasswordOrFail, which throws for a nonexistent job.
        $controller = $this->createTMXController([
            'id_job'   => (string)$this->jobId(self::TMX_BASE), // not seeded
            'password' => 'some_password',
        ]);

        $this->expectException(NotFoundException::class);

        $controller->index();
    }

    /** @throws \Throwable */
    #[Test]
    public function downloadJobTMXSaveActivityEnqueuesActivityLogWithJobAndProjectIds(): void
    {
        // _saveActivity() builds an ActivityLogStruct from jobID/jobInfo/user and
        // enqueues it via Activity::save -> WorkerClient::enqueue. Wire a mocked
        // AMQHandler + initialised queue map so the enqueue path runs fully offline,
        // and capture the published message to assert the concrete job/project ids.
        $jobId     = $this->jobId(self::TMX_BASE);
        $projectId = $this->projectId(self::TMX_BASE);

        $controller = $this->createTMXController();

        $jobInfo = new JobStruct();
        $jobInfo->id            = $jobId;
        $jobInfo->id_project    = $projectId;
        // jobID is private on DownloadJobTMXController; reflect on the declaring class.
        $jobIdProp = new ReflectionProperty(DownloadJobTMXController::class, 'jobID');
        $jobIdProp->setValue($controller, $jobId);
        $this->setControllerProp($controller, 'jobInfo', $jobInfo);

        $savedHandler = WorkerClient::$_HANDLER;
        $savedQueues  = WorkerClient::$_QUEUES;

        $captured = null;
        $handlerMock = $this->createMock(AMQHandler::class);
        $handlerMock->expects($this->once())
            ->method('publishToQueues')
            ->with(
                $this->anything(),
                $this->callback(function (Message $message) use (&$captured): bool {
                    $captured = (string)$message->getBody();
                    return true;
                })
            );

        try {
            WorkerClient::init($handlerMock);

            $method = new ReflectionMethod($controller, '_saveActivity');
            $method->invoke($controller);

            $this->assertIsString($captured);
            $this->assertStringContainsString('"id_job":' . $jobId, $captured);
            $this->assertStringContainsString('"id_project":' . $projectId, $captured);
            $this->assertStringContainsString('"action":' . ActivityLogStruct::DOWNLOAD_JOB_TMX, $captured);
        } finally {
            WorkerClient::$_HANDLER = $savedHandler;
            WorkerClient::$_QUEUES  = $savedQueues;
        }
    }

    // --- Helper factories ---

    /** @throws \Throwable */
    private function setControllerProp(object $controller, string $name, mixed $value): void
    {
        $prop = new ReflectionProperty($controller, $name);
        $prop->setValue($controller, $value);
    }

    /** @throws \Throwable */
    private function createDownloadController(): DownloadController
    {
        $request = Request::createFromGlobals();
        $response = new Response();

        return new class ($request, $response) extends DownloadController {
            protected bool $useSession = false;

            protected function identifyUser(?bool $useSession = true): void
            {
                $this->userIsLogged = false;
            }
        };
    }

    /**
     * @param array<string, string> $params
     * @throws \Throwable
     */
    private function createAnalysisReportController(array $params = []): DownloadAnalysisReportController
    {
        foreach ($params as $key => $value) {
            $_GET[$key] = $value;
        }
        $request = Request::createFromGlobals();
        $response = new Response();

        $controller = new class ($request, $response) extends DownloadAnalysisReportController {
            protected bool $useSession = false;

            protected function registerValidators(): void
            {
                // Skip validators for unit testing
            }

            protected function identifyUser(?bool $useSession = true): void
            {
                $this->userIsLogged = false;
            }
        };

        foreach ($params as $key => $value) {
            unset($_GET[$key]);
        }

        return $controller;
    }

    /**
     * @param array<string, string> $params
     * @throws \Throwable
     */
    private function createOriginalController(array $params = []): DownloadOriginalController
    {
        foreach ($params as $key => $value) {
            $_GET[$key] = $value;
        }
        $request = Request::createFromGlobals();
        $response = new Response();

        $controller = new class ($request, $response) extends DownloadOriginalController {
            protected bool $useSession = false;

            protected function identifyUser(?bool $useSession = true): void
            {
                $this->userIsLogged = false;
            }
        };

        foreach ($params as $key => $value) {
            unset($_GET[$key]);
        }

        $user = new UserStruct();
        $user->uid = 1;
        $user->email = 'test@example.org';
        $this->setControllerProp($controller, 'user', $user);
        $this->setControllerProp($controller, 'featureSet', new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));

        return $controller;
    }

    /**
     * @param array<string, string> $params
     * @throws \Throwable
     */
    private function createTMXController(array $params = []): DownloadJobTMXController
    {
        foreach ($params as $key => $value) {
            $_GET[$key] = $value;
        }
        $request = Request::createFromGlobals();
        $response = new Response();

        $controller = new class ($request, $response) extends DownloadJobTMXController {
            protected bool $useSession = false;

            protected function identifyUser(?bool $useSession = true): void
            {
                $this->userIsLogged = false;
            }
        };

        foreach ($params as $key => $value) {
            unset($_GET[$key]);
        }

        $user = new UserStruct();
        $user->uid = 1;
        $user->email = 'test@example.org';
        $this->setControllerProp($controller, 'user', $user);

        return $controller;
    }
}
