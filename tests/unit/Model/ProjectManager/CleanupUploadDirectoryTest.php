<?php

namespace unit\Model\ProjectManager;

use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use Model\FilesStorage\S3FilesStorage;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

/**
 * Unit tests for {@see \Model\ProjectManager\ProjectManager::cleanupUploadDirectory()}.
 *
 * Tests the S3 code path (where AbstractFilesStorage::isOnS3() returns true),
 * since the local filesystem path uses static Utils::deleteDir() and is_dir()
 * which cannot be intercepted without modifying production code.
 *
 * Verifies:
 * - S3 path: calls $fs->deleteQueue() with the upload directory
 * - S3 path: does not throw when deleteQueue succeeds
 * - Exception in deleteQueue is caught (does not propagate)
 */
class CleanupUploadDirectoryTest extends AbstractTest
{
    private TestableProjectManager $pm;
    private string $savedStorageMethod;

    protected function setUp(): void
    {
        $this->savedStorageMethod = AppConfig::$FILE_STORAGE_METHOD ?? '';

        $this->pm = new TestableProjectManager();
        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $this->createStub(FeatureSet::class),
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );
    }

    protected function tearDown(): void
    {
        AppConfig::$FILE_STORAGE_METHOD = $this->savedStorageMethod;
    }

    #[Test]
    public function s3PathCallsDeleteQueueWithUploadDir(): void
    {
        AppConfig::$FILE_STORAGE_METHOD = 's3';
        $this->pm->setUploadDir('/tmp/test_upload_dir');

        $fs = $this->createMock(S3FilesStorage::class);
        $fs->expects($this->once())
            ->method('deleteQueue')
            ->with('/tmp/test_upload_dir');

        $this->pm->callCleanupUploadDirectory($fs);
    }

    #[Test]
    public function s3PathDoesNotThrowOnSuccess(): void
    {
        AppConfig::$FILE_STORAGE_METHOD = 's3';
        $this->pm->setUploadDir('/tmp/dir');

        $fs = $this->createStub(S3FilesStorage::class);

        // Should not throw
        $this->pm->callCleanupUploadDirectory($fs);
        $this->assertTrue(true);
    }

    #[Test]
    public function s3PathCatchesExceptionFromDeleteQueue(): void
    {
        AppConfig::$FILE_STORAGE_METHOD = 's3';
        $this->pm->setUploadDir('/tmp/failing_dir');

        $fs = $this->createStub(S3FilesStorage::class);
        $fs->method('deleteQueue')
            ->willThrowException(new Exception('S3 delete failed'));

        // Should NOT throw — exception is caught internally
        $this->pm->callCleanupUploadDirectory($fs);
        $this->assertTrue(true);
    }

    #[Test]
    public function s3PathHandlesEmptyUploadDir(): void
    {
        AppConfig::$FILE_STORAGE_METHOD = 's3';
        $this->pm->setUploadDir('');

        $capturedDir = null;
        $fs = $this->createMock(S3FilesStorage::class);
        $fs->expects($this->once())
            ->method('deleteQueue')
            ->willReturnCallback(function ($dir) use (&$capturedDir) {
                $capturedDir = $dir;
            });

        $this->pm->callCleanupUploadDirectory($fs);

        $this->assertSame('', $capturedDir);
    }
}
