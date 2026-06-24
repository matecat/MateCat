<?php


namespace Matecat\Core\Model\FilesStorage;

use Matecat\TestHelpers\AbstractTest;
use Model\FilesStorage\NativeFilesystemAdapter;
use PHPUnit\Framework\Attributes\Test;

class NativeFilesystemAdapterTest extends AbstractTest
{
    private NativeFilesystemAdapter $adapter;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new NativeFilesystemAdapter();
        $this->tempDir = '/tmp/native_fs_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->tempDir)) {
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->tempDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($items as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($this->tempDir);
        }
    }

    #[Test]
    public function test_copy(): void
    {
        $src = $this->tempDir . '/source.txt';
        file_put_contents($src, 'hello');
        $dest = $this->tempDir . '/dest.txt';

        $result = $this->adapter->copy($src, $dest);

        $this->assertTrue($result);
        $this->assertFileExists($dest);
        $this->assertSame('hello', file_get_contents($dest));
    }

    #[Test]
    public function test_link(): void
    {
        $target = $this->tempDir . '/target.txt';
        file_put_contents($target, 'data');
        $link = $this->tempDir . '/hardlink.txt';

        $result = $this->adapter->link($target, $link);

        $this->assertTrue($result);
        $this->assertFileExists($link);
        $this->assertSame('data', file_get_contents($link));
    }

    #[Test]
    public function test_mkdir(): void
    {
        $subdir = $this->tempDir . '/sub/nested';

        $result = $this->adapter->mkdir($subdir, 0755, true);

        $this->assertTrue($result);
        $this->assertDirectoryExists($subdir);
    }

    #[Test]
    public function test_unlink(): void
    {
        $file = $this->tempDir . '/todelete.txt';
        file_put_contents($file, 'bye');

        $result = $this->adapter->unlink($file);

        $this->assertTrue($result);
        $this->assertFileDoesNotExist($file);
    }

    #[Test]
    public function test_touch(): void
    {
        $file = $this->tempDir . '/touched.txt';

        $result = $this->adapter->touch($file);

        $this->assertTrue($result);
        $this->assertFileExists($file);
    }

    #[Test]
    public function test_fileExists(): void
    {
        $file = $this->tempDir . '/exists.txt';
        file_put_contents($file, 'x');

        $this->assertTrue($this->adapter->fileExists($file));
        $this->assertFalse($this->adapter->fileExists($this->tempDir . '/nope.txt'));
    }

    #[Test]
    public function test_isDir(): void
    {
        $file = $this->tempDir . '/afile.txt';
        file_put_contents($file, 'x');

        $this->assertTrue($this->adapter->isDir($this->tempDir));
        $this->assertFalse($this->adapter->isDir($file));
    }

    #[Test]
    public function test_isFile(): void
    {
        $file = $this->tempDir . '/afile.txt';
        file_put_contents($file, 'x');

        $this->assertTrue($this->adapter->isFile($file));
        $this->assertFalse($this->adapter->isFile($this->tempDir));
    }

    #[Test]
    public function test_fileGetContents(): void
    {
        $file = $this->tempDir . '/read.txt';
        file_put_contents($file, 'content here');

        $result = $this->adapter->fileGetContents($file);

        $this->assertSame('content here', $result);
    }

    #[Test]
    public function test_filePutContents(): void
    {
        $file = $this->tempDir . '/write.txt';

        $result = $this->adapter->filePutContents($file, 'written');

        $this->assertGreaterThan(0, $result);
        $this->assertSame('written', file_get_contents($file));
    }

    #[Test]
    public function test_file(): void
    {
        $file = $this->tempDir . '/lines.txt';
        file_put_contents($file, "line1\nline2\nline3\n");

        $result = $this->adapter->file($file, FILE_IGNORE_NEW_LINES);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertSame('line1', $result[0]);
        $this->assertSame('line2', $result[1]);
        $this->assertSame('line3', $result[2]);
    }

    #[Test]
    public function test_scandir(): void
    {
        file_put_contents($this->tempDir . '/a.txt', 'a');
        file_put_contents($this->tempDir . '/b.txt', 'b');

        $result = $this->adapter->scandir($this->tempDir);

        $this->assertIsArray($result);
        $this->assertContains('a.txt', $result);
        $this->assertContains('b.txt', $result);
    }

    #[Test]
    public function test_deleteDir(): void
    {
        $sub = $this->tempDir . '/delme';
        mkdir($sub, 0755, true);
        file_put_contents($sub . '/file.txt', 'x');

        $result = $this->adapter->deleteDir($sub);

        $this->assertTrue($result);
        $this->assertDirectoryDoesNotExist($sub);
    }

    #[Test]
    public function test_iterateDirectory(): void
    {
        file_put_contents($this->tempDir . '/one.txt', '1');
        file_put_contents($this->tempDir . '/two.txt', '2');

        $iter = $this->adapter->iterateDirectory($this->tempDir);
        $names = [];
        foreach ($iter as $entry) {
            if (!$entry->isDot()) {
                $names[] = $entry->getFilename();
            }
        }

        sort($names);
        $this->assertSame(['one.txt', 'two.txt'], $names);
    }

    #[Test]
    public function test_iterateDirectoryRecursive(): void
    {
        $sub = $this->tempDir . '/nested';
        mkdir($sub, 0755, true);
        file_put_contents($this->tempDir . '/root.txt', 'r');
        file_put_contents($sub . '/deep.txt', 'd');

        $iter = $this->adapter->iterateDirectoryRecursive($this->tempDir);
        $names = [];
        foreach ($iter as $entry) {
            $names[] = $entry->getFilename();
        }

        sort($names);
        $this->assertContains('deep.txt', $names);
        $this->assertContains('root.txt', $names);
        $this->assertContains('nested', $names);
    }
}
