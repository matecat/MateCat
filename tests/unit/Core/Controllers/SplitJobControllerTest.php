<?php

namespace Matecat\Core\Controllers;

use ArrayObject;
use Controller\API\App\SplitJobController as AppSplitJobController;
use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\V2\SplitJobController;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\Exceptions\NotFoundException;
use Model\Jobs\JobStruct;
use Model\JobSplitMerge\JobSplitMergeManager;
use Model\JobSplitMerge\SplitMergeProjectData;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use Model\Users\UserStruct;

class TestableSplitJobController extends SplitJobController
{
    /** @var array{data: SplitMergeProjectData, pManager: JobSplitMergeManager, count_type: string, project: ProjectStruct}|null */
    public ?array $fakeProjectData = null;

    /** @var JobStruct[]|null */
    public ?array $fakeJobs = null;

    public function __construct()
    {
    }

    protected function getProjectData(int $project_id, string $project_pass, bool $split_raw_words = false): array
    {
        if ($this->fakeProjectData === null) {
            throw new \RuntimeException('fakeProjectData not configured');
        }

        return $this->fakeProjectData;
    }

    protected function getProjectJobs(ProjectStruct $project): array
    {
        if ($this->fakeJobs === null) {
            throw new \RuntimeException('fakeJobs not configured');
        }

        return $this->fakeJobs;
    }
}

class SplitJobControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    /**
     * Reserved ID block (Playbook §4): base = 9_069_000.
     * 9069001 project, 9069002 job, 9069003 segment, 9069004 file.
     */
    private const int REAL_DB_BASE = 9_069_000;

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

    /**
     * The conversion itself, isolated: filterJobsById() raises AuthenticationError (401) for the split
     * endpoints, and checkMergeAccess() must turn that into a 404 for merge. Without this the retired
     * JobMergeController's 404 would have silently become a 401 for its api-key callers.
     */
    #[Test]
    public function checkMergeAccess_converts_access_error_into_not_found(): void
    {
        $job = $this->makeJobStub(5, 'pw', false);

        $this->expectException(NotFoundException::class);
        $this->callPrivate('checkMergeAccess', 4321, [$job]);
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

        $project = $this->makeOwnedProject();

        // splitResult is seeded here only to prove merge() ignores it: nothing on the merge path sets
        // it, so echoing it back — as this endpoint used to — could only ever emit null.
        $data        = new SplitMergeProjectData(1);
        $data->splitResult = new ArrayObject(['chunks' => [1, 2]]);

        $pManager = $this->createMock(JobSplitMergeManager::class);
        $pManager->expects(self::once())
            ->method('mergeALL')
            ->with($data, [$job]);

        $this->controller->fakeJobs = [$job];
        $this->controller->fakeProjectData = [
            'data'       => $data,
            'pManager'   => $pManager,
            'count_type' => 'eq_word_count',
            'project'    => $project,
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
            ->with(['success' => true]);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->responseMock);

        $this->controller->merge();

        self::assertSame(99, $data->jobToMerge);
    }

    #[Test]
    public function check_returns_split_data_as_json(): void
    {
        $job = $this->makeJobStub(99, 'jp', false);

        $project = $this->makeOwnedProject();

        $splitResult = new ArrayObject(['chunks' => [1, 2, 3]]);
        $data        = new SplitMergeProjectData(1);
        $data->splitResult = $splitResult;

        $pManager = $this->createStub(JobSplitMergeManager::class);

        $this->controller->fakeJobs = [$job];
        $this->controller->fakeProjectData = [
            'data'       => $data,
            'pManager'   => $pManager,
            'count_type' => 'eq_word_count',
            'project'    => $project,
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

        $project = $this->makeOwnedProject();

        $splitResult = new ArrayObject(['chunks' => [1, 2]]);
        $data        = new SplitMergeProjectData(1);
        $data->splitResult = $splitResult;

        $pManager = $this->createMock(JobSplitMergeManager::class);
        $pManager->expects(self::once())
            ->method('applySplit')
            ->with($data);

        $this->controller->fakeJobs = [$job];
        $this->controller->fakeProjectData = [
            'data'       => $data,
            'pManager'   => $pManager,
            'count_type' => 'eq_word_count',
            'project'    => $project,
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

    /**
     * 404, not the 401 the split endpoints raise for the same condition. checkMergeAccess() converts
     * it deliberately, so that retiring JobMergeController — which answered 404 here through its
     * ProjectPasswordValidator — left the status unchanged for this route's api-key callers.
     */
    #[Test]
    public function merge_throws_when_job_not_found(): void
    {
        $job = $this->makeJobStub(10, 'pw', false);

        $project = $this->makeOwnedProject();

        $data     = new SplitMergeProjectData(1);
        $pManager = $this->createStub(JobSplitMergeManager::class);

        $this->controller->fakeJobs = [$job];
        $this->controller->fakeProjectData = [
            'data'       => $data,
            'pManager'   => $pManager,
            'count_type' => 'eq_word_count',
            'project'    => $project,
        ];

        $this->stubRequestParams([
            'project_id'   => '1',
            'project_pass' => 'pp',
            'job_id'       => '999',
            'job_pass'     => 'pw',
        ]);

        $this->expectException(NotFoundException::class);
        $this->controller->merge();
    }

    #[Test]
    public function check_throws_when_split_access_denied(): void
    {
        $job = $this->makeJobStub(99, 'correct_pw', false);

        $project = $this->makeOwnedProject();

        $data     = new SplitMergeProjectData(1);
        $pManager = $this->createStub(JobSplitMergeManager::class);

        $this->controller->fakeJobs = [$job];
        $this->controller->fakeProjectData = [
            'data'       => $data,
            'pManager'   => $pManager,
            'count_type' => 'eq_word_count',
            'project'    => $project,
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

    #[Test]
    public function registerValidators_appends_login_validator(): void
    {
        $this->callPrivate('registerValidators');

        $validators = $this->reflector->getProperty('validators')->getValue($this->controller);

        self::assertCount(1, $validators);
        self::assertInstanceOf(LoginValidator::class, $validators[0]);
    }

    #[Test]
    public function getProjectData_and_getProjectJobs_return_real_seeded_state(): void
    {
        $base        = self::REAL_DB_BASE;
        $owner       = $this->ownerEmail($base);
        $projectPass = 'projpw';
        $jobPass     = 'jobpw';

        $this->cleanFragments($base);
        $this->seedProject($base, $owner, $projectPass);
        $this->seedFile($base);
        $this->seedSegment($base);
        $this->seedJob($base, $owner, $jobPass);

        try {
            $this->reflector->getProperty('database')->setValue($this->controller, obtainTestDatabase());
            // getProjectData() builds a JobSplitMergeManager, which now requires the acting user.
            $this->reflector->getProperty('user')->setValue($this->controller, new UserStruct(['uid' => 987, 'email' => 'actor@example.org']));

            $projectId = $this->projectId($base);

            $projectStructure = $this->callPrivate('getProjectData', $projectId, $projectPass, false);

            self::assertInstanceOf(SplitMergeProjectData::class, $projectStructure['data']);
            self::assertSame($projectId, $projectStructure['data']->idProject);
            self::assertInstanceOf(JobSplitMergeManager::class, $projectStructure['pManager']);
            self::assertSame('eq_word_count', $projectStructure['count_type']);
            self::assertInstanceOf(ProjectStruct::class, $projectStructure['project']);
            self::assertSame($projectId, (int)$projectStructure['project']->id);

            $jobs = $this->callPrivate('getProjectJobs', $projectStructure['project']);

            self::assertCount(1, $jobs);
            self::assertSame($this->jobId($base), $jobs[0]->id);
        } finally {
            $this->cleanFragments($base);
        }
    }

    // ─── who may restructure a project ───────────────────────────────

    /**
     * The project password proves knowledge of the project, not standing over it. Before this check a
     * removed team member who had kept a manage URL could still split, merge and re-split every job in
     * the project, and so could any authenticated identity that came by the pair some other way.
     */
    #[Test]
    public function merge_refusesACallerWhoIsNeitherOwnerNorTeamMember(): void
    {
        $base = self::REAL_DB_BASE + 100;

        try {
            $this->seedRestructureScope($base, member: false);
            $this->stubRestructureRequest($base);

            $this->expectException(AuthorizationError::class);
            $this->controller->merge();
        } finally {
            $this->cleanFragments($base);
        }
    }

    #[Test]
    public function check_refusesACallerWhoIsNeitherOwnerNorTeamMember(): void
    {
        $base = self::REAL_DB_BASE + 200;

        try {
            $this->seedRestructureScope($base, member: false);
            $this->stubRestructureRequest($base);

            $this->expectException(AuthorizationError::class);
            $this->controller->check();
        } finally {
            $this->cleanFragments($base);
        }
    }

    #[Test]
    public function apply_refusesACallerWhoIsNeitherOwnerNorTeamMember(): void
    {
        $base = self::REAL_DB_BASE + 300;

        try {
            $this->seedRestructureScope($base, member: false);
            $this->stubRestructureRequest($base);

            $this->expectException(AuthorizationError::class);
            $this->controller->apply();
        } finally {
            $this->cleanFragments($base);
        }
    }

    /**
     * A team member who does not own the project may restructure it: the membership is the standing.
     */
    #[Test]
    public function aTeamMemberWhoIsNotTheOwnerMayRestructure(): void
    {
        $base = self::REAL_DB_BASE + 400;

        try {
            $project = $this->seedRestructureScope($base, member: true);

            $this->callPrivate('enforceRestructureAccess', $project);
            self::assertNotSame($project->id_customer, $this->controller->getUser()->email);
        } finally {
            $this->cleanFragments($base);
        }
    }

    /**
     * The owner is allowed explicitly rather than left to the membership lookup. A project outlives the
     * owner's membership of its team — moved to another team, or the owner removed from the one it is in
     * — and on the development dataset 1 project of 1205 is already in that state, plus 9 carrying no
     * team at all. Membership alone would take those away from the person who created them.
     */
    #[Test]
    public function theOwnerMayRestructureWithoutBelongingToTheProjectTeam(): void
    {
        $base = self::REAL_DB_BASE + 500;

        try {
            $project = $this->seedRestructureScope($base, member: false);
            $project->id_customer = $this->controller->getUser()->email ?? '';

            $this->callPrivate('enforceRestructureAccess', $project);
            self::assertTrue(true, 'no AuthorizationError for the owner');
        } finally {
            $this->cleanFragments($base);
        }
    }

    #[Test]
    public function theOwnerMayRestructureAProjectThatCarriesNoTeam(): void
    {
        $base = self::REAL_DB_BASE + 600;

        try {
            $project = $this->seedRestructureScope($base, member: false);
            $project->id_customer = $this->controller->getUser()->email ?? '';
            $project->id_team     = null;

            $this->callPrivate('enforceRestructureAccess', $project);
            self::assertTrue(true, 'no AuthorizationError for the owner of a team-less project');
        } finally {
            $this->cleanFragments($base);
        }
    }

    /**
     * A project created by an unauthenticated caller carries id_customer = '' (CreateProjectController),
     * so the owner branch must not treat an empty owner as a match for an equally empty caller identity.
     *
     * Defence in depth rather than a reachable state: isLogged() requires a non-empty email, so a caller
     * that got past LoginValidator has one. The emptiness check costs a comparison and removes the need
     * to keep believing that.
     */
    #[Test]
    public function anEmptyProjectOwnerMatchesAnEmptyCallerIdentity(): void
    {
        $base = self::REAL_DB_BASE + 700;

        try {
            $project = $this->seedRestructureScope($base, member: false);
            $project->id_customer = '';

            $this->reflector->getProperty('user')->setValue(
                $this->controller,
                new UserStruct(['uid' => $this->userId($base), 'email' => ''])
            );

            $this->expectException(AuthorizationError::class);
            $this->callPrivate('enforceRestructureAccess', $project);
        } finally {
            $this->cleanFragments($base);
        }
    }

    /**
     * A project the acting user owns, so the restructure authorization clears without touching a
     * database and the test stays about its own subject.
     */
    private function makeOwnedProject(): ProjectStruct
    {
        $email = 'ctrlowner@example.org';

        $this->reflector->getProperty('user')->setValue(
            $this->controller,
            new UserStruct(['uid' => 987, 'email' => $email])
        );
        $this->reflector->getProperty('userIsLogged')->setValue($this->controller, true);

        $project              = new ProjectStruct();
        $project->id          = 1;
        $project->id_customer = $email;

        return $project;
    }

    /**
     * Seed a real team, user and membership row, point the controller at the test database and act as
     * that user. Returns the project the restructure is about — owned by somebody else, so the caller's
     * standing rests on the membership alone unless a test overrides id_customer.
     */
    private function seedRestructureScope(int $base, bool $member): ProjectStruct
    {
        $this->seedUser($base);
        $this->seedTeam($base);

        if ($member) {
            $this->seedMembership($base);
        }

        $this->reflector->getProperty('database')->setValue($this->controller, obtainTestDatabase());
        $this->reflector->getProperty('user')->setValue(
            $this->controller,
            new UserStruct(['uid' => $this->userId($base), 'email' => 'ctrluser_' . $base . '@example.org'])
        );
        $this->reflector->getProperty('userIsLogged')->setValue($this->controller, true);

        $project              = new ProjectStruct();
        $project->id          = $this->projectId($base);
        $project->id_customer = 'someone_else_' . $base . '@example.org';
        $project->id_team     = $this->teamId($base);

        return $project;
    }

    /**
     * A merge/check/apply request whose project and job resolve, so the only thing that can refuse it is
     * the authorization check.
     */
    private function stubRestructureRequest(int $base): void
    {
        $job = $this->makeJobStub(99, 'jp', false);

        $data = new SplitMergeProjectData($this->projectId($base));

        $this->controller->fakeJobs        = [$job];
        $this->controller->fakeProjectData = [
            'data'       => $data,
            'pManager'   => $this->createStub(JobSplitMergeManager::class),
            'count_type' => 'eq_word_count',
            'project'    => $this->seedRestructureProject($base),
        ];

        $this->stubRequestParams([
            'project_id'   => (string)$this->projectId($base),
            'project_pass' => 'pp',
            'job_id'       => '99',
            'job_pass'     => 'jp',
            'num_split'    => '2',
        ]);
    }

    private function seedRestructureProject(int $base): ProjectStruct
    {
        $project              = new ProjectStruct();
        $project->id          = $this->projectId($base);
        $project->id_customer = 'someone_else_' . $base . '@example.org';
        $project->id_team     = $this->teamId($base);

        return $project;
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

    // ─── the App subclass ────────────────────────────────────────────

    /**
     * The App subclass exists so the routes serving the UI can reach a real session, and handing that
     * store to the cart is its only behaviour. Covered from this file rather than one of its own
     * because it is a single override on the class these tests already exercise.
     */
    #[Test]
    public function app_subclass_hands_its_own_session_store_to_the_cart(): void
    {
        $controller = (new ReflectionClass(AppSplitJobController::class))->newInstanceWithoutConstructor();
        $store      = $this->injectSessionStore($controller);

        $method = new ReflectionMethod($controller, 'outsourceCartStore');

        self::assertSame($store, $method->invoke($controller));
    }

    /**
     * The other half of the pair, and the reason the override is not redundant: the v2/v3 controller is
     * stateless, so its store refuses every operation and constructing a Cart over it would throw.
     * Returning null is what keeps the cart invalidation inert on the api-key routes.
     */
    #[Test]
    public function stateless_controller_reports_no_cart_store(): void
    {
        $controller = new TestableSplitJobController();

        $method = new ReflectionMethod($controller, 'outsourceCartStore');

        self::assertNull($method->invoke($controller));
    }
}
