<?php

namespace unit\Model\Conversion;

use Model\Conversion\ZipArchiveHandler;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;

class ZipArchiveHandlerTest extends AbstractTest
{

    // ================================================
    // message()
    // ================================================

    #[Test]
    public function messageReturnsCorrectStrings(): void
    {
        $za = new ZipArchiveHandler();

        $this->assertEquals('No error', $za->message(0));
        $this->assertEquals('No such file', $za->message(9));
        $this->assertEquals('Not a zip archive', $za->message(19));
        $this->assertStringContainsString('unknown error', $za->message(999));
    }

    // ================================================
    // isDir()
    // ================================================

    #[Test]
    public function isDirReturnsTrueForTrailingSeparator(): void
    {
        $za = new ZipArchiveHandler();
        $this->assertTrue($za->isDir('folder/'));
    }

    #[Test]
    public function isDirReturnsFalseForFile(): void
    {
        $za = new ZipArchiveHandler();
        $this->assertFalse($za->isDir('folder/file.txt'));
    }

    // ================================================
    // Static utility methods
    // ================================================

    #[Test]
    public function zipPathInfoWithValidPath(): void
    {
        $sep = ZipArchiveHandler::INTERNAL_SEPARATOR;
        $path = "archive.zip{$sep}folder{$sep}file.txt";

        $info = ZipArchiveHandler::zipPathInfo($path);

        $this->assertNotNull($info);
        $this->assertEquals('archive.zip', $info['zipfilename']);
        $this->assertEquals('file.txt', $info['basename']);
        $this->assertEquals('txt', $info['extension']);
        $this->assertEquals('file', $info['filename']);
        $this->assertEquals('folder', $info['dirname']);
    }

    #[Test]
    public function zipPathInfoReturnsNullWithoutSeparator(): void
    {
        $this->assertNull(ZipArchiveHandler::zipPathInfo('plain_file.txt'));
    }

    #[Test]
    public function getFileNameConvertsInternalSeparator(): void
    {
        $sep = ZipArchiveHandler::INTERNAL_SEPARATOR;
        $result = ZipArchiveHandler::getFileName("archive.zip{$sep}folder{$sep}doc.txt");

        $this->assertEquals('archive.zip' . DIRECTORY_SEPARATOR . 'folder' . DIRECTORY_SEPARATOR . 'doc.txt', $result);
    }

    #[Test]
    public function getInternalFileNameConvertsDirectorySeparator(): void
    {
        $sep = ZipArchiveHandler::INTERNAL_SEPARATOR;
        $result = ZipArchiveHandler::getInternalFileName('archive.zip' . DIRECTORY_SEPARATOR . 'folder' . DIRECTORY_SEPARATOR . 'doc.txt');

        $this->assertEquals("archive.zip{$sep}folder{$sep}doc.txt", $result);
    }

    #[Test]
    public function getFileNameAndGetInternalFileNameAreInverses(): void
    {
        $original = 'my.zip' . DIRECTORY_SEPARATOR . 'sub' . DIRECTORY_SEPARATOR . 'file.docx';
        $internal = ZipArchiveHandler::getInternalFileName($original);
        $restored = ZipArchiveHandler::getFileName($internal);

        $this->assertEquals($original, $restored);
    }

    // ================================================
    // createTree() + extractFilesInTmp() with real zip
    // ================================================

    private string $tmpDir;

    public function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'matecat_za_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    public function tearDown(): void
    {
        // Clean up temp files
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tmpDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($this->tmpDir);

        parent::tearDown();
    }

    private function createZip(string $name, array $entries): string
    {
        $path = $this->tmpDir . DIRECTORY_SEPARATOR . $name;
        $za = new \ZipArchive();
        $za->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($entries as $entryName => $content) {
            $za->addFromString($entryName, $content);
        }
        $za->close();

        return $path;
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function createTreeBuildsTreeAndTreeList(): void
    {
        $zipPath = $this->createZip('test.zip', [
            'readme.txt' => 'Hello',
            'docs/guide.md' => '# Guide',
        ]);

        $za = new ZipArchiveHandler();
        $za->open($zipPath);
        $za->createTree();
        $za->close();

        $this->assertNotEmpty($za->tree);
        $this->assertNotEmpty($za->treeList);
        $this->assertCount(2, $za->treeList);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function createTreeSkipsMacOSXFolder(): void
    {
        $zipPath = $this->createZip('macos.zip', [
            '__MACOSX/._file' => 'mac resource fork',
            'real.txt' => 'content',
        ]);

        $za = new ZipArchiveHandler();
        $za->open($zipPath);
        $za->createTree();
        $za->close();

        $this->assertCount(1, $za->treeList);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function createTreeSkipsDSStore(): void
    {
        $zipPath = $this->createZip('dsstore.zip', [
            'folder/.DS_Store' => '',
            'folder/file.txt' => 'content',
        ]);

        $za = new ZipArchiveHandler();
        $za->open($zipPath);
        $za->createTree();
        $za->close();

        // Only file.txt should be in treeList, .DS_Store is skipped
        $this->assertCount(1, $za->treeList);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function extractFilesInTmpExtractsCorrectly(): void
    {
        $zipPath = $this->createZip('extract.zip', [
            'hello.txt' => 'Hello World',
        ]);

        $za = new ZipArchiveHandler();
        $za->open($zipPath);
        $za->createTree();

        $extractDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'extracted' . DIRECTORY_SEPARATOR;
        mkdir($extractDir, 0777, true);

        $result = $za->extractFilesInTmp($extractDir);
        $za->close();

        $this->assertCount(1, $result);

        $first = reset($result);
        $this->assertEquals(11, $first['size']); // "Hello World" = 11 bytes
        $this->assertFileExists($first['tmp_name']);
        $this->assertEquals('Hello World', file_get_contents($first['tmp_name']));
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function zipPathInfoWithSimpleFile(): void
    {
        $sep = ZipArchiveHandler::INTERNAL_SEPARATOR;
        // Just zip name + file, no subdirectory
        $path = "archive.zip{$sep}file.txt";

        $info = ZipArchiveHandler::zipPathInfo($path);

        $this->assertNotNull($info);
        $this->assertEquals('archive.zip', $info['zipfilename']);
        $this->assertEquals('file.txt', $info['basename']);
        $this->assertEquals('txt', $info['extension']);
    }
}

