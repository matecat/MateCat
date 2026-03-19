<?php

namespace unit\Model\ProjectCreation;

use Exception;
use Model\Files\MetadataDao;
use Model\FilesStorage\AbstractFilesStorage;
use Model\ProjectCreation\ProjectCreationError;
use Model\ProjectCreation\ProjectManagerModel;
use Model\ProjectCreation\ProjectStructure;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;

/**
 * Unit tests for {@see \Model\ProjectCreation\FileInsertionService::registerNativeXliffsAsConverted()}.
 *
 * Verifies:
 * - Files with mustBeConverted=true are skipped
 * - Correct calls to makeCachePackage + linkSessionToCacheForAlreadyConvertedFiles
 * - Hash keys are correctly appended to $linkFiles['conversionHashes']
 * - Duplicate hashes are deduplicated
 * - Project error added when sha1_file fails
 * - Project error added when makeCachePackage throws
 */
class RegisterNativeXliffsAsConvertedTest extends AbstractTest
{
    private TestableFileInsertionService $service;
    private ProjectStructure $projectStructure;
    private string $uploadDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TestableFileInsertionService(
            $this->createStub(ProjectManagerModel::class),
            $this->createStub(MetadataDao::class),
            null, // no GDrive session
            (function (string $fileName): void {})(...),
            $this->createStub(MatecatLogger::class),
        );

        $this->uploadDir = sys_get_temp_dir() . '/matecat_test_upload_' . uniqid();
        mkdir($this->uploadDir, 0777, true);

        $this->projectStructure = new ProjectStructure([
            'source_language' => 'en-US',
            'uploadToken' => 'test-token-123',
            'array_files' => [],
            'array_files_meta' => [],
            'result' => ['errors' => []],
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        if (is_dir($this->uploadDir)) {
            array_map('unlink', glob($this->uploadDir . '/*') ?: []);
            rmdir($this->uploadDir);
        }
        parent::tearDown();
    }

    // ── Skipping converted files ─────────────────────────────────────

    /**
     * @return void
     * @throws Exception
     */
    #[Test]
    public function skipsFilesWithMustBeConvertedTrue(): void
    {
        $this->projectStructure->array_files = ['document.docx'];
        $this->projectStructure->array_files_meta = [
            ['mustBeConverted' => true],
        ];

        $linkFiles = ['conversionHashes' => ['sha' => [], 'fileName' => []]];

        $fs = $this->createMock(AbstractFilesStorage::class);
        $fs->expects($this->never())->method('makeCachePackage');
        $fs->expects($this->never())->method('linkSessionToCacheForAlreadyConvertedFiles');

        $this->service->registerNativeXliffsAsConverted(
            $fs,
            $this->projectStructure,
            $this->uploadDir,
            $linkFiles
        );

        $this->assertEmpty($linkFiles['conversionHashes']['sha']);
    }

    // ── Successful processing of a native XLIFF ──────────────────────

    /**
     * @return void
     * @throws Exception
     */
    #[Test]
    public function processesNativeXliffAndAppendsHashToLinkFiles(): void
    {
        $fileName = 'test_file.xliff';
        $filePath = $this->uploadDir . '/' . $fileName;
        file_put_contents($filePath, '<xliff>content</xliff>');
        $expectedSha1 = sha1_file($filePath);

        $this->projectStructure->array_files = [$fileName];
        $this->projectStructure->array_files_meta = [
            ['mustBeConverted' => false],
        ];

        $linkFiles = ['conversionHashes' => ['sha' => [], 'fileName' => []]];

        $fs = $this->createMock(AbstractFilesStorage::class);
        $fs->expects($this->once())
            ->method('makeCachePackage')
            ->with($expectedSha1, 'en-US', null, $filePath);

        $fs->expects($this->once())
            ->method('linkSessionToCacheForAlreadyConvertedFiles')
            ->with($expectedSha1, 'test-token-123', $fileName);

        $this->service->registerNativeXliffsAsConverted(
            $fs,
            $this->projectStructure,
            $this->uploadDir,
            $linkFiles
        );

        $expectedHashKey = $expectedSha1 . '__' . 'en-US';
        $this->assertContains($expectedHashKey, $linkFiles['conversionHashes']['sha']);
        $this->assertSame([$fileName], $linkFiles['conversionHashes']['fileName'][$expectedHashKey]);
        $this->assertEmpty($this->projectStructure->result['errors']);
    }

    // ── Multiple native XLIFFs ───────────────────────────────────────

    /**
     * @return void
     * @throws Exception
     */
    #[Test]
    public function processesMultipleNativeXliffs(): void
    {
        $file1 = 'first.xliff';
        $file2 = 'second.xliff';
        file_put_contents($this->uploadDir . '/' . $file1, 'content1');
        file_put_contents($this->uploadDir . '/' . $file2, 'content2');

        $this->projectStructure->array_files = [$file1, $file2];
        $this->projectStructure->array_files_meta = [
            ['mustBeConverted' => false],
            ['mustBeConverted' => false],
        ];

        $linkFiles = ['conversionHashes' => ['sha' => [], 'fileName' => []]];

        $fs = $this->createMock(AbstractFilesStorage::class);
        $fs->expects($this->exactly(2))->method('makeCachePackage');
        $fs->expects($this->exactly(2))->method('linkSessionToCacheForAlreadyConvertedFiles');

        $this->service->registerNativeXliffsAsConverted(
            $fs,
            $this->projectStructure,
            $this->uploadDir,
            $linkFiles
        );

        $this->assertCount(2, $linkFiles['conversionHashes']['sha']);
    }

    // ── Mix of converted and native files ────────────────────────────

    /**
     * @return void
     * @throws Exception
     */
    #[Test]
    public function onlyProcessesNativeXliffsInMixedFileList(): void
    {
        $nativeFile = 'native.xliff';
        file_put_contents($this->uploadDir . '/' . $nativeFile, 'native content');

        $this->projectStructure->array_files = ['converted.docx', $nativeFile, 'another_converted.pdf'];
        $this->projectStructure->array_files_meta = [
            ['mustBeConverted' => true],
            ['mustBeConverted' => false],
            ['mustBeConverted' => true],
        ];

        $linkFiles = ['conversionHashes' => ['sha' => [], 'fileName' => []]];

        $fs = $this->createMock(AbstractFilesStorage::class);
        $fs->expects($this->once())->method('makeCachePackage');
        $fs->expects($this->once())->method('linkSessionToCacheForAlreadyConvertedFiles');

        $this->service->registerNativeXliffsAsConverted(
            $fs,
            $this->projectStructure,
            $this->uploadDir,
            $linkFiles
        );

        $this->assertCount(1, $linkFiles['conversionHashes']['sha']);
    }

    // ── Deduplication ────────────────────────────────────────────────

    /**
     * @return void
     * @throws Exception
     */
    #[Test]
    public function deduplicatesHashesWhenSameFileAppearsMultipleTimes(): void
    {
        // Same content → same SHA1
        $file1 = 'copy1.xliff';
        $file2 = 'copy2.xliff';
        file_put_contents($this->uploadDir . '/' . $file1, 'identical content');
        file_put_contents($this->uploadDir . '/' . $file2, 'identical content');

        $this->projectStructure->array_files = [$file1, $file2];
        $this->projectStructure->array_files_meta = [
            ['mustBeConverted' => false],
            ['mustBeConverted' => false],
        ];

        $linkFiles = ['conversionHashes' => ['sha' => [], 'fileName' => []]];
        $fs = $this->createStub(AbstractFilesStorage::class);

        $this->service->registerNativeXliffsAsConverted(
            $fs,
            $this->projectStructure,
            $this->uploadDir,
            $linkFiles
        );

        // sha should be deduplicated, but both filenames should be present
        $this->assertCount(1, $linkFiles['conversionHashes']['sha']);
        $hashKey = $linkFiles['conversionHashes']['sha'][0];
        $this->assertCount(2, $linkFiles['conversionHashes']['fileName'][$hashKey]);
        $this->assertContains($file1, $linkFiles['conversionHashes']['fileName'][$hashKey]);
        $this->assertContains($file2, $linkFiles['conversionHashes']['fileName'][$hashKey]);
    }

    // ── Error when sha1_file fails ───────────────────────────────────

    /**
     * @return void
     * @throws Exception
     */
    #[Test]
    public function addsProjectErrorWhenSha1FileFails(): void
    {
        // Create a file name but DON'T create the actual file → sha1_file returns false
        $fileName = 'nonexistent.xliff';

        $this->projectStructure->array_files = [$fileName];
        $this->projectStructure->array_files_meta = [
            ['mustBeConverted' => false],
        ];

        $linkFiles = ['conversionHashes' => ['sha' => [], 'fileName' => []]];
        $fs = $this->createStub(AbstractFilesStorage::class);

        // Suppress expected PHP warning from sha1_file on non-existent file
        @$this->service->registerNativeXliffsAsConverted(
            $fs,
            $this->projectStructure,
            $this->uploadDir,
            $linkFiles
        );

        $errors = $this->projectStructure->result['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(ProjectCreationError::FILE_HASH_FAILED->value, $errors[0]['code']);
        $this->assertStringContainsString($fileName, $errors[0]['message']);
    }

    // ── Error when makeCachePackage throws ────────────────────────────

    /**
     * @return void
     * @throws Exception
     */
    #[Test]
    public function addsProjectErrorWhenMakeCachePackageThrows(): void
    {
        $fileName = 'test.xliff';
        file_put_contents($this->uploadDir . '/' . $fileName, 'content');

        $this->projectStructure->array_files = [$fileName];
        $this->projectStructure->array_files_meta = [
            ['mustBeConverted' => false],
        ];

        $linkFiles = ['conversionHashes' => ['sha' => [], 'fileName' => []]];

        $fs = $this->createMock(AbstractFilesStorage::class);
        $fs->expects($this->once())
            ->method('makeCachePackage')
            ->willThrowException(new Exception('Cache write failed'));

        $this->service->registerNativeXliffsAsConverted(
            $fs,
            $this->projectStructure,
            $this->uploadDir,
            $linkFiles
        );

        $errors = $this->projectStructure->result['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(ProjectCreationError::FILE_HASH_FAILED->value, $errors[0]['code']);
        $this->assertSame('Cache write failed', $errors[0]['message']);
    }

    // ── makeCachePackage error does NOT prevent linkSession + hash append ──

    /**
     * @return void
     * @throws Exception
     */
    #[Test]
    public function continuesWithLinkAndHashAppendAfterMakeCachePackageError(): void
    {
        $fileName = 'test.xliff';
        file_put_contents($this->uploadDir . '/' . $fileName, 'content');

        $this->projectStructure->array_files = [$fileName];
        $this->projectStructure->array_files_meta = [
            ['mustBeConverted' => false],
        ];

        $linkFiles = ['conversionHashes' => ['sha' => [], 'fileName' => []]];

        $fs = $this->createMock(AbstractFilesStorage::class);
        $fs->expects($this->once())
            ->method('makeCachePackage')
            ->willThrowException(new Exception('Cache write failed'));

        // linkSession should STILL be called after makeCachePackage fails
        $fs->expects($this->once())
            ->method('linkSessionToCacheForAlreadyConvertedFiles');

        $this->service->registerNativeXliffsAsConverted(
            $fs,
            $this->projectStructure,
            $this->uploadDir,
            $linkFiles
        );

        // Hash should still be appended
        $this->assertCount(1, $linkFiles['conversionHashes']['sha']);
    }

    // ── sha1_file failure prevents further processing for that file ──

    /**
     * @return void
     * @throws Exception
     */
    #[Test]
    public function sha1FailureSkipsCacheAndLinkForThatFile(): void
    {
        // File doesn't exist → sha1_file returns false
        $this->projectStructure->array_files = ['missing.xliff'];
        $this->projectStructure->array_files_meta = [
            ['mustBeConverted' => false],
        ];

        $linkFiles = ['conversionHashes' => ['sha' => [], 'fileName' => []]];

        $fs = $this->createMock(AbstractFilesStorage::class);
        $fs->expects($this->never())->method('makeCachePackage');
        $fs->expects($this->never())->method('linkSessionToCacheForAlreadyConvertedFiles');

        // Suppress expected PHP warning from sha1_file on non-existent file
        @$this->service->registerNativeXliffsAsConverted(
            $fs,
            $this->projectStructure,
            $this->uploadDir,
            $linkFiles
        );

        $this->assertEmpty($linkFiles['conversionHashes']['sha']);
    }

    // ── Empty file list ──────────────────────────────────────────────

    /**
     * @return void
     * @throws Exception
     */
    #[Test]
    public function doesNothingWhenFileListIsEmpty(): void
    {
        $this->projectStructure->array_files = [];
        $this->projectStructure->array_files_meta = [];

        $linkFiles = ['conversionHashes' => ['sha' => [], 'fileName' => []]];

        $fs = $this->createMock(AbstractFilesStorage::class);
        $fs->expects($this->never())->method('makeCachePackage');

        $this->service->registerNativeXliffsAsConverted(
            $fs,
            $this->projectStructure,
            $this->uploadDir,
            $linkFiles
        );

        $this->assertEmpty($linkFiles['conversionHashes']['sha']);
    }
}
