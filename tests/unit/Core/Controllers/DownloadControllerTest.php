<?php

namespace Matecat\Core\Controllers;

use Controller\API\V2\DownloadController;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\Exceptions\NotFoundException;
use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Throwable;
use Utils\Logger\MatecatLogger;
use View\API\Commons\ZipContentObject;

/**
 * Dedicated real-DB suite for {@see DownloadController} (Wave 5 task #29a).
 *
 * Reserved ID block (Playbook §4): base = 9_029_000.
 *   base+1 (9029001) project, base+2 (9029002) job, base+3 (9029003) segment,
 *   base+4 (9029004) file, base+11 (9029011) connected_service.
 * remote_files rows use ids 9029020/9029021 (still inside the reserved block).
 * Per-suite owner email: ctrltest_9029000@example.org.
 *
 * The full file-streaming download path (processDownload happy branch) requires
 * real XLIFF files on disk, the external converter service, ob_* / exit() and
 * Redis; it is exercised only at its DB-failure boundary here. The pure helpers
 * (filename / path / globalsight cleanup / zip-content assembly) and the
 * DB-backed remote-file lookup are fully covered.
 */
class TestableDownloadController extends DownloadController
{
    public function __construct()
    {
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

    /**
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

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
    }

    /**
     * @throws \PDOException
     */
    private function cleanTestData(): void
    {
        $conn = $this->seedConnection();
        $conn->exec("DELETE FROM remote_files WHERE id IN (9029020, 9029021)");
        $this->cleanFragments(self::BASE);
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
        // out_xliff_name was renamed to input_filename for ZipContentObject.
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
}
