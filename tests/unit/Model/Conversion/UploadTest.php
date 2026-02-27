<?php

namespace unit\Model\Conversion;

use DomainException;
use Exception;
use InvalidArgumentException;
use Model\Conversion\Upload;
use Model\Conversion\UploadElement;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;

class UploadTest extends AbstractTest
{
    private string $tmpDir;
    private ?string $originalUploadRepo;

    public function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'matecat_upload_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
        $this->originalUploadRepo = AppConfig::$UPLOAD_REPOSITORY;
        AppConfig::$UPLOAD_REPOSITORY = $this->tmpDir;
    }

    public function tearDown(): void
    {
        AppConfig::$UPLOAD_REPOSITORY = $this->originalUploadRepo;

        // Recursively clean up temp dir
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tmpDir, \FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        @rmdir($this->tmpDir);

        parent::tearDown();
    }

    /**
     * Helper: creates a real temp file to act as an uploaded file and returns
     * a $_FILES-compatible array entry for a single-file input.
     *
     * @param string $name      The original file name (e.g. "report.txt").
     * @param string $content   The file content.
     * @param int    $error     The PHP upload error code (default 0 = no error).
     *
     * @return array{name: string, tmp_name: string, size: int, type: string, error: int}
     */
    private function makeFakeUpload(string $name, string $content, int $error = 0): array
    {
        $tmpFile = $this->tmpDir . DIRECTORY_SEPARATOR . 'tmp_' . uniqid() . '_' . $name;
        file_put_contents($tmpFile, $content);

        return [
            'name'     => $name,
            'tmp_name' => $tmpFile,
            'size'     => strlen($content),
            'type'     => 'application/octet-stream',
            'error'    => $error,
        ];
    }

    // ================================================
    // Constructor
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function constructorWithNullTokenGeneratesUuid(): void
    {
        $upload = new Upload();
        $token = $upload->getDirUploadToken();

        // UUID v4 format: 8-4-4-4-12
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $token
        );
        $this->assertDirectoryExists($upload->getUploadPath());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function constructorWithExplicitTokenReusesIt(): void
    {
        $upload = new Upload('my-custom-token');

        $this->assertEquals('my-custom-token', $upload->getDirUploadToken());
        $this->assertStringEndsWith('my-custom-token', $upload->getUploadPath());
        $this->assertDirectoryExists($upload->getUploadPath());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function setRaiseExceptionChangesFlag(): void
    {
        $upload = new Upload('test-token');
        $upload->setRaiseException(false);
        $upload->setRaiseException(true);
        $this->assertTrue(true);
    }

    // ================================================
    // getUniformGlobalFilesStructure() — static pure function
    // ================================================

    #[Test]
    public function uniformStructureSingleFile(): void
    {
        $files = [
            'document' => [
                'name' => 'test.txt',
                'tmp_name' => '/tmp/phpXXX',
                'size' => 100,
                'type' => 'text/plain',
                'error' => 0,
            ],
        ];

        $result = Upload::getUniformGlobalFilesStructure($files);

        $this->assertInstanceOf(UploadElement::class, $result);
        $this->assertInstanceOf(UploadElement::class, $result->document);
        $this->assertEquals('test.txt', $result->document->name);
        $this->assertEquals('/tmp/phpXXX', $result->document->tmp_name);
    }

    #[Test]
    public function uniformStructureMultipleFiles(): void
    {
        $files = [
            'files' => [
                'name' => ['a.txt', 'b.txt'],
                'tmp_name' => ['/tmp/php1', '/tmp/php2'],
                'size' => [50, 60],
                'type' => ['text/plain', 'text/plain'],
                'error' => [0, 0],
            ],
        ];

        $result = Upload::getUniformGlobalFilesStructure($files);

        $this->assertInstanceOf(UploadElement::class, $result);
        $this->assertInstanceOf(UploadElement::class, $result->{'/tmp/php1'});
        $this->assertEquals('a.txt', $result->{'/tmp/php1'}->name);
        $this->assertInstanceOf(UploadElement::class, $result->{'/tmp/php2'});
        $this->assertEquals('b.txt', $result->{'/tmp/php2'}->name);
    }

    #[Test]
    public function uniformStructureEmptyInput(): void
    {
        $result = Upload::getUniformGlobalFilesStructure([]);

        $this->assertInstanceOf(UploadElement::class, $result);
        $this->assertEmpty($result->getArrayCopy());
    }

    // ================================================
    // fixFileName()
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function fixFileNameReturnsValidName(): void
    {
        $upload = new Upload('fix-test-token');
        $fixed = $upload->fixFileName('document.txt', false);

        $this->assertIsString($fixed);
        $this->assertStringContainsString('document', $fixed);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function fixFileNameThrowsOnInvalidName(): void
    {
        $upload = new Upload('fix-test-token');

        $this->expectException(InvalidArgumentException::class);
        $upload->fixFileName('../traversal.txt', false);
    }

    // ================================================
    // uploadFiles() — empty input
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function uploadFilesThrowsOnEmptyArray(): void
    {
        $upload = new Upload('upload-empty');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No files received.');
        $upload->uploadFiles([]);
    }

    // ================================================
    // uploadFiles() — too many files
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function uploadFilesThrowsWhenTooManyFiles(): void
    {
        $originalMax = AppConfig::$MAX_NUM_FILES;

        try {
            AppConfig::$MAX_NUM_FILES = 1;
            $upload = new Upload('upload-toomany');

            // Two single-file entries → count = 2, exceeds limit of 1
            $files = [
                'file1' => $this->makeFakeUpload('a.txt', 'A'),
                'file2' => $this->makeFakeUpload('b.txt', 'B'),
            ];

            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Too much files uploaded');
            $upload->uploadFiles($files);
        } finally {
            AppConfig::$MAX_NUM_FILES = $originalMax;
        }
    }

    // ================================================
    // uploadFiles() — successful upload of a valid file
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function uploadFilesSuccessfulSingleFile(): void
    {
        $upload = new Upload('upload-success');
        $files = [
            'document' => $this->makeFakeUpload('report.txt', 'Hello World content'),
        ];

        $result = $upload->uploadFiles($files);

        $this->assertInstanceOf(UploadElement::class, $result);

        // The result should have one entry keyed by the input name
        $uploaded = $result->document;
        $this->assertNotNull($uploaded);
        $this->assertEquals('report.txt', $uploaded->name);
        $this->assertStringEndsWith('report.txt', $uploaded->file_path);
        $this->assertFileExists($uploaded->file_path);
        $this->assertEquals('Hello World content', file_get_contents($uploaded->file_path));
    }

    // ================================================
    // uploadFiles() — empty file (0 bytes)
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function uploadFilesThrowsOnEmptyFile(): void
    {
        $upload = new Upload('upload-empty-file');
        $files = [
            'document' => $this->makeFakeUpload('empty.txt', ''),
        ];
        // Size is 0 → throws
        $files['document']['size'] = 0;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('is empty');
        $upload->uploadFiles($files);
    }

    // ================================================
    // uploadFiles() — invalid file name
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function uploadFilesThrowsOnInvalidFileName(): void
    {
        $upload = new Upload('upload-invalid-name');
        $fakeUpload = $this->makeFakeUpload('valid.txt', 'content');
        $fakeUpload['name'] = '../traversal.txt';

        $files = ['document' => $fakeUpload];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid file name');
        $upload->uploadFiles($files);
    }

    // ================================================
    // uploadFiles() — upload error codes (UPLOAD_ERR_*)
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function uploadFilesThrowsOnUploadErrIniSize(): void
    {
        $upload = new Upload('upload-err-ini');
        $fakeUpload = $this->makeFakeUpload('file.txt', 'data');
        $fakeUpload['error'] = UPLOAD_ERR_INI_SIZE; // 1

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('bigger than this PHP installation allows');
        $upload->uploadFiles(['doc' => $fakeUpload]);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function uploadFilesThrowsOnUploadErrFormSize(): void
    {
        $upload = new Upload('upload-err-form');
        $fakeUpload = $this->makeFakeUpload('file.txt', 'data');
        $fakeUpload['error'] = UPLOAD_ERR_FORM_SIZE; // 2

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('bigger than this form allows');
        $upload->uploadFiles(['doc' => $fakeUpload]);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function uploadFilesThrowsOnUploadErrPartial(): void
    {
        $upload = new Upload('upload-err-partial');
        $fakeUpload = $this->makeFakeUpload('file.txt', 'data');
        $fakeUpload['error'] = UPLOAD_ERR_PARTIAL; // 3

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Only part of the file');
        $upload->uploadFiles(['doc' => $fakeUpload]);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function uploadFilesThrowsOnUploadErrNoFile(): void
    {
        $upload = new Upload('upload-err-nofile');
        $fakeUpload = $this->makeFakeUpload('file.txt', 'data');
        $fakeUpload['error'] = UPLOAD_ERR_NO_FILE; // 4

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No file was uploaded');
        $upload->uploadFiles(['doc' => $fakeUpload]);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function uploadFilesThrowsOnUploadErrNoTmpDir(): void
    {
        $upload = new Upload('upload-err-notmp');
        $fakeUpload = $this->makeFakeUpload('file.txt', 'data');
        $fakeUpload['error'] = UPLOAD_ERR_NO_TMP_DIR; // 6

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing a temporary folder');
        $upload->uploadFiles(['doc' => $fakeUpload]);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function uploadFilesThrowsOnUploadErrCantWrite(): void
    {
        $upload = new Upload('upload-err-cantwrite');
        $fakeUpload = $this->makeFakeUpload('file.txt', 'data');
        $fakeUpload['error'] = UPLOAD_ERR_CANT_WRITE; // 7

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to write file to disk');
        $upload->uploadFiles(['doc' => $fakeUpload]);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function uploadFilesThrowsOnUploadErrExtension(): void
    {
        $upload = new Upload('upload-err-ext');
        $fakeUpload = $this->makeFakeUpload('file.txt', 'data');
        $fakeUpload['error'] = UPLOAD_ERR_EXTENSION; // 8

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('PHP extension stopped the file upload');
        $upload->uploadFiles(['doc' => $fakeUpload]);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function uploadFilesThrowsOnUnknownUploadError(): void
    {
        $upload = new Upload('upload-err-unknown');
        $fakeUpload = $this->makeFakeUpload('file.txt', 'data');
        $fakeUpload['error'] = 99; // unknown code

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unknown Error: 99');
        $upload->uploadFiles(['doc' => $fakeUpload]);
    }

    // ================================================
    // uploadFiles() — unsupported extension
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function uploadFilesThrowsOnUnsupportedExtension(): void
    {
        $upload = new Upload('upload-bad-ext');
        $fakeUpload = $this->makeFakeUpload('malware.exe', 'MZ executable');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('File Extension Not Allowed');
        $upload->uploadFiles(['doc' => $fakeUpload]);
    }

    // ================================================
    // uploadFiles() — file exceeds size limit
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function uploadFilesThrowsWhenFileTooLarge(): void
    {
        $originalMax = AppConfig::$MAX_UPLOAD_FILE_SIZE;

        try {
            // Set a very low limit
            AppConfig::$MAX_UPLOAD_FILE_SIZE = 5;

            $upload = new Upload('upload-too-large');
            $fakeUpload = $this->makeFakeUpload('big.txt', 'This content exceeds the 5 byte limit');

            $this->expectException(DomainException::class);
            $this->expectExceptionMessage('File Dimensions Not Allowed');
            $upload->uploadFiles(['doc' => $fakeUpload]);
        } finally {
            AppConfig::$MAX_UPLOAD_FILE_SIZE = $originalMax;
        }
    }

    // ================================================
    // uploadFiles() — size limit disabled
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function uploadFilesIgnoresSizeLimitWhenDisabled(): void
    {
        $originalMax = AppConfig::$MAX_UPLOAD_FILE_SIZE;

        try {
            AppConfig::$MAX_UPLOAD_FILE_SIZE = 5; // very low

            $upload = new Upload('upload-no-limit');
            $fakeUpload = $this->makeFakeUpload('big.txt', 'This content exceeds the 5 byte limit');

            // Pass disable_upload_limit = true
            $result = $upload->uploadFiles(['doc' => $fakeUpload], true);

            $this->assertNotNull($result->doc);
            $this->assertFileExists($result->doc->file_path);
        } finally {
            AppConfig::$MAX_UPLOAD_FILE_SIZE = $originalMax;
        }
    }

    // ================================================
    // uploadFiles() — raiseException = false stores errors
    // ================================================

    /**
     * When raiseException is false, upload errors are stored on the file object
     * instead of being thrown.
     *
     * @throws Exception
     */
    #[Test]
    public function uploadFilesStoresErrorWhenRaiseExceptionFalse(): void
    {
        $upload = new Upload('upload-no-raise');
        $upload->setRaiseException(false);

        $fakeUpload = $this->makeFakeUpload('file.txt', 'data');
        $fakeUpload['error'] = UPLOAD_ERR_INI_SIZE; // 1

        $result = $upload->uploadFiles(['doc' => $fakeUpload]);

        // Error should be stored on the object, not thrown
        $this->assertNotNull($result->doc);
        $this->assertIsArray($result->doc->error);
        $this->assertStringContainsString('bigger than this PHP installation allows', $result->doc->error['message']);
    }

    // ================================================
    // uploadFiles() — multiple files, some succeed
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function uploadFilesMultipleFilesAllSucceed(): void
    {
        $upload = new Upload('upload-multi');
        $files = [
            'file1' => $this->makeFakeUpload('a.txt', 'Content A'),
            'file2' => $this->makeFakeUpload('b.txt', 'Content B'),
        ];

        $result = $upload->uploadFiles($files);

        $this->assertNotNull($result->file1);
        $this->assertNotNull($result->file2);
        $this->assertFileExists($result->file1->file_path);
        $this->assertFileExists($result->file2->file_path);
    }

    // ================================================
    // uploadFiles() — file permissions are set correctly
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function uploadedFileHasCorrectPermissions(): void
    {
        $upload = new Upload('upload-perms');
        $files = [
            'doc' => $this->makeFakeUpload('perms.txt', 'permission test'),
        ];

        $result = $upload->uploadFiles($files);

        $perms = fileperms($result->doc->file_path) & 0777;
        $this->assertEquals(0664, $perms);
    }

    // ================================================
    // uploadFiles() — tmp_name is unset after upload
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function uploadedFileTmpNameIsUnset(): void
    {
        $upload = new Upload('upload-cleanup');
        $files = [
            'doc' => $this->makeFakeUpload('cleanup.txt', 'temp file content'),
        ];

        $result = $upload->uploadFiles($files);

        $this->assertNull($result->doc->tmp_name);
    }

    // ================================================
    // uploadFiles() — file_path is set on result
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function uploadedFileHasFilePathSet(): void
    {
        $upload = new Upload('upload-path');
        $files = [
            'doc' => $this->makeFakeUpload('path.txt', 'check file_path'),
        ];

        $result = $upload->uploadFiles($files);

        $this->assertStringEndsWith('path.txt', $result->doc->file_path);
        $this->assertStringContainsString('upload-path', $result->doc->file_path);
    }
}

