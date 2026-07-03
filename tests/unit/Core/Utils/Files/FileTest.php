<?php

namespace Matecat\Core\Utils\Files;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Utils\Files\File;

class FileTest extends AbstractTest
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/file_test_' . uniqid();
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmpDir . '/*') ?: []);
        rmdir($this->tmpDir);
        parent::tearDown();
    }

    #[Test]
    public function createMakesFile(): void
    {
        $path = $this->tmpDir . '/newfile.txt';

        $this->assertFalse(File::exists($path));
        File::create($path);
        $this->assertTrue(File::exists($path));
    }

    #[Test]
    public function createDoesNotOverwriteExisting(): void
    {
        $path = $this->tmpDir . '/existing.txt';
        file_put_contents($path, 'content');

        File::create($path);

        $this->assertSame('content', file_get_contents($path));
    }

    #[Test]
    public function deleteRemovesFile(): void
    {
        $path = $this->tmpDir . '/todelete.txt';
        touch($path);

        $this->assertTrue(File::exists($path));
        File::delete($path);
        $this->assertFalse(File::exists($path));
    }

    #[Test]
    public function deleteDoesNothingForNonexistent(): void
    {
        File::delete($this->tmpDir . '/nonexistent.txt');
        $this->assertFalse(File::exists($this->tmpDir . '/nonexistent.txt'));
    }

    #[Test]
    public function existsReturnsTrueForExistingFile(): void
    {
        $path = $this->tmpDir . '/exists.txt';
        touch($path);

        $this->assertTrue(File::exists($path));
    }

    #[Test]
    public function existsReturnsFalseForMissing(): void
    {
        $this->assertFalse(File::exists($this->tmpDir . '/missing.txt'));
    }

    #[Test]
    public function infoReturnsPathInfo(): void
    {
        $info = File::info('/tmp/test.csv');

        $this->assertIsArray($info);
        $this->assertSame('csv', $info['extension']);
        $this->assertSame('test', $info['filename']);
    }
}
