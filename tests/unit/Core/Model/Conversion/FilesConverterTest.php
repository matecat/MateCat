<?php

namespace Matecat\Core\Model\Conversion;

use DomainException;
use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\Conversion\ConvertedFileList;
use Model\Conversion\FilesConverter;
use Model\FeaturesBase\FeatureSet;
use PHPUnit\Framework\Attributes\Test;
use Utils\Constants\ConversionHandlerStatus;

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
            featureSet: new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)),
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

    // ================================================
    // convertFiles() — zip with a non-zip file inside
    // ================================================

    /**
     * A zip containing a missing (non-zip) file exercises the inner-zip
     * convertFile() path (lines 147-154): the file conversion fails with an
     * error, which is added to the result stack and recorded as an errored file.
     *
     * @throws Exception
     */
    #[Test]
    public function convertFilesZipWithInternalFileRecordsError(): void
    {
        // Create a zip containing one text file (not a zip).
        $zipPath = $this->tmpDir . DIRECTORY_SEPARATOR . 'test_inner.zip';
        $za = new \ZipArchive();
        $za->open($zipPath, \ZipArchive::CREATE);
        $za->addFromString('document.docx', 'fake docx content');
        $za->close();

        // Copy zip to the upload dir so the ConversionHandler can find it.
        copy($zipPath, $this->tmpDir . DIRECTORY_SEPARATOR . 'test_inner.zip');

        $converter = new \Model\Conversion\FilesConverter(
            files: ['test_inner.zip'],
            source_lang: 'en-US',
            target_lang: 'it-IT',
            intDir: $this->tmpDir,
            errDir: $this->tmpDir,
            uploadTokenValue: 'test-token-' . uniqid(),
            icu_enabled: false,
            segmentation_rule: null,
            featureSet: new \Model\FeaturesBase\FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)),
        );

        $result = $converter->convertFiles();

        // The internal file conversion failed (missing from storage) → error recorded.
        $this->assertTrue($result->hasErrors());
    }

    // ================================================
    // convertFiles() — empty zip triggers DomainException
    // ================================================

    /**
     * An empty (but valid) zip causes extractZipFile() to return [] with no
     * zipExtractionErrorFlag set, so convertFiles() throws a DomainException
     * via the `empty($internalZipFileNames)` guard (lines 234-238).
     *
     * @throws Exception
     */
    #[Test]
    public function convertFilesEmptyZipThrowsDomainException(): void
    {
        // Create a valid zip archive that contains no files.
        $zipPath = $this->tmpDir . DIRECTORY_SEPARATOR . 'empty.zip';
        $za = new \ZipArchive();
        $za->open($zipPath, \ZipArchive::CREATE);
        $za->close();

        $converter = new \Model\Conversion\FilesConverter(
            files: ['empty.zip'],
            source_lang: 'en-US',
            target_lang: 'it-IT',
            intDir: $this->tmpDir,
            errDir: $this->tmpDir,
            uploadTokenValue: 'test-token-' . uniqid(),
            icu_enabled: false,
            segmentation_rule: null,
            featureSet: new \Model\FeaturesBase\FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)),
        );

        $this->expectException(\DomainException::class);
        $converter->convertFiles();
    }
}

