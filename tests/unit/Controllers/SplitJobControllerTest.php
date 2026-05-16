<?php

namespace unit\Controllers;

use ArrayObject;
use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\V2\SplitJobController;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Model\Jobs\JobStruct;
use Model\JobSplitMerge\JobSplitMergeManager;
use Model\JobSplitMerge\SplitMergeProjectData;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use TestHelpers\AbstractTest;

class TestableSplitJobController extends SplitJobController
{
    /** @var array{data: SplitMergeProjectData, pManager: JobSplitMergeManager, count_type: string, project: ProjectStruct}|null */
    public ?array $fakeProjectData = null;

    public function __construct()
    {
    }

    protected function afterConstruct(): void
    {
    }

    protected function getProjectData(int $project_id, string $project_pass, bool $split_raw_words = false): array
    {
        if ($this->fakeProjectData === null) {
            throw new \RuntimeException('fakeProjectData not configured');
        }

        return $this->fakeProjectData;
    }
}

class SplitJobControllerTest extends AbstractTest
{
    private TestableSplitJobController $controller;
    private ReflectionClass $reflector;
    private Request $requestStub;
    private Response $responseMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new TestableSplitJobController();
        $this->reflector  = new ReflectionClass(SplitJobController::class);

        $this->requestStub  = $this->createStub(Request::class);
        $this->responseMock = $this->createStub(Response::class);

        $reqProp = $this->reflector->getProperty('request');
        $reqProp->setValue($this->controller, $this->requestStub);

        $resProp = $this->reflector->getProperty('response');
        $resProp->setValue($this->controller, $this->responseMock);
    }

    #[Test]
    public function validateTheRequest_returns_shaped_array_on_valid_input(): void
    {
        $this->stubRequestParams([
            'project_id'  => '42',
            'project_pass' => 'abc123',
            'job_id'       => '99',
            'job_pass'     => 'jobpw',
            'split_raw_words' => 'true',
            'num_split'    => '3',
            'split_values' => ['100', '200', '300'],
        ]);

        $result = $this->callPrivate('validateTheRequest');

        self::assertSame(42, $result['project_id']);
        self::assertSame('abc123', $result['project_pass']);
        self::assertSame(99, $result['job_id']);
        self::assertSame('jobpw', $result['job_pass']);
        self::assertTrue($result['split_raw_words']);
        self::assertSame(3, $result['num_split']);
        self::assertSame([100, 200, 300], $result['split_values']);
    }

    #[Test]
    public function validateTheRequest_uses_alternate_param_names(): void
    {
        $this->stubRequestParams([
            'id_project' => '10',
            'password'   => 'passAlt',
            'id_job'     => '20',
            'job_password' => 'jpAlt',
        ]);

        $result = $this->callPrivate('validateTheRequest');

        self::assertSame(10, $result['project_id']);
        self::assertSame('passAlt', $result['project_pass']);
        self::assertSame(20, $result['job_id']);
        self::assertSame('jpAlt', $result['job_pass']);
    }

    #[Test]
    public function validateTheRequest_throws_on_missing_project_id(): void
    {
        $this->stubRequestParams([
            'project_pass' => 'x',
            'job_id'       => '1',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-1);
        $this->callPrivate('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_throws_on_missing_project_pass(): void
    {
        $this->stubRequestParams([
            'project_id' => '1',
            'job_id'     => '1',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-2);
        $this->callPrivate('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_throws_on_missing_job_id(): void
    {
        $this->stubRequestParams([
            'project_id'   => '1',
            'project_pass' => 'x',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-3);
        $this->callPrivate('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_split_raw_words_defaults_to_false(): void
    {
        $this->stubRequestParams([
            'project_id'   => '1',
            'project_pass' => 'x',
            'job_id'       => '1',
        ]);

        $result = $this->callPrivate('validateTheRequest');
        self::assertFalse($result['split_raw_words']);
    }

    #[Test]
    public function validateTheRequest_split_values_defaults_to_empty_list(): void
    {
        $this->stubRequestParams([
            'project_id'   => '1',
            'project_pass' => 'x',
            'job_id'       => '1',
        ]);

        $result = $this->callPrivate('validateTheRequest');
        self::assertSame([], $result['split_values']);
    }

    #[Test]
    public function filterJobsById_returns_matching_non_deleted_jobs(): void
    {
        $job1 = $this->makeJobStub(10, 'pw1', false);
        $job2 = $this->makeJobStub(20, 'pw2', false);
        $job3 = $this->makeJobStub(10, 'pw3', false);

        $result = $this->callPrivate('filterJobsById', 10, [$job1, $job2, $job3]);

        self::assertCount(2, $result);
        self::assertSame($job1, $result[0]);
        self::assertSame($job3, $result[1]);
    }

    #[Test]
    public function filterJobsById_excludes_deleted_jobs(): void
    {
        $job = $this->makeJobStub(10, 'pw', true);

        $this->expectException(AuthenticationError::class);
        $this->callPrivate('filterJobsById', 10, [$job]);
    }

    #[Test]
    public function filterJobsById_throws_when_no_match(): void
    {
        $job = $this->makeJobStub(20, 'pw', false);

        $this->expectException(AuthenticationError::class);
        $this->expectExceptionCode(-10);
        $this->callPrivate('filterJobsById', 99, [$job]);
    }

    #[Test]
    public function checkMergeAccess_returns_matching_jobs(): void
    {
        $job = $this->makeJobStub(5, 'pw', false);

        $result = $this->callPrivate('checkMergeAccess', 5, [$job]);

        self::assertCount(1, $result);
        self::assertSame($job, $result[0]);
    }

    #[Test]
    public function checkSplitAccess_passes_with_correct_password(): void
    {
        $job = $this->makeJobStub(5, 'correct_pw', false);

        $this->callPrivate('checkSplitAccess', 5, 'correct_pw', [$job]);
        self::assertTrue(true);
    }

    #[Test]
    public function checkSplitAccess_throws_on_wrong_password(): void
    {
        $job = $this->makeJobStub(5, 'correct_pw', false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-10);
        $this->callPrivate('checkSplitAccess', 5, 'wrong_pw', [$job]);
    }

    #[Test]
    public function check_throws_when_job_pass_empty(): void
    {
        $this->stubRequestParams([
            'project_id'   => '1',
            'project_pass' => 'x',
            'job_id'       => '1',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-4);
        $this->controller->check();
    }

    #[Test]
    public function apply_throws_when_job_pass_empty(): void
    {
        $this->stubRequestParams([
            'project_id'   => '1',
            'project_pass' => 'x',
            'job_id'       => '1',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-4);
        $this->controller->apply();
    }

    #[Test]
    public function merge_delegates_to_pManager_and_returns_json(): void
    {
        $job = $this->makeJobStub(99, 'jp', false);

        $projectStub = $this->createStub(ProjectStruct::class);
        $projectStub->method('getJobs')->willReturn([$job]);

        $splitResult = new ArrayObject(['chunks' => [1, 2]]);
        $data        = new SplitMergeProjectData(1);
        $data->splitResult = $splitResult;

        $pManager = $this->createMock(JobSplitMergeManager::class);
        $pManager->expects(self::once())
            ->method('mergeALL')
            ->with($data, [$job]);

        $this->controller->fakeProjectData = [
            'data'       => $data,
            'pManager'   => $pManager,
            'count_type' => 'eq_word_count',
            'project'    => $projectStub,
        ];

        $this->stubRequestParams([
            'project_id'   => '1',
            'project_pass' => 'pp',
            'job_id'       => '99',
            'job_pass'     => 'jp',
        ]);

        $this->responseMock = $this->createMock(Response::class);
        $this->responseMock->expects(self::once())
            ->method('json')
            ->with(['data' => $splitResult]);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->responseMock);

        $this->controller->merge();

        self::assertSame(99, $data->jobToMerge);
    }

    #[Test]
    public function check_returns_split_data_as_json(): void
    {
        $job = $this->makeJobStub(99, 'jp', false);

        $projectStub = $this->createStub(ProjectStruct::class);
        $projectStub->method('getJobs')->willReturn([$job]);

        $splitResult = new ArrayObject(['chunks' => [1, 2, 3]]);
        $data        = new SplitMergeProjectData(1);
        $data->splitResult = $splitResult;

        $pManager = $this->createStub(JobSplitMergeManager::class);

        $this->controller->fakeProjectData = [
            'data'       => $data,
            'pManager'   => $pManager,
            'count_type' => 'eq_word_count',
            'project'    => $projectStub,
        ];

        $this->stubRequestParams([
            'project_id'   => '1',
            'project_pass' => 'pp',
            'job_id'       => '99',
            'job_pass'     => 'jp',
            'num_split'    => '3',
        ]);

        $this->responseMock = $this->createMock(Response::class);
        $this->responseMock->expects(self::once())
            ->method('json')
            ->with(['data' => $splitResult]);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->responseMock);

        $this->controller->check();

        self::assertSame(99, $data->jobToSplit);
        self::assertSame('jp', $data->jobToSplitPass);
    }

    #[Test]
    public function apply_calls_applySplit_and_returns_json(): void
    {
        $job = $this->makeJobStub(99, 'jp', false);

        $projectStub = $this->createStub(ProjectStruct::class);
        $projectStub->method('getJobs')->willReturn([$job]);

        $splitResult = new ArrayObject(['chunks' => [1, 2]]);
        $data        = new SplitMergeProjectData(1);
        $data->splitResult = $splitResult;

        $pManager = $this->createMock(JobSplitMergeManager::class);
        $pManager->expects(self::once())
            ->method('applySplit')
            ->with($data);

        $this->controller->fakeProjectData = [
            'data'       => $data,
            'pManager'   => $pManager,
            'count_type' => 'eq_word_count',
            'project'    => $projectStub,
        ];

        $this->stubRequestParams([
            'project_id'   => '1',
            'project_pass' => 'pp',
            'job_id'       => '99',
            'job_pass'     => 'jp',
            'num_split'    => '2',
        ]);

        $this->responseMock = $this->createMock(Response::class);
        $this->responseMock->expects(self::once())
            ->method('json')
            ->with(['data' => $splitResult]);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->responseMock);

        $this->controller->apply();
    }

    #[Test]
    public function merge_throws_when_job_not_found(): void
    {
        $job = $this->makeJobStub(10, 'pw', false);

        $projectStub = $this->createStub(ProjectStruct::class);
        $projectStub->method('getJobs')->willReturn([$job]);

        $data     = new SplitMergeProjectData(1);
        $pManager = $this->createStub(JobSplitMergeManager::class);

        $this->controller->fakeProjectData = [
            'data'       => $data,
            'pManager'   => $pManager,
            'count_type' => 'eq_word_count',
            'project'    => $projectStub,
        ];

        $this->stubRequestParams([
            'project_id'   => '1',
            'project_pass' => 'pp',
            'job_id'       => '999',
            'job_pass'     => 'pw',
        ]);

        $this->expectException(AuthenticationError::class);
        $this->controller->merge();
    }

    #[Test]
    public function check_throws_when_split_access_denied(): void
    {
        $job = $this->makeJobStub(99, 'correct_pw', false);

        $projectStub = $this->createStub(ProjectStruct::class);
        $projectStub->method('getJobs')->willReturn([$job]);

        $data     = new SplitMergeProjectData(1);
        $pManager = $this->createStub(JobSplitMergeManager::class);

        $this->controller->fakeProjectData = [
            'data'       => $data,
            'pManager'   => $pManager,
            'count_type' => 'eq_word_count',
            'project'    => $projectStub,
        ];

        $this->stubRequestParams([
            'project_id'   => '1',
            'project_pass' => 'pp',
            'job_id'       => '99',
            'job_pass'     => 'wrong_pw',
            'num_split'    => '2',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-10);
        $this->controller->check();
    }

    /**
     * @param array<string, mixed> $params
     */
    private function stubRequestParams(array $params): void
    {
        $this->requestStub
            ->method('param')
            ->willReturnCallback(static fn (string $key) => $params[$key] ?? null);
    }

    private function callPrivate(string $method, mixed ...$args): mixed
    {
        $m = $this->reflector->getMethod($method);

        return $m->invoke($this->controller, ...$args);
    }

    private function makeJobStub(int $id, string $password, bool $deleted): JobStruct
    {
        $job = $this->createStub(JobStruct::class);
        $job->id       = $id;
        $job->password = $password;
        $job->method('isDeleted')->willReturn($deleted);

        return $job;
    }
}
