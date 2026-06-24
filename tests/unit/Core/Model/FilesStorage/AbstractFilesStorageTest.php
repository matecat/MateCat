<?php


namespace Matecat\Core\Model\FilesStorage;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\FilesStorage\AbstractFilesStorage;
use Model\FilesStorage\FsFilesStorage;
use PHPUnit\Framework\Attributes\Test;
use Utils\Registry\AppConfig;

/**
 * Tests for AbstractFilesStorage behavioral fixes.
 * Uses FsFilesStorage as the concrete implementation since AbstractFilesStorage is abstract.
 */
class AbstractFilesStorageTest extends AbstractTest
{
    private FsFilesStorage $fs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fs = new FsFilesStorage();
    }

    /**
     * getDatePath() must throw when given an invalid date string.
     * Before fix: date_create() returns false, then ->format() causes TypeError.
     * After fix: should throw an InvalidArgumentException.
     */
    #[Test]
    public function test_getDatePath_throws_on_invalid_date(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->fs->getDatePath('not-a-valid-date');
    }

    /**
     * getDatePath() with a valid date should return YYYYMMDD format.
     */
    #[Test]
    public function test_getDatePath_returns_formatted_date(): void
    {
        $result = $this->fs->getDatePath('2024-03-15');
        $this->assertSame('20240315', $result);
    }

    /**
     * getDatePath() with null should return today's date in YYYYMMDD format.
     */
    #[Test]
    public function test_getDatePath_with_null_returns_today(): void
    {
        $result = $this->fs->getDatePath(null);
        $this->assertSame(date('Ymd'), $result);
    }

    /**
     * _linkToCache must return 0 when file cannot be opened (fopen returns false).
     * Before fix: fopen returns false, passed to flock() causing TypeError.
     * After fix: should handle gracefully and return 0.
     */
    #[Test]
    public function test_linkToCache_returns_zero_when_dir_not_exists(): void
    {
        $nonExistentDir = '/tmp/opencode/non_existent_dir_' . uniqid();
        $reflection = new \ReflectionMethod($this->fs, '_linkToCache');

        // Suppress the expected E_WARNING from fopen() on non-existent path
        set_error_handler(static fn() => true, E_WARNING);
        try {
            $result = $reflection->invoke($this->fs, $nonExistentDir, 'somehash', 'file.txt');
        } finally {
            restore_error_handler();
        }

        $this->assertSame(0, $result);
    }

    /**
     * _linkToCache must write correctly when directory exists and file can be opened.
     */
    #[Test]
    public function test_linkToCache_writes_to_valid_dir(): void
    {
        $tempDir = '/tmp/opencode/linktoCache_test_' . uniqid();
        mkdir($tempDir, 0755, true);

        try {
            $reflection = new \ReflectionMethod($this->fs, '_linkToCache');

            $result = $reflection->invoke($this->fs, $tempDir, 'testhash', 'original_file.txt');
            $this->assertGreaterThan(0, $result);

            $content = file_get_contents($tempDir . DIRECTORY_SEPARATOR . 'testhash');
            $this->assertStringContainsString('original_file.txt', $content);
        } finally {
            array_map('unlink', glob($tempDir . '/*'));
            rmdir($tempDir);
        }
    }

    /**
     * _linkToCache must not duplicate entries when called twice with same filename.
     */
    #[Test]
    public function test_linkToCache_does_not_duplicate_entries(): void
    {
        $tempDir = '/tmp/opencode/linktoCache_dedup_' . uniqid();
        mkdir($tempDir, 0755, true);

        try {
            $reflection = new \ReflectionMethod($this->fs, '_linkToCache');

            $reflection->invoke($this->fs, $tempDir, 'testhash', 'file.txt');
            $reflection->invoke($this->fs, $tempDir, 'testhash', 'file.txt');

            $content = file_get_contents($tempDir . DIRECTORY_SEPARATOR . 'testhash');
            $lines = array_filter(explode("\n", $content), fn($l) => !empty($l));
            $this->assertCount(1, $lines);
            $this->assertSame('file.txt', $lines[0]);
        } finally {
            array_map('unlink', glob($tempDir . '/*'));
            rmdir($tempDir);
        }
    }

    /**
     * composeCachePath returns expected structure.
     */
    #[Test]
    public function test_composeCachePath_returns_three_levels(): void
    {
        $result = FsFilesStorage::composeCachePath('abcdef1234567890');
        $this->assertSame([
            'firstLevel' => 'ab',
            'secondLevel' => 'cd',
            'thirdLevel' => 'ef1234567890'
        ], $result);
    }

    /**
     * pathinfo_fix returns correct array for UTF-8 paths.
     */
    #[Test]
    public function test_pathinfo_fix_handles_utf8(): void
    {
        $result = FsFilesStorage::pathinfo_fix('/some/path/файл.docx');
        $this->assertIsArray($result);
        $this->assertSame('/some/path', $result['dirname']);
        $this->assertSame('файл.docx', $result['basename']);
        $this->assertSame('docx', $result['extension']);
        $this->assertSame('файл', $result['filename']);
    }

    /**
     * pathinfo_fix with single option flag returns a string, not array.
     */
    #[Test]
    public function test_pathinfo_fix_single_flag_returns_string(): void
    {
        $result = FsFilesStorage::pathinfo_fix('/some/path/file.txt', PATHINFO_EXTENSION);
        $this->assertSame('txt', $result);
    }

    /**
     * basename_fix handles path correctly.
     */
    #[Test]
    public function test_basename_fix(): void
    {
        $result = FsFilesStorage::basename_fix('/some/path/to/file.txt');
        $this->assertSame('file.txt', $result);
    }

    #[Test]
    public function test_isOnS3_returns_false_for_fs(): void
    {
        $original = AppConfig::$FILE_STORAGE_METHOD;
        try {
            AppConfig::$FILE_STORAGE_METHOD = 'fs';
            $this->assertFalse(FsFilesStorage::isOnS3());
        } finally {
            AppConfig::$FILE_STORAGE_METHOD = $original;
        }
    }

    #[Test]
    public function test_isOnS3_returns_true_for_s3(): void
    {
        $original = AppConfig::$FILE_STORAGE_METHOD;
        try {
            AppConfig::$FILE_STORAGE_METHOD = 's3';
            $this->assertTrue(FsFilesStorage::isOnS3());
        } finally {
            AppConfig::$FILE_STORAGE_METHOD = $original;
        }
    }

    #[Test]
    public function test_getStorageCachePath_returns_cache_repository_for_fs(): void
    {
        $original = AppConfig::$FILE_STORAGE_METHOD;
        try {
            AppConfig::$FILE_STORAGE_METHOD = 'fs';
            $result = AbstractFilesStorage::getStorageCachePath();
            $this->assertSame(AppConfig::$CACHE_REPOSITORY, $result);
        } finally {
            AppConfig::$FILE_STORAGE_METHOD = $original;
        }
    }

    #[Test]
    public function test_getSingleFileInPath_returns_first_file(): void
    {
        $tempDir = '/tmp/opencode/single_file_test_' . uniqid();
        mkdir($tempDir, 0755, true);
        file_put_contents($tempDir . '/test.txt', 'content');
        try {
            $result = $this->fs->getSingleFileInPath($tempDir);
            $this->assertSame($tempDir . DIRECTORY_SEPARATOR . 'test.txt', $result);
        } finally {
            unlink($tempDir . '/test.txt');
            rmdir($tempDir);
        }
    }

    #[Test]
    public function test_getSingleFileInPath_returns_false_for_empty_dir(): void
    {
        $tempDir = '/tmp/opencode/empty_dir_test_' . uniqid();
        mkdir($tempDir, 0755, true);
        try {
            $result = $this->fs->getSingleFileInPath($tempDir);
            $this->assertFalse($result);
        } finally {
            rmdir($tempDir);
        }
    }

    #[Test]
    public function test_getSingleFileInPath_returns_false_for_missing_dir(): void
    {
        $result = $this->fs->getSingleFileInPath('/tmp/opencode/nonexistent_' . uniqid());
        $this->assertFalse($result);
    }

    #[Test]
    public function test_deleteHashFromUploadDir_removes_matching_hash(): void
    {
        $tempDir = '/tmp/opencode/hash_test_' . uniqid();
        mkdir($tempDir, 0755, true);
        $linkFile = 'abc123_seg__en-US';
        file_put_contents($tempDir . '/' . $linkFile, 'keep');
        file_put_contents($tempDir . '/abc123_seg__it-IT', 'delete');
        try {
            $result = $this->fs->deleteHashFromUploadDir($tempDir, $linkFile);
            $this->assertTrue($result);
            $this->assertFileDoesNotExist($tempDir . '/abc123_seg__it-IT');
            $this->assertFileExists($tempDir . '/' . $linkFile);
        } finally {
            array_map('unlink', glob($tempDir . '/*') ?: []);
            rmdir($tempDir);
        }
    }

    #[Test]
    public function test_deleteHashFromUploadDir_returns_false_when_no_match(): void
    {
        $tempDir = '/tmp/opencode/hash_nomatch_' . uniqid();
        mkdir($tempDir, 0755, true);
        $linkFile = 'abc123__en-US';
        file_put_contents($tempDir . '/' . $linkFile, 'only');
        try {
            $result = $this->fs->deleteHashFromUploadDir($tempDir, $linkFile);
            $this->assertFalse($result);
        } finally {
            array_map('unlink', glob($tempDir . '/*') ?: []);
            rmdir($tempDir);
        }
    }

    #[Test]
    public function test_linkSessionToCacheForAlreadyConvertedFiles_writes_file(): void
    {
        $originalQueue = AppConfig::$QUEUE_PROJECT_REPOSITORY;
        $tempDir = '/tmp/opencode/link_session_test_' . uniqid();
        mkdir($tempDir . '/uid123', 0755, true);
        try {
            AppConfig::$QUEUE_PROJECT_REPOSITORY = $tempDir;
            $result = $this->fs->linkSessionToCacheForAlreadyConvertedFiles('testhash', 'uid123', 'file.txt');
            $this->assertGreaterThan(0, $result);
            $this->assertFileExists($tempDir . '/uid123/testhash');
        } finally {
            AppConfig::$QUEUE_PROJECT_REPOSITORY = $originalQueue;
            array_map('unlink', glob($tempDir . '/uid123/*') ?: []);
            rmdir($tempDir . '/uid123');
            rmdir($tempDir);
        }
    }

    #[Test]
    public function test_linkSessionToCacheForOriginalFiles_writes_file(): void
    {
        $originalUpload = AppConfig::$UPLOAD_REPOSITORY;
        $tempDir = '/tmp/opencode/link_original_test_' . uniqid();
        mkdir($tempDir . '/uid456', 0755, true);
        try {
            AppConfig::$UPLOAD_REPOSITORY = $tempDir;
            $result = $this->fs->linkSessionToCacheForOriginalFiles('orighash', 'uid456', 'original.txt');
            $this->assertGreaterThan(0, $result);
            $this->assertFileExists($tempDir . '/uid456/orighash');
            $content = file_get_contents($tempDir . '/uid456/orighash');
            $this->assertStringContainsString('original.txt', $content);
        } finally {
            AppConfig::$UPLOAD_REPOSITORY = $originalUpload;
            array_map('unlink', glob($tempDir . '/uid456/*') ?: []);
            rmdir($tempDir . '/uid456');
            rmdir($tempDir);
        }
    }

    #[Test]
    public function test_getStorageCachePath_returns_s3_folder_for_s3(): void
    {
        $original = AppConfig::$FILE_STORAGE_METHOD;
        try {
            AppConfig::$FILE_STORAGE_METHOD = 's3';
            $result = AbstractFilesStorage::getStorageCachePath();
            $this->assertSame(\Model\FilesStorage\S3FilesStorage::CACHE_PACKAGE_FOLDER, $result);
        } finally {
            AppConfig::$FILE_STORAGE_METHOD = $original;
        }
    }

    #[Test]
    public function test_getFilesForJob_returns_files_with_paths(): void
    {
        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockStmt->method('fetchAll')->willReturn([
            [
                'id_file' => '1',
                'filename' => 'test.docx',
                'id_project' => '42',
                'source' => 'en-US',
                'mime_type' => ' application/xml ',
                'sha1_original_file' => '20240101/hash123',
            ],
        ]);
        $mockStmt->expects($this->once())->method('execute');

        $mockPdo = $this->createStub(\PDO::class);
        $mockPdo->method('prepare')->willReturn($mockStmt);

        $mockDb = $this->createStub(IDatabase::class);
        $mockDb->method('getConnection')->willReturn($mockPdo);

        $mockFs = $this->createStub(\Model\FilesStorage\FilesystemAdapter::class);
        $mockFs->method('iterateDirectory')->willThrowException(new \RuntimeException('not found'));

        $fs = new FsFilesStorage($mockFs, $mockDb);
        $results = $fs->getFilesForJob(1, false);

        $this->assertCount(1, $results);
        $this->assertSame('application/xml', $results[0]['mime_type']);
    }

    #[Test]
    public function test_getFilesForJob_empty_results(): void
    {
        $mockStmt = $this->createStub(\PDOStatement::class);
        $mockStmt->method('fetchAll')->willReturn([]);

        $mockPdo = $this->createStub(\PDO::class);
        $mockPdo->method('prepare')->willReturn($mockStmt);

        $mockDb = $this->createStub(IDatabase::class);
        $mockDb->method('getConnection')->willReturn($mockPdo);

        $fs = new FsFilesStorage(null, $mockDb);
        $results = $fs->getFilesForJob(999, true);

        $this->assertEmpty($results);
    }

    #[Test]
    public function test_getFilesForJob_with_xliff_path(): void
    {
        $mockStmt = $this->createStub(\PDOStatement::class);
        $mockStmt->method('fetchAll')->willReturn([
            [
                'id_file' => '5',
                'filename' => 'doc.txt',
                'id_project' => '10',
                'source' => 'en-US',
                'mime_type' => 'text/plain',
                'sha1_original_file' => '20240601/hashABC',
            ],
        ]);

        $mockPdo = $this->createStub(\PDO::class);
        $mockPdo->method('prepare')->willReturn($mockStmt);

        $mockDb = $this->createStub(IDatabase::class);
        $mockDb->method('getConnection')->willReturn($mockPdo);

        $mockFs = $this->createStub(\Model\FilesStorage\FilesystemAdapter::class);
        $mockFs->method('iterateDirectory')->willThrowException(new \RuntimeException('not found'));

        $fs = new FsFilesStorage($mockFs, $mockDb);
        $results = $fs->getFilesForJob(1, true);

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('xliffFilePath', $results[0]);
    }
}
