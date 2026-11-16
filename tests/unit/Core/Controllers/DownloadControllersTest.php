<?php

namespace Matecat\Core\Controllers;

use Aws\CommandInterface;
use Aws\Result;
use Controller\API\App\DownloadAnalysisReportController;
use Controller\API\V2\DownloadController;
use Controller\API\V2\DownloadJobTMXController;
use Controller\API\V2\DownloadOriginalController;
use Controller\Exceptions\RenderTerminatedException;
use Controller\Traits\KleinResponseFileStream;
use InvalidArgumentException;
use Klein\App;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\ActivityLog\ActivityLogStruct;
use Model\Exceptions\NotFoundException;
use Model\FeaturesBase\FeatureSet;
use Model\FilesStorage\AbstractFilesStorage;
use Model\FilesStorage\FsFilesStorage;
use Model\FilesStorage\S3FilesStorage;
use Matecat\SimpleS3\Client as S3Client;
use Model\Jobs\JobStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use SplTempFileObject;
use Stomp\Transport\Message;
use Utils\Registry\AppConfig;
use TypeError;
use ZipArchive;
use Utils\ActiveMQ\AMQHandler;
use Utils\ActiveMQ\WorkerClient;
use Utils\TMS\TMSService;

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
    // Dedicated block for the TMX export happy-path (seeded/cleaned inside the test
    // only, so the "unknown job" NotFound case at TMX_BASE stays unseeded/hermetic).
    private const int TMX_EXPORT_BASE = 9071000;

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
            $this->assertSame("Wrong ID project provided", $e->getMessage());
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
        // rows seeded the zip has no entries, so composeZip fails, and the work folder
        // is deleted anyway. We assert the concrete resolved id_job, proving the
        // project/job lookup returned the seeded job.
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
            $this->assertStringContainsString('every file is empty', $e->getMessage());
        }
        $this->assertDirectoryDoesNotExist(\Utils\Registry\AppConfig::$TMP_DOWNLOAD . '/' . $jobId);

        // concrete payload value: id_job was set from the resolved getProjectAndJobData row
        $idJobProp = new ReflectionProperty(\Controller\Abstracts\AbstractDownloadController::class, 'id_job');
        $this->assertSame($jobId, $idJobProp->getValue($controller));
    }

    /**
     * An analysed project: the report counts the words of each chunk, zips one report per chunk
     * and sends it.
     *
     * @throws \Throwable
     */
    #[Test]
    public function downloadActionSendsTheAnalysisReportOfEachChunkAsAZip(): void
    {
        $projectId = $this->projectId(self::ANALYSIS_BASE);
        $jobId     = $this->jobId(self::ANALYSIS_BASE);
        $this->seedSegment(self::ANALYSIS_BASE);
        $this->seedSegmentTranslation(self::ANALYSIS_BASE);
        $controller = $this->createAnalysisReportController(['id_project' => (string)$projectId, 'password' => 'projpw']);
        $user        = new UserStruct();
        $user->uid   = 1;
        $user->email = $this->ownerEmail(self::ANALYSIS_BASE);
        $this->setControllerProp($controller, 'user', $user);

        $handler = $this->createStub(AMQHandler::class);
        $activity = null;
        $handler->method('publishToQueues')->willReturnCallback(static function (string $queue, Message $message) use (&$activity): bool {
            $activity = (string)$message->getBody();

            return true;
        });
        $savedHandler = WorkerClient::$_HANDLER;
        $savedQueues  = WorkerClient::$_QUEUES;
        $previousEnv  = AppConfig::$ENV;
        $obLevel      = ob_get_level();
        $output       = '';
        WorkerClient::init($handler);
        AppConfig::$ENV = 'testing';
        // finalize() drops the top output buffer: the one below it catches the streamed file.
        ob_start();
        ob_start();
        set_error_handler(static fn (int $errno, string $errstr): bool => str_contains($errstr, 'Cannot modify header information'), E_WARNING);

        try {
            $controller->download();
            $this->fail('download() must end the request with RenderTerminatedException under the testing env');
        } catch (RenderTerminatedException) {
        } finally {
            restore_error_handler();
            while (ob_get_level() > $obLevel) {
                $output = (string)ob_get_clean() . $output;
            }
            AppConfig::$ENV = $previousEnv;
            WorkerClient::$_HANDLER = $savedHandler;
            WorkerClient::$_QUEUES  = $savedQueues;
        }

        $entries = $this->zipEntries($output);
        $this->assertSame([$jobId . '-jobpw'], array_keys($entries));
        $this->assertStringContainsString('Total:', $entries[$jobId . '-jobpw']);
        $this->assertSame('CtrlTestProject_' . self::ANALYSIS_BASE . '.zip', $controller->getFilename());
        $this->assertMatchesRegularExpression('/"action":' . ActivityLogStruct::DOWNLOAD_ANALYSIS_REPORT . '(?!\d)/', (string)$activity);
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

        $controller = new class ($request, $response, null, $this->dbApp()) extends DownloadAnalysisReportController {
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
    public function downloadOriginalIndexAnswersNotFoundOnWrongPassword(): void
    {
        // Wrong password and no review row: 404, the same answer the translation and TMX
        // downloads give, never a 200 with an empty file.
        $jobId = $this->jobId(self::ORIGINAL_BASE);
        $controller = $this->createOriginalController([
            'id_job'   => (string)$jobId,
            'password' => 'definitely_wrong_pw',
        ]);

        try {
            $controller->index();
            $this->fail('Expected NotFoundException');
        } catch (NotFoundException $e) {
            // router.php maps this exception class to a 404 response
            $this->assertSame('Not found.', $e->getMessage());
        }
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

    /**
     * The originals of a job can be hundreds of MB: with more than one file the zip is written in
     * the work folder and streamed from there, never loaded in memory, and the folder is deleted.
     *
     * @throws \Throwable
     */
    #[Test]
    public function downloadOriginalIndexStreamsTheZipOfManyFilesFromDisk(): void
    {
        $sourceDir = sys_get_temp_dir() . '/original-test-' . uniqid();
        mkdir($sourceDir);
        file_put_contents($sourceDir . '/a.docx', 'aaa');
        file_put_contents($sourceDir . '/b.docx', 'bbb');

        try {
            $stream = $this->runOriginalDownload([
                ['filename' => 'a.docx', 'originalFilePath' => $sourceDir . '/a.docx'],
                ['filename' => 'b.docx', 'originalFilePath' => $sourceDir . '/b.docx'],
            ]);
        } finally {
            @unlink($sourceDir . '/a.docx');
            @unlink($sourceDir . '/b.docx');
            @rmdir($sourceDir);
        }

        $this->assertStringStartsWith(AppConfig::$TMP_DOWNLOAD . '/' . $this->jobId(self::ORIGINAL_BASE) . '/', (string)$stream->path);
        $this->assertDirectoryDoesNotExist(AppConfig::$TMP_DOWNLOAD . '/' . $this->jobId(self::ORIGINAL_BASE));
        $this->assertSame(['a.docx' => 'aaa', 'b.docx' => 'bbb'], $this->zipEntries((string)$stream->body));
    }

    /**
     * On S3 the originals are copied to the work folder first, so they are zipped and streamed
     * from the disk like local files instead of being read into memory.
     *
     * @throws \Throwable
     */
    #[Test]
    public function downloadOriginalIndexCopiesTheOriginalsFromS3ToDiskBeforeZippingThem(): void
    {
        $storageMethod = AppConfig::$FILE_STORAGE_METHOD;
        AppConfig::$FILE_STORAGE_METHOD = 's3';

        try {
            $stream = $this->runOriginalDownload(
                [
                    ['filename' => 'a.docx', 'originalFilePath' => 'files/20261110/a.docx'],
                    ['filename' => 'b.docx', 'originalFilePath' => 'files/20261110/b.docx'],
                ],
                ['files/20261110/a.docx' => 'aaa', 'files/20261110/b.docx' => 'bbb']
            );
        } finally {
            AppConfig::$FILE_STORAGE_METHOD = $storageMethod;
        }

        $this->assertSame(['files/20261110/a.docx', 'files/20261110/b.docx'], $stream->s3Keys);
        $this->assertDirectoryDoesNotExist(AppConfig::$TMP_DOWNLOAD . '/' . $this->jobId(self::ORIGINAL_BASE));
        $this->assertSame(['a.docx' => 'aaa', 'b.docx' => 'bbb'], $this->zipEntries((string)$stream->body));
    }

    /**
     * A single original on S3 is copied to the work folder and streamed from there.
     *
     * @throws \Throwable
     */
    #[Test]
    public function downloadOriginalIndexStreamsASingleOriginalFromS3FromDisk(): void
    {
        $storageMethod = AppConfig::$FILE_STORAGE_METHOD;
        AppConfig::$FILE_STORAGE_METHOD = 's3';

        try {
            $stream = $this->runOriginalDownload(
                [['filename' => 'a.docx', 'originalFilePath' => 'files/20261110/a.docx']],
                ['files/20261110/a.docx' => 'aaa']
            );
        } finally {
            AppConfig::$FILE_STORAGE_METHOD = $storageMethod;
        }

        $this->assertSame(['files/20261110/a.docx'], $stream->s3Keys);
        $this->assertSame('aaa', $stream->body);
        $this->assertSame('a.docx', $stream->filename);
        $this->assertDirectoryDoesNotExist(AppConfig::$TMP_DOWNLOAD . '/' . $this->jobId(self::ORIGINAL_BASE));
    }

    /**
     * The copy of an original is named after its file id: a long name (254 bytes here) with any
     * prefix would pass the 255 bytes a file name can have. The download keeps the original name.
     *
     * @throws \Throwable
     */
    #[Test]
    public function downloadOriginalIndexCopiesAnOriginalWithALongNameFromS3(): void
    {
        $storageMethod = AppConfig::$FILE_STORAGE_METHOD;
        AppConfig::$FILE_STORAGE_METHOD = 's3';
        $name = str_repeat('文', 83) . '.docx';

        try {
            $stream = $this->runOriginalDownload(
                [['filename' => $name, 'originalFilePath' => 'files/20261110/' . $name]],
                ['files/20261110/' . $name => 'aaa']
            );
        } finally {
            AppConfig::$FILE_STORAGE_METHOD = $storageMethod;
        }

        $this->assertSame('1', basename((string)$stream->path));
        $this->assertSame('aaa', $stream->body);
        $this->assertSame($name, $stream->filename);
    }

    /**
     * Runs DownloadOriginalController::index() on the seeded job with the given files and returns
     * what it streamed. $s3Objects maps an S3 key to its content.
     *
     * @param list<array{filename: string, originalFilePath: string}> $files
     * @param array<string, string> $s3Objects
     *
     * @throws \Throwable
     */
    private function runOriginalDownload(array $files, array $s3Objects = []): KleinResponseFileStream
    {
        $jobId = $this->jobId(self::ORIGINAL_BASE);
        // finalize() names the download after the project files, whose row needs every field
        $this->seedConnection()->exec("UPDATE files SET sha1_original_file = 'ctrltest', is_converted = 0 WHERE id = " . $this->fileId(self::ORIGINAL_BASE));

        $rows = [];
        foreach ($files as $i => $file) {
            $rows[] = ['id_file' => $i + 1, 'id_project' => $this->projectId(self::ORIGINAL_BASE)] + $file;
        }
        $storage = $this->createStub(FsFilesStorage::class);
        $storage->method('getFilesForJob')->willReturn($rows);

        $stream = new class (new Response(), $s3Objects) extends KleinResponseFileStream {
            public ?string $path = null;
            public ?string $body = null;
            public ?string $filename = null;
            /** @var list<string> */
            public array $s3Keys = [];

            /** @param array<string, string> $s3Objects */
            public function __construct(Response $response, public readonly array $s3Objects)
            {
                parent::__construct($response);
            }

            public function streamFileFromPointer($filePointer, string $mimeType, string $disposition, string $filename): void
            {
                $this->path = stream_get_meta_data($filePointer)['uri'];
                $this->body = (string)stream_get_contents($filePointer);
                $this->filename = $filename;
                fclose($filePointer);
            }
        };

        $_GET['id_job']   = (string)$jobId;
        $_GET['password'] = 'jobpw';
        $controller = new class (Request::createFromGlobals(), new Response(), null, $this->dbApp(), $storage, $stream) extends DownloadOriginalController {
            protected bool $useSession = false;

            public function __construct($request, $response, $service, $app, private readonly AbstractFilesStorage $storage, private readonly KleinResponseFileStream $stream)
            {
                parent::__construct($request, $response, $service, $app);
            }

            protected function identifyUser(?bool $useSession = true): void
            {
                $this->userIsLogged = false;
            }

            protected function getFilesStorage(): AbstractFilesStorage
            {
                return $this->storage;
            }

            protected function createFileStream(): KleinResponseFileStream
            {
                return $this->stream;
            }

            protected function downloadFromS3(string $key, string $saveAs): void
            {
                $this->stream->s3Keys[] = $key;
                file_put_contents($saveAs, $this->stream->s3Objects[$key]);
            }
        };
        unset($_GET['id_job'], $_GET['password']);

        $user = new UserStruct();
        $user->uid = 1;
        $user->email = 'test@example.org';
        $this->setControllerProp($controller, 'user', $user);
        $this->setControllerProp($controller, 'featureSet', new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));

        $savedHandler = WorkerClient::$_HANDLER;
        $savedQueues  = WorkerClient::$_QUEUES;
        $previousEnv  = AppConfig::$ENV;
        $obLevel      = ob_get_level();

        try {
            WorkerClient::init($this->createStub(AMQHandler::class));
            AppConfig::$ENV = 'testing';
            ob_start();
            $controller->index();
            $this->fail('index() must end the request with RenderTerminatedException under the testing env');
        } catch (RenderTerminatedException) {
        } finally {
            while (ob_get_level() > $obLevel) {
                ob_end_clean();
            }
            AppConfig::$ENV = $previousEnv;
            WorkerClient::$_HANDLER = $savedHandler;
            WorkerClient::$_QUEUES  = $savedQueues;
        }

        return $stream;
    }

    /**
     * @return array<string, string> entry name => content
     */
    private function zipEntries(string $zipBytes): array
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'original-zip-');
        file_put_contents($zipPath, $zipBytes);
        $zip = new ZipArchive();
        $entries = [];

        try {
            $this->assertTrue($zip->open($zipPath));
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string)$zip->getNameIndex($i);
                $entries[$name] = (string)$zip->getFromIndex($i);
            }
            $zip->close();
        } finally {
            @unlink($zipPath);
        }

        ksort($entries);

        return $entries;
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

    /** @throws \Throwable */
    #[Test]
    public function downloadJobTMXIndexReturns500JsonErrorsWhenIdAndPasswordMissing(): void
    {
        // No id_job / password → both validation errors accumulate and index()
        // short-circuits to a 500 JSON error response before any DB lookup.
        $controller = $this->createTMXController();

        // index() calls Response::json(), which send()s the body to stdout and pollutes
        // the test-runner output. Swallow that echo; the body is still recorded on the
        // Response object, so the assertions below are unaffected.
        ob_start();
        try {
            $controller->index();
        } finally {
            ob_end_clean();
        }

        $response = (new ReflectionProperty($controller, 'response'))->getValue($controller);
        $this->assertSame(500, $response->status()->getCode());

        $body = json_decode((string)$response->body(), true);
        $this->assertIsArray($body);
        $this->assertCount(2, $body);
        $this->assertSame(-1, $body[0]['code']);
        $this->assertSame('Job ID missing', $body[0]['message']);
        $this->assertSame(-2, $body[1]['code']);
        $this->assertSame('Job password missing', $body[1]['message']);
    }

    /** @throws \Throwable */
    #[Test]
    public function downloadJobTMXIndexExportsTmxForSeededJob(): void
    {
        $base = self::TMX_EXPORT_BASE;
        $this->cleanFragments($base);
        $this->seedProject($base, $this->ownerEmail($base));
        $this->seedFile($base);
        $this->seedJob($base, $this->ownerEmail($base));

        try {
            $controller = $this->createTMXControllerWithStub($this->tmsServiceStub(), [
                'id_job'   => (string)$this->jobId($base),
                'password' => 'jobpw',
            ]);

            try {
                $controller->index();
                $this->fail('finalize() sentinel was not thrown');
            } catch (RuntimeException $e) {
                $this->assertSame('finalize-reached', $e->getMessage());
            }

            // default switch arm → .tmx filename built from project name + job id
            $fileName = (new ReflectionProperty(DownloadJobTMXController::class, 'fileName'))->getValue($controller);
            $this->assertSame('CtrlTestProject_' . $base . '-' . $this->jobId($base) . '.tmx', $fileName);

            $tmx = (new ReflectionProperty(DownloadJobTMXController::class, 'tmx'))->getValue($controller);
            $this->assertInstanceOf(SplTempFileObject::class, $tmx);
        } finally {
            $this->cleanFragments($base);
        }
    }

    /** @throws \Throwable */
    #[Test]
    public function downloadJobTMXIndexExportsCsvForSeededJobWhenTypeIsCsv(): void
    {
        $base = self::TMX_EXPORT_BASE;
        $this->cleanFragments($base);
        $this->seedProject($base, $this->ownerEmail($base));
        $this->seedFile($base);
        $this->seedJob($base, $this->ownerEmail($base));

        try {
            $controller = $this->createTMXControllerWithStub($this->tmsServiceStub(), [
                'id_job'   => (string)$this->jobId($base),
                'password' => 'jobpw',
                'type'     => 'csv',
            ]);

            try {
                $controller->index();
                $this->fail('finalize() sentinel was not thrown');
            } catch (RuntimeException $e) {
                $this->assertSame('finalize-reached', $e->getMessage());
            }

            // 'csv' switch arm → .csv filename built from project name + job id
            $fileName = (new ReflectionProperty(DownloadJobTMXController::class, 'fileName'))->getValue($controller);
            $this->assertSame('CtrlTestProject_' . $base . '-' . $this->jobId($base) . '.csv', $fileName);

            $tmx = (new ReflectionProperty(DownloadJobTMXController::class, 'tmx'))->getValue($controller);
            $this->assertInstanceOf(SplTempFileObject::class, $tmx);
        } finally {
            $this->cleanFragments($base);
        }
    }

    /** @throws \Throwable */
    #[Test]
    public function downloadJobTMXGetTMSServiceBuildsServiceFromDatabaseAndFeatureSet(): void
    {
        // Exercise the real factory body (not the test stub override) so the
        // seam's `new TMSService(...)` is covered.
        $controller = $this->createTMXController();
        $this->setControllerProp($controller, 'featureSet', new FeatureSet(obtainTestDatabase()));

        $method  = new ReflectionMethod($controller, 'getTMSService');
        $service = $method->invoke($controller);

        $this->assertInstanceOf(TMSService::class, $service);
    }

    /**
     * The S3 copy of an original goes through the shared simple-s3 client, here on a mocked AWS
     * transport.
     *
     * @throws \Throwable
     */
    #[Test]
    public function downloadFromS3SavesTheObjectOfTheKeyToTheGivenPath(): void
    {
        $controller = $this->createOriginalController();
        $saveAs     = tempnam(sys_get_temp_dir(), 'original-s3-');
        $requests   = [];

        $configured = S3FilesStorage::getStaticS3Client();
        $client     = new ReflectionProperty(S3FilesStorage::class, 'CLIENT');
        $client->setValue(null, new S3Client([
            'version'     => 'latest',
            'region'      => 'eu-central-1',
            'credentials' => ['key' => 'test', 'secret' => 'test'],
            'handler'     => static function (CommandInterface $command) use (&$requests) {
                $requests[] = [$command->getName(), $command['Bucket'], $command['Key']];
                file_put_contents((string)($command['SaveAs'] ?? $command['@http']['sink']), 'original-bytes');

                return \GuzzleHttp\Promise\Create::promiseFor(new Result(['@metadata' => ['statusCode' => 200]]));
            },
        ]));

        try {
            (new ReflectionMethod($controller, 'downloadFromS3'))->invoke($controller, 'files/20261110/a.docx', $saveAs);
            $saved = file_get_contents($saveAs);
        } finally {
            $client->setValue(null, $configured);
            @unlink($saveAs);
        }

        $this->assertSame([['GetObject', S3FilesStorage::getFilesStorageBucket(), 'files/20261110/a.docx']], $requests);
        $this->assertSame('original-bytes', $saved);
    }

    /**
     * finalize() sends the export line by line and, under the testing env, ends with
     * RenderTerminatedException instead of exit.
     *
     * @throws \Throwable
     */
    #[Test]
    public function downloadJobTMXFinalizeSendsTheExportAndEndsTheRequest(): void
    {
        $controller = $this->createTMXController();
        $file       = new SplTempFileObject();
        $file->fwrite("<tmx version=\"1.4\">\n<body/>\n</tmx>\n");
        $file->rewind();
        (new ReflectionProperty(DownloadJobTMXController::class, 'tmx'))->setValue($controller, $file);
        (new ReflectionProperty(DownloadJobTMXController::class, 'fileName'))->setValue($controller, 'Project "A"-42.tmx');

        $previousEnv = AppConfig::$ENV;
        $obLevel     = ob_get_level();
        $output      = '';
        ob_start();
        AppConfig::$ENV = 'testing';
        set_error_handler(static fn (int $errno, string $errstr): bool => str_contains($errstr, 'Cannot modify header information'), E_WARNING);

        try {
            $controller->finalize();
        } catch (RenderTerminatedException) {
            $output = (string)ob_get_contents();
        } finally {
            restore_error_handler();
            AppConfig::$ENV = $previousEnv;
            while (ob_get_level() > $obLevel) {
                ob_end_clean();
            }
        }

        $this->assertSame("<tmx version=\"1.4\">\n<body/>\n</tmx>\n", $output);
    }

    // --- Helper factories ---

    /** @throws \Throwable */
    private function setControllerProp(object $controller, string $name, mixed $value): void
    {
        $prop = new ReflectionProperty($controller, $name);
        $prop->setValue($controller, $value);
    }

    private function dbApp(): App
    {
        $app = new App();
        $app->register('getDatabase', static fn() => obtainTestDatabase());

        return $app;
    }

    /** @throws \Throwable */
    private function createDownloadController(): DownloadController
    {
        $request = Request::createFromGlobals();
        $response = new Response();

        return new class ($request, $response, null, $this->dbApp()) extends DownloadController {
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

        $controller = new class ($request, $response, null, $this->dbApp()) extends DownloadAnalysisReportController {
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

        $controller = new class ($request, $response, null, $this->dbApp()) extends DownloadOriginalController {
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

        $controller = new class ($request, $response, null, $this->dbApp()) extends DownloadJobTMXController {
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

    /**
     * A DownloadJobTMXController whose TMSService is stubbed (via getTMSService()),
     * whose activity enqueue is a no-op, and whose finalize() throws a sentinel
     * carrying the composed filename instead of streaming/exit-ing. This lets
     * index() run through both switch arms up to the IO/exit boundary hermetically.
     *
     * @param array<string, string> $params
     * @throws \Throwable
     */
    private function createTMXControllerWithStub(TMSService $tmsService, array $params = []): DownloadJobTMXController
    {
        foreach ($params as $key => $value) {
            $_GET[$key] = $value;
        }
        $request = Request::createFromGlobals();
        $response = new Response();

        $controller = new class ($request, $response, null, $this->dbApp()) extends DownloadJobTMXController {
            protected bool $useSession = false;
            public TMSService $tmsServiceStub;

            protected function identifyUser(?bool $useSession = true): void
            {
                $this->userIsLogged = false;
            }

            protected function getTMSService(): TMSService
            {
                return $this->tmsServiceStub;
            }

            protected function _saveActivity(): void
            {
                // no-op: the activity enqueue path has its own dedicated test.
            }

            public function finalize(bool $forceXliff = false): never
            {
                // Halt before the untestable ob_gzhandler/header/stdout/exit block.
                // ($fileName is private on the parent, so it's asserted via reflection.)
                throw new RuntimeException('finalize-reached');
            }
        };

        foreach ($params as $key => $value) {
            unset($_GET[$key]);
        }

        $controller->tmsServiceStub = $tmsService;

        $user = new UserStruct();
        $user->uid = 1;
        $user->email = 'test@example.org';
        $this->setControllerProp($controller, 'user', $user);

        return $controller;
    }

    /**
     * A TMSService stub whose exporters return a small in-memory TMX/CSV file.
     */
    private function tmsServiceStub(): TMSService
    {
        $file = new SplTempFileObject();
        $file->fwrite('<tmx version="1.4"></tmx>');
        $file->rewind();

        $stub = $this->createStub(TMSService::class);
        $stub->method('exportJobAsTMX')->willReturn($file);
        $stub->method('exportJobAsCSV')->willReturn($file);

        return $stub;
    }
}
