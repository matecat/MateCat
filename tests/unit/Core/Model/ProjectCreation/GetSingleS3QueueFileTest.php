<?php

namespace Matecat\Core\Model\ProjectCreation;

use Matecat\TestHelpers\AbstractTest;
use Model\FilesStorage\S3FilesStorage;
use Model\ProjectCreation\ProjectManager;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use ReflectionProperty;
use Utils\Registry\AppConfig;

class GetSingleS3QueueFileTest extends AbstractTest
{
    private string $uploadDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uploadDir = sys_get_temp_dir() . '/matecat-test-s3-queue-' . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->uploadDir)) {
            rmdir($this->uploadDir);
        }
        parent::tearDown();
    }

    #[Test]
    public function itCreatesTheUploadDirAndDownloadsTheQueuedFileThroughTheStorage(): void
    {
        $storage = $this->createMock(S3FilesStorage::class);
        $storage->expects($this->once())
            ->method('downloadQueueFile')
            ->with($this->uploadDir, 'спасибо.xlf');

        $projectManager = new class ($storage) extends TestableProjectManager {
            public function __construct(private readonly S3FilesStorage $storage)
            {
                parent::__construct();
            }

            protected function getS3FilesStorage(): S3FilesStorage
            {
                return $this->storage;
            }
        };
        (new ReflectionProperty(ProjectManager::class, 'uploadDir'))->setValue($projectManager, $this->uploadDir);

        $projectManager->getSingleS3QueueFile('спасибо.xlf');

        $this->assertDirectoryExists($this->uploadDir);
    }

    #[Test]
    public function itBuildsAnS3StorageByDefault(): void
    {
        $originalBucket = AppConfig::$AWS_STORAGE_BASE_BUCKET ?? '';
        if ($originalBucket === '') {
            AppConfig::$AWS_STORAGE_BASE_BUCKET = 'test-bucket';
        }

        try {
            $storage = (new ReflectionMethod(ProjectManager::class, 'getS3FilesStorage'))->invoke(new TestableProjectManager());
            $this->assertInstanceOf(S3FilesStorage::class, $storage);
        } finally {
            AppConfig::$AWS_STORAGE_BASE_BUCKET = $originalBucket;
        }
    }
}
