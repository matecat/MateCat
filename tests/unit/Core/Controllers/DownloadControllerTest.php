<?php

namespace Matecat\Core\Controllers;

use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Controller\Abstracts\AbstractDownloadController;
use Controller\API\Commons\Exceptions\ExternalServiceException;
use Controller\API\V2\DownloadController;
use Controller\Exceptions\RenderTerminatedException;
use Controller\Traits\KleinResponseFileStream;
use Exception;
use Google_Client;
use Google_Service_Drive_DriveFile;
use Klein\Request;
use Klein\Response;
use Matecat\SimpleS3\Client as S3Client;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\ActivityLog\ActivityLogStruct;
use Model\ConnectedServices\GDrive\RemoteFileService;
use Model\ConnectedServices\Oauth\OauthTokenEncryption;
use Model\Conversion\Filters;
use Model\Exceptions\NotFoundException;
use Model\Files\FileDao;
use Model\Files\FileStruct;
use Model\FeaturesBase\FeatureSet;
use Model\FilesStorage\AbstractFilesStorage;
use Model\FilesStorage\FsFilesStorage;
use Model\FilesStorage\S3FilesStorage;
use Model\Jobs\JobDao;
use Model\RemoteFiles\RemoteFileStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\TextUI\Configuration\Registry;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Stomp\Transport\Message;
use Throwable;
use Utils\ActiveMQ\AMQHandler;
use Utils\ActiveMQ\WorkerClient;
use Utils\Logger\MatecatLogger;
use Utils\Redis\RedisHandler;
use Utils\Registry\AppConfig;
use View\API\Commons\ZipContentObject;
use ZipArchive;

/**
 * Dedicated real-DB suite for {@see DownloadController} (Wave 5 task #29a).
 *
 * Reserved ID block (Playbook §4): base = 9_029_000.
 *   base+1 (9029001) project, base+2 (9029002) job, base+3 (9029003) segment,
 *   base+4 (9029004) file, base+8 (9029008) chunk review, base+11 (9029011) connected_service,
 *   base+14 (9029014) and base+15 (9029015) more files of the project.
 * remote_files rows use ids 9029020-9029022 (still inside the reserved block).
 * Per-suite owner email: ctrltest_9029000@example.org.
 *
 * A whole download runs here end to end: the xliffs are files on disk handed out by a stubbed
 * storage, the converter is a stubbed Filters, S3 is a real simple-s3 client on a mocked AWS
 * transport, Google Drive is a stubbed RemoteFileService, and the file sent is caught by a
 * recording file stream.
 */
class TestableDownloadController extends DownloadController
{
    public ?AbstractFilesStorage $storage = null;
    public ?Filters $filters = null;
    public ?KleinResponseFileStream $stream = null;
    public ?RemoteFileService $remoteService = null;

    public function __construct()
    {
    }

    protected function getFilesStorage(): AbstractFilesStorage
    {
        return $this->storage ?? parent::getFilesStorage();
    }

    protected function createFilters(): Filters
    {
        return $this->filters ?? parent::createFilters();
    }

    protected function createFileStream(): KleinResponseFileStream
    {
        return $this->stream ?? parent::createFileStream();
    }

    protected function createRemoteFileService(string $token, Google_Client $client): RemoteFileService
    {
        return $this->remoteService ?? parent::createRemoteFileService($token, $client);
    }
}

/**
 * Keeps what a download sends instead of writing it to the response.
 */
class RecordingFileStream extends KleinResponseFileStream
{
    public ?string $body = null;
    public ?string $mimeType = null;
    public ?string $filename = null;

    public function __construct()
    {
        parent::__construct(new Response());
    }

    public function streamFileFromPointer($filePointer, string $mimeType, string $disposition, string $filename): void
    {
        $this->body = (string)stream_get_contents($filePointer);
        $this->mimeType = $mimeType;
        $this->filename = $filename;
        fclose($filePointer);
    }
}

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(DownloadController::class)]
class DownloadControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_029_000;

    /** @var ReflectionClass<DownloadController> */
    private ReflectionClass $reflector;
    private TestableDownloadController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;
    private string $storageMethod;
    private string $fixtureDir;
    /** @var array{?string, ?string, ?string} */
    private array $googleConfig;

    /**
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        // The test config stores files on S3: a download test opts in to S3 on its own.
        $this->storageMethod = AppConfig::$FILE_STORAGE_METHOD;
        AppConfig::$FILE_STORAGE_METHOD = 'fs';

        // The GDrive downloads build a Google client, which needs these values; CI has no Google config.
        $this->googleConfig = [AppConfig::$GOOGLE_OAUTH_CLIENT_APP_NAME, AppConfig::$GOOGLE_OAUTH_CLIENT_ID, AppConfig::$GOOGLE_OAUTH_CLIENT_SECRET];
        AppConfig::$GOOGLE_OAUTH_CLIENT_APP_NAME = 'test-app';
        AppConfig::$GOOGLE_OAUTH_CLIENT_ID = 'test-client-id';
        AppConfig::$GOOGLE_OAUTH_CLIENT_SECRET = 'test-client-secret';
        $this->fixtureDir = sys_get_temp_dir() . '/download-test-' . uniqid();
        mkdir($this->fixtureDir);

        $this->cleanTestData();
        $this->seedTestData();

        $this->controller = new TestableDownloadController();
        $this->reflector  = new ReflectionClass(DownloadController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);
        $this->setProp('database', obtainTestDatabase());

        $user        = new UserStruct();
        $user->uid   = $this->userId(self::BASE);
        $user->email = $this->ownerEmail(self::BASE);
        $this->setProp('user', $user);

        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));
    }

    /**
     * @throws \PDOException
     */
    protected function tearDown(): void
    {
        AppConfig::$FILE_STORAGE_METHOD = $this->storageMethod;
        [AppConfig::$GOOGLE_OAUTH_CLIENT_APP_NAME, AppConfig::$GOOGLE_OAUTH_CLIENT_ID, AppConfig::$GOOGLE_OAUTH_CLIENT_SECRET] = $this->googleConfig;
        $this->deleteTree($this->fixtureDir);
        $this->cleanTestData();
        parent::tearDown();
    }

    /**
     * @throws \PDOException
     */
    private function seedTestData(): void
    {
        $owner = $this->ownerEmail(self::BASE);
        $this->seedProject(self::BASE, $owner);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $owner);
        $this->seedSegment(self::BASE);
        $this->seedSegmentTranslation(self::BASE);
        // A download names its file after the project files, whose rows need every field.
        $this->seedConnection()->exec("UPDATE files SET sha1_original_file = 'ctrltest', is_converted = 0 WHERE id = " . $this->fileId(self::BASE));
    }

    /**
     * @throws \PDOException
     */
    private function cleanTestData(): void
    {
        $conn = $this->seedConnection();
        $conn->exec("DELETE FROM remote_files WHERE id IN (9029020, 9029021, 9029022)");
        $conn->exec("DELETE FROM files_job WHERE id_job = " . $this->jobId(self::BASE));
        $conn->exec("DELETE FROM files WHERE id IN (" . (self::BASE + 14) . ", " . (self::BASE + 15) . ")");
        $this->cleanFragments(self::BASE);
        $this->evictProjectFilesCache();
        (new RedisHandler())->getConnection()->del(['job_completeness:' . $this->jobId(self::BASE)]);
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
     * @param array<int, mixed> $args
     *
     * @throws ReflectionException
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        return $this->reflector->getMethod($method)->invoke($this->controller, ...$args);
    }

    // ─── pathinfoString (private, pure) ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function pathinfoStringReturnsBasename(): void
    {
        $this->assertSame('file.xlf', $this->invokePrivate('pathinfoString', ['/a/b/file.xlf', PATHINFO_BASENAME]));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function pathinfoStringReturnsExtensionAndFilename(): void
    {
        $this->assertSame('sdlxliff', $this->invokePrivate('pathinfoString', ['/a/doc.sdlxliff', PATHINFO_EXTENSION]));
        $this->assertSame('doc', $this->invokePrivate('pathinfoString', ['/a/doc.sdlxliff', PATHINFO_FILENAME]));
        $this->assertSame('/a', $this->invokePrivate('pathinfoString', ['/a/doc.sdlxliff', PATHINFO_DIRNAME]));
    }

    // ─── isAnIWorkFile / overrideExtensionForIWorkFiles (private, pure) ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function isAnIWorkFileDetectsIWorkExtensions(): void
    {
        $this->assertTrue($this->invokePrivate('isAnIWorkFile', ['pages']));
        $this->assertTrue($this->invokePrivate('isAnIWorkFile', ['numbers']));
        $this->assertTrue($this->invokePrivate('isAnIWorkFile', ['key']));
        $this->assertFalse($this->invokePrivate('isAnIWorkFile', ['docx']));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function overrideExtensionForIWorkFilesMapsToOfficeFormats(): void
    {
        $this->assertSame('pptx', $this->invokePrivate('overrideExtensionForIWorkFiles', ['key']));
        $this->assertSame('xlsx', $this->invokePrivate('overrideExtensionForIWorkFiles', ['numbers']));
        $this->assertSame('docx', $this->invokePrivate('overrideExtensionForIWorkFiles', ['pages']));
    }

    // ─── generateFilename (private) ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function generateFilenameAppendsTargetAndKeepsExtension(): void
    {
        $result = $this->invokePrivate('generateFilename', ['report.docx', 'it-IT']);
        $this->assertSame('report_it-IT.docx', $result);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function generateFilenameWithoutTargetKeepsName(): void
    {
        $result = $this->invokePrivate('generateFilename', ['plain.txt', null]);
        $this->assertSame('plain.txt', $result);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function generateFilenameConvertsIWorkExtension(): void
    {
        // .key (iWork Keynote) must be overridden to .pptx
        $result = $this->invokePrivate('generateFilename', ['slides.key', null]);
        $this->assertSame('slides.pptx', $result);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function generateFilenamePreservesDirname(): void
    {
        $result = $this->invokePrivate('generateFilename', ['sub/dir/file.docx', null]);
        $this->assertStringContainsString('file.docx', $result);
        $this->assertStringContainsString('sub', $result);
    }

    // ─── ifGlobalSightXliffRemoveTargetMarks (public, pure) ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function ifGlobalSightXliffRemoveTargetMarksReturnsContentUnchangedForNonXliffPath(): void
    {
        $content = '<root><mrk>keep</mrk></root>';
        $result  = $this->controller->ifGlobalSightXliffRemoveTargetMarks($content, '/tmp/file.txt');
        $this->assertSame($content, $result);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function ifGlobalSightXliffRemoveTargetMarksLeavesNonGlobalSightXliffUntouched(): void
    {
        $content = '<?xml version="1.0"?><xliff version="1.2"><file><body>'
            . '<trans-unit id="1"><target>ciao</target></trans-unit></body></file></xliff>';

        $result = $this->controller->ifGlobalSightXliffRemoveTargetMarks($content, '/tmp/file.xlf');

        // Not a globalsight file → no mrk stripping happens, content returned as-is.
        $this->assertSame($content, $result);
    }

    // ─── getOutputContentsWithZipFiles (private, non-zip branch) ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function getOutputContentsWithZipFilesWrapsNonZipFilesIntoZipContentObjects(): void
    {
        $input = [
            9029004 => [
                'output_filename'  => 'document.docx',
                'document_content' => 'hello content',
                'input_filename'   => '/tmp/document.docx',
                'out_xliff_name'   => '/tmp/document.docx',
                'source'           => 'en-US',
                'target'           => 'it-IT',
            ],
        ];

        /** @var array<int|string, ZipContentObject> $result */
        $result = $this->invokePrivate('getOutputContentsWithZipFiles', [$input]);

        $this->assertArrayHasKey(9029004, $result);
        $this->assertInstanceOf(ZipContentObject::class, $result[9029004]);
        // generateFilename ran on output_filename (no target → unchanged extension).
        $this->assertSame('document.docx', $result[9029004]->output_filename);
        // out_xliff_name, source and target, which ZipContentObject does not have, were dropped.
        $this->assertSame('/tmp/document.docx', $result[9029004]->input_filename);
    }

    // ─── anyRemoteFile (private, real-DB via RemoteFileDao) ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function anyRemoteFileReturnsFalseWhenNoRemoteRowsExist(): void
    {
        $this->setProp('id_job', $this->jobId(self::BASE));
        $this->assertFalse($this->invokePrivate('anyRemoteFile'));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function anyRemoteFileReturnsTrueWhenNonOriginalRemoteRowExists(): void
    {
        $conn = $this->seedConnection();
        $conn->exec(
            "INSERT IGNORE INTO remote_files (id, id_file, id_job, remote_id, is_original) "
            . "VALUES (9029020, " . $this->fileId(self::BASE) . ", " . $this->jobId(self::BASE) . ", 'remote_dl', 0)"
        );

        $this->setProp('id_job', $this->jobId(self::BASE));
        $this->assertTrue($this->invokePrivate('anyRemoteFile'));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function anyRemoteFileCachesResultAfterFirstQuery(): void
    {
        $this->setProp('id_job', $this->jobId(self::BASE));

        // First call: no rows → false, caches the negative result.
        $this->assertFalse($this->invokePrivate('anyRemoteFile'));

        // Insert a row AFTER caching; cached false must be returned again.
        $conn = $this->seedConnection();
        $conn->exec(
            "INSERT IGNORE INTO remote_files (id, id_file, id_job, remote_id, is_original) "
            . "VALUES (9029021, " . $this->fileId(self::BASE) . ", " . $this->jobId(self::BASE) . ", 'remote_cache', 0)"
        );

        $this->assertFalse($this->invokePrivate('anyRemoteFile'));
    }

    // ─── processDownload failure boundary (real-DB) ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function processDownloadThrowsNotFoundWhenJobAndReviewPasswordAbsent(): void
    {
        // id_job set to a non-existent job → JobDao + ChunkReviewDao both empty.
        $this->setProp('id_job', 90299999);
        $this->setProp('password', 'definitely_wrong_password');
        $this->setProp('featureSet', new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Not found.');

        $this->invokePrivate('processDownload');
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function processDownloadThrowsNotFoundWhenPasswordWrongForExistingJob(): void
    {
        // Existing job id, wrong password → no job row, no chunk review → NotFound.
        $this->setProp('id_job', $this->jobId(self::BASE));
        $this->setProp('password', 'wrong_pw_for_seeded_job');
        $this->setProp('featureSet', new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));

        $this->expectException(NotFoundException::class);

        $this->invokePrivate('processDownload');
    }

    // ─── downloadFile / index / forceXliff input-parse path (real-DB, NotFound boundary) ───

    /**
     * index() delegates to downloadFile(false): parses request params, builds the
     * FeatureSet and reaches processDownload(), which throws NotFoundException for a
     * non-existent job. Covers the whole input-parse block up to the DB boundary.
     *
     * @throws Throwable
     */
    #[Test]
    public function indexParsesInputAndThrowsNotFoundForMissingJob(): void
    {
        $this->setProp('request', new Request([
            'id_job'   => '90299999',
            'password' => 'wrong_password',
        ]));

        $this->expectException(NotFoundException::class);
        $this->controller->index();
    }

    /**
     * forceXliff() delegates to downloadFile(true): the $forceXliff argument forces
     * the forceXliff flag on before reaching the NotFound boundary.
     *
     * @throws Throwable
     */
    #[Test]
    public function forceXliffParsesInputAndThrowsNotFoundForMissingJob(): void
    {
        $this->setProp('request', new Request([
            'id_job'   => '90299999',
            'password' => 'wrong_password',
        ]));

        $this->expectException(NotFoundException::class);
        $this->controller->forceXliff();
    }

    /**
     * downloadFile() with every optional input supplied exercises the truthy side of
     * each parse assignment (filename, id_file, download_type, downloadToken,
     * disableErrorCheck, forceXliff, original) before reaching the NotFound boundary.
     *
     * @throws Throwable
     */
    #[Test]
    public function downloadFileParsesAllOptionalInputsThenThrowsNotFound(): void
    {
        $this->setProp('request', new Request([
            'filename'          => 'my file.docx',
            'id_file'           => '9029004',
            'id_job'            => '90299999',
            'download_type'     => 'all',
            'password'          => 'wrong_password',
            'downloadToken'     => 'tok-123',
            'forceXliff'        => '1',
            'original'          => '1',
            'disableErrorCheck' => '1',
        ]));

        $this->expectException(NotFoundException::class);
        $this->invokePrivate('downloadFile', [false]);
    }

    // ─── ifGlobalSightXliffRemoveTargetMarks — globalsight + UTF-16 arms ───

    /**
     * A GlobalSight-detected xliff triggers seg-source removal and mrk-tag stripping.
     *
     * @throws Throwable
     */
    #[Test]
    public function ifGlobalSightXliffRemoveTargetMarksStripsMrkAndSegSourceForGlobalSight(): void
    {
        $content = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<xliff version="1.2"><file original="globalsight" datatype="html" source-language="en-US">'
            . '<body>'
            . '<trans-unit id="1"><source>hello</source>'
            . '<seg-source><mrk mid="0" mtype="seg">hello</mrk></seg-source>'
            . '<target><mrk mid="0" mtype="seg">ciao</mrk></target></trans-unit>'
            . '</body></file></xliff>';

        $result = $this->controller->ifGlobalSightXliffRemoveTargetMarks($content, '/tmp/file.xlf');

        // seg-source block removed and mrk open/close tags stripped, inner text kept.
        $this->assertStringNotContainsString('<seg-source', $result);
        $this->assertStringNotContainsString('<mrk', $result);
        $this->assertStringNotContainsString('</mrk>', $result);
        $this->assertStringContainsString('ciao', $result);
    }

    /**
     * A UTF-16 encoded xliff (no ASCII "<?xml " marker in the first 100 bytes) enters
     * the encoding-conversion arms: decode to UTF-8 for processing, then re-encode.
     *
     * @throws Throwable
     */
    #[Test]
    public function ifGlobalSightXliffRemoveTargetMarksHandlesUtf16EncodedContent(): void
    {
        $utf8 = '<?xml version="1.0" encoding="UTF-16"?>'
            . '<xliff version="1.2"><file original="doc" datatype="html" source-language="en-US">'
            . '<body><trans-unit id="1"><target>ciao</target></trans-unit></body></file></xliff>';

        // UTF-16LE with a little-endian BOM so `file --mime` reliably reports
        // charset=utf-16le (a BOM-less / big-endian blob is detected as binary).
        $utf16 = "\xFF\xFE" . mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8');
        // Sanity: the ASCII "<?xml " marker must not survive in the UTF-16 head,
        // otherwise the UTF-16 branch would not be taken.
        $this->assertFalse(stripos(substr($utf16, 0, 100), '<?xml '));

        $result = $this->controller->ifGlobalSightXliffRemoveTargetMarks($utf16, '/tmp/file.xlf');

        // The method always returns a string; the round-trip conversion branches ran.
        $this->assertIsString($result);
    }

    // ─── outputResultForRemoteFiles (private, echoes JSON from injected remoteFiles) ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function outputResultForRemoteFilesEchoesJsonUrls(): void
    {
        $this->setProp('remoteFiles', [
            9029020 => ['webViewLink' => 'https://drive.example/a'],
            9029021 => ['webViewLink' => 'https://drive.example/b'],
        ]);

        ob_start();
        $this->invokePrivate('outputResultForRemoteFiles');
        $output = ob_get_clean();

        $decoded = json_decode((string)$output, true);
        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded['urls']);
        $this->assertSame(9029020, $decoded['urls'][0]['localId']);
        $this->assertSame('https://drive.example/a', $decoded['urls'][0]['alternateLink']);
        $this->assertSame('https://drive.example/b', $decoded['urls'][1]['alternateLink']);
    }

    // ─── converted documents kept on disk ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function getOutputContentsWithZipFilesKeepsAnInputFilenameAlreadySet(): void
    {
        $input = [
            9029005 => [
                'output_filename'  => 'document.docx',
                'document_content' => null,
                'input_filename'   => '/tmp/converted',
                'out_xliff_name'   => '/tmp/document.xlf',
                'source'           => 'en-US',
                'target'           => 'it-IT',
            ],
        ];

        /** @var array<int|string, ZipContentObject> $result */
        $result = $this->invokePrivate('getOutputContentsWithZipFiles', [$input]);

        $this->assertSame('/tmp/converted', $result[9029005]->input_filename);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function storeConvertedDocumentWritesTheDocumentNextToTheXliff(): void
    {
        // 253 bytes, as getOutputContents() names it: a suffix would pass the 255 bytes a file name can have.
        $xliff = $this->fixtureDir . '/' . uniqid('', true) . '_.out.' . str_repeat('a', 210) . '.docx.sdlxliff';
        file_put_contents($xliff, '<xliff/>');

        $path = $this->invokePrivate('storeConvertedDocument', ['hello', 'document.docx', $xliff]);

        $this->assertSame($this->fixtureDir . '/converted', $path);
        $this->assertSame('hello', file_get_contents($path));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function storeConvertedDocumentWritesStringsFilesAsUtf16(): void
    {
        $path = $this->invokePrivate('storeConvertedDocument', ['ab', 'Localizable.strings', $this->fixtureDir . '/doc.xlf']);

        $this->assertSame(self::utf16WithoutBom('ab'), bin2hex((string)file_get_contents($path)));
    }

    /**
     * The hex of a text written as UTF-16 by this platform's iconv, BOM removed, as the download writes it.
     * The byte order follows iconv: little endian on Linux (production, CI), big endian on macOS.
     * Example: "ab" gives "61006200" on Linux.
     */
    private static function utf16WithoutBom(string $text): string
    {
        return bin2hex(substr((string)iconv('UTF-8', 'UTF-16', $text), 2));
    }

    // ─── whole downloads, end to end ───

    private ?string $activityBody = null;

    /** @var list<array<int|string, array<string, mixed>>> what each call to the converter received */
    private array $sentToFilters = [];

    /** @var array<int|string, string> the xliff each file sent to the converter held, by file id */
    private array $sentXliffs = [];

    /**
     * Runs a download action on the seeded job and returns what it echoed. What it streamed is
     * on $this->controller->stream, the activity it logged on $this->activityBody.
     *
     * @param array<string, string> $params
     *
     * @throws Throwable
     */
    private function runDownload(array $params, string $action = 'index'): string
    {
        $this->setProp('request', new Request($params + ['id_job' => (string)$this->jobId(self::BASE), 'password' => 'jobpw']));
        $this->controller->stream ??= new RecordingFileStream();

        $handler = $this->createStub(AMQHandler::class);
        $handler->method('publishToQueues')->willReturnCallback(function (string $queue, Message $message): bool {
            $this->activityBody = (string)$message->getBody();

            return true;
        });

        $savedHandler = WorkerClient::$_HANDLER;
        $savedQueues  = WorkerClient::$_QUEUES;
        $previousEnv  = AppConfig::$ENV;
        $obLevel      = ob_get_level();
        $output       = '';

        WorkerClient::init($handler);
        AppConfig::$ENV = 'testing';
        ob_start();
        // finalize() sends headers; under PHPUnit the output has already started.
        set_error_handler(static fn (int $errno, string $errstr): bool => str_contains($errstr, 'Cannot modify header information'), E_WARNING);

        try {
            $this->controller->$action();
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

        return $output;
    }

    private function xliff(bool $matecatConverter = false): string
    {
        $header = $matecatConverter ? '<header><tool tool-id="matecat-converter 1.1" tool-name="MateCAT Converter"/></header>' : '';

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">' . "\n"
            . '<file original="report.docx" source-language="en-US" target-language="it-IT" datatype="plaintext">'
            . $header . "\n"
            . '<body>' . "\n"
            . '<trans-unit id="1"><source>Hello world</source><target>Hello world</target></trans-unit>' . "\n"
            . '</body>' . "\n"
            . '</file>' . "\n"
            . '</xliff>' . "\n";
    }

    private function writeXliff(string $name, bool $matecatConverter = false): string
    {
        $path = $this->fixtureDir . '/' . $name;
        file_put_contents($path, $this->xliff($matecatConverter));

        return $path;
    }

    /**
     * A row of AbstractFilesStorage::getFilesForJob(). With no original the xliff is the
     * original, so it is not converted.
     *
     * @return array<string, mixed>
     */
    private function fileRow(int $idFile, string $filename, string $xliffPath, ?string $originalPath = '/originals/any.docx'): array
    {
        return [
            'id_file'          => $idFile,
            'filename'         => $filename,
            'id_project'       => $this->projectId(self::BASE),
            'mime_type'        => 'application/octet-stream',
            'xliffFilePath'    => $xliffPath,
            'originalFilePath' => $originalPath ?? $xliffPath,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function useFiles(array $rows, ?string $zipKey = null): void
    {
        $storage = $this->createStub(FsFilesStorage::class);
        $storage->method('getFilesForJob')->willReturn($rows);
        $storage->method('getOriginalZipPath')->willReturn((string)$zipKey);
        $this->controller->storage = $storage;
    }

    /**
     * A converter that answers "converted-<id_file>" for each file, or the given result.
     *
     * @param array<int, array<string, mixed>> $results
     */
    private function useConverter(array $results = []): void
    {
        $filters = $this->createStub(Filters::class);
        $filters->method('xliffToTarget')->willReturnCallback(function (array $files) use ($results): array {
            $this->sentToFilters[] = $files;
            $response = [];
            foreach ($files as $id => $file) {
                $this->sentXliffs[$id] = (string)file_get_contents($file['input_filename']);
                $response[$id] = $results[$id] ?? ['successful' => true, 'document_content' => 'converted-' . $id];
            }

            return $response;
        });
        $this->controller->filters = $filters;
    }

    private function stream(): RecordingFileStream
    {
        $stream = $this->controller->stream;
        $this->assertInstanceOf(RecordingFileStream::class, $stream);

        return $stream;
    }

    private function seedSecondProjectFile(): void
    {
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO files (id, id_project, filename, source_language, mime_type, sha1_original_file, is_converted) "
            . "VALUES (" . (self::BASE + 14) . ", " . $this->projectId(self::BASE) . ", 'second.docx', 'en-US', 'application/octet-stream', 'ctrltest', 1)"
        );
        $this->evictProjectFilesCache();
    }

    /**
     * FileDao::getByProjectId() keeps the files of a project in the cache for 10 minutes.
     *
     * @throws Throwable
     */
    private function evictProjectFilesCache(): void
    {
        $dao  = new FileDao(obtainTestDatabase());
        $stmt = obtainTestDatabase()->getConnection()->prepare("SELECT * FROM files where id_project = :id_project ");
        (new ReflectionMethod($dao, '_destroyObjectCache'))->invoke($dao, $stmt, FileStruct::class, ['id_project' => $this->projectId(self::BASE)]);
    }

    /**
     * @return array<string, string> entry name => content
     */
    private function zipEntries(string $zipBytes): array
    {
        $zipPath = $this->fixtureDir . '/read-' . uniqid() . '.zip';
        file_put_contents($zipPath, $zipBytes);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath));
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            $entries[$name] = (string)$zip->getFromIndex($i);
        }
        $zip->close();
        ksort($entries);

        return $entries;
    }

    private function deleteTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) && !is_link($path) ? $this->deleteTree($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Swaps the shared simple-s3 client for one whose AWS transport is $answer, runs $test and
     * puts the client of the configuration back.
     *
     * @param callable(CommandInterface): Result $answer
     * @param callable(): void $test
     *
     * @throws Throwable
     */
    private function withMockedS3(callable $answer, callable $test): void
    {
        $configured = S3FilesStorage::getStaticS3Client();
        $client     = new ReflectionProperty(S3FilesStorage::class, 'CLIENT');
        $client->setValue(null, new S3Client([
            'version'     => 'latest',
            'region'      => 'eu-central-1',
            'credentials' => ['key' => 'test', 'secret' => 'test'],
            'handler'     => static fn (CommandInterface $command) => \GuzzleHttp\Promise\Create::promiseFor($answer($command)),
        ]));

        try {
            $test();
        } finally {
            $client->setValue(null, $configured);
        }
    }

    /**
     * The local path a GetObject writes to: the SDK moves SaveAs to the sink of the HTTP request.
     */
    private function saveAs(CommandInterface $command): string
    {
        return (string)($command['SaveAs'] ?? $command['@http']['sink']);
    }

    /**
     * A remote_files pair (the translation and the original) on a connected service whose token
     * is still valid, so no call reaches Google.
     *
     * @throws Throwable
     */
    private function seedGDriveFiles(?string $token = null): void
    {
        $this->seedConnectedService(self::BASE);
        $token ??= (string)json_encode([
            'access_token'  => 'access',
            'refresh_token' => 'refresh',
            'token_type'    => 'Bearer',
            'expires_in'    => 3600,
            'created'       => time(),
        ]);
        $stmt = $this->seedConnection()->prepare("UPDATE connected_services SET oauth_access_token = ? WHERE id = ?");
        $stmt->execute([OauthTokenEncryption::getInstance()->encrypt($token), $this->connectedServiceId(self::BASE)]);

        $file = $this->fileId(self::BASE);
        $job  = $this->jobId(self::BASE);
        $cs   = $this->connectedServiceId(self::BASE);
        $conn = $this->seedConnection();
        $conn->exec("INSERT INTO files_job (id_job, id_file) VALUES ($job, $file)");
        $conn->exec("INSERT INTO remote_files (id, id_file, id_job, remote_id, is_original, connected_service_id) VALUES (9029020, $file, $job, 'remote_translation', 0, $cs)");
        $conn->exec("INSERT INTO remote_files (id, id_file, id_job, remote_id, is_original, connected_service_id) VALUES (9029021, $file, $job, 'remote_original', 1, $cs)");
    }

    /**
     * @throws Throwable
     */
    private function setSeededJob(): void
    {
        $this->setProp('id_job', $this->jobId(self::BASE));
        $this->setProp('job', (new JobDao(obtainTestDatabase()))->getByIdAndPassword($this->jobId(self::BASE), 'jobpw'));
    }

    /**
     * Writes the original zip of a project as the file storage keeps it and returns its path.
     *
     * @param array<string, string> $entries
     *
     * @throws Throwable
     */
    private function writeOriginalZip(string $zipName, array $entries): string
    {
        $project = (new \Model\Projects\ProjectDao(obtainTestDatabase()))->findById($this->projectId(self::BASE));
        $this->assertNotNull($project);
        $path = (new FsFilesStorage())->getOriginalZipPath($project->create_date, (string)$this->projectId(self::BASE), $zipName);
        @mkdir(dirname($path), 0775, true);
        $this->writeZip($path, $entries);

        return $path;
    }

    /**
     * @param array<string, string> $entries
     */
    private function writeZip(string $path, array $entries): void
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
    }

    private function deleteOriginalZip(string $path): void
    {
        @unlink($path);
        @rmdir(dirname($path));
        @rmdir(dirname($path, 2));
    }

    // ─── a single file ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function indexStreamsTheConvertedDocumentOfASingleFileJob(): void
    {
        $this->useFiles([$this->fileRow($this->fileId(self::BASE), 'report.docx', $this->writeXliff('report.docx.sdlxliff'))]);
        $this->useConverter();

        $this->runDownload([]);

        $this->assertSame('converted-' . $this->fileId(self::BASE), $this->stream()->body);
        $this->assertSame('report.docx', $this->stream()->filename);
        $this->assertSame('application/octet-stream', $this->stream()->mimeType);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function indexSendsTheXliffWithTheTranslationsToTheConverter(): void
    {
        $this->useFiles([$this->fileRow($this->fileId(self::BASE), 'report.docx', $this->writeXliff('report.docx.sdlxliff'))]);
        $this->useConverter();

        $this->runDownload([]);

        $file = $this->sentToFilters[0][$this->fileId(self::BASE)];
        $this->assertSame('en-US', $file['source']);
        $this->assertSame('it-IT', $file['target']);
        $this->assertSame($file['out_xliff_name'], $file['input_filename']);
        $this->assertStringContainsString('>Ciao mondo</target>', $this->sentXliffs[$this->fileId(self::BASE)]);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function indexDeletesTheWorkFolderOnceTheFileIsSent(): void
    {
        $this->useFiles([$this->fileRow($this->fileId(self::BASE), 'report.docx', $this->writeXliff('report.docx.sdlxliff'))]);
        $this->useConverter();

        $this->runDownload([]);

        $this->assertDirectoryDoesNotExist(AppConfig::$TMP_DOWNLOAD . '/' . $this->jobId(self::BASE));
    }

    /**
     * A file the converter cannot convert back fails the whole download: the intermediate xliff
     * must not be sent in place of the document.
     *
     * @throws Throwable
     */
    #[Test]
    public function indexFailsTheDownloadWhenAFileCannotBeConvertedBack(): void
    {
        $this->useFiles([$this->fileRow($this->fileId(self::BASE), 'report.docx', $this->writeXliff('report.docx.sdlxliff'))]);
        $this->useConverter([$this->fileId(self::BASE) => ['successful' => false, 'errorMessage' => 'converter down']]);

        try {
            $this->runDownload(['downloadToken' => 'token-1']);
            $this->fail('A failed conversion must fail the download');
        } catch (ExternalServiceException $e) {
            $this->assertStringStartsWith('Download failed: a file could not be converted back', $e->getMessage());
        }

        $this->assertNull($this->stream()->body);
        $this->assertDirectoryDoesNotExist(AppConfig::$TMP_DOWNLOAD . '/' . $this->jobId(self::BASE));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function indexAcceptsTheReviewPasswordOfTheJob(): void
    {
        $this->seedChunkReview(self::BASE, 'jobpw', 'revpw');
        $this->useFiles([$this->fileRow($this->fileId(self::BASE), 'report.docx', $this->writeXliff('report.docx.sdlxliff'))]);
        $this->useConverter();

        $this->runDownload(['password' => 'revpw']);

        $this->assertSame('converted-' . $this->fileId(self::BASE), $this->stream()->body);
    }

    /**
     * A file the converter does not handle (its xliff is its original) is sent as the xliff,
     * and a file with no segments is copied as it is.
     *
     * @throws Throwable
     */
    #[Test]
    public function indexCopiesTheXliffOfAFileWithNoSegmentsAsItIs(): void
    {
        $xliff = $this->writeXliff('empty.xlf');
        $this->useFiles([$this->fileRow(self::BASE + 14, 'empty.xlf', $xliff, null)]);
        $this->useConverter();

        $this->runDownload([]);

        $this->assertSame($this->xliff(), $this->stream()->body);
        $this->assertSame([], $this->sentToFilters[0]);
        $this->assertSame('application/xliff+xml', $this->stream()->mimeType);
    }

    /**
     * With the error check on, a translation that breaks the size limit of its segment is not
     * written to the target; with disableErrorCheck it is.
     *
     * @throws Throwable
     */
    #[Test]
    public function indexWritesTranslationsWithErrorsWhenTheErrorCheckIsDisabled(): void
    {
        $this->seedConnection()->exec(
            "UPDATE segment_translations SET serialized_errors_list = '[{\"outcome\":3000}]' WHERE id_job = " . $this->jobId(self::BASE)
        );
        $this->useFiles([$this->fileRow($this->fileId(self::BASE), 'report.xlf', $this->writeXliff('report.xlf'), null)]);
        $this->useConverter();

        $this->runDownload(['disableErrorCheck' => '1']);

        $this->assertStringContainsString('>Ciao mondo</target>', (string)$this->stream()->body);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function indexDoesNotWriteATranslationThatFailsTheErrorCheck(): void
    {
        $this->seedConnection()->exec(
            "UPDATE segment_translations SET serialized_errors_list = '[{\"outcome\":3000}]' WHERE id_job = " . $this->jobId(self::BASE)
        );
        $this->useFiles([$this->fileRow($this->fileId(self::BASE), 'report.xlf', $this->writeXliff('report.xlf'), null)]);
        $this->useConverter();

        $this->runDownload([]);

        $this->assertStringNotContainsString('Ciao mondo', (string)$this->stream()->body);
    }

    /**
     * forceXliff sends the xliff of a converted file instead of converting it back. The name of a
     * single xliff gets ".xlf" twice: once in processDownload() and once more when the name is set.
     *
     * @throws Throwable
     */
    #[Test]
    public function forceXliffSendsTheXliffOfAConvertedFile(): void
    {
        $this->useFiles([$this->fileRow($this->fileId(self::BASE), 'report.docx', $this->writeXliff('report.docx.sdlxliff'))]);
        $this->useConverter();

        $this->runDownload([], 'forceXliff');

        $this->assertStringContainsString('>Ciao mondo</target>', (string)$this->stream()->body);
        $this->assertSame([], $this->sentToFilters[0]);
        $this->assertSame('report.docx.xlf.xlf', $this->stream()->filename);
        $this->assertSame('application/xliff+xml', $this->stream()->mimeType);
    }

    /**
     * The xliff of the MateCat converter loses the ".xlf" it already had before one more is added,
     * where any other xliff keeps it: "report.xlf" gives "report.xlf.xlf", not "report.xlf.xlf.xlf".
     *
     * @throws Throwable
     */
    #[Test]
    public function forceXliffDropsTheRepeatedXlfOfAMatecatConverterXliff(): void
    {
        $this->useFiles([$this->fileRow($this->fileId(self::BASE), 'report.xlf', $this->writeXliff('report.xlf', true))]);
        $this->useConverter();

        $this->runDownload(['forceXliff' => '1']);

        $this->assertSame('report.xlf.xlf', $this->stream()->filename);
    }

    /**
     * iOS reads a .strings file as UTF-16 with no BOM.
     *
     * @throws Throwable
     */
    #[Test]
    public function indexWritesAStringsFileAsUtf16(): void
    {
        $this->useFiles([$this->fileRow($this->fileId(self::BASE), 'Localizable.strings', $this->writeXliff('Localizable.strings.xlf'))]);
        $this->useConverter([$this->fileId(self::BASE) => ['successful' => true, 'document_content' => 'ab']]);

        $this->runDownload([]);

        $this->assertSame(self::utf16WithoutBom('ab'), bin2hex((string)$this->stream()->body));
    }

    /**
     * On S3 the xliff is first copied to the work folder, under a name that never starts with a dot.
     *
     * @throws Throwable
     */
    #[Test]
    public function indexCopiesTheXliffFromS3BeforeRebuildingIt(): void
    {
        AppConfig::$FILE_STORAGE_METHOD = 's3';
        $key = 'files/20261110/' . $this->fileId(self::BASE) . '/.report.docx.sdlxliff';
        $this->useFiles([$this->fileRow($this->fileId(self::BASE), 'report.docx', $key)]);
        $this->useConverter();
        $requests = [];

        $this->withMockedS3(function (CommandInterface $command) use (&$requests): Result {
            $requests[] = [$command->getName(), $command['Bucket'], $command['Key'], basename($this->saveAs($command))];
            file_put_contents($this->saveAs($command), $this->xliff());

            return new Result(['@metadata' => ['statusCode' => 200]]);
        }, function (): void {
            $this->runDownload([]);
        });

        $this->assertSame([['GetObject', AppConfig::$AWS_STORAGE_BASE_BUCKET, $key, 'source_.report.docx.sdlxliff']], $requests);
        $this->assertSame('converted-' . $this->fileId(self::BASE), $this->stream()->body);
    }

    // ─── many files ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function indexZipsTheFilesOfAJobWithManyFiles(): void
    {
        $this->seedSecondProjectFile();
        $this->useFiles([
            $this->fileRow($this->fileId(self::BASE), 'a.docx', $this->writeXliff('a.docx.sdlxliff')),
            $this->fileRow(self::BASE + 14, 'b.docx', $this->writeXliff('b.docx.sdlxliff')),
        ]);
        $this->useConverter();

        $this->runDownload([]);

        $this->assertSame(
            ['a.docx' => 'converted-' . $this->fileId(self::BASE), 'b.docx' => 'converted-' . (self::BASE + 14)],
            $this->zipEntries((string)$this->stream()->body)
        );
        $this->assertSame('CtrlTestProject_' . self::BASE . '.zip', $this->stream()->filename);
        $this->assertSame('application/zip', $this->stream()->mimeType);
    }

    /**
     * The zip is named after the project file when the project has one file only.
     *
     * @throws Throwable
     */
    #[Test]
    public function indexNamesTheZipAfterTheOnlyProjectFile(): void
    {
        $this->useFiles([
            $this->fileRow($this->fileId(self::BASE), 'a.docx', $this->writeXliff('a.docx.sdlxliff')),
            $this->fileRow(self::BASE + 14, 'b.docx', $this->writeXliff('b.docx.sdlxliff')),
        ]);
        $this->useConverter();

        $this->runDownload([]);

        $this->assertSame('ctrltest_' . self::BASE . '.xliff.zip', $this->stream()->filename);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function forceXliffZipsTheXliffsOfManyFilesUnderTheJobId(): void
    {
        $this->useFiles([
            $this->fileRow($this->fileId(self::BASE), 'a.docx', $this->writeXliff('a.docx.sdlxliff')),
            $this->fileRow(self::BASE + 14, 'b.docx', $this->writeXliff('b.docx.sdlxliff')),
        ]);
        $this->useConverter();

        $this->runDownload([], 'forceXliff');

        $this->assertSame(['a.docx.xlf', 'b.docx.xlf'], array_keys($this->zipEntries((string)$this->stream()->body)));
        $this->assertSame($this->jobId(self::BASE) . '.zip', $this->stream()->filename);
    }

    /**
     * Files that came from a zip go back into a copy of that zip: converted files replace their
     * originals, a pdf comes back as a docx, a tmx is dropped and anything else is kept.
     *
     * @throws Throwable
     */
    #[Test]
    public function indexPutsTheFilesOfAnUploadedZipBackIntoIt(): void
    {
        $zipPath = $this->writeOriginalZip('pack.zip', [
            'sub/doc.docx' => 'original-doc',
            'scan.pdf'     => 'original-pdf',
            'memory.tmx'   => 'tm',
            'keep.txt'     => 'keep',
        ]);
        $this->useFiles([
            $this->fileRow($this->fileId(self::BASE), 'pack.zip___SEP___sub___SEP___doc.docx', $this->writeXliff('doc.docx.sdlxliff')),
            $this->fileRow(self::BASE + 14, 'pack.zip___SEP___scan.pdf', $this->writeXliff('scan.pdf.sdlxliff')),
        ]);
        $this->useConverter();

        try {
            $this->runDownload([]);
        } finally {
            $this->deleteOriginalZip($zipPath);
        }

        $this->assertSame([
            'keep.txt'      => 'keep',
            'scan.pdf.docx' => 'converted-' . (self::BASE + 14),
            'sub/doc.docx'  => 'converted-' . $this->fileId(self::BASE),
        ], $this->zipEntries((string)$this->stream()->body));
        $this->assertSame('pack.zip', $this->stream()->filename);
    }

    /**
     * With forceXliff the xliff of a zipped file is added next to where its original was.
     *
     * @throws Throwable
     */
    #[Test]
    public function forceXliffPutsTheXliffOfAZippedFileIntoTheZip(): void
    {
        $zipPath = $this->writeOriginalZip('pack.zip', ['sub/doc.docx' => 'original-doc', 'keep.txt' => 'keep']);
        $this->useFiles([
            $this->fileRow($this->fileId(self::BASE), 'pack.zip___SEP___sub___SEP___doc.docx', $this->writeXliff('doc.docx.sdlxliff')),
        ]);
        $this->useConverter();

        try {
            $this->runDownload([], 'forceXliff');
        } finally {
            $this->deleteOriginalZip($zipPath);
        }

        $entries = $this->zipEntries((string)$this->stream()->body);
        $this->assertSame(['keep.txt', 'sub/doc.docx.xlf'], array_keys($entries));
        $this->assertStringContainsString('>Ciao mondo</target>', $entries['sub/doc.docx.xlf']);
    }

    // ─── OmegaT ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function indexBuildsAnOmegatPackage(): void
    {
        $this->useFiles([$this->fileRow($this->fileId(self::BASE), 'report.xlf', $this->writeXliff('report.xlf'), null)]);
        $this->useConverter();

        $this->runDownload(['download_type' => 'omegat']);

        $entries = $this->zipEntries((string)$this->stream()->body);
        $job     = $this->jobId(self::BASE);
        $this->assertArrayHasKey($job . '/omegat.project', $entries);
        // OmegaT gets the source in the target, to translate it again.
        $this->assertStringContainsString('>Hello world</target>', $entries[$job . '/inbox/report.xlf']);
        $this->assertSame('ctrltest_' . self::BASE . '_it-IT.xliff.zip', $this->stream()->filename);
    }

    // ─── activity log ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function indexLogsAPreviewDownloadOfAnIncompleteJob(): void
    {
        $this->useFiles([$this->fileRow($this->fileId(self::BASE), 'report.docx', $this->writeXliff('report.docx.sdlxliff'))]);
        $this->useConverter();

        $this->runDownload([]);

        $this->assertMatchesRegularExpression('/"action":' . ActivityLogStruct::DOWNLOAD_PREVIEW . '(?!\d)/', (string)$this->activityBody);
        $this->assertMatchesRegularExpression('/"id_job":' . $this->jobId(self::BASE) . '(?!\\d)/', (string)$this->activityBody);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function indexLogsATranslationDownloadOfACompleteJob(): void
    {
        (new RedisHandler())->getConnection()->set('job_completeness:' . $this->jobId(self::BASE), '1');
        $this->useFiles([$this->fileRow($this->fileId(self::BASE), 'report.docx', $this->writeXliff('report.docx.sdlxliff'))]);
        $this->useConverter();

        $this->runDownload([]);

        $this->assertMatchesRegularExpression('/"action":' . ActivityLogStruct::DOWNLOAD_TRANSLATION . '(?!\d)/', (string)$this->activityBody);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function forceXliffLogsAnXliffDownload(): void
    {
        $this->useFiles([$this->fileRow($this->fileId(self::BASE), 'report.docx', $this->writeXliff('report.docx.sdlxliff'))]);
        $this->useConverter();

        $this->runDownload([], 'forceXliff');

        $this->assertMatchesRegularExpression('/"action":' . ActivityLogStruct::DOWNLOAD_XLIFF . '(?!\d)/', (string)$this->activityBody);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function indexLogsAnOmegatDownload(): void
    {
        $this->useFiles([$this->fileRow($this->fileId(self::BASE), 'report.xlf', $this->writeXliff('report.xlf'), null)]);
        $this->useConverter();

        $this->runDownload(['download_type' => 'omegat']);

        $this->assertMatchesRegularExpression('/"action":' . ActivityLogStruct::DOWNLOAD_OMEGAT . '(?!\d)/', (string)$this->activityBody);
    }

    // ─── Google Drive ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function indexUploadsTheTranslationOfAGDriveFileAndAnswersItsLink(): void
    {
        $this->seedGDriveFiles();
        $this->useFiles([$this->fileRow($this->fileId(self::BASE), 'report.docx', $this->writeXliff('report.docx.sdlxliff'))]);
        $this->useConverter();
        $uploaded = [];
        $service  = $this->createStub(RemoteFileService::class);
        $service->method('updateFile')->willReturnCallback(function (RemoteFileStruct $remoteFile, string $content) use (&$uploaded): Google_Service_Drive_DriveFile {
            $uploaded[$remoteFile->remote_id] = $content;

            return new Google_Service_Drive_DriveFile(['webViewLink' => 'https://drive.example/translation']);
        });
        $this->controller->remoteService = $service;

        $output = $this->runDownload([]);

        $this->assertSame(['remote_translation' => 'converted-' . $this->fileId(self::BASE)], $uploaded);
        $this->assertSame('{"urls":[{"localId":9029020,"alternateLink":"https:\/\/drive.example\/translation"}]}', $output);
        $this->assertNull($this->stream()->body);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function indexLogsAGDrivePreviewDownload(): void
    {
        $this->seedGDriveFiles();
        $this->useFiles([$this->fileRow($this->fileId(self::BASE), 'report.docx', $this->writeXliff('report.docx.sdlxliff'))]);
        $this->useConverter();
        $service = $this->createStub(RemoteFileService::class);
        $service->method('updateFile')->willReturn(new Google_Service_Drive_DriveFile(['webViewLink' => 'https://drive.example/translation']));
        $this->controller->remoteService = $service;

        $this->runDownload([]);

        $this->assertMatchesRegularExpression('/"action":' . ActivityLogStruct::DOWNLOAD_GDRIVE_PREVIEW . '(?!\d)/', (string)$this->activityBody);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function indexAnswersTheLinksOfTheGDriveOriginals(): void
    {
        $this->seedGDriveFiles();
        $this->useFiles([$this->fileRow($this->fileId(self::BASE), 'report.docx', $this->writeXliff('report.docx.sdlxliff'))]);
        $this->useConverter();
        $service = $this->createStub(RemoteFileService::class);
        $service->method('getFileLink')->willReturnMap([
            ['remote_original', new Google_Service_Drive_DriveFile(['webViewLink' => 'https://drive.example/original'])],
        ]);
        $this->controller->remoteService = $service;

        $output = $this->runDownload(['original' => '1']);

        $this->assertSame('{"urls":[{"localId":9029021,"alternateLink":"https:\/\/drive.example\/original"}]}', $output);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function indexFailsWhenAGDriveOriginalHasNoLink(): void
    {
        $this->seedGDriveFiles();
        $this->useFiles([$this->fileRow($this->fileId(self::BASE), 'report.docx', $this->writeXliff('report.docx.sdlxliff'))]);
        $this->useConverter();
        $service = $this->createStub(RemoteFileService::class);
        $service->method('getFileLink')->willReturn(new Google_Service_Drive_DriveFile());
        $this->controller->remoteService = $service;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('alternateLink was not found for file with local ID 9029021');

        $this->runDownload(['original' => '1']);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function startRemoteFileServiceFailsWhenTheFileIsNotOnGDrive(): void
    {
        $this->setSeededJob();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Remote file not found');

        $this->invokePrivate('startRemoteFileService', [[self::BASE + 99 => []]]);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function startRemoteFileServiceFailsWhenTheConnectedServiceIsDisabled(): void
    {
        $this->seedGDriveFiles();
        $this->seedConnection()->exec("UPDATE connected_services SET disabled_at = NOW() WHERE id = " . $this->connectedServiceId(self::BASE));
        $this->setSeededJob();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Connected service missing or disabled');

        $this->invokePrivate('startRemoteFileService', [[$this->fileId(self::BASE) => []]]);
    }

    /**
     * A token Google cannot read is not refreshed: the service is marked expired.
     *
     * @throws Throwable
     */
    #[Test]
    public function startRemoteFileServiceFailsWhenTheTokenCannotBeRefreshed(): void
    {
        $this->seedGDriveFiles('{"no_access_token":true}');
        $this->setSeededJob();

        try {
            $this->invokePrivate('startRemoteFileService', [[$this->fileId(self::BASE) => []]]);
            $this->fail('An unusable token must stop the download');
        } catch (Exception $e) {
            $this->assertSame('Unable to refresh token for service', $e->getMessage());
        }

        $expired = $this->seedConnection()->query("SELECT expired_at FROM connected_services WHERE id = " . $this->connectedServiceId(self::BASE))->fetchColumn();
        $this->assertNotNull($expired);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function updateRemoteFilesFailsWhenTheFileIsNotOnGDrive(): void
    {
        $this->setSeededJob();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Remote file not found');

        $this->invokePrivate('updateRemoteFiles', [[self::BASE + 99 => new ZipContentObject(['output_filename' => 'a.docx'])]]);
    }

    // ─── failures ───

    /**
     * A failed download tells the waiting browser through the token cookie, then fails.
     *
     * @throws Throwable
     */
    #[Test]
    public function indexReleasesTheDownloadTokenWhenTheDownloadFails(): void
    {
        $this->useFiles([]);
        $this->useConverter();

        try {
            $this->runDownload(['downloadToken' => 'token-1']);
            $this->fail('A job with no files cannot be downloaded');
        } catch (Exception $e) {
            $this->assertSame('Unexpected output content', $e->getMessage());
        }

        $this->assertNull((new ReflectionProperty(DownloadController::class, 'downloadToken'))->getValue($this->controller));
        $this->assertDirectoryDoesNotExist(AppConfig::$TMP_DOWNLOAD . '/' . $this->jobId(self::BASE));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function storeConvertedDocumentFailsWhenAStringsFileCannotBeConverted(): void
    {
        $xliff = $this->fixtureDir . '/doc.xlf';
        set_error_handler(static fn (): bool => true, E_NOTICE | E_WARNING);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Failed to convert encoding for .strings file');

            $this->invokePrivate('storeConvertedDocument', ["\x00\x01\x02\xFF\xFE\x00binary", 'Localizable.strings', $xliff]);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function storeConvertedDocumentFailsWhenTheFileCannotBeWritten(): void
    {
        set_error_handler(static fn (): bool => true, E_WARNING);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Failed to write the converted file: ' . $this->fixtureDir . '/missing/converted');

            $this->invokePrivate('storeConvertedDocument', ['content', 'doc.docx', $this->fixtureDir . '/missing/doc.xlf']);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function pathinfoStringRefusesAnOptionThatGivesAnArray(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unexpected pathinfo result');

        $this->invokePrivate('pathinfoString', ['/a/doc.xlf', PATHINFO_ALL]);
    }

    // ─── reBuildZipContent ───

    /**
     * A converted file not on disk yet is added from its content.
     *
     * @throws Throwable
     */
    #[Test]
    public function reBuildZipContentAddsAFileFromItsContent(): void
    {
        $this->setSeededJob();
        $zipPath = $this->writeOriginalZip('pack.zip', ['doc.docx' => 'original-doc']);

        try {
            $rebuilt = $this->controller->reBuildZipContent('pack.zip', [
                new ZipContentObject(['output_filename' => 'doc.docx', 'document_content' => 'in-memory']),
            ]);
            $entries = $this->zipEntries((string)file_get_contents($rebuilt));
        } finally {
            $this->deleteOriginalZip($zipPath);
            $this->invokePrivate('deleteWorkDir');
        }

        $this->assertSame(['doc.docx' => 'in-memory'], $entries);
    }

    /**
     * On S3 the original zip is copied from the bucket when it is not on the local disk.
     *
     * @throws Throwable
     */
    #[Test]
    public function reBuildZipContentCopiesTheOriginalZipFromS3(): void
    {
        AppConfig::$FILE_STORAGE_METHOD = 's3';
        $this->setSeededJob();
        $this->useFiles([], 'originalZip/20261110/' . $this->projectId(self::BASE) . '/pack.zip');
        $zipBytes = $this->fixtureDir . '/pack.zip';
        $this->writeZip($zipBytes, ['doc.docx' => 'original-doc', 'keep.txt' => 'keep']);
        $keys    = [];
        $entries = [];

        $this->withMockedS3(function (CommandInterface $command) use (&$keys, $zipBytes): Result {
            $keys[] = $command['Key'];
            copy($zipBytes, $this->saveAs($command));

            return new Result(['@metadata' => ['statusCode' => 200]]);
        }, function () use (&$entries): void {
            try {
                $rebuilt = $this->controller->reBuildZipContent('pack.zip', [
                    new ZipContentObject(['output_filename' => 'doc.docx', 'document_content' => 'translated']),
                ]);
                $entries = $this->zipEntries((string)file_get_contents($rebuilt));
            } finally {
                $this->invokePrivate('deleteWorkDir');
            }
        });

        $this->assertSame(['originalZip/20261110/' . $this->projectId(self::BASE) . '/pack.zip'], $keys);
        $this->assertSame(['doc.docx' => 'translated', 'keep.txt' => 'keep'], $entries);
    }

    /**
     * ZipArchive reads the files added by path in close(): a file it cannot read fails there.
     *
     * @throws Throwable
     */
    #[Test]
    public function reBuildZipContentFailsWhenTheZipCannotBeWritten(): void
    {
        $this->setSeededJob();
        $zipPath = $this->writeOriginalZip('pack.zip', ['doc.docx' => 'original-doc']);
        // The file goes away once it is added, so close() cannot read it, also for root (CI).
        $gone = $this->fixtureDir . '/gone.docx';
        file_put_contents($gone, 'secret');
        $vanishing = new class (['output_filename' => 'doc.docx', 'input_filename' => $gone]) extends ZipContentObject {
            public function addToZip(ZipArchive $zip, string $entryName): void
            {
                parent::addToZip($zip, $entryName);
                unlink((string)$this->input_filename);
            }
        };
        set_error_handler(static fn (): bool => true, E_WARNING);

        try {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Unable to write the zip file pack.zip');

            $this->controller->reBuildZipContent('pack.zip', [$vanishing]);
        } finally {
            restore_error_handler();
            $this->deleteOriginalZip($zipPath);
            $this->invokePrivate('deleteWorkDir');
        }
    }

    /**
     * A stored zip that is not a zip fails with an error, not with the ValueError of closing an
     * archive that never opened.
     *
     * @throws Throwable
     */
    #[Test]
    public function reBuildZipContentFailsWhenTheOriginalZipCannotBeOpened(): void
    {
        $this->setSeededJob();
        $zipPath = $this->writeOriginalZip('pack.zip', []);
        file_put_contents($zipPath, 'not a zip');

        try {
            $this->controller->reBuildZipContent('pack.zip', []);
            $this->fail('reBuildZipContent() must fail on a corrupt zip');
        } catch (Exception $e) {
            $this->assertSame(Exception::class, $e::class);
            $this->assertSame('Unable to open the zip file pack.zip: error ' . ZipArchive::ER_NOZIP, $e->getMessage());
        } finally {
            $this->deleteOriginalZip($zipPath);
            $this->invokePrivate('deleteWorkDir');
        }
    }

    /**
     * tempnam() falls back to the system temp folder, so it fails only when open_basedir bars the
     * work folder. open_basedir cannot be relaxed once set, hence the separate process.
     *
     * @throws Throwable
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function reBuildZipContentFailsWhenItCannotCreateATemporaryFile(): void
    {
        $this->setSeededJob();
        // A work folder outside every path open_basedir allows; it is never created.
        $workDir = new ReflectionProperty(AbstractDownloadController::class, 'workDir');
        $workDir->setValue($this->controller, '/matecat-download-test-' . uniqid());
        // PHPUnit still needs its own temp files and, under coverage, its cache.
        $configuration = Registry::get();
        ini_set('open_basedir', implode(PATH_SEPARATOR, array_filter([
            self::projectRoot(),
            (string)realpath(sys_get_temp_dir()),
            (string)realpath(AppConfig::$LOG_REPOSITORY),
            $configuration->hasCacheDirectory() ? $configuration->cacheDirectory() : '',
        ])));
        set_error_handler(static fn (int $errno, string $errstr): bool => str_contains($errstr, 'open_basedir restriction'), E_WARNING);

        try {
            $this->controller->reBuildZipContent('pack.zip', []);
            $this->fail('reBuildZipContent() must fail without a temporary file');
        } catch (Exception $e) {
            $this->assertSame('Unable to create a temporary file for the zip archive', $e->getMessage());
        } finally {
            restore_error_handler();
            $workDir->setValue($this->controller, null);
        }
    }

    // ─── seams ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function getFilesStorageFollowsTheConfiguredStorage(): void
    {
        $this->assertInstanceOf(FsFilesStorage::class, $this->invokePrivate('getFilesStorage'));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function createFiltersGivesTheConverterClient(): void
    {
        $this->assertInstanceOf(Filters::class, $this->invokePrivate('createFilters'));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function createRemoteFileServiceGivesADriveClientOnTheToken(): void
    {
        $client = new Google_Client();

        $service = $this->invokePrivate('createRemoteFileService', ['{"access_token":"access"}', $client]);

        $this->assertInstanceOf(RemoteFileService::class, $service);
        $this->assertSame('access', $client->getAccessToken()['access_token']);
    }
}
