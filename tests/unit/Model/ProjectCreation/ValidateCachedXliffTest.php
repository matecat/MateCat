<?php

namespace unit\Model\ProjectCreation;

use Closure;
use Exception;
use Model\Files\MetadataDao;
use Model\ProjectCreation\ProjectManagerModel;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

/**
 * Unit tests for {@see \Model\ProjectCreation\FileInsertionService::validateCachedXliff()}.
 *
 * Verifies:
 * - Exception when original file names array is empty
 * - Exception on S3 when cachedXliffFilePathName is null/empty
 * - Exception on local filesystem when file does not exist
 * - Exception when extension is not xliff/sdlxliff/xlf
 * - Success path for valid xliff/sdlxliff/xlf extensions
 */
class ValidateCachedXliffTest extends AbstractTest
{
    private TestableFileInsertionService $service;
    private string $originalFileStorageMethod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalFileStorageMethod = AppConfig::$FILE_STORAGE_METHOD;
        AppConfig::$FILE_STORAGE_METHOD = 'fs';

        $this->service = new TestableFileInsertionService(
            $this->createStub(ProjectManagerModel::class),
            $this->createStub(MetadataDao::class),
            null, // no GDrive session
            Closure::fromCallable(function (string $fileName): void {}),
            $this->createStub(MatecatLogger::class),
        );
    }

    protected function tearDown(): void
    {
        AppConfig::$FILE_STORAGE_METHOD = $this->originalFileStorageMethod;
        parent::tearDown();
    }

    // ── Empty file names ────────────────────────────────────────────

    #[Test]
    public function throwsWhenOriginalFileNamesEmpty(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(-6);
        $this->expectExceptionMessage('No hash files found');

        $this->service->callValidateCachedXliff(
            '/some/path/file.xliff',
            [],
            ['conversionHashes' => []]
        );
    }

    #[Test]
    public function throwsWhenOriginalFileNamesNull(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(-6);

        $this->service->callValidateCachedXliff(
            '/some/path/file.xliff',
            [],
            ['conversionHashes' => []]
        );
    }

    // ── S3 path validation ──────────────────────────────────────────

    #[Test]
    public function throwsOnS3WhenCachedPathIsNull(): void
    {
        AppConfig::$FILE_STORAGE_METHOD = 's3';

        $this->expectException(Exception::class);
        $this->expectExceptionCode(-6);
        $this->expectExceptionMessage('Key not found on S3 cache bucket');

        $this->service->callValidateCachedXliff(
            null,
            ['test.docx'],
            ['conversionHashes' => []]
        );
    }

    #[Test]
    public function throwsOnS3WhenCachedPathIsFalsy(): void
    {
        AppConfig::$FILE_STORAGE_METHOD = 's3';

        $this->expectException(Exception::class);
        $this->expectExceptionCode(-6);

        $this->service->callValidateCachedXliff(
            '',
            ['test.docx'],
            ['conversionHashes' => []]
        );
    }

    // ── Local filesystem validation ─────────────────────────────────

    #[Test]
    public function throwsOnLocalFsWhenFileDoesNotExist(): void
    {
        AppConfig::$FILE_STORAGE_METHOD = 'fs';

        $this->expectException(Exception::class);
        $this->expectExceptionCode(-6);
        $this->expectExceptionMessage('not found on server after upload');

        $this->service->callValidateCachedXliff(
            '/nonexistent/path/file.xliff',
            ['test.docx'],
            ['conversionHashes' => []]
        );
    }

    // ── Extension validation ────────────────────────────────────────

    #[Test]
    public function throwsWhenExtensionIsNotXliff(): void
    {
        // Create a temp file with wrong extension
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_') . '.txt';
        file_put_contents($tmpFile, 'content');

        try {
            $this->expectException(Exception::class);
            $this->expectExceptionCode(-3);
            $this->expectExceptionMessage('Failed to find converted Xliff');

            $this->service->callValidateCachedXliff(
                $tmpFile,
                ['test.docx'],
                ['conversionHashes' => []]
            );
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function acceptsXliffExtension(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        $xliffFile = $tmpFile . '.xliff';
        rename($tmpFile, $xliffFile);
        file_put_contents($xliffFile, 'content');

        try {
            // Should not throw
            $this->service->callValidateCachedXliff(
                $xliffFile,
                ['test.docx'],
                ['conversionHashes' => []]
            );
            $this->assertTrue(true); // reached without exception
        } finally {
            @unlink($xliffFile);
        }
    }

    #[Test]
    public function acceptsSdlxliffExtension(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        $sdlxliffFile = $tmpFile . '.sdlxliff';
        rename($tmpFile, $sdlxliffFile);
        file_put_contents($sdlxliffFile, 'content');

        try {
            $this->service->callValidateCachedXliff(
                $sdlxliffFile,
                ['test.docx'],
                ['conversionHashes' => []]
            );
            $this->assertTrue(true);
        } finally {
            @unlink($sdlxliffFile);
        }
    }

    #[Test]
    public function acceptsXlfExtension(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        $xlfFile = $tmpFile . '.xlf';
        rename($tmpFile, $xlfFile);
        file_put_contents($xlfFile, 'content');

        try {
            $this->service->callValidateCachedXliff(
                $xlfFile,
                ['test.docx'],
                ['conversionHashes' => []]
            );
            $this->assertTrue(true);
        } finally {
            @unlink($xlfFile);
        }
    }

    #[Test]
    public function throwsForDocxExtension(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        $docxFile = $tmpFile . '.docx';
        rename($tmpFile, $docxFile);
        file_put_contents($docxFile, 'content');

        try {
            $this->expectException(Exception::class);
            $this->expectExceptionCode(-3);

            $this->service->callValidateCachedXliff(
                $docxFile,
                ['test.docx'],
                ['conversionHashes' => []]
            );
        } finally {
            @unlink($docxFile);
        }
    }

    #[Test]
    public function s3ValidationIncludesFileNameInMessage(): void
    {
        AppConfig::$FILE_STORAGE_METHOD = 's3';

        try {
            $this->service->callValidateCachedXliff(
                null,
                ['my_document.docx', 'other.pdf'],
                ['conversionHashes' => []]
            );
            $this->fail('Expected exception');
        } catch (Exception $e) {
            $this->assertStringContainsString('my_document.docx', $e->getMessage());
            $this->assertStringContainsString('other.pdf', $e->getMessage());
        }
    }
}
