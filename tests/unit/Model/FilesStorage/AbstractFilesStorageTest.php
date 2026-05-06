<?php

use Model\FilesStorage\FsFilesStorage;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

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

        $result = $reflection->invoke($this->fs, $nonExistentDir, 'somehash', 'file.txt');
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
}
