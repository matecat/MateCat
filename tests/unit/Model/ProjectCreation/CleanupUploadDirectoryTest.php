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

/**
 * Unit tests for {@see \Model\ProjectCreation\ProjectManager::cleanupUploadDirectory()}.
 *
 * Verifies:
 * - calls $fs->deleteQueue() with the upload directory
 * - does not throw when deleteQueue succeeds
 * - Exception in deleteQueue is caught (does not propagate)
 */
class CleanupUploadDirectoryTest extends AbstractTest
{
    private TestableProjectManager $pm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pm = new TestableProjectManager();
        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $this->createStub(FeatureSet::class),
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );
    }

    #[Test]
    public function callsDeleteQueueWithUploadDir(): void
    {
        $this->pm->setUploadDir('/tmp/test_upload_dir');

        $fs = $this->createMock(AbstractFilesStorage::class);
        $fs->expects($this->once())
            ->method('deleteQueue')
            ->with('/tmp/test_upload_dir');

        $this->pm->callCleanupUploadDirectory($fs);
    }

    #[Test]
    public function doesNotThrowOnSuccess(): void
    {
        $this->pm->setUploadDir('/tmp/dir');

        $fs = $this->createStub(AbstractFilesStorage::class);

        // Should not throw
        $this->pm->callCleanupUploadDirectory($fs);
        $this->assertTrue(true);
    }

    #[Test]
    public function catchesExceptionFromDeleteQueue(): void
    {
        $this->pm->setUploadDir('/tmp/failing_dir');

        $fs = $this->createStub(AbstractFilesStorage::class);
        $fs->method('deleteQueue')
            ->willThrowException(new Exception('delete failed'));

        // Should NOT throw — exception is caught internally
        $this->pm->callCleanupUploadDirectory($fs);
        $this->assertTrue(true);
    }

    #[Test]
    public function handlesEmptyUploadDir(): void
    {
        $this->pm->setUploadDir('');

        $capturedDir = null;
        $fs = $this->createMock(AbstractFilesStorage::class);
        $fs->expects($this->once())
            ->method('deleteQueue')
            ->willReturnCallback(function ($dir) use (&$capturedDir) {
                $capturedDir = $dir;
            });

        $this->pm->callCleanupUploadDirectory($fs);

        $this->assertSame('', $capturedDir);
    }
}
