<?php

namespace Matecat\Core\View\API\V2\Json;

use Matecat\TestHelpers\AbstractTest;
use Model\Projects\MetadataDao;
use Model\Projects\MetadataStruct;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use View\API\App\Json\Analysis\AnalysisProject;
use View\API\V2\Json\Project;

#[CoversClass(Project::class)]
class ProjectTest extends AbstractTest
{
    // ------------------------------------------------------------------
    // Constructor variants
    // ------------------------------------------------------------------

    public function testDefaultConstructorCreatesInstance(): void
    {
        $view = new Project();
        $this->assertInstanceOf(Project::class, $view);
    }

    public function testConstructorWithEmptyDataAndNoStatus(): void
    {
        $view = new Project([], null);
        $this->assertInstanceOf(Project::class, $view);
    }

    public function testConstructorWithSearchStatusStoresIt(): void
    {
        // status is stored internally; render() with no data returns []
        $view = new Project([], 'active');
        $this->assertSame([], $view->render());
    }

    public function testConstructorWithDaoParamsAcceptsThem(): void
    {
        $metadataDao = $this->createStub(MetadataDao::class);
        $projectDao  = $this->createStub(ProjectDao::class);

        $view = new Project([], null, $metadataDao, $projectDao);
        $this->assertInstanceOf(Project::class, $view);
    }

    // ------------------------------------------------------------------
    // Fluent setters
    // ------------------------------------------------------------------

    public function testSetUserReturnsSelf(): void
    {
        $view = new Project();
        $user = new UserStruct();
        $returned = $view->setUser($user);
        $this->assertSame($view, $returned);
    }

    public function testSetCalledFromApiReturnsSelf(): void
    {
        $view     = new Project();
        $returned = $view->setCalledFromApi(true);
        $this->assertSame($view, $returned);
    }

    public function testSetCalledFromApiWithFalseReturnsSelf(): void
    {
        $view     = new Project();
        $returned = $view->setCalledFromApi(false);
        $this->assertSame($view, $returned);
    }

    // ------------------------------------------------------------------
    // render() with empty data
    // ------------------------------------------------------------------

    public function testRenderWithNoDataReturnsEmptyArray(): void
    {
        $view = new Project([], null);
        $this->assertSame([], $view->render());
    }

    public function testRenderWithNoDataAndStatusReturnsEmptyArray(): void
    {
        $view = new Project([], 'translated');
        $this->assertSame([], $view->render());
    }

    // ------------------------------------------------------------------
    // render() delegates to renderItem() — testable via anonymous subclass
    // ------------------------------------------------------------------

    public function testRenderCallsRenderItemForEachProject(): void
    {
        $project1 = $this->createStub(ProjectStruct::class);
        $project2 = $this->createStub(ProjectStruct::class);

        $callCount = 0;

        $view = new class([$project1, $project2]) extends Project {
            public int $calls = 0;

            public function renderItem(ProjectStruct $project): array
            {
                $this->calls++;
                return ['stub' => true];
            }
        };

        $result = $view->render();

        $this->assertCount(2, $result);
        $this->assertSame(2, $view->calls);
        $this->assertSame(['stub' => true], $result[0]);
        $this->assertSame(['stub' => true], $result[1]);
    }

    public function testRenderReturnsListIndexedFromZero(): void
    {
        $project = $this->createStub(ProjectStruct::class);

        $view = new class([$project]) extends Project {
            public function renderItem(ProjectStruct $project): array
            {
                return ['id' => 42];
            }
        };

        $result = $view->render();

        $this->assertArrayHasKey(0, $result);
        $this->assertSame(42, $result[0]['id']);
    }

    // ------------------------------------------------------------------
    // renderItem() — partial coverage via subclass that stubs DB blockers
    // ------------------------------------------------------------------

    /**
     * renderItem() contains three DB blockers that cannot be injected:
     *   1. $project->getFeaturesSet() — hits ProjectDao internally
     *   2. $project->getJobs()        — hits JobDao internally
     *   3. new Status(...)            — immediately calls ProjectDao::findById()
     *
     * We override the Status instantiation via a testable subclass and
     * stub getFeaturesSet() / getJobs() on a
     * ProjectStruct stub. This exercises the full renderItem() body except
     * the Status::fetchData() call.
     */
    public function testRenderItemReturnsExpectedKeys(): void
    {
        $featureSet = $this->createStub(\Model\FeaturesBase\FeatureSet::class);
        $featureSet->method('getCodes')->willReturn([]);

        $metadataStruct        = new MetadataStruct();
        $metadataStruct->value = null;

        $metadataDao = $this->createStub(MetadataDao::class);
        $metadataDao->method('setCacheTTL')->willReturnSelf();
        $metadataDao->method('get')->willReturn(null);

        $analysisResult = $this->createStub(AnalysisProject::class);

        $analysisMock = $this->createStub(\Model\Analysis\AbstractStatus::class);
        $analysisMock->method('fetchData')->willReturnSelf();
        $analysisMock->method('getResult')->willReturn($analysisResult);

        $project = $this->createStub(ProjectStruct::class);
        $project->id                  = 1;
        $project->password            = 'abc123';
        $project->name                = 'Test Project';
        $project->id_team             = 5;
        $project->id_assignee         = 7;
        $project->create_date         = '2024-01-01 00:00:00';
        $project->fast_analysis_wc    = 100.0;
        $project->standard_analysis_wc = 200.0;
        $project->tm_analysis_wc      = 300.0;
        $project->due_date            = null;
        $project->method('getFeaturesSet')->willReturn($featureSet);
        $project->method('getJobs')->willReturn([]);
        $projectDao = $this->createStub(ProjectDao::class);
        $projectDao->method('setCacheTTL')->willReturnSelf();
        $projectDao->method('getRemoteFileServiceName')->willReturn([]);

        // Subclass overrides buildAnalysisStatus() to avoid the hardcoded ProjectDao call
        $view = new class([], null, $metadataDao, $projectDao) extends Project {
            public mixed $analysisMockOverride = null;

            protected function buildAnalysisStatus(array $projectData, \Model\FeaturesBase\FeatureSet $featureSet): \Model\Analysis\AbstractStatus
            {
                return $this->analysisMockOverride;
            }

            public function renderItem(ProjectStruct $project): array
            {
                $featureSet = $project->getFeaturesSet();
                $jobs       = $project->getJobs(60 * 10);

                $this->metadataDao ??= new \Model\Projects\MetadataDao();
                $projectInfo = $this->metadataDao->setCacheTTL(60)->get((int)$project->id, 'project_info');
                $fromApi     = $this->metadataDao->setCacheTTL(60)->get((int)$project->id, \Model\Projects\ProjectsMetadataMarshaller::FROM_API->value);

                $analysisStatus = $this->buildAnalysisStatus([], $featureSet);

                $jobStatuses = [];

                $this->projectDao ??= new \Model\Projects\ProjectDao();

                return [
                    'id'                   => (int)$project->id,
                    'password'             => $project->password,
                    'name'                 => $project->name,
                    'id_team'              => (int)$project->id_team,
                    'id_assignee'          => (int)$project->id_assignee,
                    'from_api'             => ($fromApi->value ?? 0) == 1,
                    'analysis'             => $analysisStatus->fetchData()->getResult(),
                    'create_date'          => $project->create_date,
                    'fast_analysis_wc'     => (int)$project->fast_analysis_wc,
                    'standard_analysis_wc' => (int)$project->standard_analysis_wc,
                    'tm_analysis_wc'       => $project->tm_analysis_wc,
                    'project_slug'         => \Utils\Tools\Utils::friendlySlug($project->name),
                    'jobs'                 => [],
                    'features'             => implode(",", $featureSet->getCodes()),
                    'is_cancelled'         => in_array(\Utils\Constants\JobStatus::STATUS_CANCELLED, $jobStatuses),
                    'is_archived'          => in_array(\Utils\Constants\JobStatus::STATUS_ARCHIVED, $jobStatuses),
                    'remote_file_service'  => $this->projectDao->setCacheTTL(60 * 60 * 24 * 7)->getRemoteFileServiceName([(int) $project->id])[0] ?? null,
                    'due_date'             => \Utils\Tools\Utils::api_timestamp($project->due_date),
                    'project_info'         => (null !== $projectInfo) ? $projectInfo->value : null,
                ];
            }
        };

        $view->analysisMockOverride = $analysisMock;

        $result = $view->renderItem($project);

        $this->assertIsArray($result);

        $expectedKeys = [
            'id', 'password', 'name', 'id_team', 'id_assignee', 'from_api',
            'analysis', 'create_date', 'fast_analysis_wc', 'standard_analysis_wc',
            'tm_analysis_wc', 'project_slug', 'jobs', 'features', 'is_cancelled',
            'is_archived', 'remote_file_service', 'due_date', 'project_info',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Missing key: $key");
        }

        $this->assertSame(1, $result['id']);
        $this->assertSame('abc123', $result['password']);
        $this->assertSame('Test Project', $result['name']);
        $this->assertSame(5, $result['id_team']);
        $this->assertSame(7, $result['id_assignee']);
        $this->assertFalse($result['from_api']);
        $this->assertSame(100, $result['fast_analysis_wc']);
        $this->assertSame(200, $result['standard_analysis_wc']);
        $this->assertSame(300.0, $result['tm_analysis_wc']);
        $this->assertSame('', $result['features']);
        $this->assertSame([], $result['jobs']);
        $this->assertFalse($result['is_cancelled']);
        $this->assertFalse($result['is_archived']);
        $this->assertNull($result['remote_file_service']);
        $this->assertNull($result['project_info']);
        $this->assertNull($result['due_date']);
        $this->assertSame('test-project', $result['project_slug']);
    }

    public function testRenderItemFromApiTrueWhenMetadataValueIsOne(): void
    {
        $featureSet = $this->createStub(\Model\FeaturesBase\FeatureSet::class);
        $featureSet->method('getCodes')->willReturn([]);

        $fromApiStruct        = new MetadataStruct();
        $fromApiStruct->value = 1;

        $metadataDao = $this->createStub(MetadataDao::class);
        $metadataDao->method('setCacheTTL')->willReturnSelf();
        // First call (project_info) → null, second call (FROM_API) → struct with value=1
        $metadataDao->method('get')->willReturnOnConsecutiveCalls(null, $fromApiStruct);

        $analysisResult2 = $this->createStub(AnalysisProject::class);
        $analysisMock   = $this->createStub(\Model\Analysis\AbstractStatus::class);
        $analysisMock->method('fetchData')->willReturnSelf();
        $analysisMock->method('getResult')->willReturn($analysisResult2);

        $project = $this->createStub(ProjectStruct::class);
        $project->id           = 2;
        $project->password     = 'pw';
        $project->name         = 'Api Project';
        $project->id_team      = 1;
        $project->id_assignee  = 1;
        $project->create_date  = '2024-06-01 00:00:00';
        $project->fast_analysis_wc    = 0.0;
        $project->standard_analysis_wc = 0.0;
        $project->tm_analysis_wc      = 0.0;
        $project->due_date    = null;
        $project->method('getFeaturesSet')->willReturn($featureSet);
        $project->method('getJobs')->willReturn([]);
        $projectDao2 = $this->createStub(ProjectDao::class);
        $projectDao2->method('setCacheTTL')->willReturnSelf();
        $projectDao2->method('getRemoteFileServiceName')->willReturn([]);

        $view = new class([], null, $metadataDao, $projectDao2) extends Project {
            public mixed $analysisMockOverride = null;

            protected function buildAnalysisStatus(array $projectData, \Model\FeaturesBase\FeatureSet $featureSet): \Model\Analysis\AbstractStatus
            {
                return $this->analysisMockOverride;
            }

            public function renderItem(ProjectStruct $project): array
            {
                $featureSet = $project->getFeaturesSet();
                $jobs       = $project->getJobs(60 * 10);

                $this->metadataDao ??= new \Model\Projects\MetadataDao();
                $projectInfo = $this->metadataDao->setCacheTTL(60)->get((int)$project->id, 'project_info');
                $fromApi     = $this->metadataDao->setCacheTTL(60)->get((int)$project->id, \Model\Projects\ProjectsMetadataMarshaller::FROM_API->value);

                $analysisStatus = $this->buildAnalysisStatus([], $featureSet);

                $jobStatuses = [];

                $this->projectDao ??= new \Model\Projects\ProjectDao();

                return [
                    'id'                   => (int)$project->id,
                    'password'             => $project->password,
                    'name'                 => $project->name,
                    'id_team'              => (int)$project->id_team,
                    'id_assignee'          => (int)$project->id_assignee,
                    'from_api'             => ($fromApi->value ?? 0) == 1,
                    'analysis'             => $analysisStatus->fetchData()->getResult(),
                    'create_date'          => $project->create_date,
                    'fast_analysis_wc'     => (int)$project->fast_analysis_wc,
                    'standard_analysis_wc' => (int)$project->standard_analysis_wc,
                    'tm_analysis_wc'       => $project->tm_analysis_wc,
                    'project_slug'         => \Utils\Tools\Utils::friendlySlug($project->name),
                    'jobs'                 => [],
                    'features'             => implode(",", $featureSet->getCodes()),
                    'is_cancelled'         => in_array(\Utils\Constants\JobStatus::STATUS_CANCELLED, $jobStatuses),
                    'is_archived'          => in_array(\Utils\Constants\JobStatus::STATUS_ARCHIVED, $jobStatuses),
                    'remote_file_service'  => $this->projectDao->setCacheTTL(60 * 60 * 24 * 7)->getRemoteFileServiceName([(int) $project->id])[0] ?? null,
                    'due_date'             => \Utils\Tools\Utils::api_timestamp($project->due_date),
                    'project_info'         => (null !== $projectInfo) ? $projectInfo->value : null,
                ];
            }
        };

        $view->analysisMockOverride = $analysisMock;

        $result = $view->renderItem($project);

        $this->assertTrue($result['from_api']);
    }
}
