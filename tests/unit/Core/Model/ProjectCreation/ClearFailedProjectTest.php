<?php

namespace Matecat\Core\Model\ProjectCreation;

use Matecat\SubFiltering\MateCatFilter;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use Model\ProjectCreation\ProjectManagerModel;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Utils\Logger\MatecatLogger;

/**
 * Unit tests for {@see \Model\ProjectCreation\ProjectManager::clearFailedProject()}.
 *
 * Verifies:
 * - Delegates to ProjectManagerModel::deleteProject() with the project ID
 * - Does nothing when no project ID is available
 * - Keeps a project row another run inserted (a redelivered message)
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
        $model->method('projectExists')->with(42)->willReturn(false);
        $model->expects($this->once())
            ->method('deleteProject')
            ->with(42);

        $this->pm->setProjectManagerModel($model);

        $this->pm->callClearFailedProject();
    }

    #[Test]
    public function keepsAProjectAnotherRunInserted(): void
    {
        $model = $this->createMock(ProjectManagerModel::class);
        $model->expects($this->once())->method('projectExists')->with(42)->willReturn(true);
        $model->expects($this->never())->method('deleteProject');

        $logger = $this->createMock(MatecatLogger::class);
        $logger->expects($this->once())->method('warning')
            ->with('Project 42 was inserted by another run: cleanup skipped');
        $this->pm->setLogger($logger);

        $this->pm->setProjectStructureValue('id_project', 42);
        $this->pm->setProjectManagerModel($model);

        $this->pm->callClearFailedProject();
    }

    #[Test]
    public function deletesTheProjectThisRunInserted(): void
    {
        $this->pm->setProjectStructureValue('id_project', 42);
        $this->pm->setProject(new ProjectStruct());

        $model = $this->createMock(ProjectManagerModel::class);
        $model->expects($this->never())->method('projectExists');
        $model->expects($this->once())->method('deleteProject')->with(42);

        $this->pm->setProjectManagerModel($model);

        $this->pm->callClearFailedProject();
    }

    #[Test]
    public function deletesNothingWhenTheExistenceCheckFails(): void
    {
        $this->pm->setProjectStructureValue('id_project', 42);

        $model = $this->createMock(ProjectManagerModel::class);
        $model->method('projectExists')->willThrowException(new RuntimeException('DB gone'));
        $model->expects($this->never())->method('deleteProject');

        $this->pm->setProjectManagerModel($model);

        $this->pm->callClearFailedProject();
    }

    #[Test]
    public function deletesByTheIdInTheStructure(): void
    {
        $this->pm->setProjectStructureValue('id_project', '123');

        $model = $this->createMock(ProjectManagerModel::class);
        $model->expects($this->once())
            ->method('deleteProject')
            ->with(123);

        $this->pm->setProjectManagerModel($model);

        $this->pm->callClearFailedProject();
    }

    #[Test]
    public function skipsCleanupWhenIdProjectIsNull(): void
    {
        $this->pm->setProjectStructureValue('id_project', null);

        $model = $this->createMock(ProjectManagerModel::class);
        $model->expects($this->never())
            ->method('deleteProject');

        $this->pm->setProjectManagerModel($model);

        $this->pm->callClearFailedProject();
    }

    #[Test]
    public function skipsCleanupWhenIdProjectIsZero(): void
    {
        $this->pm->setProjectStructureValue('id_project', 0);

        $model = $this->createMock(ProjectManagerModel::class);
        $model->expects($this->never())
            ->method('deleteProject');

        $this->pm->setProjectManagerModel($model);

        $this->pm->callClearFailedProject();
    }

    #[Test]
    public function swallowsCleanupExceptionWithoutRethrowing(): void
    {
        $this->pm->setProjectStructureValue('id_project', 99);

        $model = $this->createStub(ProjectManagerModel::class);
        $model->method('deleteProject')
            ->willThrowException(new RuntimeException('DB gone'));

        $this->pm->setProjectManagerModel($model);

        $this->pm->callClearFailedProject();

        $this->assertTrue(true);
    }
}
