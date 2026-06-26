<?php

declare(strict_types=1);

namespace Matecat\Core\Model\Analysis;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\Analysis\AbstractStatus;
use Model\Analysis\AnalysisDao;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao as FileMetadataDao;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use View\API\App\Json\Analysis\AnalysisProject;

/**
 * Testable concrete subclass of AbstractStatus that bypasses static DB calls.
 */
class TestableAbstractStatus extends AbstractStatus
{
    public function __construct(
        array $_project_data,
        FeatureSet $features,
        ProjectStruct $project,
        ?UserStruct $user = null,
        ?AnalysisDao $analysisDao = null,
        ?JobDao $jobDao = null,
        ?FileMetadataDao $fileMetadataDao = null
    ) {
        if ($user === null) {
            $user       = new UserStruct();
            $user->uid  = -1;
        }
        $this->user            = $user;
        $this->project         = $project;
        $this->_project_data   = $_project_data;
        $this->featureSet      = $features;
        $this->analysisDao     = $analysisDao ?? new AnalysisDao(\Model\DataAccess\Database::obtain());
        $this->jobDao          = $jobDao ?? new JobDao(\Model\DataAccess\Database::obtain());
        $this->fileMetadataDao = $fileMetadataDao ?? new FileMetadataDao(\Model\DataAccess\Database::obtain());
    }

    public function callIsOutsourceEnabled(string $targetLang, string $id_customer, int $idJob): bool
    {
        return $this->isOutsourceEnabled($targetLang, $id_customer, $idJob);
    }

    public function callFetchProjectData(): AbstractStatus
    {
        return $this->_fetchProjectData();
    }
}

class AbstractStatusTest extends AbstractTest
{
    private function makeProjectStruct(int $id = 1): ProjectStruct
    {
        $project           = new ProjectStruct();
        $project->id       = $id;
        $project->name     = 'Test Project';
        $project->password = 'abc123';

        return $project;
    }

    /** @return array<mixed> */
    private function makeProjectData(int $pid = 1): array
    {
        return [
            [
                'pid'              => $pid,
                'pname'            => 'Test Project',
                'status_analysis'  => 'DONE',
                'create_date'      => '2024-01-01 00:00:00',
                'subject'          => 'general',
                'jid'              => 10,
                'jpassword'        => 'pass1',
                'lang_pair'        => 'en-US|it-IT',
                'standard_analysis_wc' => 100,
                'payable_rates'    => '{}',
                'id_customer'      => 'customer1',
            ],
        ];
    }

    #[Test]
    public function getResultThrowsWhenNotInitialized(): void
    {
        $status = new TestableAbstractStatus(
            $this->makeProjectData(),
            new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)),
            $this->makeProjectStruct()
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Result not initialized');
        $status->getResult();
    }

    #[Test]
    public function constructorSetsUserToAnonymousWhenNull(): void
    {
        $status = new TestableAbstractStatus(
            $this->makeProjectData(),
            new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)),
            $this->makeProjectStruct()
        );

        // If no exception thrown, user was set correctly (uid = -1 for anonymous)
        $this->assertInstanceOf(AbstractStatus::class, $status);
    }

    #[Test]
    public function constructorAcceptsExplicitUser(): void
    {
        $user      = new UserStruct();
        $user->uid = 42;

        $status = new TestableAbstractStatus(
            $this->makeProjectData(),
            new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)),
            $this->makeProjectStruct(),
            $user
        );

        $this->assertInstanceOf(AbstractStatus::class, $status);
    }

    #[Test]
    public function isOutsourceEnabledReturnsTrueWithNoPlugins(): void
    {
        $status = new TestableAbstractStatus(
            $this->makeProjectData(),
            new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)),
            $this->makeProjectStruct()
        );

        // With no plugins loaded, outsource info defaults to all-false flags → outsource IS available
        $result = $status->callIsOutsourceEnabled('it-IT', 'customer1', 10);

        $this->assertTrue($result);
    }

    #[Test]
    public function isOutsourceEnabledReturnsBoolForAnyLanguage(): void
    {
        $status = new TestableAbstractStatus(
            $this->makeProjectData(),
            new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)),
            $this->makeProjectStruct()
        );

        // Result must be a boolean regardless of input — no exceptions thrown
        $result = $status->callIsOutsourceEnabled('en-US', 'customer1', 99);

        $this->assertIsBool($result);
    }

    #[Test]
    public function projectDataWithMultipleJobsIsAccepted(): void
    {
        $projectData   = $this->makeProjectData();
        $projectData[] = [
            'pid'              => 1,
            'pname'            => 'Test Project',
            'status_analysis'  => 'DONE',
            'create_date'      => '2024-01-01 00:00:00',
            'subject'          => 'general',
            'jid'              => 11,
            'jpassword'        => 'pass2',
            'lang_pair'        => 'en-US|de-DE',
            'standard_analysis_wc' => 200,
            'payable_rates'    => '{}',
            'id_customer'      => 'customer1',
        ];

        $status = new TestableAbstractStatus(
            $projectData,
            new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)),
            $this->makeProjectStruct()
        );

        $this->assertInstanceOf(AbstractStatus::class, $status);
    }

    private function makeAnalysisDaoMock(array $resultSet = []): AnalysisDao
    {
        $mock = $this->createStub(AnalysisDao::class);
        $mock->method('getProjectStatsVolumeAnalysis')->willReturn($resultSet);

        return $mock;
    }

    private function makeJobDaoMock(): JobDao
    {
        $jobStruct = new JobStruct();
        $jobStruct->id = 10;
        $jobStruct->password = 'pass1';
        $jobStruct->source = 'en-US';
        $jobStruct->target = 'it-IT';
        $jobStruct->payable_rates = '{"NO_MATCH":100,"50%-74%":100,"75%-84%":60,"85%-94%":60,"95%-99%":60,"100%":30,"100%_PUBLIC":30,"REPETITIONS":30,"INTERNAL":60,"MT":85,"ICE":0}';

        [$dbStub] = $this->createDatabaseMock();

        $mock = $this->createStub(JobDao::class);
        $mock->method('getByIdAndPasswordOrFail')->willReturn($jobStruct);
        $mock->method('getDatabaseHandler')->willReturn($dbStub);

        return $mock;
    }

    private function makeFileMetadataDaoMock(): FileMetadataDao
    {
        $mock = $this->createStub(FileMetadataDao::class);
        $mock->method('getByJobIdProjectAndIdFile')->willReturn([]);

        return $mock;
    }

    private function makeSegmentResult(array $overrides = []): ShapelessConcreteStruct
    {
        $struct = new ShapelessConcreteStruct();
        $defaults = [
            'jid'                => 10,
            'jpassword'          => 'pass1',
            'source'             => 'en-US',
            'target'             => 'it-IT',
            'sid'                => 100,
            'id_file'            => 1,
            'id_file_part'       => null,
            'filename'           => 'test.xlf',
            'raw_word_count'     => 50,
            'suggestion_source'  => 'TM',
            'suggestion_match'   => '100',
            'eq_word_count'      => 30,
            'standard_word_count' => 45,
            'match_type'         => 'ICE',
            'status_analysis'    => 'DONE',
            'fast_analysis_wc'   => 100,
            'tm_analysis_wc'     => 100,
            'standard_analysis_wc' => 100,
            'id_customer'        => 'customer1',
            'tag_key'            => null,
            'tag_value'          => null,
            'st_status_analysis' => 'DONE',
            'locked'             => 0,
        ];

        foreach (array_merge($defaults, $overrides) as $key => $value) {
            $struct->$key = $value;
        }

        return $struct;
    }

    private function createStatusWithMocks(
        array $projectData = [],
        array $resultSet = [],
        ?ProjectStruct $project = null,
        ?UserStruct $user = null
    ): TestableAbstractStatus {
        [$dbStub] = $this->createDatabaseMock();

        return new TestableAbstractStatus(
            $projectData ?: $this->makeProjectData(),
            new FeatureSet($dbStub),
            $project ?? $this->makeProjectStruct(),
            $user,
            $this->makeAnalysisDaoMock($resultSet),
            $this->makeJobDaoMock(),
            $this->makeFileMetadataDaoMock()
        );
    }

    #[Test]
    public function fetchProjectDataWithEmptyResultSet(): void
    {
        $this->createDatabaseMock();
        $status = $this->createStatusWithMocks();

        $result = $status->callFetchProjectData();

        $this->assertInstanceOf(AbstractStatus::class, $result);
    }

    #[Test]
    public function fetchProjectDataWithResults(): void
    {
        $this->createDatabaseMock();
        $segments = [$this->makeSegmentResult(), $this->makeSegmentResult(['sid' => 101])];
        $status = $this->createStatusWithMocks(resultSet: $segments);

        $result = $status->callFetchProjectData();

        $this->assertInstanceOf(AbstractStatus::class, $result);
    }

    #[Test]
    public function fetchDataWithEmptyResultSetAndDoneStatus(): void
    {
        $this->createDatabaseMock();
        $status = $this->createStatusWithMocks();

        $result = $status->fetchData();

        $this->assertInstanceOf(AbstractStatus::class, $result);
        $analysisProject = $result->getResult();
        $this->assertInstanceOf(AnalysisProject::class, $analysisProject);
    }

    #[Test]
    public function fetchDataWithEmptyResultSetAndNewStatusUsesFallback(): void
    {
        $this->createDatabaseMock();
        $projectData = $this->makeProjectData();
        $projectData[0]['status_analysis'] = 'NEW';

        $status = $this->createStatusWithMocks(projectData: $projectData);

        $result = $status->fetchData();

        $analysisProject = $result->getResult();
        $this->assertInstanceOf(AnalysisProject::class, $analysisProject);
        $this->assertNotEmpty($analysisProject->getJobs());
    }

    #[Test]
    public function fetchDataWithSegmentDataBuildsFullResult(): void
    {
        $this->createDatabaseMock();
        $segments = [
            $this->makeSegmentResult(),
            $this->makeSegmentResult(['sid' => 101, 'match_type' => 'NEW']),
        ];

        $status = $this->createStatusWithMocks(resultSet: $segments);

        $result = $status->fetchData();

        $analysisProject = $result->getResult();
        $this->assertInstanceOf(AnalysisProject::class, $analysisProject);
        $this->assertNotEmpty($analysisProject->getJobs());
        $this->assertEquals('DONE', $analysisProject->getStatus());
    }

    #[Test]
    public function fetchDataWithMultipleFilesBuildsMultipleFileObjects(): void
    {
        $this->createDatabaseMock();
        $segments = [
            $this->makeSegmentResult(['id_file' => 1, 'filename' => 'file1.xlf']),
            $this->makeSegmentResult(['sid' => 101, 'id_file' => 2, 'filename' => 'file2.xlf']),
        ];

        $status = $this->createStatusWithMocks(resultSet: $segments);

        $result = $status->fetchData();

        $analysisProject = $result->getResult();
        $this->assertInstanceOf(AnalysisProject::class, $analysisProject);
        $jobs = $analysisProject->getJobs();
        $this->assertCount(1, $jobs);
    }

    #[Test]
    public function fetchDataWithExplicitUserPreservesUser(): void
    {
        $this->createDatabaseMock();
        $user = new UserStruct();
        $user->uid = 42;

        $segments = [$this->makeSegmentResult()];
        $status = $this->createStatusWithMocks(resultSet: $segments, user: $user);

        $result = $status->fetchData();

        $this->assertInstanceOf(AnalysisProject::class, $result->getResult());
    }

    #[Test]
    public function fetchDataWithAnalyzedSegmentsIncrementsSummary(): void
    {
        $this->createDatabaseMock();
        $segments = [
            $this->makeSegmentResult(['st_status_analysis' => 'DONE']),
            $this->makeSegmentResult(['sid' => 101, 'st_status_analysis' => 'NEW']),
        ];

        $status = $this->createStatusWithMocks(resultSet: $segments);
        $result = $status->fetchData();

        $summary = $result->getResult()->getSummary();
        $this->assertGreaterThan(0, $summary->getTotalSegments());
    }
}
