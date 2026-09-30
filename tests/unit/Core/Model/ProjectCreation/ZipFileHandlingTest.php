<?php

namespace Matecat\Core\Model\ProjectCreation;

use ArrayObject;
use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use Model\ProjectCreation\ProjectCreationError;
use PHPUnit\Framework\Attributes\Test;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

/**
 * Unit tests for {@see \Model\ProjectCreation\ProjectManager::zipFileHandling()}.
 *
 * The method moves each uploaded ZIP from the upload area into the project's own directory and must
 * abort project creation if any of them cannot be stored — a silently dropped ZIP would produce a
 * project whose source archive is gone.
 *
 * FilesStorageFactory::create() is a static call, so the storage backend is chosen by
 * AppConfig::$FILE_STORAGE_METHOD. Both test configs set it to "s3"; it is pinned to "fs" here so
 * the test resolves against the local filesystem instead of reaching for AWS.
 */
class ZipFileHandlingTest extends AbstractTest
{
    private TestableProjectManager $pm;
    private ?string $originalStorageMethod = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalStorageMethod = AppConfig::$FILE_STORAGE_METHOD;
        AppConfig::$FILE_STORAGE_METHOD = 'fs';

        $this->pm = new TestableProjectManager();
        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $this->createStub(FeatureSet::class),
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );
        $this->pm->enableRealZipFileHandling();

        $this->pm->setProjectStructureValue('result', ['errors' => new ArrayObject()]);
        $this->pm->setProjectStructureValue('create_date', '2026-08-14 10:00:00');
        $this->pm->setProjectStructureValue('id_project', 9_932_000);
    }

    protected function tearDown(): void
    {
        AppConfig::$FILE_STORAGE_METHOD = $this->originalStorageMethod;
        parent::tearDown();
    }

    #[Test]
    public function doesNothingWhenThereAreNoZipHashes(): void
    {
        $this->pm->callZipFileHandling(['zipHashes' => []]);

        $this->assertCount(0, $this->pm->getTestProjectStructure()->result['errors']);
    }

    /**
     * An unknown hash has no directory under the zip area, so linkZipToProject() reports failure.
     */
    #[Test]
    public function throwsWhenAZipCannotBeStored(): void
    {
        $zipHash = 'ctrltest-missing-zip-9932000';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Failed to store the original ZIP $zipHash");
        $this->expectExceptionCode(ProjectCreationError::ZIP_STORE_FAILED->value);

        $this->pm->callZipFileHandling(['zipHashes' => [$zipHash]]);
    }

    /**
     * The loop must abort on the first failure rather than carrying on to the remaining archives.
     */
    #[Test]
    public function stopsAtTheFirstUnstorableZip(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to store the original ZIP first-bad-hash-9932000');

        $this->pm->callZipFileHandling([
            'zipHashes' => ['first-bad-hash-9932000', 'second-bad-hash-9932000'],
        ]);
    }
}
