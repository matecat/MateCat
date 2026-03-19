<?php

namespace unit\Model\ProjectCreation;

use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use Model\ProjectCreation\ProjectManagerModel;
use Model\ProjectCreation\ProjectStructure;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionException;
use TestHelpers\AbstractTest;
use Utils\Constants\ProjectStatus;
use Utils\Logger\MatecatLogger;

/**
 * Unit tests for {@see \Model\ProjectCreation\ProjectManager::createProjectRecord()}.
 *
 * Verifies:
 * - Delegates to ProjectManagerModel::createProjectRecord()
 * - Stores the returned ProjectStruct in $this->project
 * - Passes the config DTO plus mutable pipeline keys to the model
 */
class CreateProjectRecordTest extends AbstractTest
{
    private TestableProjectManager $pm;

    /**
     */
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

    /**
     * @throws ReflectionException
     * @throws Exception
     */
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

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    #[Test]
    public function passesConfigAndMutableKeysToModel(): void
    {
        $capturedConfig = null;
        $capturedIdTeam = null;
        $capturedStatus = null;
        $capturedIdAssignee = null;

        $model = $this->createMock(ProjectManagerModel::class);
        $model->expects($this->once())
            ->method('createProjectRecord')
            ->willReturnCallback(function (
                ProjectStructure $projectStructure,
                ?int $idTeam,
                string $status,
                ?int $idAssignee
            ) use (&$capturedConfig, &$capturedIdTeam, &$capturedStatus, &$capturedIdAssignee) {
                $capturedConfig = $projectStructure;
                $capturedIdTeam = $idTeam;
                $capturedStatus = $status;
                $capturedIdAssignee = $idAssignee;

                return new ProjectStruct(['id' => 1]);
            });

        $this->pm->setProjectManagerModel($model);
        $this->pm->setProjectStructureValue('project_name', 'My Project');
        $this->pm->setProjectStructureValue('id_team', 7);
        $this->pm->setProjectStructureValue('id_assignee', 42);

        $this->pm->callCreateProjectRecord();

        $this->assertInstanceOf(ProjectStructure::class, $capturedConfig);
        $this->assertSame('My Project', $capturedConfig->project_name);
        $this->assertSame(999, $capturedConfig->id_project);
        $this->assertSame(7, $capturedIdTeam);
        $this->assertSame(ProjectStatus::STATUS_NOT_READY_FOR_ANALYSIS, $capturedStatus);
        $this->assertSame(42, $capturedIdAssignee);
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

    /**
     * @throws ReflectionException
     * @throws Exception
     */
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
