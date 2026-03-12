<?php

namespace unit\Model\ProjectManager;

use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use Model\ProjectManager\ProjectManagerModel;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;

/**
 * Unit tests for {@see \Model\ProjectManager\ProjectManager::createProjectRecord()}.
 *
 * Verifies:
 * - Delegates to ProjectManagerModel::createProjectRecord()
 * - Stores the returned ProjectStruct in $this->project
 * - Passes the projectStructure to the model
 */
class CreateProjectRecordTest extends AbstractTest
{
    private TestableProjectManager $pm;

    protected function setUp(): void
    {
        $this->pm = new TestableProjectManager();
        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $this->createStub(FeatureSet::class),
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );
    }

    #[Test]
    public function delegatesToModelAndStoresProject(): void
    {
        $expectedProject = new ProjectStruct([
            'id'   => 42,
            'name' => 'Test Project',
        ]);

        $model = $this->createMock(ProjectManagerModel::class);
        $model->expects($this->once())
            ->method('createProjectRecord')
            ->willReturn($expectedProject);

        $this->pm->setProjectManagerModel($model);

        $this->pm->callCreateProjectRecord();

        $this->assertSame($expectedProject, $this->pm->getProject());
    }

    #[Test]
    public function passesProjectStructureToModel(): void
    {
        $capturedStructure = null;

        $model = $this->createMock(ProjectManagerModel::class);
        $model->expects($this->once())
            ->method('createProjectRecord')
            ->willReturnCallback(function ($ps) use (&$capturedStructure) {
                $capturedStructure = $ps;

                return new ProjectStruct(['id' => 1]);
            });

        $this->pm->setProjectManagerModel($model);
        $this->pm->setProjectStructureValue('project_name', 'My Project');

        $this->pm->callCreateProjectRecord();

        $this->assertSame('My Project', $capturedStructure['project_name']);
        $this->assertSame(999, $capturedStructure['id_project']);
    }

    #[Test]
    public function projectIsUninitializedBeforeCallingCreate(): void
    {
        // The $project property is typed (ProjectStruct) and uninitialized
        // before createProjectRecord() is called, so accessing it throws.
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('must not be accessed before initialization');
        $this->pm->getProject();
    }

    #[Test]
    public function overwritesPreviousProjectOnSecondCall(): void
    {
        $project1 = new ProjectStruct(['id' => 1, 'name' => 'First']);
        $project2 = new ProjectStruct(['id' => 2, 'name' => 'Second']);

        $model = $this->createStub(ProjectManagerModel::class);
        $model->method('createProjectRecord')
            ->willReturnOnConsecutiveCalls($project1, $project2);

        $this->pm->setProjectManagerModel($model);

        $this->pm->callCreateProjectRecord();
        $this->assertSame($project1, $this->pm->getProject());

        $this->pm->callCreateProjectRecord();
        $this->assertSame($project2, $this->pm->getProject());
    }
}
