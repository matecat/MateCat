<?php

namespace Matecat\Core\Controllers;

use Controller\API\Commons\Validators\JobPasswordValidator;
use Controller\API\V2\ReviseTranslationIssuesController;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Utils\Logger\MatecatLogger;

class TestableReviseTranslationIssuesController extends ReviseTranslationIssuesController
{
    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }

    protected function registerValidators(): void
    {
    }
}

/**
 * Real-DB suite for {@see ReviseTranslationIssuesController}.
 *
 * Reserved ID block base = 9_044_000 (task N=44, Playbook §4):
 *   project 9044001, job 9044002, segment 9044003, file 9044004.
 * Clean ONLY by reserved id; owner email ctrltest_9044000@example.org.
 */
#[AllowMockObjectsWithoutExpectations]
class ReviseTranslationIssuesControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_044_000;
    private const string JOB_PASSWORD = 'jobpw';

    /** @var ReflectionClass<ReviseTranslationIssuesController> */
    private ReflectionClass $reflector;
    private TestableReviseTranslationIssuesController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws ReflectionException
     * @throws \Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);
        $this->seedFixtures();

        $this->controller = new TestableReviseTranslationIssuesController();
        $this->reflector = new ReflectionClass(ReviseTranslationIssuesController::class);

        $this->requestStub = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->responseMock);
        $this->reflector->getProperty('database')->setValue($this->controller, obtainTestDatabase());
        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createMock(MatecatLogger::class));
        $this->reflector->getProperty('featureSet')->setValue($this->controller, new FeatureSet(obtainTestDatabase()));
    }

    protected function tearDown(): void
    {
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    private function seedFixtures(): void
    {
        $owner = $this->ownerEmail(self::BASE);
        $this->seedProject(self::BASE, $owner);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $owner, self::JOB_PASSWORD);
        $this->seedSegment(self::BASE);
        $this->seedSegmentTranslation(self::BASE);
    }

    /**
     * @param array<string, string> $params
     *
     * @throws ReflectionException
     */
    private function setRequestParams(array $params): void
    {
        $serverParams = ['REQUEST_URI' => '/api/v2/jobs/revise-issues', 'REQUEST_METHOD' => 'GET'];
        $this->requestStub = new Request($params, [], [], $serverParams);
        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
        // index() also reads $this->params['id_job'] / ['password'] (merged at dispatch time)
        $this->controller->params = $params;
    }

    // ─── index() happy path ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function index_returns_json_with_versions_key_for_valid_job_and_segment(): void
    {
        $this->setRequestParams([
            'id_job' => (string) $this->jobId(self::BASE),
            'id_segment' => (string) $this->segmentId(self::BASE),
            'password' => self::JOB_PASSWORD,
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->index();

        $this->assertIsArray($captured);
        $this->assertArrayHasKey('versions', $captured);
        $this->assertIsArray($captured['versions']);
        // One seeded segment_translation (current version) → exactly one rendered version.
        $this->assertCount(1, $captured['versions']);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function index_rendered_version_carries_seeded_translation_and_segment_id(): void
    {
        $this->setRequestParams([
            'id_job' => (string) $this->jobId(self::BASE),
            'id_segment' => (string) $this->segmentId(self::BASE),
            'password' => self::JOB_PASSWORD,
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->index();

        $this->assertIsArray($captured);
        $this->assertArrayHasKey('versions', $captured);
        $version = $captured['versions'][0];
        $this->assertSame($this->segmentId(self::BASE), (int) $version['id_segment']);
        $this->assertArrayHasKey('issues', $version);
    }

    // ─── index() failure path ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function index_throws_not_found_for_wrong_password(): void
    {
        $this->setRequestParams([
            'id_job' => (string) $this->jobId(self::BASE),
            'id_segment' => (string) $this->segmentId(self::BASE),
            'password' => 'wrong_password_xyz_999',
        ]);

        $this->expectException(NotFoundException::class);

        $this->controller->index();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function index_throws_not_found_for_nonexistent_job(): void
    {
        $this->setRequestParams([
            'id_job' => '90449999',
            'id_segment' => (string) $this->segmentId(self::BASE),
            'password' => self::JOB_PASSWORD,
        ]);

        $this->expectException(NotFoundException::class);

        $this->controller->index();
    }

    // ─── registerValidators() — append the three expected validators ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function registerValidators_appends_login_job_password_and_segment_validators(): void
    {
        // Build a REAL controller (not the Testable) so the production
        // registerValidators() body runs; the validator onSuccess closure is
        // only registered here, executed later by the dispatch chain.
        $real = (new ReflectionClass(ReviseTranslationIssuesController::class))
            ->newInstanceWithoutConstructor();

        $reqProp = $this->reflector->getProperty('request');
        $reqProp->setValue($real, new Request());
        $this->reflector->getProperty('featureSet')->setValue($real, new FeatureSet(obtainTestDatabase()));

        $method = $this->reflector->getMethod('registerValidators');
        $method->invoke($real);

        $validatorsProp = $this->reflector->getProperty('validators');
        $validators = $validatorsProp->getValue($real);

        $this->assertIsArray($validators);
        $this->assertCount(3, $validators);
        $this->assertInstanceOf(\Controller\API\Commons\Validators\LoginValidator::class, $validators[0]);
        $this->assertInstanceOf(\Controller\API\Commons\Validators\JobPasswordValidator::class, $validators[1]);
        $this->assertInstanceOf(\Controller\API\Commons\Validators\SegmentTranslation::class, $validators[2]);
    }

    // ─── registerValidators() — JobPasswordValidator onSuccess closure ───

    /**
     * Drives the production onSuccess closure registered on the
     * JobPasswordValidator (ReviseTranslationIssuesController lines 32-34):
     * on successful password validation it must load the featureSet for the
     * job's project and stash the validated job on $this->chunk.
     *
     * @throws \Throwable
     * @throws ReflectionException
     */
    #[Test]
    public function jobPasswordValidator_onSuccess_closure_sets_chunk_and_loads_featureset(): void
    {
        // Build a REAL controller (not the Testable) so the production
        // registerValidators() body runs and the onSuccess closure under
        // test is actually registered on the JobPasswordValidator.
        $real = (new ReflectionClass(ReviseTranslationIssuesController::class))
            ->newInstanceWithoutConstructor();

        $this->reflector->getProperty('request')->setValue($real, new Request());
        $this->reflector->getProperty('database')->setValue($real, obtainTestDatabase());

        $expectedProjectId = $this->projectId(self::BASE);

        $featureSetMock = $this->createMock(FeatureSet::class);
        $featureSetMock->expects($this->once())
            ->method('loadForProject')
            ->with($this->callback(function ($project) use ($expectedProjectId): bool {
                return $project instanceof ProjectStruct && (int) $project->id === $expectedProjectId;
            }));
        $this->reflector->getProperty('featureSet')->setValue($real, $featureSetMock);

        // JobPasswordValidator::_validate() reads $this->controller->params.
        $real->params = [
            'id_job' => (string) $this->jobId(self::BASE),
            'password' => self::JOB_PASSWORD,
        ];

        $this->reflector->getMethod('registerValidators')->invoke($real);

        $validators = $this->reflector->getProperty('validators')->getValue($real);
        $jobValidator = $validators[1];
        $this->assertInstanceOf(JobPasswordValidator::class, $jobValidator);

        // Drives _validate() (real DB lookup against the seeded job) then
        // _executeCallbacks(), which runs the onSuccess closure under test.
        $jobValidator->validate();

        $chunk = $this->reflector->getProperty('chunk')->getValue($real);
        $this->assertInstanceOf(JobStruct::class, $chunk);
        $this->assertSame($this->jobId(self::BASE), $chunk->id);
    }
}
