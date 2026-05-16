<?php

use Model\FilesStorage\FsFilesStorage;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class FsFilesStorageUnitTest extends AbstractTest
{
    private FsFilesStorage $fs;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = '/tmp/opencode/fs_test_' . uniqid();
        mkdir($this->tempDir . '/files', 0755, true);
        mkdir($this->tempDir . '/cache', 0755, true);
        mkdir($this->tempDir . '/zip', 0755, true);
        $this->fs = new FsFilesStorage(
            $this->tempDir . '/files',
            $this->tempDir . '/cache',
            $this->tempDir . '/zip'
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->tempDir)) {
            $this->deleteDir($this->tempDir);
        }
    }

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

    /**
     * getOriginalZipPath must throw InvalidArgumentException on invalid date.
     * Before fix: date_create() returns false, ->format() causes TypeError.
     */
    #[Test]
    public function test_getOriginalZipPath_throws_on_invalid_date(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->fs->getOriginalZipPath('not-a-date', '123', 'archive.zip');
    }

    /**
     * getOriginalZipDir must throw InvalidArgumentException on invalid date.
     * Before fix: date_create() returns false, ->format() causes TypeError.
     */
    #[Test]
    public function test_getOriginalZipDir_throws_on_invalid_date(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->fs->getOriginalZipDir('not-a-date', '123');
    }

    #[Test]
    public function test_getOriginalZipPath_returns_correct_path(): void
    {
        $result = $this->fs->getOriginalZipPath('2024-06-15', '42', 'test.zip');
        $this->assertStringContainsString('20240615', $result);
        $this->assertStringContainsString('42', $result);
        $this->assertStringEndsWith('test.zip', $result);
    }

    #[Test]
    public function test_getOriginalZipDir_returns_correct_path(): void
    {
        $result = $this->fs->getOriginalZipDir('2024-06-15', '42');
        $this->assertStringContainsString('20240615', $result);
        $this->assertStringContainsString('42', $result);
    }

    #[Test]
    public function test_getHashesFromDir_with_empty_dir(): void
    {
        $scanDir = $this->tempDir . '/scan';
        mkdir($scanDir, 0755, true);

        $result = $this->fs->getHashesFromDir($scanDir);
        $this->assertArrayHasKey('conversionHashes', $result);
        $this->assertArrayHasKey('zipHashes', $result);
        $this->assertEmpty($result['zipHashes']);
    }

    #[Test]
    public function test_getHashesFromDir_identifies_zip_placeholders(): void
    {
        $scanDir = $this->tempDir . '/scan';
        mkdir($scanDir, 0755, true);
        touch($scanDir . '/abc123' . FsFilesStorage::ORIGINAL_ZIP_PLACEHOLDER);

        $result = $this->fs->getHashesFromDir($scanDir);
        $this->assertCount(1, $result['zipHashes']);
        $this->assertStringContainsString('abc123', $result['zipHashes'][0]);
    }

    #[Test]
    public function test_getHashesFromDir_reads_hash_links(): void
    {
        $scanDir = $this->tempDir . '/scan';
        mkdir($scanDir, 0755, true);
        file_put_contents($scanDir . '/abc123__en-US', "file1.txt\nfile2.txt\n");

        $result = $this->fs->getHashesFromDir($scanDir);
        $this->assertContains('abc123__en-US', $result['conversionHashes']['sha']);
        $this->assertCount(2, $result['conversionHashes']['fileName']['abc123__en-US']);
    }

    #[Test]
    public function test_getXliffFromCache_returns_false_when_not_exists(): void
    {
        $result = $this->fs->getXliffFromCache('nonexistenthash1234567890abcdef12345678', 'en-US');
        $this->assertFalse($result);
    }

    #[Test]
    public function test_getOriginalFromCache_returns_false_when_not_exists(): void
    {
        $result = $this->fs->getOriginalFromCache('nonexistenthash1234567890abcdef12345678', 'en-US');
        $this->assertFalse($result);
    }

    #[Test]
    public function test_getOriginalFromFileDir_returns_false_when_not_exists(): void
    {
        $result = $this->fs->getOriginalFromFileDir('999', '20240101' . DIRECTORY_SEPARATOR . 'hash');
        $this->assertFalse($result);
    }

    #[Test]
    public function test_deleteQueue_removes_directory(): void
    {
        $queueDir = $this->tempDir . '/queue_test';
        mkdir($queueDir, 0755, true);
        touch($queueDir . '/file.txt');

        $this->fs->deleteQueue($queueDir);
        $this->assertDirectoryDoesNotExist($queueDir);
    }

    #[Test]
    public function test_deleteQueue_removes_converted_directory_too(): void
    {
        $queueDir = $this->tempDir . '/queue_test2';
        mkdir($queueDir, 0755, true);
        mkdir($queueDir . '_converted', 0755, true);
        touch($queueDir . '/file.txt');
        touch($queueDir . '_converted/file.xlf');

        $this->fs->deleteQueue($queueDir);
        $this->assertDirectoryDoesNotExist($queueDir);
        $this->assertDirectoryDoesNotExist($queueDir . '_converted');
    }

    #[Test]
    public function test_cacheZipArchive_creates_archive(): void
    {
        $zipSource = $this->tempDir . '/source.zip';
        file_put_contents($zipSource, 'fake zip content');

        $result = $this->fs->cacheZipArchive('abc123def456', $zipSource);
        $this->assertTrue($result);
        $this->assertFileDoesNotExist($zipSource);
    }

    #[Test]
    public function test_transferFiles_returns_true(): void
    {
        $result = $this->fs->transferFiles('/source', '/dest');
        $this->assertTrue($result);
    }
}
