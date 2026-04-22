<?php

namespace unit\Model\ProjectCreation;

use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use Model\ProjectCreation\ProjectManagerModel;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;

/**
 * Unit tests for {@see \Model\ProjectCreation\ProjectManager::clearFailedProject()}.
 *
 * Verifies:
 * - Delegates to ProjectManagerModel::deleteProject() with the project ID
 * - Does nothing when no project ID is available
 * - Swallows cleanup exceptions without rethrowing
 */
class ClearFailedProjectTest extends AbstractTest
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
    public function delegatesToDeleteProjectWithCorrectId(): void
    {
        $this->pm->setProjectStructureValue('id_project', 42);

        $model = $this->createMock(ProjectManagerModel::class);
        $model->expects($this->once())
            ->method('deleteProject')
            ->with(42);

        $this->pm->setProjectManagerModel($model);

        $this->pm->callClearFailedProject(new Exception('Something went wrong'));
    }

    #[Test]
    public function castsStringIdToInt(): void
    {
        $this->pm->setProjectStructureValue('id_project', '123');

        $model = $this->createMock(ProjectManagerModel::class);
        $model->expects($this->once())
            ->method('deleteProject')
            ->with(123);

        $this->pm->setProjectManagerModel($model);

        $this->pm->callClearFailedProject(new Exception('fail'));
    }

    #[Test]
    public function skipsCleanupWhenIdProjectIsNull(): void
    {
        $this->pm->setProjectStructureValue('id_project', null);

        $model = $this->createMock(ProjectManagerModel::class);
        $model->expects($this->never())
            ->method('deleteProject');

        $this->pm->setProjectManagerModel($model);

        $this->pm->callClearFailedProject(new Exception('fail'));
    }

    #[Test]
    public function skipsCleanupWhenIdProjectIsZero(): void
    {
        $this->pm->setProjectStructureValue('id_project', 0);

        $model = $this->createMock(ProjectManagerModel::class);
        $model->expects($this->never())
            ->method('deleteProject');

        $this->pm->setProjectManagerModel($model);

        $this->pm->callClearFailedProject(new Exception('fail'));
    }

    #[Test]
    public function swallowsCleanupExceptionWithoutRethrowing(): void
    {
        $this->pm->setProjectStructureValue('id_project', 99);

        $model = $this->createStub(ProjectManagerModel::class);
        $model->method('deleteProject')
            ->willThrowException(new RuntimeException('DB gone'));

        $this->pm->setProjectManagerModel($model);

        $this->pm->callClearFailedProject(new Exception('original error'));

        $this->assertTrue(true);
    }
}
