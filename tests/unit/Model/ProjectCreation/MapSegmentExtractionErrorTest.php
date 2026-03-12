<?php

namespace unit\Model\ProjectCreation;

use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use Model\FilesStorage\AbstractFilesStorage;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

/**
 * Unit tests for {@see \Model\ProjectCreation\ProjectManager::mapSegmentExtractionError()}.
 *
 * Verifies:
 * - Code -1 maps to "No text to translate" with the file name extracted
 * - Code -1 on non-S3 storage calls deleteHashFromUploadDir
 * - Code -1 on S3 storage does NOT call deleteHashFromUploadDir
 * - Code -4 maps to code -7 with "Xliff Import Error" prefix
 * - Code 400 includes previous exception message when available
 * - Code 400 uses own message when no previous exception
 * - Other codes use the exception code and message directly
 */
class MapSegmentExtractionErrorTest extends AbstractTest
{
    private TestableProjectManager $pm;
    private string $originalFileStorageMethod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalFileStorageMethod = AppConfig::$FILE_STORAGE_METHOD;
        AppConfig::$FILE_STORAGE_METHOD = 'fs';

        $this->pm = new TestableProjectManager();
        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $this->createStub(FeatureSet::class),
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );
        $this->pm->setUploadDir('/tmp/test_upload');
    }

    protected function tearDown(): void
    {
        AppConfig::$FILE_STORAGE_METHOD = $this->originalFileStorageMethod;
        parent::tearDown();
    }

    // ── Code -1: no text to translate ───────────────────────────────

    #[Test]
    public function code1MapsToNoTextToTranslateError(): void
    {
        $fs = $this->createMock(AbstractFilesStorage::class);
        $fs->expects($this->once())->method('deleteHashFromUploadDir');

        $this->pm->callMapSegmentExtractionError(
            new Exception('test_file.docx', -1),
            $fs,
            'someLinkFile'
        );

        $errors = $this->pm->getTestProjectStructure()['result']['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(-1, $errors[0]['code']);
        $this->assertStringContainsString('No text to translate', $errors[0]['message']);
    }

    #[Test]
    public function code1OnS3DoesNotDeleteHash(): void
    {
        AppConfig::$FILE_STORAGE_METHOD = 's3';

        $fs = $this->createMock(AbstractFilesStorage::class);
        $fs->expects($this->never())->method('deleteHashFromUploadDir');

        $this->pm->callMapSegmentExtractionError(
            new Exception('file.txt', -1),
            $fs,
            'linkFile'
        );

        $errors = $this->pm->getTestProjectStructure()['result']['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(-1, $errors[0]['code']);
    }

    // ── Code -4: XLIFF import error ─────────────────────────────────

    #[Test]
    public function code4MapsToCode7WithXliffPrefix(): void
    {
        $fs = $this->createStub(AbstractFilesStorage::class);

        $this->pm->callMapSegmentExtractionError(
            new Exception('Parsing failed', -4),
            $fs,
            ''
        );

        $errors = $this->pm->getTestProjectStructure()['result']['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(-7, $errors[0]['code']);
        $this->assertSame('Xliff Import Error: Parsing failed', $errors[0]['message']);
    }

    // ── Code 400: includes previous exception ───────────────────────

    #[Test]
    public function code400IncludesPreviousExceptionMessage(): void
    {
        $fs = $this->createStub(AbstractFilesStorage::class);
        $prev = new Exception('Root cause');
        $ex = new Exception('file_context.xlf', 400, $prev);

        $this->pm->callMapSegmentExtractionError($ex, $fs, '');

        $errors = $this->pm->getTestProjectStructure()['result']['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(400, $errors[0]['code']);
        $this->assertStringContainsString('Root cause', $errors[0]['message']);
        $this->assertStringContainsString('file_context.xlf', $errors[0]['message']);
    }

    #[Test]
    public function code400WithoutPreviousUsesOwnMessage(): void
    {
        $fs = $this->createStub(AbstractFilesStorage::class);

        $this->pm->callMapSegmentExtractionError(
            new Exception('Validation failed', 400),
            $fs,
            ''
        );

        $errors = $this->pm->getTestProjectStructure()['result']['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(400, $errors[0]['code']);
        $this->assertSame('Validation failed', $errors[0]['message']);
    }

    // ── Other codes: passthrough ────────────────────────────────────

    #[Test]
    public function otherCodeUsesExceptionCodeAndMessage(): void
    {
        $fs = $this->createStub(AbstractFilesStorage::class);

        $this->pm->callMapSegmentExtractionError(
            new Exception('Unexpected error', 128),
            $fs,
            ''
        );

        $errors = $this->pm->getTestProjectStructure()['result']['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(128, $errors[0]['code']);
        $this->assertSame('Unexpected error', $errors[0]['message']);
    }

    #[Test]
    public function code0PassesThroughDirectly(): void
    {
        $fs = $this->createStub(AbstractFilesStorage::class);

        $this->pm->callMapSegmentExtractionError(
            new Exception('Generic failure', 0),
            $fs,
            ''
        );

        $errors = $this->pm->getTestProjectStructure()['result']['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(0, $errors[0]['code']);
    }
}
