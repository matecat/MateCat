<?php

namespace unit\Model\Conversion;

use DomainException;
use Exception;
use Model\Conversion\ConvertedFileList;
use Model\Conversion\FilesConverter;
use Model\FeaturesBase\FeatureSet;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use TestHelpers\AbstractTest;
use Utils\Constants\ConversionHandlerStatus;
use Utils\Registry\AppConfig;

class FilesConverterTest extends AbstractTest
{
    private string $tmpDir;

    public function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'matecat_fc_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    public function tearDown(): void
    {
        // Recursively clean up temp dir
        if (is_dir($this->tmpDir)) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->tmpDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $file) {
                $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
            }
            @rmdir($this->tmpDir);
        }

        parent::tearDown();
    }

    private function createConverter(array $files): FilesConverter
    {
        return new FilesConverter(
            files: $files,
            source_lang: 'en-US',
            target_lang: 'it-IT',
            intDir: $this->tmpDir,
            errDir: $this->tmpDir,
            uploadTokenValue: 'test-token-' . uniqid(),
            icu_enabled: false,
            segmentation_rule: null,
            featureSet: new FeatureSet(),
        );
    }

    // ================================================
    // Constructor & getResult()
    // ================================================

    #[Test]
    public function constructorInitializesEmptyResultStack(): void
    {
        $converter = $this->createConverter([]);
        $result = $converter->getResult();

        $this->assertInstanceOf(ConvertedFileList::class, $result);
        $this->assertFalse($result->hasErrors());
        $this->assertFalse($result->hasWarnings());
        $this->assertEmpty($result->getData());
    }

    // ================================================
    // convertFiles() — empty file list
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function convertFilesWithEmptyListReturnsEmptyResult(): void
    {
        $converter = $this->createConverter([]);
        $result = $converter->convertFiles();

        $this->assertInstanceOf(ConvertedFileList::class, $result);
        $this->assertEmpty($result->getData());
        $this->assertFalse($result->hasErrors());
    }

    // ================================================
    // convertFiles() — file not found triggers error
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function convertFilesWithMissingFileRecordsError(): void
    {
        $converter = $this->createConverter(['nonexistent.docx']);
        $result = $converter->convertFiles();

        $this->assertTrue($result->hasErrors());
        $errors = $result->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals(ConversionHandlerStatus::UPLOAD_ERROR, $errors[0]['code']);
    }

    // ================================================
    // convertFiles() — nested zip throws DomainException
    // ================================================

    /**
     * When a zip file contains another .zip file, convertFiles() must throw
     * a DomainException with NESTED_ZIP_FILES_NOT_ALLOWED code.
     *
     * @throws Exception
     */
    #[Test]
    public function convertFilesNestedZipThrowsDomainException(): void
    {
        // Create an inner zip
        $innerZipPath = $this->tmpDir . DIRECTORY_SEPARATOR . 'inner.zip';
        $innerZa = new \ZipArchive();
        $innerZa->open($innerZipPath, \ZipArchive::CREATE);
        $innerZa->addFromString('nested.txt', 'content');
        $innerZa->close();

        // Create an outer zip containing the inner zip
        $outerZipPath = $this->tmpDir . DIRECTORY_SEPARATOR . 'outer.zip';
        $outerZa = new \ZipArchive();
        $outerZa->open($outerZipPath, \ZipArchive::CREATE);
        $outerZa->addFile($innerZipPath, 'inner.zip');
        $outerZa->close();

        $converter = $this->createConverter(['outer.zip']);

        $this->expectException(DomainException::class);
        $this->expectExceptionCode(ConversionHandlerStatus::NESTED_ZIP_FILES_NOT_ALLOWED);
        $converter->convertFiles();
    }

    // ================================================
    // convertFiles() — multiple files, all missing
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function convertFilesMultipleMissingFilesRecordsAllErrors(): void
    {
        $converter = $this->createConverter(['missing1.docx', 'missing2.txt']);
        $result = $converter->convertFiles();

        $this->assertTrue($result->hasErrors());
        $this->assertCount(2, $result->getErrors());
    }

    // ================================================
    // getResult() reflects state after convertFiles()
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function getResultReflectsStateAfterConversion(): void
    {
        $converter = $this->createConverter(['missing.docx']);
        $converter->convertFiles();

        $result = $converter->getResult();
        $this->assertTrue($result->hasErrors());
    }
}

