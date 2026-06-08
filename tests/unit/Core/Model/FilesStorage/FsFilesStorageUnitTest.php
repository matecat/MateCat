<?php


namespace Matecat\Core\Model\FilesStorage;

use Matecat\TestHelpers\AbstractTest;
use Model\FilesStorage\Exceptions\FileSystemException;
use Model\FilesStorage\FilesystemAdapter;
use Model\FilesStorage\FsFilesStorage;
use PHPUnit\Framework\Attributes\Test;
use Utils\Registry\AppConfig;

class FsFilesStorageUnitTest extends AbstractTest
{
    private function deleteDir(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($dir);
    }

    private function withTempDir(callable $fn): void
    {
        $tempDir = '/tmp/fs_test_' . uniqid();
        mkdir($tempDir, 0755, true);
        try {
            $fn($tempDir);
        } finally {
            if (is_dir($tempDir)) {
                $this->deleteDir($tempDir);
            }
        }
    }

    // ── Pure logic tests (no mock needed) ──

    #[Test]
    public function test_getOriginalZipPath_throws_on_invalid_date(): void
    {
        $fs = new FsFilesStorage();
        $this->expectException(\InvalidArgumentException::class);
        $fs->getOriginalZipPath('not-a-date', '123', 'archive.zip');
    }

    #[Test]
    public function test_getOriginalZipDir_throws_on_invalid_date(): void
    {
        $fs = new FsFilesStorage();
        $this->expectException(\InvalidArgumentException::class);
        $fs->getOriginalZipDir('not-a-date', '123');
    }

    #[Test]
    public function test_getOriginalZipPath_returns_correct_path(): void
    {
        $fs = new FsFilesStorage();
        $result = $fs->getOriginalZipPath('2024-06-15', '42', 'test.zip');
        $this->assertStringContainsString('20240615', $result);
        $this->assertStringContainsString('42', $result);
        $this->assertStringEndsWith('test.zip', $result);
        $this->assertStringContainsString(AppConfig::$ZIP_REPOSITORY, $result);
    }

    #[Test]
    public function test_getOriginalZipDir_returns_correct_path(): void
    {
        $fs = new FsFilesStorage();
        $result = $fs->getOriginalZipDir('2024-06-15', '42');
        $this->assertStringContainsString('20240615', $result);
        $this->assertStringContainsString('42', $result);
        $this->assertStringContainsString(AppConfig::$ZIP_REPOSITORY, $result);
    }

    #[Test]
    public function test_transferFiles_returns_true(): void
    {
        $fs = new FsFilesStorage();
        $result = $fs->transferFiles('/source', '/dest');
        $this->assertTrue($result);
    }

    #[Test]
    public function test_constructor_uses_appconfig_defaults(): void
    {
        $fs = new FsFilesStorage();
        $ref = new \ReflectionObject($fs);
        $filesDir = $ref->getProperty('filesDir');
        $this->assertSame(AppConfig::$FILES_REPOSITORY, $filesDir->getValue($fs));
        $cacheDir = $ref->getProperty('cacheDir');
        $this->assertSame(AppConfig::$CACHE_REPOSITORY, $cacheDir->getValue($fs));
    }

    // ── Mock adapter tests ──

    #[Test]
    public function test_ensureDirectoryExists_creates_new_directory(): void
    {
        $mockFs = $this->createMock(FilesystemAdapter::class);
        $mockFs->expects($this->once())->method('fileExists')->with('/some/new/dir')->willReturn(false);
        $mockFs->expects($this->once())->method('mkdir')->with('/some/new/dir', 0755, true)->willReturn(true);

        $fs = new FsFilesStorage($mockFs);
        $ref = new \ReflectionMethod($fs, 'ensureDirectoryExists');
        $result = $ref->invoke($fs, '/some/new/dir');
        $this->assertTrue($result);
    }

    #[Test]
    public function test_ensureDirectoryExists_returns_true_for_existing(): void
    {
        $mockFs = $this->createMock(FilesystemAdapter::class);
        $mockFs->expects($this->once())->method('fileExists')->with('/existing/dir')->willReturn(true);
        $mockFs->expects($this->never())->method('mkdir');

        $fs = new FsFilesStorage($mockFs);
        $ref = new \ReflectionMethod($fs, 'ensureDirectoryExists');
        $result = $ref->invoke($fs, '/existing/dir');
        $this->assertTrue($result);
    }

    #[Test]
    public function test_getHashesFromDir_with_empty_dir(): void
    {
        $mockFs = $this->createStub(FilesystemAdapter::class);
        $mockFs->method('scandir')->willReturn([]);

        $fs = new FsFilesStorage($mockFs);
        $result = $fs->getHashesFromDir('/scan');
        $this->assertArrayHasKey('conversionHashes', $result);
        $this->assertArrayHasKey('zipHashes', $result);
        $this->assertEmpty($result['zipHashes']);
    }

    #[Test]
    public function test_getHashesFromDir_identifies_zip_placeholders(): void
    {
        $mockFs = $this->createStub(FilesystemAdapter::class);
        $mockFs->method('scandir')->willReturn(['abc123' . FsFilesStorage::ORIGINAL_ZIP_PLACEHOLDER]);

        $fs = new FsFilesStorage($mockFs);
        $result = $fs->getHashesFromDir('/scan');
        $this->assertCount(1, $result['zipHashes']);
        $this->assertStringContainsString('abc123', $result['zipHashes'][0]);
    }

    #[Test]
    public function test_getHashesFromDir_reads_hash_links(): void
    {
        $mockFs = $this->createStub(FilesystemAdapter::class);
        $mockFs->method('scandir')->willReturn(['abc123__en-US']);
        $mockFs->method('file')->willReturn(['file1.txt', 'file2.txt']);

        $fs = new FsFilesStorage($mockFs);
        $result = $fs->getHashesFromDir('/scan');
        $this->assertContains('abc123__en-US', $result['conversionHashes']['sha']);
        $this->assertCount(2, $result['conversionHashes']['fileName']['abc123__en-US']);
    }

    #[Test]
    public function test_deleteQueue_removes_directory(): void
    {
        $mockFs = $this->createMock(FilesystemAdapter::class);
        $mockFs->expects($this->once())->method('deleteDir')->with('/queue/dir');
        $mockFs->method('isDir')->with('/queue/dir_converted')->willReturn(false);

        $fs = new FsFilesStorage($mockFs);
        $fs->deleteQueue('/queue/dir');
    }

    #[Test]
    public function test_deleteQueue_removes_converted_directory_too(): void
    {
        $mockFs = $this->createMock(FilesystemAdapter::class);
        $mockFs->expects($this->exactly(2))->method('deleteDir')
            ->willReturnCallback(function (string $path): bool {
                $this->assertContains($path, ['/queue/dir', '/queue/dir_converted']);
                return true;
            });
        $mockFs->method('isDir')->with('/queue/dir_converted')->willReturn(true);

        $fs = new FsFilesStorage($mockFs);
        $fs->deleteQueue('/queue/dir');
    }

    #[Test]
    public function test_cacheZipArchive_creates_archive(): void
    {
        $mockFs = $this->createStub(FilesystemAdapter::class);
        $mockFs->method('isDir')->willReturn(false);
        $mockFs->method('fileExists')->willReturn(false);
        $mockFs->method('mkdir')->willReturn(true);
        $mockFs->method('copy')->willReturn(true);
        $mockFs->method('unlink')->willReturn(true);
        $mockFs->method('touch')->willReturn(true);

        $fs = new FsFilesStorage($mockFs);
        $result = $fs->cacheZipArchive('abc123def456', '/tmp/source.zip');
        $this->assertTrue($result);
    }

    #[Test]
    public function test_storeFastAnalysisFile_and_getFastAnalysisData_round_trip(): void
    {
        $data = ['seg1' => ['words' => 10], 'seg2' => ['words' => 20]];
        $serialized = serialize($data);

        $mockFs = $this->createStub(FilesystemAdapter::class);
        $mockFs->method('filePutContents')->willReturn(strlen($serialized));
        $mockFs->method('fileGetContents')->willReturn($serialized);

        $fs = new FsFilesStorage($mockFs);
        $fs->storeFastAnalysisFile('999', $data);
        $result = $fs->getFastAnalysisData(999);
        $this->assertSame($data, $result);
    }

    #[Test]
    public function test_deleteFastAnalysisFile_removes_file(): void
    {
        $mockFs = $this->createMock(FilesystemAdapter::class);
        $mockFs->expects($this->once())->method('unlink')->willReturn(true);

        $fs = new FsFilesStorage($mockFs);
        $result = $fs->deleteFastAnalysisFile('888');
        $this->assertTrue($result);
    }

    #[Test]
    public function test_getFastAnalysisData_throws_on_missing_file(): void
    {
        $mockFs = $this->createStub(FilesystemAdapter::class);
        $mockFs->method('fileGetContents')->willReturn(false);

        $fs = new FsFilesStorage($mockFs);
        $this->expectException(\UnexpectedValueException::class);
        $fs->getFastAnalysisData(777);
    }

    #[Test]
    public function test_getXliffFromCache_returns_false_when_not_exists(): void
    {
        $mockFs = $this->createStub(FilesystemAdapter::class);
        $mockFs->method('iterateDirectory')->willThrowException(new \UnexpectedValueException('not found'));

        $fs = new FsFilesStorage($mockFs);
        $result = $fs->getXliffFromCache('nonexistenthash1234567890abcdef12345678', 'en-US');
        $this->assertFalse($result);
    }

    #[Test]
    public function test_getOriginalFromCache_returns_false_when_not_exists(): void
    {
        $mockFs = $this->createStub(FilesystemAdapter::class);
        $mockFs->method('iterateDirectory')->willThrowException(new \UnexpectedValueException('not found'));

        $fs = new FsFilesStorage($mockFs);
        $result = $fs->getOriginalFromCache('nonexistenthash1234567890abcdef12345678', 'en-US');
        $this->assertFalse($result);
    }

    #[Test]
    public function test_getOriginalFromFileDir_returns_false_when_not_exists(): void
    {
        $mockFs = $this->createStub(FilesystemAdapter::class);
        $mockFs->method('iterateDirectory')->willThrowException(new \UnexpectedValueException('not found'));

        $fs = new FsFilesStorage($mockFs);
        $result = $fs->getOriginalFromFileDir('999', '20240101' . DIRECTORY_SEPARATOR . 'hash');
        $this->assertFalse($result);
    }

    #[Test]
    public function test_linkZipToProject_returns_false_when_no_zip_found(): void
    {
        $mockFs = $this->createStub(FilesystemAdapter::class);
        $mockFs->method('iterateDirectory')->willThrowException(new \UnexpectedValueException('not found'));

        $fs = new FsFilesStorage($mockFs);
        $result = $fs->linkZipToProject('2024-06-15', 'nonexistent', '42');
        $this->assertFalse($result);
    }

    // ── Real FS with AppConfig override tests ──

    #[Test]
    public function test_getOriginalFromCache_returns_file_path(): void
    {
        $this->withTempDir(function (string $tempDir): void {
            $originalCache = AppConfig::$CACHE_REPOSITORY;
            try {
                AppConfig::$CACHE_REPOSITORY = $tempDir;
                $hash = 'abcdef1234567890abcdef1234567890abcdef12';
                $lang = 'en-US';
                $cacheTree = implode(DIRECTORY_SEPARATOR, FsFilesStorage::composeCachePath($hash));
                $origDir = $tempDir . '/' . $cacheTree . '__' . $lang . '/package/orig';
                mkdir($origDir, 0755, true);
                file_put_contents($origDir . '/test.docx', 'content');

                $fs = new FsFilesStorage();
                $result = $fs->getOriginalFromCache($hash, $lang);
                $this->assertStringEndsWith('test.docx', $result);
            } finally {
                AppConfig::$CACHE_REPOSITORY = $originalCache;
            }
        });
    }

    #[Test]
    public function test_getXliffFromCache_returns_file_path(): void
    {
        $this->withTempDir(function (string $tempDir): void {
            $originalCache = AppConfig::$CACHE_REPOSITORY;
            try {
                AppConfig::$CACHE_REPOSITORY = $tempDir;
                $hash = 'abcdef1234567890abcdef1234567890abcdef12';
                $lang = 'en-US';
                $cacheTree = implode(DIRECTORY_SEPARATOR, FsFilesStorage::composeCachePath($hash));
                $workDir = $tempDir . '/' . $cacheTree . '__' . $lang . '/package/work';
                mkdir($workDir, 0755, true);
                file_put_contents($workDir . '/test.sdlxliff', 'content');

                $fs = new FsFilesStorage();
                $result = $fs->getXliffFromCache($hash, $lang);
                $this->assertStringEndsWith('test.sdlxliff', $result);
            } finally {
                AppConfig::$CACHE_REPOSITORY = $originalCache;
            }
        });
    }

    #[Test]
    public function test_getOriginalFromCache_falls_back_to_xliff(): void
    {
        $this->withTempDir(function (string $tempDir): void {
            $originalCache = AppConfig::$CACHE_REPOSITORY;
            try {
                AppConfig::$CACHE_REPOSITORY = $tempDir;
                $hash = 'abcdef1234567890abcdef1234567890abcdef12';
                $lang = 'en-US';
                $cacheTree = implode(DIRECTORY_SEPARATOR, FsFilesStorage::composeCachePath($hash));
                $origDir = $tempDir . '/' . $cacheTree . '__' . $lang . '/package/orig';
                $workDir = $tempDir . '/' . $cacheTree . '__' . $lang . '/package/work';
                mkdir($origDir, 0755, true);
                mkdir($workDir, 0755, true);
                file_put_contents($workDir . '/test.sdlxliff', 'content');

                $fs = new FsFilesStorage();
                $result = $fs->getOriginalFromCache($hash, $lang);
                $this->assertStringEndsWith('test.sdlxliff', $result);
            } finally {
                AppConfig::$CACHE_REPOSITORY = $originalCache;
            }
        });
    }

    #[Test]
    public function test_getOriginalFromFileDir_returns_file(): void
    {
        $this->withTempDir(function (string $tempDir): void {
            $originalFiles = AppConfig::$FILES_REPOSITORY;
            try {
                AppConfig::$FILES_REPOSITORY = $tempDir;
                $origDir = $tempDir . '/20240101/42/orig';
                mkdir($origDir, 0755, true);
                file_put_contents($origDir . '/doc.txt', 'content');

                $fs = new FsFilesStorage();
                $result = $fs->getOriginalFromFileDir('42', '20240101' . DIRECTORY_SEPARATOR . 'hash');
                $this->assertStringEndsWith('doc.txt', $result);
            } finally {
                AppConfig::$FILES_REPOSITORY = $originalFiles;
            }
        });
    }

    #[Test]
    public function test_getXliffFromFileDir_returns_file(): void
    {
        $this->withTempDir(function (string $tempDir): void {
            $originalFiles = AppConfig::$FILES_REPOSITORY;
            try {
                AppConfig::$FILES_REPOSITORY = $tempDir;
                $xliffDir = $tempDir . '/20240101/42/xliff';
                mkdir($xliffDir, 0755, true);
                file_put_contents($xliffDir . '/doc.sdlxliff', 'content');

                $fs = new FsFilesStorage();
                $result = $fs->getXliffFromFileDir('42', '20240101' . DIRECTORY_SEPARATOR . 'hash');
                $this->assertStringEndsWith('doc.sdlxliff', $result);
            } finally {
                AppConfig::$FILES_REPOSITORY = $originalFiles;
            }
        });
    }

    #[Test]
    public function test_getOriginalFromFileDir_falls_back_to_xliff(): void
    {
        $this->withTempDir(function (string $tempDir): void {
            $originalFiles = AppConfig::$FILES_REPOSITORY;
            try {
                AppConfig::$FILES_REPOSITORY = $tempDir;
                $origDir = $tempDir . '/20240101/42/orig';
                $xliffDir = $tempDir . '/20240101/42/xliff';
                mkdir($origDir, 0755, true);
                mkdir($xliffDir, 0755, true);
                file_put_contents($xliffDir . '/doc.sdlxliff', 'content');

                $fs = new FsFilesStorage();
                $result = $fs->getOriginalFromFileDir('42', '20240101' . DIRECTORY_SEPARATOR . 'hash');
                $this->assertStringEndsWith('doc.sdlxliff', $result);
            } finally {
                AppConfig::$FILES_REPOSITORY = $originalFiles;
            }
        });
    }

    #[Test]
    public function test_linkZipToProject_creates_link(): void
    {
        $this->withTempDir(function (string $tempDir): void {
            $originalZip = AppConfig::$ZIP_REPOSITORY;
            try {
                AppConfig::$ZIP_REPOSITORY = $tempDir;
                $hash = 'zipHash123__##originalZip##';
                $zipCacheDir = $tempDir . '/' . $hash;
                mkdir($zipCacheDir, 0755, true);
                file_put_contents($zipCacheDir . '/archive.zip', 'zip content');

                $fs = new FsFilesStorage();
                $result = $fs->linkZipToProject('2024-06-15', $hash, '42');
                $this->assertTrue($result);
                $linkedPath = $tempDir . '/20240615/42/archive.zip';
                $this->assertFileExists($linkedPath);
            } finally {
                AppConfig::$ZIP_REPOSITORY = $originalZip;
            }
        });
    }

    // ── makeCachePackage tests ──

    #[Test]
    public function test_makeCachePackage_early_return_when_filter_forced_and_cache_exists(): void
    {
        $original = AppConfig::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION;
        try {
            AppConfig::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION = '2.0';

            $mockFs = $this->createMock(FilesystemAdapter::class);
            // The early-return check: fileExists for the cache path returns true
            $mockFs->method('fileExists')->willReturn(true);
            $mockFs->expects($this->never())->method('copy');

            $fs = new FsFilesStorage($mockFs);
            $result = $fs->makeCachePackage('abcdef1234567890abcdef1234567890abcdef12', 'en-US', '/tmp/orig.docx', '/tmp/conv.xlf');
            $this->assertTrue($result);
        } finally {
            AppConfig::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION = $original;
        }
    }

    #[Test]
    public function test_makeCachePackage_success_with_original_file(): void
    {
        $mockFs = $this->createMock(FilesystemAdapter::class);
        $mockFs->method('fileExists')->willReturn(false);
        $mockFs->method('mkdir')->willReturn(true);
        $mockFs->expects($this->exactly(2))->method('copy')->willReturn(true);
        $mockFs->expects($this->once())->method('unlink')->willReturn(true);

        $fs = new FsFilesStorage($mockFs);
        $result = $fs->makeCachePackage('abcdef1234567890abcdef1234567890abcdef12', 'en-US', '/tmp/original.docx', '/tmp/converted.xlf');
        $this->assertTrue($result);
    }

    #[Test]
    public function test_makeCachePackage_failure_on_original_copy_deletes_cache(): void
    {
        $mockFs = $this->createMock(FilesystemAdapter::class);
        // ensureDirectoryExists calls
        $callCount = 0;
        $mockFs->method('fileExists')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            // First 4 calls are ensureDirectoryExists (return false to trigger mkdir)
            // 5th call is the check after copy failure (return true so deleteDir is called)
            return $callCount >= 5;
        });
        $mockFs->method('mkdir')->willReturn(true);
        // First copy (original) fails
        $mockFs->expects($this->once())->method('copy')->willReturn(false);
        $mockFs->expects($this->once())->method('deleteDir');

        $fs = new FsFilesStorage($mockFs);
        $result = $fs->makeCachePackage('abcdef1234567890abcdef1234567890abcdef12', 'en-US', '/tmp/original.docx', '/tmp/converted.xlf');
        $this->assertFalse($result);
    }

    #[Test]
    public function test_makeCachePackage_failure_on_original_copy_throws_when_cache_dir_missing(): void
    {
        $mockFs = $this->createMock(FilesystemAdapter::class);
        // All fileExists return false (including the check after copy failure)
        $mockFs->method('fileExists')->willReturn(false);
        $mockFs->method('mkdir')->willReturn(true);
        $mockFs->expects($this->once())->method('copy')->willReturn(false);

        $fs = new FsFilesStorage($mockFs);
        $this->expectException(FileSystemException::class);
        $fs->makeCachePackage('abcdef1234567890abcdef1234567890abcdef12', 'en-US', '/tmp/original.docx', '/tmp/converted.xlf');
    }

    #[Test]
    public function test_makeCachePackage_failure_on_xliff_copy_deletes_cache(): void
    {
        $mockFs = $this->createStub(FilesystemAdapter::class);
        $copyCallCount = 0;
        $fileExistsCallCount = 0;
        $mockFs->method('fileExists')->willReturnCallback(function () use (&$fileExistsCallCount) {
            $fileExistsCallCount++;
            // ensureDirectoryExists calls fileExists 4 times (all return false to trigger mkdir)
            // then after xliff copy failure, fileExists is called once more to check cache dir exists
            // That's the 5th call - return true
            return $fileExistsCallCount >= 5;
        });
        $mockFs->method('mkdir')->willReturn(true);
        // First copy succeeds (original), second fails (xliff)
        $mockFs->method('copy')->willReturnCallback(function () use (&$copyCallCount) {
            $copyCallCount++;
            return $copyCallCount === 1;
        });

        $fs = new FsFilesStorage($mockFs);
        $result = $fs->makeCachePackage('abcdef1234567890abcdef1234567890abcdef12', 'en-US', '/tmp/original.docx', '/tmp/converted.xlf');
        $this->assertFalse($result);
    }

    #[Test]
    public function test_makeCachePackage_failure_on_xliff_copy_throws_when_cache_dir_missing(): void
    {
        $mockFs = $this->createStub(FilesystemAdapter::class);
        $mockFs->method('fileExists')->willReturn(false);
        $mockFs->method('mkdir')->willReturn(true);
        $copyCallCount = 0;
        $mockFs->method('copy')->willReturnCallback(function () use (&$copyCallCount) {
            $copyCallCount++;
            return $copyCallCount === 1;
        });

        $fs = new FsFilesStorage($mockFs);
        $this->expectException(FileSystemException::class);
        $fs->makeCachePackage('abcdef1234567890abcdef1234567890abcdef12', 'en-US', '/tmp/original.docx', '/tmp/converted.xlf');
    }

    // ── moveFromCacheToFileDir tests ──

    #[Test]
    public function test_moveFromCacheToFileDir_success_new_dir(): void
    {
        $mockFs = $this->createStub(FilesystemAdapter::class);

        $partialMock = $this->getMockBuilder(FsFilesStorage::class)
            ->onlyMethods(['getSingleFileInPath'])
            ->setConstructorArgs([$mockFs])
            ->getMock();

        $mockFs->method('isDir')->willReturn(false);
        $mockFs->method('fileExists')->willReturn(false);
        $mockFs->method('mkdir')->willReturn(true);
        $mockFs->method('isFile')->willReturn(true);
        $mockFs->method('link')->willReturn(true);

        // First call: orig dir (return a file), second call: work dir (return a file)
        $partialMock->expects($this->exactly(2))->method('getSingleFileInPath')
            ->willReturnOnConsecutiveCalls(
                '/cache/orig/document.docx',
                '/cache/work/document.docx.sdlxliff'
            );

        $hash = 'abcdef1234567890abcdef1234567890abcdef12';
        $dateHashPath = '20240101' . DIRECTORY_SEPARATOR . $hash;
        $result = $partialMock->moveFromCacheToFileDir($dateHashPath, 'en-US', '42');
        $this->assertTrue($result);
    }

    #[Test]
    public function test_moveFromCacheToFileDir_success_existing_dir(): void
    {
        $mockFs = $this->createStub(FilesystemAdapter::class);

        $partialMock = $this->getMockBuilder(FsFilesStorage::class)
            ->onlyMethods(['getSingleFileInPath'])
            ->setConstructorArgs([$mockFs])
            ->getMock();

        $mockFs->method('isDir')->willReturn(true);
        $mockFs->method('isFile')->willReturn(true);
        $mockFs->method('link')->willReturn(true);

        $partialMock->expects($this->exactly(2))->method('getSingleFileInPath')
            ->willReturnOnConsecutiveCalls(
                '/cache/orig/document.docx',
                '/cache/work/document.docx.sdlxliff'
            );

        $hash = 'abcdef1234567890abcdef1234567890abcdef12';
        $dateHashPath = '20240101' . DIRECTORY_SEPARATOR . $hash;
        $result = $partialMock->moveFromCacheToFileDir($dateHashPath, 'en-US', '42');
        $this->assertTrue($result);
    }

    #[Test]
    public function test_moveFromCacheToFileDir_throws_when_no_converted_file(): void
    {
        $mockFs = $this->createStub(FilesystemAdapter::class);

        $partialMock = $this->getMockBuilder(FsFilesStorage::class)
            ->onlyMethods(['getSingleFileInPath'])
            ->setConstructorArgs([$mockFs])
            ->getMock();

        $mockFs->method('isDir')->willReturn(true);
        $mockFs->method('isFile')->willReturn(false);

        // orig returns false, work returns false
        $partialMock->expects($this->exactly(2))->method('getSingleFileInPath')
            ->willReturn(false);

        $hash = 'abcdef1234567890abcdef1234567890abcdef12';
        $dateHashPath = '20240101' . DIRECTORY_SEPARATOR . $hash;

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(-13);
        $partialMock->moveFromCacheToFileDir($dateHashPath, 'en-US', '42');
    }

    #[Test]
    public function test_moveFromCacheToFileDir_with_new_filename(): void
    {
        $mockFs = $this->createStub(FilesystemAdapter::class);

        $partialMock = $this->getMockBuilder(FsFilesStorage::class)
            ->onlyMethods(['getSingleFileInPath'])
            ->setConstructorArgs([$mockFs])
            ->getMock();

        $mockFs->method('isDir')->willReturn(true);
        $mockFs->method('isFile')->willReturn(true);

        $linkPaths = [];
        $mockFs->method('link')->willReturnCallback(function (string $target, string $link) use (&$linkPaths) {
            $linkPaths[] = $link;
            return true;
        });

        $partialMock->expects($this->exactly(2))->method('getSingleFileInPath')
            ->willReturnOnConsecutiveCalls(
                '/cache/orig/document.docx',
                '/cache/work/document.docx.sdlxliff'
            );

        $hash = 'abcdef1234567890abcdef1234567890abcdef12';
        $dateHashPath = '20240101' . DIRECTORY_SEPARATOR . $hash;
        $result = $partialMock->moveFromCacheToFileDir($dateHashPath, 'en-US', '42', 'renamed.docx');
        $this->assertTrue($result);

        // Verify orig link uses new filename
        $this->assertStringEndsWith('renamed.docx', $linkPaths[0]);
        // Verify xliff link uses new filename + extension
        $this->assertStringEndsWith('renamed.docx.sdlxliff', $linkPaths[1]);
    }

    #[Test]
    public function test_moveFromCacheToFileDir_throws_when_link_fails(): void
    {
        $mockFs = $this->createStub(FilesystemAdapter::class);

        $partialMock = $this->getMockBuilder(FsFilesStorage::class)
            ->onlyMethods(['getSingleFileInPath'])
            ->setConstructorArgs([$mockFs])
            ->getMock();

        $mockFs->method('isDir')->willReturn(true);
        $mockFs->method('isFile')->willReturn(true);
        $mockFs->method('link')->willReturn(false);

        $partialMock->expects($this->exactly(2))->method('getSingleFileInPath')
            ->willReturnOnConsecutiveCalls(
                '/cache/orig/document.docx',
                '/cache/work/document.docx.sdlxliff'
            );

        $hash = 'abcdef1234567890abcdef1234567890abcdef12';
        $dateHashPath = '20240101' . DIRECTORY_SEPARATOR . $hash;

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(-13);
        $partialMock->moveFromCacheToFileDir($dateHashPath, 'en-US', '42');
    }

    // ── cacheZipArchive additional tests ──

    #[Test]
    public function test_cacheZipArchive_overwrites_existing_dir(): void
    {
        $mockFs = $this->createMock(FilesystemAdapter::class);
        $mockFs->method('isDir')->willReturn(true);
        $mockFs->expects($this->once())->method('deleteDir');
        $mockFs->method('fileExists')->willReturn(false);
        $mockFs->method('mkdir')->willReturn(true);
        $mockFs->method('copy')->willReturn(true);
        $mockFs->method('unlink')->willReturn(true);
        $mockFs->method('touch')->willReturn(true);

        $fs = new FsFilesStorage($mockFs);
        $result = $fs->cacheZipArchive('abc123def456', '/tmp/source.zip');
        $this->assertTrue($result);
    }

    #[Test]
    public function test_cacheZipArchive_returns_false_when_mkdir_fails(): void
    {
        $mockFs = $this->createStub(FilesystemAdapter::class);
        $mockFs->method('isDir')->willReturn(false);
        $mockFs->method('fileExists')->willReturn(false);
        $mockFs->method('mkdir')->willReturn(false);

        $fs = new FsFilesStorage($mockFs);
        $result = $fs->cacheZipArchive('abc123def456', '/tmp/source.zip');
        $this->assertFalse($result);
    }

    #[Test]
    public function test_cacheZipArchive_returns_false_when_copy_fails(): void
    {
        $mockFs = $this->createMock(FilesystemAdapter::class);
        $mockFs->method('isDir')->willReturn(false);
        $mockFs->method('fileExists')->willReturn(false);
        $mockFs->method('mkdir')->willReturn(true);
        $mockFs->method('copy')->willReturn(false);
        $mockFs->expects($this->once())->method('deleteDir');

        $fs = new FsFilesStorage($mockFs);
        $result = $fs->cacheZipArchive('abc123def456', '/tmp/source.zip');
        $this->assertFalse($result);
    }

    // ── storeFastAnalysisFile failure test ──

    #[Test]
    public function test_storeFastAnalysisFile_throws_on_write_failure(): void
    {
        $mockFs = $this->createStub(FilesystemAdapter::class);
        $mockFs->method('filePutContents')->willReturn(false);

        $fs = new FsFilesStorage($mockFs);
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(-14);
        $fs->storeFastAnalysisFile('999', ['data']);
    }

    #[Test]
    public function test_getFastAnalysisData_throws_on_corrupt_data(): void
    {
        $mockFs = $this->createStub(FilesystemAdapter::class);
        $mockFs->method('fileGetContents')->willReturn('not-valid-serialized');

        $fs = new FsFilesStorage($mockFs);
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(-15);
        $fs->getFastAnalysisData(777);
    }

    // ── linkZipToProject additional tests ──

    #[Test]
    public function test_linkZipToProject_returns_false_when_mkdir_fails(): void
    {
        $mockFs = $this->createStub(FilesystemAdapter::class);

        $partialMock = $this->getMockBuilder(FsFilesStorage::class)
            ->onlyMethods(['getSingleFileInPath'])
            ->setConstructorArgs([$mockFs])
            ->getMock();

        $partialMock->expects($this->once())->method('getSingleFileInPath')->willReturn('/zip/dir/archive.zip');
        $mockFs->method('fileExists')->willReturn(false);
        $mockFs->method('mkdir')->willReturn(false);

        $result = $partialMock->linkZipToProject('2024-06-15', 'zipHash', '42');
        $this->assertFalse($result);
    }

    #[Test]
    public function test_linkZipToProject_returns_false_when_link_fails(): void
    {
        $mockFs = $this->createStub(FilesystemAdapter::class);

        $partialMock = $this->getMockBuilder(FsFilesStorage::class)
            ->onlyMethods(['getSingleFileInPath'])
            ->setConstructorArgs([$mockFs])
            ->getMock();

        $partialMock->expects($this->once())->method('getSingleFileInPath')->willReturn('/zip/dir/archive.zip');
        $mockFs->method('fileExists')->willReturn(false);
        $mockFs->method('mkdir')->willReturn(true);
        $mockFs->method('link')->willReturn(false);

        $result = $partialMock->linkZipToProject('2024-06-15', 'zipHash', '42');
        $this->assertFalse($result);
    }

    #[Test]
    public function test_moveFromCacheToFileDir_no_orig_only_xliff(): void
    {
        $mockFs = $this->createStub(FilesystemAdapter::class);

        $partialMock = $this->getMockBuilder(FsFilesStorage::class)
            ->onlyMethods(['getSingleFileInPath'])
            ->setConstructorArgs([$mockFs])
            ->getMock();

        $mockFs->method('isDir')->willReturn(true);
        $mockFs->method('isFile')->willReturn(false);
        $mockFs->method('link')->willReturn(true);

        // orig returns false (no original), work returns converted file
        $partialMock->expects($this->exactly(2))->method('getSingleFileInPath')
            ->willReturnOnConsecutiveCalls(
                false,
                '/cache/work/document.sdlxliff'
            );

        $hash = 'abcdef1234567890abcdef1234567890abcdef12';
        $dateHashPath = '20240101' . DIRECTORY_SEPARATOR . $hash;
        $result = $partialMock->moveFromCacheToFileDir($dateHashPath, 'en-US', '42');
        $this->assertTrue($result);
    }

    #[Test]
    public function test_moveFileFromUploadSessionToQueuePath_copies_files(): void
    {
        $this->withTempDir(function (string $tempDir): void {
            $originalUpload = AppConfig::$UPLOAD_REPOSITORY;
            $originalQueue = AppConfig::$QUEUE_PROJECT_REPOSITORY;
            $uploadDir = $tempDir . '/upload';
            $queueDir = $tempDir . '/queue';
            mkdir($uploadDir . '/session1', 0755, true);
            mkdir($queueDir, 0755, true);
            file_put_contents($uploadDir . '/session1/abc123__en-US', "file.txt\n");
            try {
                AppConfig::$UPLOAD_REPOSITORY = $uploadDir;
                AppConfig::$QUEUE_PROJECT_REPOSITORY = $queueDir;

                $fs = new FsFilesStorage();
                $fs->moveFileFromUploadSessionToQueuePath('session1');
                $this->assertDirectoryExists($queueDir . '/session1');
                $this->assertDirectoryDoesNotExist($uploadDir . '/session1');
            } finally {
                AppConfig::$UPLOAD_REPOSITORY = $originalUpload;
                AppConfig::$QUEUE_PROJECT_REPOSITORY = $originalQueue;
            }
        });
    }
}
