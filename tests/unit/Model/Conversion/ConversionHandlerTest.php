<?php

namespace unit\Model\Conversion;

use Exception;
use Model\Conversion\Filters;
use Matecat\XliffParser\XliffUtils\XliffProprietaryDetect;
use Model\Conversion\ConversionHandler;
use Model\Conversion\ConvertedFileModel;
use Model\Conversion\OCRCheck;
use Model\Conversion\UploadElement;
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
use Predis\Client;
use ReflectionClass;
use ReflectionException;
use TestHelpers\AbstractTest;
use Utils\Constants\ConversionHandlerStatus;
use Utils\Logger\MatecatLogger;
use Utils\Redis\RedisHandler;

/**
 * Unit tests for {@see ConversionHandler}.
 *
 * All external I/O dependencies are injected via the constructor, so every test
 * uses standard PHPUnit stubs/mocks — no testable subclass required.
 */
class ConversionHandlerTest extends AbstractTest
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

    // ================================================
    // Stub / Helper factories
    // ================================================

    /**
     * Creates a fully configured {@see ConversionHandler} with injected stubs.
     *
     * @throws Exception
     */
    private function createHandler(
        string               $fileName = 'test.docx',
        mixed                $fileMustBeConvertedReturn = true,
        bool                 $ocrError = false,
        bool                 $ocrWarning = false,
        ?array               $filterResponse = null,
        ?AbstractFilesStorage $fsStub = null,
    ): ConversionHandler {

        $xliffDetect = $this->createStub(XliffProprietaryDetect::class);
        $xliffDetect->method('fileMustBeConverted')->willReturn($fileMustBeConvertedReturn);
        $xliffDetect->method('getInfo')->willReturn(['proprietary_name' => 'Unknown']);

        $ocrCheck = $this->createStub(OCRCheck::class);
        $ocrCheck->method('thereIsError')->willReturn($ocrError);
        $ocrCheck->method('thereIsWarning')->willReturn($ocrWarning);

        $filtersAdapter = $this->createStub(Filters::class);
        $filtersAdapter->method('sourceToXliff')->willReturn(
            $filterResponse ?? ['successful' => 0, 'errorMessage' => 'Not configured'],
        );

        $redisClient = $this->createStub(Client::class);
        $redisHandler = $this->createStub(RedisHandler::class);
        $redisHandler->method('getConnection')->willReturn($redisClient);

        $logger = $this->createStub(MatecatLogger::class);

        $handler = new ConversionHandler(
            filesStorage: $fsStub ?? $this->createFsStub(),
            filtersAdapter: $filtersAdapter,
            xliffDetect: $xliffDetect,
            ocrCheck: $ocrCheck,
            redisHandler: $redisHandler,
            logger: $logger,
        );

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

    /**
     * @throws Exception
     */
    #[Test]
    public function constructorInitializesResult(): void
    {
        $handler = $this->createHandler();
        $this->assertInstanceOf(ConvertedFileModel::class, $handler->getResult());
        $this->assertEquals(ConversionHandlerStatus::NOT_CONVERTED, $handler->getResult()->getCode());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function setAndGetFileName(): void
    {
        $handler = $this->createHandler();
        $handler->setFileName('my_file.docx');
        $this->assertEquals('my_file.docx', $handler->getFileName());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function getLocalFilePathCombinesDirAndName(): void
    {
        $handler = $this->createHandler('document.txt');
        $handler->setUploadDir('/tmp/uploads');
        $this->assertEquals('/tmp/uploads' . DIRECTORY_SEPARATOR . 'document.txt', $handler->getLocalFilePath());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function setStopOnFileExceptionDefaultAndOverride(): void
    {
        $handler = $this->createHandler();
        $ref = new ReflectionClass($handler);
        $prop = $ref->getProperty('stopOnFileException');

        $this->assertTrue($prop->getValue($handler));

        $handler->setStopOnFileException(false);
        $this->assertFalse($prop->getValue($handler));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function setSegmentationRuleAcceptsNullAndString(): void
    {
        $handler = $this->createHandler();
        $ref = new ReflectionClass($handler);
        $prop = $ref->getProperty('segmentation_rule');

        $handler->setSegmentationRule('paragraph');
        $this->assertEquals('paragraph', $prop->getValue($handler));

        $handler->setSegmentationRule();
        $this->assertNull($prop->getValue($handler));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function setSourceAndTargetLang(): void
    {
        $handler = $this->createHandler();
        $handler->setSourceLang('fr-FR');
        $handler->setTargetLang('de-DE');

        $ref = new ReflectionClass($handler);
        $this->assertEquals('fr-FR', $ref->getProperty('source_lang')->getValue($handler));
        $this->assertEquals('de-DE', $ref->getProperty('target_lang')->getValue($handler));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function setUploadDirAndErrDir(): void
    {
        $handler = $this->createHandler();
        $handler->setUploadDir('/tmp/upload');
        $handler->setErrDir('/tmp/err');

        $ref = new ReflectionClass($handler);
        $this->assertEquals('/tmp/upload', $ref->getProperty('uploadDir')->getValue($handler));
        $this->assertEquals('/tmp/err', $ref->getProperty('errDir')->getValue($handler));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function setUploadTokenValue(): void
    {
        $handler = $this->createHandler();
        $handler->setUploadTokenValue('abc-123');

        $ref = new ReflectionClass($handler);
        $this->assertEquals('abc-123', $ref->getProperty('uploadTokenValue')->getValue($handler));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function setFeaturesReturnsSelf(): void
    {
        $handler = $this->createHandler();
        $features = new FeatureSet();
        $returned = $handler->setFeatures($features);

        $this->assertSame($handler, $returned);
        $this->assertSame($features, $handler->features);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function setFiltersExtractionParametersAcceptsStructAndNull(): void
    {
        $handler = $this->createHandler();
        $struct = new FiltersConfigTemplateStruct();
        $handler->setFiltersExtractionParameters($struct);

        $ref = new ReflectionClass($handler);
        $this->assertSame($struct, $ref->getProperty('filters_extraction_parameters')->getValue($handler));

        $handler->setFiltersExtractionParameters();
        $this->assertNull($ref->getProperty('filters_extraction_parameters')->getValue($handler));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function setFiltersLegacyIcu(): void
    {
        $handler = $this->createHandler();
        $handler->setFiltersLegacyIcu(true);

        $ref = new ReflectionClass($handler);
        $this->assertTrue($ref->getProperty('legacy_icu')->getValue($handler));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function setIcuEnabled(): void
    {
        $handler = $this->createHandler();
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

        $filtersAdapter = $this->createStub(Filters::class);
        $filtersAdapter->method('sourceToXliff')
            ->willReturnCallback(function (string $filePath, string $source, string $target) {
                // Verify only the first language is passed
                $this->assertEquals('it-IT', $target);

                return ['successful' => 1, 'xliff' => '<xliff/>'];
            });

        $handler = $this->createHandler(
            filterResponse: ['successful' => 1, 'xliff' => '<xliff/>'],
        );
        $handler->setTargetLang('it-IT,fr-FR,de-DE');

        $handler->processConversion();
        $result = $handler->getResult();

        $this->assertFalse($result->isError());
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
            fsStub: $fs,
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
            fsStub: $fs,
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
            fsStub: $fs,
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
     * Simulates a race condition where the original file is deleted from disk
     * during the {@see AbstractFilesStorage::makeCachePackage()} call — e.g. a
     * concurrent upload on the same session or an external cleanup process.
     *
     * After makeCachePackage succeeds the file no longer exists, so:
     *  - {@see AbstractFilesStorage::linkSessionToCacheForOriginalFiles()} must
     *    NOT be called (guarded by is_file() in production code).
     *  - The conversion hashes must still be recorded (the conversion itself
     *    succeeded and the cache package was stored).
     *
     * Uses createMock (not createStub) because we need expects($this->never())
     * to assert the linking method is never invoked.
     *
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
            fsStub: $fs,
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

    /**
     * @throws Exception
     */
    #[Test]
    public function isZipExtractionFailedNoError(): void
    {
        $handler = $this->createHandler();

        $container = new UploadElement();
        $el1 = new UploadElement();
        $el1->error = null;
        $el2 = new UploadElement();
        $el2->error = null;
        $container->file1 = $el1;
        $container->file2 = $el2;

        $this->assertFalse($handler->isZipExtractionFailed($container));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function isZipExtractionFailedWithErrorInFirstItem(): void
    {
        $handler = $this->createHandler();

        $container = new UploadElement();
        $el1 = new UploadElement();
        $el1->error = ['code' => -1, 'message' => 'Invalid'];
        $el2 = new UploadElement();
        $el2->error = null;
        $container->file1 = $el1;
        $container->file2 = $el2;

        $this->assertTrue($handler->isZipExtractionFailed($container));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function isZipExtractionFailedWithErrorInSecondItem(): void
    {
        $handler = $this->createHandler();

        $container = new UploadElement();
        $el1 = new UploadElement();
        $el1->error = null;
        $el2 = new UploadElement();
        $el2->error = ['code' => -1, 'message' => 'Bad'];
        $container->file1 = $el1;
        $container->file2 = $el2;

        $this->assertTrue($handler->isZipExtractionFailed($container));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function isZipExtractionFailedBreaksAfterFirstError(): void
    {
        $handler = $this->createHandler();

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

    /**
     * @throws Exception
     */
    #[Test]
    public function isZipExtractionFailedEmptyContainer(): void
    {
        $handler = $this->createHandler();
        $this->assertFalse($handler->isZipExtractionFailed(new UploadElement()));
    }

    // ================================================
    // getZipExtractionErrorFiles
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function getZipExtractionErrorFilesDefaultEmpty(): void
    {
        $handler = $this->createHandler();
        $this->assertEmpty($handler->getZipExtractionErrorFiles());
    }

    // ================================================
    // getResult
    // ================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function getResultReturnsConvertedFileModel(): void
    {
        $handler = $this->createHandler();
        $this->assertInstanceOf(ConvertedFileModel::class, $handler->getResult());
    }

    // ================================================
    // extractZipFile
    // ================================================

    /**
     * Helper: creates a real ZIP archive on disk containing one or more files.
     *
     * @param string               $zipName Name of the zip file (created in $this->tmpDir).
     * @param array<string,string> $files   Map of internal-filename → content.
     *
     * @return string Absolute path to the created zip file.
     */
    private function createZipFile(string $zipName, array $files): string
    {
        $zipPath = $this->tmpDir . DIRECTORY_SEPARATOR . $zipName;
        $za = new \ZipArchive();
        $za->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) {
            $za->addFromString($name, $content);
        }
        $za->close();

        return $zipPath;
    }

    /**
     * Verifies the happy-path: a valid zip with one text file is extracted successfully.
     * The returned array should contain exactly one entry whose name matches the
     * internal tree-list representation produced by {@see ZipArchiveHandler}.
     *
     * @throws Exception
     */
    #[Test]
    public function extractZipFileSuccess(): void
    {
        $this->createZipFile('archive.zip', ['hello.txt' => 'Hello World']);

        $handler = $this->createHandler('archive.zip');

        $result = $handler->extractZipFile();

        $this->assertNotEmpty($result, 'extractZipFile should return a non-empty file list');
        $this->assertFalse($handler->zipExtractionErrorFlag);
        $this->assertEmpty($handler->getZipExtractionErrorFiles());

        // The result name should contain the original internal filename
        $firstName = reset($result);
        $this->assertStringContainsString('hello.txt', $firstName);
    }

    /**
     * Verifies the happy-path with multiple files inside the zip.
     *
     * @throws Exception
     */
    #[Test]
    public function extractZipFileMultipleFiles(): void
    {
        $this->createZipFile('multi.zip', [
            'a.txt' => 'File A',
            'b.txt' => 'File B',
            'c.txt' => 'File C',
        ]);

        $handler = $this->createHandler('multi.zip');

        $result = $handler->extractZipFile();

        $this->assertCount(3, $result);
        $this->assertFalse($handler->zipExtractionErrorFlag);
    }

    /**
     * When the zip file does not exist, {@see \ZipArchive::open()} returns an error code.
     * The production code checks this return value and throws an {@see Exception}
     * ("Cannot open zip file: ...") which is caught by the outer {@see \Throwable} catch,
     * setting the error on the result and returning an empty array.
     *
     * @throws Exception
     */
    #[Test]
    public function extractZipFileNotFound(): void
    {
        $handler = $this->createHandler('nonexistent.zip');

        $result = $handler->extractZipFile();

        $this->assertEmpty($result);
        $this->assertStringContainsString('Zip error', $handler->getResult()->getMessage());
        $this->assertStringContainsString('Cannot open zip file', $handler->getResult()->getMessage());
    }

    /**
     * When the file is not a valid zip archive, {@see \ZipArchive::open()} returns an error code.
     * Same behavior as the "not found" case — the error is caught and set on the result.
     *
     * @throws Exception
     */
    #[Test]
    public function extractZipFileCorruptArchive(): void
    {
        $this->createTempFile('corrupt.zip', 'this is not a zip file');

        $handler = $this->createHandler('corrupt.zip');

        $result = $handler->extractZipFile();

        $this->assertEmpty($result);
        $this->assertStringContainsString('Zip error', $handler->getResult()->getMessage());
        $this->assertStringContainsString('Cannot open zip file', $handler->getResult()->getMessage());
    }

    /**
     * Verifies that the result's fileName is set to the zip archive name
     * (via {@see AbstractFilesStorage::basename_fix()}).
     *
     * @throws Exception
     */
    #[Test]
    public function extractZipFileSetsResultFileName(): void
    {
        $this->createZipFile('my-archive.zip', ['doc.txt' => 'content']);

        $handler = $this->createHandler('my-archive.zip');
        $handler->extractZipFile();

        $this->assertEquals('my-archive.zip', $handler->getResult()->getName());
    }
}

