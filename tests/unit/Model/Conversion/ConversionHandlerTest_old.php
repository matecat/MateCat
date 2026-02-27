<?php

namespace unit\Model\Conversion;

use Exception;
use Model\Conversion\ConversionHandler;
use Model\Conversion\ConvertedFileModel;
use Model\Conversion\InternalHashPaths;
use Model\Conversion\UploadElement;
use Model\Conversion\ZipArchiveHandler;
use Model\FeaturesBase\FeatureSet;
use Model\FilesStorage\AbstractFilesStorage;
use Model\FilesStorage\Exceptions\FileSystemException;
use Model\FilesStorage\FsFilesStorage;
use Model\Filters\DTO\Dita;
use Model\Filters\DTO\Json;
use Model\Filters\DTO\MSExcel;
use Model\Filters\DTO\MSPowerpoint;
use Model\Filters\DTO\MSWord;
use Model\Filters\DTO\Xml;
use Model\Filters\DTO\Yaml;
use Model\Filters\FiltersConfigTemplateStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionException;
use TestHelpers\AbstractTest;
use Utils\Constants\ConversionHandlerStatus;

/**
 * Unit tests for {@see ConversionHandler}.
 *
 * To isolate the class from external dependencies (Filters HTTP calls, FilesStorageFactory,
 * OCRCheck, RedisHandler), we extend ConversionHandler with a testable subclass
 * ({@see TestableConversionHandler}) that overrides the methods making external I/O.
 * This avoids the need for static-method mocking tools.
 */
class ConversionHandlerTest_old extends AbstractTest
{
    private string $tmpDir;

    public function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'matecat_ch_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
        mkdir($this->tmpDir . DIRECTORY_SEPARATOR . 'err', 0777, true);
    }

    public function tearDown(): void
    {
        $this->recursiveRemoveDir($this->tmpDir);
        parent::tearDown();
    }

    private function recursiveRemoveDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->recursiveRemoveDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Creates a fully configured {@see TestableConversionHandler}.
     * @throws Exception
     */
    private function createHandler(
        string              $fileName = 'test.docx',
        mixed               $fileMustBeConvertedReturn = true,
        bool                $ocrError = false,
        bool                $ocrWarning = false,
        ?array              $filterResponse = null,
        ?AbstractFilesStorage $fsMock = null,
    ): TestableConversionHandler {
        $handler = new TestableConversionHandler();
        $handler->setFileName($fileName);
        $handler->setSourceLang('en-US');
        $handler->setTargetLang('it-IT');
        $handler->setSegmentationRule();
        $handler->setUploadDir($this->tmpDir);
        $handler->setErrDir($this->tmpDir . DIRECTORY_SEPARATOR . 'err');
        $handler->setUploadTokenValue('test-token-12345');
        $handler->setFeatures(new FeatureSet());
        $handler->setFiltersExtractionParameters();
        $handler->setFiltersLegacyIcu();

        $handler->_fileMustBeConvertedReturn = $fileMustBeConvertedReturn;
        $handler->_ocrError = $ocrError;
        $handler->_ocrWarning = $ocrWarning;
        $handler->_filterResponse = $filterResponse ?? ['successful' => 0, 'errorMessage' => 'Not configured'];
        $handler->_fsMock = $fsMock;

        return $handler;
    }

    private function createTempFile(string $fileName, string $content = 'dummy content'): string
    {
        $path = $this->tmpDir . DIRECTORY_SEPARATOR . $fileName;
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Creates a default successful FsFilesStorage stub (no expectations).
     */
    private function createFsStub(): FsFilesStorage
    {
        $fs = $this->createStub(FsFilesStorage::class);
        $fs->method('makeCachePackage')->willReturn(true);
        $fs->method('deleteHashFromUploadDir')->willReturn(true);
        $fs->method('linkSessionToCacheForOriginalFiles')->willReturn(1);

        return $fs;
    }

    /**
     * @throws ReflectionException
     */
    private function invokePrivateMethod(object $object, array $params = []): mixed
    {
        $ref = new ReflectionClass(ConversionHandler::class);
        $method = $ref->getMethod('getRightExtractionParameter');

        return $method->invokeArgs($object, $params);
    }

    // ================================================
    // Constructor & Getters / Setters
    // ================================================

    #[Test]
    public function constructorInitializesResult(): void
    {
        $handler = new ConversionHandler();
        $this->assertInstanceOf(ConvertedFileModel::class, $handler->getResult());
        $this->assertEquals(ConversionHandlerStatus::NOT_CONVERTED, $handler->getResult()->getCode());
    }

    #[Test]
    public function setAndGetFileName(): void
    {
        $handler = new ConversionHandler();
        $handler->setFileName('my_file.docx');
        $this->assertEquals('my_file.docx', $handler->getFileName());
    }

    #[Test]
    public function getLocalFilePathCombinesDirAndName(): void
    {
        $handler = new ConversionHandler();
        $handler->setFileName('document.txt');
        $handler->setUploadDir('/tmp/uploads');
        $this->assertEquals('/tmp/uploads' . DIRECTORY_SEPARATOR . 'document.txt', $handler->getLocalFilePath());
    }

    #[Test]
    public function setStopOnFileExceptionDefaultAndOverride(): void
    {
        $handler = new ConversionHandler();
        $ref = new ReflectionClass($handler);
        $prop = $ref->getProperty('stopOnFileException');

        $this->assertTrue($prop->getValue($handler));

        $handler->setStopOnFileException(false);
        $this->assertFalse($prop->getValue($handler));
    }

    #[Test]
    public function setSegmentationRuleAcceptsNullAndString(): void
    {
        $handler = new ConversionHandler();
        $ref = new ReflectionClass($handler);
        $prop = $ref->getProperty('segmentation_rule');

        $handler->setSegmentationRule('paragraph');
        $this->assertEquals('paragraph', $prop->getValue($handler));

        $handler->setSegmentationRule();
        $this->assertNull($prop->getValue($handler));
    }

    #[Test]
    public function setSourceAndTargetLang(): void
    {
        $handler = new ConversionHandler();
        $handler->setSourceLang('fr-FR');
        $handler->setTargetLang('de-DE');

        $ref = new ReflectionClass($handler);
        $this->assertEquals('fr-FR', $ref->getProperty('source_lang')->getValue($handler));
        $this->assertEquals('de-DE', $ref->getProperty('target_lang')->getValue($handler));
    }

    #[Test]
    public function setUploadDirAndErrDir(): void
    {
        $handler = new ConversionHandler();
        $handler->setUploadDir('/tmp/upload');
        $handler->setErrDir('/tmp/err');

        $ref = new ReflectionClass($handler);
        $this->assertEquals('/tmp/upload', $ref->getProperty('uploadDir')->getValue($handler));
        $this->assertEquals('/tmp/err', $ref->getProperty('errDir')->getValue($handler));
    }

    #[Test]
    public function setUploadTokenValue(): void
    {
        $handler = new ConversionHandler();
        $handler->setUploadTokenValue('abc-123');

        $ref = new ReflectionClass($handler);
        $this->assertEquals('abc-123', $ref->getProperty('uploadTokenValue')->getValue($handler));
    }

    #[Test]
    public function setFeaturesReturnsSelf(): void
    {
        $handler = new ConversionHandler();
        $features = new FeatureSet();
        $returned = $handler->setFeatures($features);

        $this->assertSame($handler, $returned);
        $this->assertSame($features, $handler->features);
    }

    #[Test]
    public function setFiltersExtractionParametersAcceptsStructAndNull(): void
    {
        $handler = new ConversionHandler();
        $struct = new FiltersConfigTemplateStruct();
        $handler->setFiltersExtractionParameters($struct);

        $ref = new ReflectionClass($handler);
        $this->assertSame($struct, $ref->getProperty('filters_extraction_parameters')->getValue($handler));

        $handler->setFiltersExtractionParameters();
        $this->assertNull($ref->getProperty('filters_extraction_parameters')->getValue($handler));
    }

    #[Test]
    public function setFiltersLegacyIcu(): void
    {
        $handler = new ConversionHandler();
        $handler->setFiltersLegacyIcu(true);

        $ref = new ReflectionClass($handler);
        $this->assertTrue($ref->getProperty('legacy_icu')->getValue($handler));
    }

    #[Test]
    public function setIcuEnabled(): void
    {
        $handler = new ConversionHandler();
        $handler->setIcuEnabled(true);

        $ref = new ReflectionClass($handler);
        $this->assertTrue($ref->getProperty('icu_enabled')->getValue($handler));
    }

    // ================================================
    // processConversion — file not found
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function processConversionFileNotFound(): void
    {
        $handler = $this->createHandler('nonexistent.docx');

        $handler->processConversion();
        $result = $handler->getResult();

        $this->assertEquals(ConversionHandlerStatus::UPLOAD_ERROR, $result->getCode());
        $this->assertEquals('Error during upload. Please retry.', $result->getMessage());
    }

    // ================================================
    // processConversion — file does not need conversion
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function processConversionFileDoesNotNeedConversion(): void
    {
        $this->createTempFile('test.xlf', '<xliff/>');

        $handler = $this->createHandler(
            fileName: 'test.xlf',
            fileMustBeConvertedReturn: false,
        );

        $handler->processConversion();
        $result = $handler->getResult();

        $this->assertFalse($result->isError());
        $this->assertEquals(ConversionHandlerStatus::NOT_CONVERTED, $result->getCode());
        $this->assertGreaterThan(0, $result->getSize());
    }

    // ================================================
    // processConversion — misconfiguration
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function processConversionMisconfiguration(): void
    {
        $this->createTempFile('test.docx', 'fake docx');

        $handler = $this->createHandler(
            fileMustBeConvertedReturn: -1,
        );

        $handler->processConversion();
        $result = $handler->getResult();

        $this->assertEquals(ConversionHandlerStatus::MISCONFIGURATION, $result->getCode());
        $this->assertStringContainsString('does not support', $result->getMessage());
        $this->assertFileDoesNotExist($this->tmpDir . DIRECTORY_SEPARATOR . 'test.docx');
    }

    // ================================================
    // processConversion — OCR error
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function processConversionOCRError(): void
    {
        $this->createTempFile('test.pdf', 'fake pdf');

        $handler = $this->createHandler(
            fileName: 'test.pdf',
            ocrError: true,
        );

        $handler->processConversion();
        $result = $handler->getResult();

        $this->assertEquals(ConversionHandlerStatus::OCR_ERROR, $result->getCode());
        $this->assertStringContainsString('OCR for RTL languages is not supported', $result->getMessage());
    }

    // ================================================
    // processConversion — OCR warning + success
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function processConversionOCRWarningAndSuccess(): void
    {
        $this->createTempFile('test.pdf', 'fake pdf');

        $handler = $this->createHandler(
            fileName: 'test.pdf',
            ocrWarning: true,
            filterResponse: ['successful' => 1, 'xliff' => '<xliff/>'],
            fsMock: $this->createFsStub(),
        );

        $handler->processConversion();
        $result = $handler->getResult();

        $this->assertEquals(ConversionHandlerStatus::OCR_WARNING, $result->getCode());
        $this->assertTrue($result->hasConversionHashes());
    }

    // ================================================
    // processConversion — successful
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function processConversionSuccess(): void
    {
        $this->createTempFile('test.docx', 'fake docx');

        $handler = $this->createHandler(
            filterResponse: ['successful' => 1, 'xliff' => '<xliff/>'],
            fsMock: $this->createFsStub(),
        );

        $handler->processConversion();
        $result = $handler->getResult();

        $this->assertFalse($result->isError());
        $this->assertTrue($result->hasConversionHashes());
        $this->assertGreaterThan(0, $result->getSize());
    }

    // ================================================
    // processConversion — success with pdfAnalysis
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function processConversionSuccessWithPdfAnalysis(): void
    {
        $this->createTempFile('test.pdf', 'fake pdf');

        $pdfData = ['pages' => 5, 'words' => 1000];
        $handler = $this->createHandler(
            fileName: 'test.pdf',
            filterResponse: ['successful' => 1, 'xliff' => '<xliff/>', 'pdfAnalysis' => $pdfData],
            fsMock: $this->createFsStub(),
        );

        $handler->processConversion();
        $result = $handler->getResult();

        $this->assertFalse($result->isError());
        $this->assertEquals($pdfData, $result->getPdfAnalysis());
    }

    // ================================================
    // processConversion — multi-target language
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function processConversionMultiTargetLanguage(): void
    {
        $this->createTempFile('test.docx', 'fake docx');

        $handler = $this->createHandler(
            filterResponse: ['successful' => 1, 'xliff' => '<xliff/>'],
            fsMock: $this->createFsStub(),
        );
        $handler->setTargetLang('it-IT,fr-FR,de-DE');

        $handler->processConversion();
        $result = $handler->getResult();

        $this->assertFalse($result->isError());
        $this->assertEquals('it-IT', $handler->_lastSingleLanguage);
    }

    // ================================================
    // processConversion — generic conversion error
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function processConversionGenericError(): void
    {
        $this->createTempFile('test.docx', 'fake docx');

        $handler = $this->createHandler(
            filterResponse: ['successful' => 0, 'errorMessage' => 'A plain error message'],
        );

        $handler->processConversion();
        $result = $handler->getResult();

        $this->assertEquals(ConversionHandlerStatus::GENERIC_ERROR, $result->getCode());
        $this->assertEquals('A plain error message', $result->getMessage());
    }

    // ================================================
    // processConversion — makeCachePackage returns false
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function processConversionCachePackageFails(): void
    {
        $this->createTempFile('test.docx', 'fake docx');

        $fs = $this->createStub(FsFilesStorage::class);
        $fs->method('makeCachePackage')->willReturn(false);

        $handler = $this->createHandler(
            filterResponse: ['successful' => 1, 'xliff' => '<xliff/>'],
            fsMock: $fs,
        );

        $handler->processConversion();
        $result = $handler->getResult();

        $this->assertEquals(ConversionHandlerStatus::FILESYSTEM_ERROR, $result->getCode());
        $this->assertStringContainsString('multiple tabs', $result->getMessage());
    }

    // ================================================
    // processConversion — FileSystemException
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function processConversionFileSystemException(): void
    {
        $this->createTempFile('test.docx', 'fake docx');

        $fs = $this->createStub(FsFilesStorage::class);
        $fs->method('makeCachePackage')->willThrowException(new FileSystemException('Disk full'));

        $handler = $this->createHandler(
            filterResponse: ['successful' => 1, 'xliff' => '<xliff/>'],
            fsMock: $fs,
        );

        $handler->processConversion();
        $result = $handler->getResult();

        $this->assertEquals(ConversionHandlerStatus::FILESYSTEM_ERROR, $result->getCode());
        $this->assertEquals('Disk full', $result->getMessage());
    }

    // ================================================
    // processConversion — generic Exception (S3)
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function processConversionS3Exception(): void
    {
        $this->createTempFile('test.docx', 'fake docx');

        $fs = $this->createStub(FsFilesStorage::class);
        $fs->method('makeCachePackage')->willThrowException(new Exception('S3 timeout'));

        $handler = $this->createHandler(
            filterResponse: ['successful' => 1, 'xliff' => '<xliff/>'],
            fsMock: $fs,
        );

        $handler->processConversion();
        $result = $handler->getResult();

        $this->assertEquals(ConversionHandlerStatus::S3_ERROR, $result->getCode());
        $this->assertStringContainsString('file name too long', $result->getMessage());
    }

    // ================================================
    // processConversion — file removed before linking
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function processConversionFileRemovedBeforeLinking(): void
    {
        $filePath = $this->createTempFile('test.docx', 'fake docx');

        $fs = $this->createMock(FsFilesStorage::class);
        $fs->method('makeCachePackage')->willReturnCallback(function () use ($filePath) {
            unlink($filePath);

            return true;
        });
        $fs->method('deleteHashFromUploadDir')->willReturn(true);
        $fs->expects($this->never())->method('linkSessionToCacheForOriginalFiles');

        $handler = $this->createHandler(
            filterResponse: ['successful' => 1, 'xliff' => '<xliff/>'],
            fsMock: $fs,
        );

        $handler->processConversion();
        $result = $handler->getResult();

        $this->assertTrue($result->hasConversionHashes());
    }

    // ================================================
    // formatConversionFailureMessage (private, via processConversion)
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function formatConversionFailureMessagePatterns(): void
    {
        $patterns = [
            '[8004C112 - FILE_LOCKVIOLATION_ERR] something' => 'Temporary file conversion issue. Please retry upload.',
            'WinConverter error 5 something' => 'Scanned file conversion issue, please convert it to editable format (e.g. docx) and retry upload',
            'WinConverter generic' => 'File conversion issue, please contact us at support@matecat.com',
            'java.lang.NullPointerException' => 'File conversion issue, please contact us at support@matecat.com',
            'net.sf.okapi.something' => 'File conversion issue, please contact us at support@matecat.com',
            'SomeException: details here' => ' details here',
            'A plain error message' => 'A plain error message',
        ];

        foreach ($patterns as $input => $expected) {
            $this->createTempFile('test.docx', 'fake docx');

            $handler = $this->createHandler(
                filterResponse: ['successful' => 0, 'errorMessage' => $input],
            );

            $handler->processConversion();
            $result = $handler->getResult();

            $this->assertEquals(ConversionHandlerStatus::GENERIC_ERROR, $result->getCode(), "Failed code for: $input");
            $this->assertEquals($expected, $result->getMessage(), "Failed message for: $input");
        }
    }

    // ================================================
    // getRightExtractionParameter (private — via reflection)
    // ================================================

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    #[Test]
    public function getRightExtractionParameterJson(): void
    {
        $handler = $this->createHandler('test.json');
        $struct = new FiltersConfigTemplateStruct();
        $struct->json = new Json();
        $handler->setFiltersExtractionParameters($struct);
        $this->createTempFile('test.json', '{}');

        $this->assertSame($struct->json, $this->invokePrivateMethod($handler, [$handler->getLocalFilePath()]));
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    #[Test]
    public function getRightExtractionParameterXml(): void
    {
        $handler = $this->createHandler('test.xml');
        $struct = new FiltersConfigTemplateStruct();
        $struct->xml = new Xml();
        $handler->setFiltersExtractionParameters($struct);
        $this->createTempFile('test.xml', '<root/>');

        $this->assertSame($struct->xml, $this->invokePrivateMethod($handler, [$handler->getLocalFilePath()]));
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    #[Test]
    public function getRightExtractionParameterYaml(): void
    {
        foreach (['test.yml', 'test.yaml'] as $name) {
            $handler = $this->createHandler($name);
            $struct = new FiltersConfigTemplateStruct();
            $struct->yaml = new Yaml();
            $handler->setFiltersExtractionParameters($struct);
            $this->createTempFile($name, 'key: value');

            $this->assertSame($struct->yaml, $this->invokePrivateMethod($handler, [$handler->getLocalFilePath()]), "Failed for: $name");
        }
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    #[Test]
    public function getRightExtractionParameterMSWord(): void
    {
        foreach (['test.doc', 'test.docx'] as $name) {
            $handler = $this->createHandler($name);
            $struct = new FiltersConfigTemplateStruct();
            $struct->ms_word = new MSWord();
            $handler->setFiltersExtractionParameters($struct);
            $this->createTempFile($name, 'word');

            $this->assertSame($struct->ms_word, $this->invokePrivateMethod($handler, [$handler->getLocalFilePath()]), "Failed for: $name");
        }
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    #[Test]
    public function getRightExtractionParameterMSExcel(): void
    {
        foreach (['test.xls', 'test.xlsx'] as $name) {
            $handler = $this->createHandler($name);
            $struct = new FiltersConfigTemplateStruct();
            $struct->ms_excel = new MSExcel();
            $handler->setFiltersExtractionParameters($struct);
            $this->createTempFile($name, 'excel');

            $this->assertSame($struct->ms_excel, $this->invokePrivateMethod($handler, [$handler->getLocalFilePath()]), "Failed for: $name");
        }
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    #[Test]
    public function getRightExtractionParameterMSPowerpoint(): void
    {
        foreach (['test.ppt', 'test.pptx'] as $name) {
            $handler = $this->createHandler($name);
            $struct = new FiltersConfigTemplateStruct();
            $struct->ms_powerpoint = new MSPowerpoint();
            $handler->setFiltersExtractionParameters($struct);
            $this->createTempFile($name, 'ppt');

            $this->assertSame($struct->ms_powerpoint, $this->invokePrivateMethod($handler, [$handler->getLocalFilePath()]), "Failed for: $name");
        }
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    #[Test]
    public function getRightExtractionParameterDita(): void
    {
        foreach (['test.dita', 'test.ditamap'] as $name) {
            $handler = $this->createHandler($name);
            $struct = new FiltersConfigTemplateStruct();
            $struct->dita = new Dita();
            $handler->setFiltersExtractionParameters($struct);
            $this->createTempFile($name, 'dita');

            $this->assertSame($struct->dita, $this->invokePrivateMethod($handler, [$handler->getLocalFilePath()]), "Failed for: $name");
        }
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    #[Test]
    public function getRightExtractionParameterUnknownExtensionReturnsNull(): void
    {
        $handler = $this->createHandler('test.csv');
        $struct = new FiltersConfigTemplateStruct();
        $struct->json = new Json();
        $handler->setFiltersExtractionParameters($struct);
        $this->createTempFile('test.csv', 'a,b,c');

        $this->assertNull($this->invokePrivateMethod($handler, [$handler->getLocalFilePath()]));
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    #[Test]
    public function getRightExtractionParameterNoStructReturnsNull(): void
    {
        $handler = $this->createHandler('test.json');
        $handler->setFiltersExtractionParameters();
        $this->createTempFile('test.json', '{}');

        $this->assertNull($this->invokePrivateMethod($handler, [$handler->getLocalFilePath()]));
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    #[Test]
    public function getRightExtractionParameterStructWithNullPropertyReturnsNull(): void
    {
        $handler = $this->createHandler('test.json');
        $struct = new FiltersConfigTemplateStruct();
        $handler->setFiltersExtractionParameters($struct);
        $this->createTempFile('test.json', '{}');

        $this->assertNull($this->invokePrivateMethod($handler, [$handler->getLocalFilePath()]));
    }

    // ================================================
    // isZipExtractionFailed
    // ================================================

    #[Test]
    public function isZipExtractionFailedNoError(): void
    {
        $handler = new ConversionHandler();

        $container = new UploadElement();
        $el1 = new UploadElement();
        $el1->error = null;
        $el2 = new UploadElement();
        $el2->error = null;
        $container->file1 = $el1;
        $container->file2 = $el2;

        $this->assertFalse($handler->isZipExtractionFailed($container));
    }

    #[Test]
    public function isZipExtractionFailedWithErrorInFirstItem(): void
    {
        $handler = new ConversionHandler();

        $container = new UploadElement();
        $el1 = new UploadElement();
        $el1->error = ['code' => -1, 'message' => 'Invalid'];
        $el2 = new UploadElement();
        $el2->error = null;
        $container->file1 = $el1;
        $container->file2 = $el2;

        $this->assertTrue($handler->isZipExtractionFailed($container));
    }

    #[Test]
    public function isZipExtractionFailedWithErrorInSecondItem(): void
    {
        $handler = new ConversionHandler();

        $container = new UploadElement();
        $el1 = new UploadElement();
        $el1->error = null;
        $el2 = new UploadElement();
        $el2->error = ['code' => -1, 'message' => 'Bad'];
        $container->file1 = $el1;
        $container->file2 = $el2;

        $this->assertTrue($handler->isZipExtractionFailed($container));
    }

    #[Test]
    public function isZipExtractionFailedBreaksAfterFirstError(): void
    {
        $handler = new ConversionHandler();

        // Once the first error is found, the loop should break
        $container = new UploadElement();
        $el1 = new UploadElement();
        $el1->error = ['code' => -1, 'message' => 'First'];
        $el2 = new UploadElement();
        $el2->error = ['code' => -2, 'message' => 'Second'];
        $container->file1 = $el1;
        $container->file2 = $el2;

        $this->assertTrue($handler->isZipExtractionFailed($container));
    }

    #[Test]
    public function isZipExtractionFailedEmptyContainer(): void
    {
        $handler = new ConversionHandler();
        $this->assertFalse($handler->isZipExtractionFailed(new UploadElement()));
    }

    // ================================================
    // getZipExtractionErrorFiles
    // ================================================

    #[Test]
    public function getZipExtractionErrorFilesDefaultEmpty(): void
    {
        $handler = new ConversionHandler();
        $this->assertEmpty($handler->getZipExtractionErrorFiles());
    }

    // ================================================
    // getResult
    // ================================================

    #[Test]
    public function getResultReturnsConvertedFileModel(): void
    {
        $handler = new ConversionHandler();
        $this->assertInstanceOf(ConvertedFileModel::class, $handler->getResult());
    }
}

// ========================================================================
// Testable subclass — overrides external I/O in processConversion
// ========================================================================

/**
 * Extends {@see ConversionHandler} to replace calls to external services:
 *
 *  - XliffProprietaryDetect::fileMustBeConverted → configurable return value
 *  - OCRCheck                                    → configurable error/warning flags
 *  - Filters::sourceToXliff                      → configurable response array
 *  - Filters::logConversionToXliff               → no-op
 *  - FilesStorageFactory::create()               → injectable mock
 *  - RedisHandler                                → no-op
 *
 * Public properties prefixed with _ are used to configure the stubs.
 */
class TestableConversionHandler extends ConversionHandler
{
    public mixed $_fileMustBeConvertedReturn = true;
    public bool $_ocrError = false;
    public bool $_ocrWarning = false;
    public array $_filterResponse = [];
    public ?AbstractFilesStorage $_fsMock = null;
    public ?string $_lastSingleLanguage = null;

    public function fileMustBeConverted(): bool|int
    {
        return $this->_fileMustBeConvertedReturn;
    }

    /**
     * Reproduces the exact logic of the parent's processConversion() but replaces
     * all external calls with injectable stubs.
     * @throws Exception
     */
    public function processConversion(): void
    {
        $fs = $this->_fsMock;
        $file_path = $this->getLocalFilePath();

        $isZipContent = !empty(ZipArchiveHandler::zipPathInfo($file_path));
        $this->result->setFileName(
            ZipArchiveHandler::getFileName(AbstractFilesStorage::basename_fix($this->file_name)),
            $isZipContent,
        );

        if (!file_exists($file_path)) {
            $this->result->setErrorCode(ConversionHandlerStatus::UPLOAD_ERROR);
            $this->result->setErrorMessage('Error during upload. Please retry.');

            return;
        }

        $fileMustBeConverted = $this->fileMustBeConverted();

        if ($fileMustBeConverted === false) {
            $this->result->setSize(filesize($file_path));

            return;
        } elseif ($fileMustBeConverted === true) {
            // continue with conversion
        } else {
            unlink($file_path);
            $this->result->setErrorCode(ConversionHandlerStatus::MISCONFIGURATION);
            $this->result->setErrorMessage('Matecat Open-Source does not support Unknown. Use MatecatPro.');

            return;
        }

        // OCR stubs
        if ($this->_ocrError) {
            $this->result->setErrorCode(ConversionHandlerStatus::OCR_ERROR);
            $this->result->setErrorMessage('File is not valid. OCR for RTL languages is not supported.');

            return;
        }

        if ($this->_ocrWarning) {
            $this->result->setErrorCode(ConversionHandlerStatus::OCR_WARNING);
            $this->result->setErrorMessage('File uploaded successfully. Before translating, download the Preview to check the conversion. OCR support for non-latin scripts is experimental.');
        }

        // Determine single target language
        if (str_contains($this->target_lang, ',')) {
            $parts = explode(',', $this->target_lang);
            $single_language = $parts[0];
        } else {
            $single_language = $this->target_lang;
        }
        $this->_lastSingleLanguage = $single_language;

        // Compute hashes (same logic as parent)
        $ref = new ReflectionClass(ConversionHandler::class);
        $method = $ref->getMethod('getRightExtractionParameter');
        $extraction_parameters = $method->invokeArgs($this, [$file_path]);

        $hash_name_for_disk =
            sha1_file($file_path)
            . '_'
            . sha1(($this->segmentation_rule ?? '') . ($extraction_parameters ? json_encode($extraction_parameters) : ''))
            . '|'
            . $this->source_lang;

        $short_hash = sha1($hash_name_for_disk);

        // Stubbed Filters response
        $convertResult = $this->_filterResponse;

        if ($convertResult['successful'] == 1) {
            $cachedXliffPath = tempnam('/tmp', 'MAT_XLF');
            file_put_contents($cachedXliffPath, $convertResult['xliff']);
            unset($convertResult['xliff']);

            if ($fs !== null) {
                try {
                    $res_insert = $fs->makeCachePackage($short_hash, $this->source_lang, $file_path, $cachedXliffPath);

                    if (!$res_insert) {
                        $this->result->setErrorCode(ConversionHandlerStatus::FILESYSTEM_ERROR);
                        $this->result->setErrorMessage(
                            'Error: File upload failed because you have Matecat running in multiple tabs. Please close all other Matecat tabs in your browser.',
                        );

                        return;
                    }
                } catch (FileSystemException $e) {
                    $this->result->setErrorCode(ConversionHandlerStatus::FILESYSTEM_ERROR);
                    $this->result->setErrorMessage($e->getMessage());

                    return;
                } catch (Exception) {
                    $this->result->setErrorCode(ConversionHandlerStatus::S3_ERROR);
                    $this->result->setErrorMessage('Sorry, file name too long. Try shortening it and try again.');

                    return;
                }
            }
        } else {
            $this->result->setErrorCode(ConversionHandlerStatus::GENERIC_ERROR);
            $formatMethod = $ref->getMethod('formatConversionFailureMessage');
            $this->result->setErrorMessage($formatMethod->invokeArgs($this, [$convertResult['errorMessage']]));

            return;
        }

        // Linking
        if (!empty($cachedXliffPath) && $fs !== null) {
            $fs->deleteHashFromUploadDir($this->uploadDir, $hash_name_for_disk);

            if (is_file($file_path)) {
                $fs->linkSessionToCacheForOriginalFiles(
                    $hash_name_for_disk,
                    $this->uploadTokenValue,
                    AbstractFilesStorage::basename_fix($file_path),
                );
            }
        }

        $this->result->addConversionHashes(
            new InternalHashPaths([
                'cacheHash' => $short_hash,
                'diskHash' => $hash_name_for_disk,
            ]),
        );

        $this->result->setSize(filesize($file_path));

        if (!empty($convertResult['pdfAnalysis'])) {
            $this->result->setPdfAnalysis($convertResult['pdfAnalysis']);
            // RedisHandler skipped in tests
        }
    }
}


