<?php

namespace unit\Model\ProjectCreation;

use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use Model\FilesStorage\AbstractFilesStorage;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

/**
 * Unit tests for {@see \Model\ProjectCreation\ProjectManager::resolveUploadDirAndGetHashes()}.
 *
 * Verifies:
 * - Sets uploadDir from AppConfig::$QUEUE_PROJECT_REPOSITORY + upload token
 * - Delegates to $fs->getHashesFromDir()
 * - Returns the hashes array from storage
 */
class ResolveUploadDirAndGetHashesTest extends AbstractTest
{
    private TestableProjectManager $pm;
    private string $savedQueueRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedQueueRepo = AppConfig::$QUEUE_PROJECT_REPOSITORY ?? '';

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
        AppConfig::$QUEUE_PROJECT_REPOSITORY = $this->savedQueueRepo;
        parent::tearDown();
    }

    #[Test]
    public function setsUploadDirFromConfigAndToken(): void
    {
        AppConfig::$QUEUE_PROJECT_REPOSITORY = '/tmp/queue_repo';
        $this->pm->setProjectStructureValue('uploadToken', 'abc123');

        $fs = $this->createStub(AbstractFilesStorage::class);
        $fs->method('getHashesFromDir')->willReturn([
            'conversionHashes' => [],
            'zipHashes'        => [],
        ]);

        $this->pm->callResolveUploadDirAndGetHashes($fs);

        $this->assertSame(
            '/tmp/queue_repo' . DIRECTORY_SEPARATOR . 'abc123',
            $this->pm->getUploadDir()
        );
    }

    #[Test]
    public function returnsHashesFromStorage(): void
    {
        AppConfig::$QUEUE_PROJECT_REPOSITORY = '/tmp/repo';
        $this->pm->setProjectStructureValue('uploadToken', 'token456');

        $expectedHashes = [
            'conversionHashes' => [
                'sha' => ['hash1|en-US', 'hash2|it-IT'],
            ],
            'zipHashes' => ['ziphash1'],
        ];

        $fs = $this->createStub(AbstractFilesStorage::class);
        $fs->method('getHashesFromDir')->willReturn($expectedHashes);

        $result = $this->pm->callResolveUploadDirAndGetHashes($fs);

        $this->assertSame($expectedHashes, $result);
    }

    #[Test]
    public function passesUploadDirToGetHashesFromDir(): void
    {
        AppConfig::$QUEUE_PROJECT_REPOSITORY = '/data/projects';
        $this->pm->setProjectStructureValue('uploadToken', 'tok789');

        $capturedDir = null;
        $fs = $this->createMock(AbstractFilesStorage::class);
        $fs->expects($this->once())
            ->method('getHashesFromDir')
            ->willReturnCallback(function ($dir) use (&$capturedDir) {
                $capturedDir = $dir;

                return ['conversionHashes' => [], 'zipHashes' => []];
            });

        $this->pm->callResolveUploadDirAndGetHashes($fs);

        $this->assertSame(
            '/data/projects' . DIRECTORY_SEPARATOR . 'tok789',
            $capturedDir
        );
    }

    #[Test]
    public function handlesEmptyHashesGracefully(): void
    {
        AppConfig::$QUEUE_PROJECT_REPOSITORY = '/tmp';
        $this->pm->setProjectStructureValue('uploadToken', 'empty');

        $fs = $this->createStub(AbstractFilesStorage::class);
        $fs->method('getHashesFromDir')->willReturn([]);

        $result = $this->pm->callResolveUploadDirAndGetHashes($fs);

        $this->assertSame([], $result);
    }
}
