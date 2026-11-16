<?php

namespace Matecat\Core\Controllers\Abstracts;

use Controller\Abstracts\AbstractDownloadController;
use Controller\Exceptions\RenderTerminatedException;
use Controller\Traits\KleinResponseFileStream;
use Exception;
use ErrorException;
use Klein\App;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\Files\FileDao;
use Model\Files\FileStruct;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Utils\Registry\AppConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use ReflectionProperty;
use View\API\Commons\ZipContentObject;
use ZipArchive;

#[CoversClass(AbstractDownloadController::class)]
class AbstractDownloadControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    /**
     * Reserved ID block (Playbook §4): base+1 project, base+2 job, base+4 and base+14 files.
     */
    private const int BASE = 9_073_000;

    private AbstractDownloadController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanTestData();
        $this->controller = $this->createController();
    }

    /**
     * Makes the deletion of $dir fail, also for root (the CI container), where a chmod would not:
     * Utils::deleteDir() leaves a dot file in place, so its rmdir() of $dir warns, and the handler set
     * here turns that one warning into an exception, as any failed deletion would. Other warnings,
     * such as the silenced rmdir() of the job folder, stay silent. The caller restores the handler.
     * Example: `<work dir>/.keep` keeps `<work dir>` on the disk.
     */
    private function makeUndeletable(string $dir): void
    {
        touch($dir . '/.keep');
        set_error_handler(static function (int $severity, string $message) use ($dir): bool {
            if (str_starts_with($message, 'rmdir(' . $dir . ')')) {
                throw new ErrorException($message, 0, $severity);
            }

            return true;
        }, E_WARNING);
    }

    protected function tearDown(): void
    {
        $this->cleanTestData();
        parent::tearDown();
    }

    private function createController(): AbstractDownloadController
    {
        $request = Request::createFromGlobals();
        $response = new Response();
        $app = new App();
        $app->register('getDatabase', static fn() => obtainTestDatabase());

        return new class ($request, $response, null, $app) extends AbstractDownloadController {
            protected bool $useSession = false;
            protected ?string $downloadToken = null;

            protected function identifyUser(?bool $useSession = true): void
            {
                $this->userIsLogged = false;
            }
        };
    }

    #[Test]
    public function setMimeTypeSetsXliffForXlfExtension(): void
    {
        $this->setProperty('_filename', 'document.xlf');
        $this->invokeProtected('setMimeType');

        $this->assertSame('application/xliff+xml', $this->getProperty('mimeType'));
    }

    #[Test]
    public function setMimeTypeSetsXliffForSdlxliff(): void
    {
        $this->setProperty('_filename', 'document.sdlxliff');
        $this->invokeProtected('setMimeType');

        $this->assertSame('application/xliff+xml', $this->getProperty('mimeType'));
    }

    #[Test]
    public function setMimeTypeSetsXliffForXliffExtension(): void
    {
        $this->setProperty('_filename', 'document.xliff');
        $this->invokeProtected('setMimeType');

        $this->assertSame('application/xliff+xml', $this->getProperty('mimeType'));
    }

    #[Test]
    public function setMimeTypeSetsZipForZipExtension(): void
    {
        $this->setProperty('_filename', 'archive.zip');
        $this->invokeProtected('setMimeType');

        $this->assertSame('application/zip', $this->getProperty('mimeType'));
    }

    #[Test]
    public function setMimeTypeSetsOctetStreamForUnknown(): void
    {
        $this->setProperty('_filename', 'readme.txt');
        $this->invokeProtected('setMimeType');

        $this->assertSame('application/octet-stream', $this->getProperty('mimeType'));
    }

    #[Test]
    public function finalizeThrowsRenderTerminatedExceptionInTestingEnvironment(): void
    {
        // The render path used to be wrapped in a try/catch that print_r'd the exception and exit()'d,
        // which leaked stack traces to clients and made finalize() untestable. It now emits the file and,
        // under the 'testing' env, throws RenderTerminatedException instead of exit() so the path
        // (unlockToken + header emission + body output) can be exercised here.
        $project     = new ProjectStruct();
        $project->id = 1;
        $this->setProperty('project', $project);
        $this->setProperty('_filename', 'document.xlf');
        $this->setProperty('mimeType', 'application/xliff+xml');
        $this->controller->id_job = 9030001;
        $this->controller->setOutputContent(new ZipContentObject(['output_filename' => 'document.xlf', 'document_content' => 'translated-file-body']));

        $previousEnv     = AppConfig::$ENV;
        $originalObLevel = ob_get_level();
        // Shield PHPUnit's own output buffer: finalize() calls ob_get_clean() on the top buffer.
        ob_start();
        AppConfig::$ENV = 'testing';

        // finalize() emits HTTP headers; under the CLI/PHPUnit SAPI output is already sent, so
        // header() raises "headers already sent" warnings that are irrelevant to what we assert.
        set_error_handler(
            static fn (int $errno, string $errstr): bool => str_contains($errstr, 'Cannot modify header information'),
            E_WARNING
        );

        try {
            $this->controller->finalize(true);
            $this->fail('finalize() must throw RenderTerminatedException under the testing env');
        } catch (RenderTerminatedException $e) {
            $this->assertInstanceOf(RenderTerminatedException::class, $e);
        } finally {
            restore_error_handler();
            AppConfig::$ENV = $previousEnv;
            while (ob_get_level() > $originalObLevel) {
                @ob_end_clean();
            }
            $this->invokeProtected('deleteWorkDir');
        }
    }

    #[Test]
    #[DataProvider('ocrExtensionProvider')]
    public function forceOcrExtensionConvertsImageExtensions(string $input, string $expected): void
    {
        $result = AbstractDownloadController::forceOcrExtension($input);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function ocrExtensionProvider(): array
    {
        return [
            'pdf becomes docx' => ['report.pdf', 'report.pdf.docx'],
            'png becomes docx' => ['image.png', 'image.png.docx'],
            'jpg becomes docx' => ['photo.jpg', 'photo.jpg.docx'],
            'jpeg becomes docx' => ['scan.jpeg', 'scan.jpeg.docx'],
            'tiff becomes docx' => ['fax.tiff', 'fax.tiff.docx'],
            'tif becomes docx' => ['doc.tif', 'doc.tif.docx'],
            'bmp becomes docx' => ['bitmap.bmp', 'bitmap.bmp.docx'],
            'gif becomes docx' => ['anim.gif', 'anim.gif.docx'],
            'docx stays docx' => ['file.docx', 'file.docx'],
            'txt stays txt' => ['notes.txt', 'notes.txt'],
            'xlf stays xlf' => ['trans.xlf', 'trans.xlf'],
        ];
    }

    #[Test]
    #[DataProvider('contentDispositionFilenameProvider')]
    public function sanitizeContentDispositionFilenameStripsHeaderBreakingChars(string $input, string $expected): void
    {
        $result = AbstractDownloadController::sanitizeContentDispositionFilename($input);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function contentDispositionFilenameProvider(): array
    {
        return [
            'plain name untouched'     => ['report.docx', 'report.docx'],
            'double quote stripped'    => ['a".xliff', 'a.xliff'],
            'backslash stripped'       => ['a\\b.xliff', 'ab.xliff'],
            'CR stripped'              => ["a\rb.xliff", 'ab.xliff'],
            'LF stripped'              => ["a\nb.xliff", 'ab.xliff'],
            'NUL stripped'             => ["a\0b.xliff", 'ab.xliff'],
            'header injection attempt' => ["f.xliff\"\r\nSet-Cookie: x=1", 'f.xliffSet-Cookie: x=1'],
            'unicode name preserved'   => ['résumé.docx', 'résumé.docx'],
        ];
    }

    #[Test]
    public function composeZipWritesEveryFileToTheArchive(): void
    {
        $entries = $this->composeZipEntries([
            new ZipContentObject(['output_filename' => 'file1.txt', 'document_content' => 'Hello World', 'input_filename' => 'file1.txt']),
            new ZipContentObject(['output_filename' => 'file2.txt', 'document_content' => 'Second file', 'input_filename' => 'file2.txt']),
        ]);

        $this->assertSame(['file1.txt' => 'Hello World', 'file2.txt' => 'Second file'], $entries);
    }

    #[Test]
    public function composeZipHandlesDuplicateFilenames(): void
    {
        $entries = $this->composeZipEntries([
            new ZipContentObject(['output_filename' => 'same.txt', 'document_content' => 'First', 'input_filename' => 'same.txt']),
            new ZipContentObject(['output_filename' => 'same.txt', 'document_content' => 'Second', 'input_filename' => 'same.txt']),
        ]);

        $this->assertSame(['same.txt' => 'First', 'same.txt(1)' => 'Second'], $entries);
    }

    #[Test]
    public function composeZipSkipsEmptyContent(): void
    {
        $emptyFile = (string)tempnam(sys_get_temp_dir(), 'empty');

        try {
            $entries = $this->composeZipEntries([
                new ZipContentObject(['output_filename' => 'filled.txt', 'document_content' => 'Has content', 'input_filename' => 'filled.txt']),
                new ZipContentObject(['output_filename' => 'empty.txt', 'document_content' => null, 'input_filename' => $emptyFile]),
                new ZipContentObject(['output_filename' => 'blank.txt', 'document_content' => '']),
            ]);
        } finally {
            unlink($emptyFile);
        }

        $this->assertSame(['filled.txt' => 'Has content'], $entries);
    }

    #[Test]
    public function composeZipConvertsOcrExtensions(): void
    {
        $entries = $this->composeZipEntries([
            new ZipContentObject(['output_filename' => 'scan.pdf', 'document_content' => 'PDF content', 'input_filename' => 'scan.pdf']),
        ]);

        $this->assertSame(['scan.pdf.docx'], array_keys($entries));
    }

    #[Test]
    public function composeZipPreservesOriginalExtensionWhenIsOriginalFile(): void
    {
        $entries = $this->composeZipEntries([
            new ZipContentObject(['output_filename' => 'scan.pdf', 'document_content' => 'PDF content', 'input_filename' => 'scan.pdf']),
        ], true);

        $this->assertSame(['scan.pdf'], array_keys($entries));
    }

    /**
     * close() writes no file for an archive with no entries: there is nothing to send.
     */
    #[Test]
    public function composeZipFailsWhenEveryFileIsEmpty(): void
    {
        $zipPath = sys_get_temp_dir() . '/compose-zip-' . uniqid() . '.zip';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unable to write the zip file ' . $zipPath . ': every file is empty');

        (new ReflectionMethod(AbstractDownloadController::class, 'composeZip'))->invoke(null, [
            new ZipContentObject(['output_filename' => 'blank.txt', 'document_content' => '']),
        ], $zipPath);
    }

    #[Test]
    public function setOutputContentWritesContentNotOnDiskToTheWorkDir(): void
    {
        $this->controller->id_job = 9030001;

        try {
            $result = $this->controller->setOutputContent(new ZipContentObject([
                'output_filename' => 'test.txt',
                'document_content' => 'Test content here',
                'input_filename' => 'test.txt',
            ]));

            $outputFile = (string)$this->getProperty('outputFile');
            $this->assertSame($this->controller, $result);
            $this->assertStringStartsWith($this->controller->getWorkDir() . '/', $outputFile);
            $this->assertSame('Test content here', file_get_contents($outputFile));
        } finally {
            $this->invokeProtected('deleteWorkDir');
        }
    }

    #[Test]
    public function setOutputContentFailsWhenTheWorkDirIsNotWritable(): void
    {
        $this->controller->id_job = 9030001;
        $dir = $this->controller->getWorkDir();
        // A folder where the file should go blocks the write, also for root (CI).
        mkdir($dir . '/output');
        set_error_handler(static fn (): bool => true, E_WARNING);

        try {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Unable to write the download file ' . $dir . '/output');

            $this->controller->setOutputContent(new ZipContentObject(['output_filename' => 'test.txt', 'document_content' => 'body']));
        } finally {
            restore_error_handler();
            $this->invokeProtected('deleteWorkDir');
        }
    }

    #[Test]
    public function setFilenameAndGetFilenameRoundTrip(): void
    {
        $this->controller->setFilename('my-file.xlf');
        $this->assertSame('my-file.xlf', $this->controller->getFilename());
    }

    #[Test]
    public function getProjectReturnsProjectStruct(): void
    {
        $project = new ProjectStruct();
        $project->name = 'Test Project';
        $this->setProperty('project', $project);

        $this->assertSame($project, $this->controller->getProject());
    }

    #[Test]
    public function unlockTokenDoesNothingWhenNoToken(): void
    {
        $this->invokeProtected('unlockToken');

        $this->assertTrue(true);
    }

    // ── work directory, zip on disk, streamed output ───────────────────

    #[Test]
    public function getWorkDirReturnsTheSameDirectoryUnderTheJobFolder(): void
    {
        $this->controller->id_job = 9030001;

        try {
            $a = $this->invokeProtected('getWorkDir');
            $b = $this->invokeProtected('getWorkDir');

            $this->assertSame($a, $b);
            $this->assertDirectoryExists($a);
            $this->assertStringStartsWith(AppConfig::$TMP_DOWNLOAD . '/9030001/', $a);
        } finally {
            $this->invokeProtected('deleteWorkDir');
        }
    }

    #[Test]
    public function deleteWorkDirRemovesTheDirectoryAndCanRunTwice(): void
    {
        $this->controller->id_job = 9030001;

        $dir = $this->invokeProtected('getWorkDir');
        file_put_contents($dir . '/a.txt', 'x');

        $this->invokeProtected('deleteWorkDir');
        $this->invokeProtected('deleteWorkDir');

        $this->assertDirectoryDoesNotExist($dir);
    }

    #[Test]
    public function composeZipAddsAFileFromDiskWithoutLoadingIt(): void
    {
        $this->controller->id_job = 9030001;

        try {
            $dir = $this->invokeProtected('getWorkDir');
            file_put_contents($dir . '/a.target', 'converted-bytes');
            $entry = new ZipContentObject(['output_filename' => 'a.docx', 'input_filename' => $dir . '/a.target']);

            $zipPath = $this->invokeProtected('composeZip', [$entry], $dir . '/out.zip');

            $this->assertSame($dir . '/out.zip', $zipPath);
            $this->assertNull($entry->document_content);

            $zip = new ZipArchive();
            $this->assertTrue($zip->open($zipPath));
            $this->assertSame('converted-bytes', $zip->getFromName('a.docx'));
            $zip->close();
        } finally {
            $this->invokeProtected('deleteWorkDir');
        }
    }

    #[Test]
    public function finalizeStreamsAFileOutputAndDeletesTheWorkDir(): void
    {
        $this->controller->id_job = 9030001;

        $project     = new ProjectStruct();
        $project->id = 1;
        $this->setProperty('project', $project);
        $this->setProperty('_filename', 'document.zip');
        $this->setProperty('mimeType', 'application/zip');

        $dir = $this->invokeProtected('getWorkDir');
        file_put_contents($dir . '/out.zip', 'zip-bytes');
        $this->controller->setOutputContent(new ZipContentObject(['output_filename' => 'document.zip', 'input_filename' => $dir . '/out.zip']));

        $previousEnv     = AppConfig::$ENV;
        $originalObLevel = ob_get_level();
        ob_start();
        AppConfig::$ENV = 'testing';

        set_error_handler(
            static fn (int $errno, string $errstr): bool => str_contains($errstr, 'Cannot modify header information'),
            E_WARNING
        );

        $output = null;
        try {
            $this->controller->finalize(true);
            $this->fail('finalize() must throw RenderTerminatedException under the testing env');
        } catch (RenderTerminatedException) {
            $output = ob_get_contents();
        } finally {
            restore_error_handler();
            AppConfig::$ENV = $previousEnv;
            while (ob_get_level() > $originalObLevel) {
                @ob_end_clean();
            }
            $this->invokeProtected('deleteWorkDir');
        }

        $this->assertSame('zip-bytes', $output);
        $this->assertDirectoryDoesNotExist($dir);
    }

    /**
     * A client that cancels the download makes PHP stop inside the stream, so nothing after it
     * runs. The work folder must already be gone when the file starts to go out; the open handle
     * still reads the deleted file.
     */
    #[Test]
    public function finalizeDeletesTheWorkDirBeforeItStreamsTheFile(): void
    {
        $stream = new class (new Response()) extends KleinResponseFileStream {
            public ?bool $fileExisted = null;
            public ?string $body = null;

            public function streamFileFromPointer($filePointer, string $mimeType, string $disposition, string $filename): void
            {
                $this->fileExisted = is_file(stream_get_meta_data($filePointer)['uri']);
                $this->body = (string)stream_get_contents($filePointer);
                fclose($filePointer);
            }
        };

        $app = new App();
        $app->register('getDatabase', static fn() => obtainTestDatabase());
        $controller = new class (Request::createFromGlobals(), new Response(), null, $app, $stream) extends AbstractDownloadController {
            protected bool $useSession = false;

            public function __construct($request, $response, $service, $app, private readonly KleinResponseFileStream $stream)
            {
                parent::__construct($request, $response, $service, $app);
            }

            protected function identifyUser(?bool $useSession = true): void
            {
                $this->userIsLogged = false;
            }

            protected function createFileStream(): KleinResponseFileStream
            {
                return $this->stream;
            }
        };
        $this->controller = $controller;
        $this->controller->id_job = 9030001;

        $project     = new ProjectStruct();
        $project->id = 1;
        $this->setProperty('project', $project);
        $this->setProperty('_filename', 'document.zip');
        $this->setProperty('mimeType', 'application/zip');

        $dir = $this->controller->getWorkDir();
        file_put_contents($dir . '/out.zip', 'zip-bytes');
        $this->controller->setOutputContent(new ZipContentObject(['output_filename' => 'document.zip', 'input_filename' => $dir . '/out.zip']));

        $previousEnv     = AppConfig::$ENV;
        $originalObLevel = ob_get_level();
        ob_start();
        AppConfig::$ENV = 'testing';

        try {
            $this->controller->finalize(true);
            $this->fail('finalize() must throw RenderTerminatedException under the testing env');
        } catch (RenderTerminatedException) {
        } finally {
            AppConfig::$ENV = $previousEnv;
            while (ob_get_level() > $originalObLevel) {
                @ob_end_clean();
            }
            $this->invokeProtected('deleteWorkDir');
        }

        $this->assertFalse($stream->fileExisted);
        $this->assertSame('zip-bytes', $stream->body);
        $this->assertDirectoryDoesNotExist($dir);
    }

    /**
     * A request killed by a fatal error (memory, timeout) never runs its cleanup. The next download
     * removes the work folders older than WORK_DIR_MAX_AGE, of any job, and leaves recent ones alone.
     */
    #[Test]
    public function getWorkDirDeletesWorkDirsLeftByOldRequests(): void
    {
        $this->controller->id_job = 9030001;

        $jobDir = AppConfig::$TMP_DOWNLOAD . '/9030002';
        $stale  = $jobDir . '/stale';
        $recent = $jobDir . '/recent';
        @mkdir($stale . '/7', 0775, true);
        @mkdir($recent, 0775, true);
        file_put_contents($stale . '/7/a.xlf', 'x');
        file_put_contents($recent . '/b.xlf', 'y');
        touch($stale, time() - AbstractDownloadController::WORK_DIR_MAX_AGE - 60);

        try {
            $this->controller->getWorkDir();

            $this->assertDirectoryDoesNotExist($stale);
            $this->assertFileExists($recent . '/b.xlf');
        } finally {
            $this->invokeProtected('deleteWorkDir');
            @unlink($stale . '/7/a.xlf');
            @rmdir($stale . '/7');
            @rmdir($stale);
            @unlink($recent . '/b.xlf');
            @rmdir($recent);
            @rmdir($jobDir);
        }
    }

    #[Test]
    public function getWorkDirCreatesAFolderOnlyItsOwnerCanOpen(): void
    {
        $this->controller->id_job = 9030001;

        try {
            $dir = $this->controller->getWorkDir();

            $this->assertSame(0700, fileperms($dir) & 0777);
        } finally {
            $this->invokeProtected('deleteWorkDir');
        }
    }

    /**
     * ZipArchive reads the files added by path only in close(): a file that cannot be read fails
     * there, and the error must not be lost.
     */
    #[Test]
    public function composeZipThrowsWhenTheArchiveCannotBeWritten(): void
    {
        $this->controller->id_job = 9030001;
        $dir = $this->controller->getWorkDir();
        file_put_contents($dir . '/doc.docx', 'body');

        try {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Unable to write the zip file');

            $method = new ReflectionMethod(AbstractDownloadController::class, 'composeZip');
            set_error_handler(static fn (): bool => true, E_WARNING);
            try {
                // The zip is written at close; a missing folder fails it, also for root (CI).
                $method->invoke(null, [new ZipContentObject(['output_filename' => 'doc.docx', 'input_filename' => $dir . '/doc.docx'])], $dir . '/missing/out.zip');
            } finally {
                restore_error_handler();
            }
        } finally {
            $this->invokeProtected('deleteWorkDir');
        }
    }

    // ── failures and the paths that need the database ──────────────────

    #[Test]
    public function getJobLoadsTheJobOfTheControllerOnce(): void
    {
        $this->seedJobWithProject();
        $this->controller->id_job = $this->jobId(self::BASE);

        $job = $this->controller->getJob();

        $this->assertSame($this->jobId(self::BASE), (int)$job->id);
        $this->assertSame($job, $this->controller->getJob());
    }

    #[Test]
    public function getWorkDirFailsWhenTheFolderCannotBeCreated(): void
    {
        $this->controller->id_job = 9030001;
        $tmpDownload = AppConfig::$TMP_DOWNLOAD;
        // A folder cannot be created below a file.
        AppConfig::$TMP_DOWNLOAD = '/dev/null/tmp_download';
        set_error_handler(static fn (): bool => true, E_WARNING);

        try {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Unable to create the download folder /dev/null/tmp_download/9030001/');

            $this->controller->getWorkDir();
        } finally {
            restore_error_handler();
            AppConfig::$TMP_DOWNLOAD = $tmpDownload;
        }
    }

    /**
     * A folder that cannot be deleted is logged and forgotten: the download it belongs to is sent
     * anyway, and the next getWorkDir() makes a new one.
     */
    #[Test]
    public function deleteWorkDirForgetsAFolderItCannotDelete(): void
    {
        $this->controller->id_job = 9030001;
        $dir = $this->controller->getWorkDir();
        $this->makeUndeletable($dir);

        try {
            $this->invokeProtected('deleteWorkDir');

            $this->assertDirectoryExists($dir);
            $this->assertNotSame($dir, $this->controller->getWorkDir());
        } finally {
            restore_error_handler();
            unlink($dir . '/.keep');
            $this->invokeProtected('deleteWorkDir');
            @rmdir($dir);
            @rmdir(dirname($dir));
        }
    }

    #[Test]
    public function getWorkDirKeepsGoingWhenAnAbandonedFolderCannotBeDeleted(): void
    {
        $this->controller->id_job = 9030001;
        $stale = AppConfig::$TMP_DOWNLOAD . '/9030002/stale';
        @mkdir($stale, 0700, true);
        $this->makeUndeletable($stale);
        touch($stale, time() - AbstractDownloadController::WORK_DIR_MAX_AGE - 60);

        try {
            $dir = $this->controller->getWorkDir();

            $this->assertDirectoryExists($dir);
            $this->assertDirectoryExists($stale);
        } finally {
            restore_error_handler();
            unlink($stale . '/.keep');
            @rmdir($stale);
            @rmdir(dirname($stale));
            $this->invokeProtected('deleteWorkDir');
        }
    }

    /**
     * The browser waits for the token cookie to know the download ended; the token is used once.
     */
    #[Test]
    public function unlockTokenUsesTheDownloadTokenOnce(): void
    {
        $this->setProperty('downloadToken', 'token-1');

        $this->invokeProtected('unlockToken');

        $this->assertNull($this->getProperty('downloadToken'));
    }

    #[Test]
    public function unlockTokenKeepsTheTokenWhenTheMessageCannotBeEncoded(): void
    {
        $this->setProperty('downloadToken', 'token-1');

        $this->invokeProtected('unlockToken', ['code' => -110, 'message' => "\xB1\x31"]);

        $this->assertSame('token-1', $this->getProperty('downloadToken'));
    }

    /**
     * With no project and no name yet, finalize() loads the project of the job and names the file
     * after its only file.
     */
    #[Test]
    public function finalizeNamesTheFileAfterTheProjectOfTheJob(): void
    {
        $this->seedJobWithProject();
        $this->controller->id_job = $this->jobId(self::BASE);
        $this->controller->setOutputContent(new ZipContentObject(['output_filename' => 'a.xliff', 'document_content' => 'translated-file-body']));

        $output = $this->runFinalize();

        $this->assertSame('translated-file-body', $output);
        $this->assertSame('ctrltest_' . self::BASE . '.xliff', $this->controller->getFilename());
        $this->assertSame($this->projectId(self::BASE), (int)$this->controller->getProject()->id);
    }

    #[Test]
    public function finalizeFailsWhenTheJobHasNoProject(): void
    {
        $this->controller->id_job = $this->jobId(self::BASE);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Project not found for job ' . $this->jobId(self::BASE));

        $this->controller->finalize();
    }

    #[Test]
    public function finalizeFailsWhenTheFileToSendCannotBeOpened(): void
    {
        $project     = new ProjectStruct();
        $project->id = 1;
        $this->setProperty('project', $project);
        $this->setProperty('_filename', 'document.zip');
        $this->setProperty('outputFile', '/nonexistent/download.zip');
        $obLevel = ob_get_level();
        ob_start();
        set_error_handler(static fn (): bool => true, E_WARNING);

        try {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Unable to open the download file /nonexistent/download.zip');

            $this->controller->finalize(true);
        } finally {
            restore_error_handler();
            while (ob_get_level() > $obLevel) {
                ob_end_clean();
            }
        }
    }

    /**
     * A controller that never set its output has no file to send: finalize() fails with its own
     * error, not with the ValueError of fopen('').
     */
    #[Test]
    public function finalizeFailsWhenNoFileToSendWasSet(): void
    {
        $project     = new ProjectStruct();
        $project->id = 1;
        $this->setProperty('project', $project);
        $this->setProperty('_filename', 'document.zip');
        $obLevel = ob_get_level();
        ob_start();

        try {
            $this->controller->finalize(true);
            $this->fail('finalize() must fail with no file to send');
        } catch (Exception $e) {
            $this->assertSame(Exception::class, $e::class);
            $this->assertSame('Unable to open the download file', $e->getMessage());
        } finally {
            while (ob_get_level() > $obLevel) {
                ob_end_clean();
            }
        }
    }

    /**
     * Another download that deletes its own folder also deletes the job folder when it looks
     * empty, and it can do it between the two levels mkdir() creates: the second try succeeds.
     */
    #[Test]
    public function getWorkDirTriesAgainWhenTheJobFolderIsDeletedWhileItIsCreated(): void
    {
        $this->controller->id_job = 9030001;
        $tmpDownload = AppConfig::$TMP_DOWNLOAD;
        stream_wrapper_register('racing-mkdir', RacingMkdirStream::class);
        AppConfig::$TMP_DOWNLOAD = 'racing-mkdir://' . $tmpDownload;
        RacingMkdirStream::$failures = 1;
        set_error_handler(static fn (): bool => true, E_WARNING);

        try {
            $dir = $this->controller->getWorkDir();

            $this->assertSame(0, RacingMkdirStream::$failures);
            $this->assertStringStartsWith('racing-mkdir://' . $tmpDownload . '/9030001/', $dir);
            $this->assertDirectoryExists(substr($dir, strlen('racing-mkdir://')));
        } finally {
            restore_error_handler();
            AppConfig::$TMP_DOWNLOAD = $tmpDownload;
            stream_wrapper_unregister('racing-mkdir');
            if (isset($dir)) {
                @rmdir(substr($dir, strlen('racing-mkdir://')));
                @rmdir(dirname(substr($dir, strlen('racing-mkdir://'))));
            }
        }
    }

    #[Test]
    public function getDefaultFileNameNamesAZipAfterAProjectWithManyFiles(): void
    {
        $this->seedJobWithProject();
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO files (id, id_project, filename, source_language, mime_type, sha1_original_file, is_converted) "
            . "VALUES (" . (self::BASE + 14) . ", " . $this->projectId(self::BASE) . ", 'second.docx', 'en-US', 'application/octet-stream', 'ctrltest', 1)"
        );
        $this->evictProjectFilesCache();
        $project = (new ProjectDao(obtainTestDatabase()))->findById($this->projectId(self::BASE));
        $this->assertNotNull($project);
        $this->setProperty('project', $project);

        $this->assertSame('CtrlTestProject_' . self::BASE . '.zip', $this->controller->getDefaultFileName($project));
    }

    #[Test]
    public function composeZipFailsWhenTheZipCannotBeCreated(): void
    {
        $method = new ReflectionMethod(AbstractDownloadController::class, 'composeZip');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unable to create the zip file ' . sys_get_temp_dir());

        // A folder is not a zip file.
        $method->invoke(null, [], sys_get_temp_dir());
    }

    /**
     * Runs composeZip() on $files and returns the entries of the archive, name => content.
     *
     * @param ZipContentObject[] $files
     *
     * @return array<string, string>
     */
    private function composeZipEntries(array $files, bool $isOriginalFile = false): array
    {
        $zipPath = sys_get_temp_dir() . '/compose-zip-' . uniqid() . '.zip';
        (new ReflectionMethod(AbstractDownloadController::class, 'composeZip'))->invoke(null, $files, $zipPath, $isOriginalFile);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath));
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[(string)$zip->getNameIndex($i)] = (string)$zip->getFromIndex($i);
        }
        $zip->close();
        unlink($zipPath);

        return $entries;
    }

    /**
     * Calls finalize() with ENV testing and returns what it echoed.
     */
    private function runFinalize(): string
    {
        $previousEnv = AppConfig::$ENV;
        $obLevel     = ob_get_level();
        $output      = '';
        // finalize() drops the top output buffer: the one below it catches the streamed file.
        ob_start();
        ob_start();
        AppConfig::$ENV = 'testing';
        set_error_handler(static fn (int $errno, string $errstr): bool => str_contains($errstr, 'Cannot modify header information'), E_WARNING);

        try {
            $this->controller->finalize();
            $this->fail('finalize() must throw RenderTerminatedException under the testing env');
        } catch (RenderTerminatedException) {
        } finally {
            restore_error_handler();
            AppConfig::$ENV = $previousEnv;
            while (ob_get_level() > $obLevel) {
                $output = (string)ob_get_clean() . $output;
            }
        }

        return $output;
    }

    private function seedJobWithProject(): void
    {
        $owner = $this->ownerEmail(self::BASE);
        $this->seedProject(self::BASE, $owner);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $owner);
        // finalize() names the file after the project files, whose rows need every field.
        $this->seedConnection()->exec("UPDATE files SET sha1_original_file = 'ctrltest', is_converted = 0 WHERE id = " . $this->fileId(self::BASE));
    }

    private function cleanTestData(): void
    {
        $this->seedConnection()->exec("DELETE FROM files WHERE id = " . (self::BASE + 14));
        $this->cleanFragments(self::BASE);
        $this->evictProjectFilesCache();
    }

    /**
     * FileDao::getByProjectId() keeps the files of a project in the cache for 10 minutes.
     */
    private function evictProjectFilesCache(): void
    {
        $dao  = new FileDao(obtainTestDatabase());
        $stmt = obtainTestDatabase()->getConnection()->prepare("SELECT * FROM files where id_project = :id_project ");
        (new ReflectionMethod($dao, '_destroyObjectCache'))->invoke($dao, $stmt, FileStruct::class, ['id_project' => $this->projectId(self::BASE)]);
    }

    private function invokeProtected(string $method, mixed ...$args): mixed
    {
        $ref = new ReflectionMethod($this->controller, $method);

        return $ref->invoke($this->controller, ...$args);
    }

    private function setProperty(string $name, mixed $value): void
    {
        $ref = new ReflectionProperty($this->controller, $name);
        $ref->setValue($this->controller, $value);
    }

    private function getProperty(string $name): mixed
    {
        $ref = new ReflectionProperty($this->controller, $name);

        return $ref->getValue($this->controller);
    }
}

/**
 * A stream wrapper over the local disk whose mkdir() fails the first $failures times, as when
 * another download deletes the job folder halfway through. Example: `racing-mkdir:///tmp/a`.
 */
class RacingMkdirStream
{
    public static int $failures = 0;

    /** @var resource|null */
    public $context;

    public function mkdir(string $path, int $mode, int $options): bool
    {
        if (self::$failures > 0) {
            self::$failures--;

            return false;
        }

        return mkdir(substr($path, strlen('racing-mkdir://')), $mode, (bool)($options & STREAM_MKDIR_RECURSIVE));
    }

    /**
     * @return array<int|string, int>|false
     */
    public function url_stat(string $path, int $flags): array|false
    {
        return @stat(substr($path, strlen('racing-mkdir://')));
    }
}
