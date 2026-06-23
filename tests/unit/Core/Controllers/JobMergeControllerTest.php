<?php

namespace Matecat\Core\Controllers;

use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Validators\Base;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use Controller\API\V2\JobMergeController;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Utils\Logger\MatecatLogger;

class TestableJobMergeController extends JobMergeController
{
    public function __construct()
    {
    }

    public function callRegisterValidators(): void
    {
        $this->registerValidators();
    }

    /**
     * @return Base[]
     */
    public function getRegisteredValidators(): array
    {
        return $this->validators;
    }
}

#[AllowMockObjectsWithoutExpectations]
class JobMergeControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_045_000;
    private const string PROJECT_PASSWORD = 'jobmergeprojpw';
    private const string JOB_PASSWORD = 'jobmergejobpw';

    /** @var ReflectionClass<JobMergeController> */
    private ReflectionClass $reflector;
    private TestableJobMergeController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;
    private string $owner;

    /**
     * @throws \Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->ownerEmail(self::BASE);

        $this->cleanFragments(self::BASE);
        $this->seedProject(self::BASE, $this->owner, self::PROJECT_PASSWORD);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $this->owner, self::JOB_PASSWORD);
        $this->seedSegment(self::BASE);
        $this->seedSegmentTranslation(self::BASE);
        $this->seedWordCountFixtures();

        $this->controller = new TestableJobMergeController();
        $this->reflector  = new ReflectionClass(JobMergeController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);
        $this->setProp('database', \Model\DataAccess\Database::obtain());

        $user        = new UserStruct();
        $user->uid   = 1;
        $user->email = $this->owner;
        $this->setProp('user', $user);

        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));
    }

    /**
     * Add the files_job link row and a non-null equivalent word count so the
     * merge word-count recomputation (CounterModel::getStatsForJob) returns
     * numeric aggregates instead of NULL.
     *
     * @throws \PDOException
     */
    private function seedWordCountFixtures(): void
    {
        $conn   = $this->seedConnection();
        $jobId  = $this->jobId(self::BASE);
        $fileId = $this->fileId(self::BASE);
        $segId  = $this->segmentId(self::BASE);

        $conn->exec("INSERT IGNORE INTO files_job (id_job, id_file) VALUES ($jobId, $fileId)");
        $conn->exec("UPDATE segment_translations SET eq_word_count = 2 WHERE id_segment = $segId AND id_job = $jobId");
    }

    /**
     * @throws \PDOException
     */
    protected function tearDown(): void
    {
        $this->seedConnection()->exec("DELETE FROM files_job WHERE id_job = " . $this->jobId(self::BASE));
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    /**
     * @throws ReflectionException
     */
    private function setProp(string $name, mixed $value): void
    {
        $prop = $this->reflector->getProperty($name);
        $prop->setValue($this->controller, $value);
    }

    /**
     * @throws ReflectionException
     */
    private function getProp(string $name): mixed
    {
        $prop = $this->reflector->getProperty($name);

        return $prop->getValue($this->controller);
    }

    /**
     * Build a Request carrying the given GET params and bind it to the controller.
     *
     * @param array<string, string> $params
     *
     * @throws ReflectionException
     */
    private function bindRequest(array $params): void
    {
        $serverParams      = ['REQUEST_URI' => '/api/v2/jobs/merge', 'REQUEST_METHOD' => 'POST'];
        $this->requestStub = new Request($params, [], [], $serverParams);
        $this->setProp('request', $this->requestStub);
    }

    // ─── registerValidators ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function registerValidators_appends_login_and_project_password_validators(): void
    {
        $this->controller->params = [
            'id_project' => (string) $this->projectId(self::BASE),
            'password'   => self::PROJECT_PASSWORD,
        ];
        $this->bindRequest(['id_job' => (string) $this->jobId(self::BASE)]);

        $this->controller->callRegisterValidators();

        $validators = $this->controller->getRegisteredValidators();

        $this->assertCount(2, $validators);
        $this->assertInstanceOf(LoginValidator::class, $validators[0]);
        $this->assertInstanceOf(ProjectPasswordValidator::class, $validators[1]);
    }

    // ─── ProjectPasswordValidator onSuccess closure ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function onSuccess_closure_populates_project_and_job_list_for_valid_job(): void
    {
        $this->controller->params = [
            'id_project' => (string) $this->projectId(self::BASE),
            'password'   => self::PROJECT_PASSWORD,
        ];
        $this->bindRequest(['id_job' => (string) $this->jobId(self::BASE)]);

        $this->controller->callRegisterValidators();
        $validators = $this->controller->getRegisteredValidators();

        // index 1 is the ProjectPasswordValidator; validate() runs _validate + onSuccess closure
        $validators[1]->validate();

        $project = $this->getProp('project');
        $jobList = $this->getProp('jobList');

        $this->assertInstanceOf(ProjectStruct::class, $project);
        $this->assertSame($this->projectId(self::BASE), (int) $project->id);
        $this->assertIsArray($jobList);
        $this->assertNotEmpty($jobList);
        $this->assertSame($this->jobId(self::BASE), (int) $jobList[0]->id);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function onSuccess_closure_throws_not_found_when_job_does_not_exist(): void
    {
        $this->controller->params = [
            'id_project' => (string) $this->projectId(self::BASE),
            'password'   => self::PROJECT_PASSWORD,
        ];
        $this->bindRequest(['id_job' => '99999999']);

        $this->controller->callRegisterValidators();
        $validators = $this->controller->getRegisteredValidators();

        $this->expectException(NotFoundException::class);

        $validators[1]->validate();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function onSuccess_closure_throws_not_found_when_job_belongs_to_other_project(): void
    {
        // Seed a foreign job (different project) inside the reserved block.
        $foreignJobId     = self::BASE + 102;
        $foreignProjectId = self::BASE + 101;
        $conn             = $this->seedConnection();
        $conn->exec(
            "INSERT IGNORE INTO projects (id, id_customer, password, name, create_date, status_analysis) "
            . "VALUES ($foreignProjectId, '$this->owner', 'otherpw', 'CtrlForeign', NOW(), 'DONE')"
        );
        $conn->exec(
            "INSERT IGNORE INTO jobs (id, password, id_project, source, target, job_first_segment, job_last_segment, owner, tm_keys, create_date, disabled, status) "
            . "VALUES ($foreignJobId, 'fjobpw', $foreignProjectId, 'en-US', 'it-IT', 1, 1, '$this->owner', '[]', NOW(), 0, 'active')"
        );

        try {
            $this->controller->params = [
                'id_project' => (string) $this->projectId(self::BASE),
                'password'   => self::PROJECT_PASSWORD,
            ];
            $this->bindRequest(['id_job' => (string) $foreignJobId]);

            $this->controller->callRegisterValidators();
            $validators = $this->controller->getRegisteredValidators();

            $this->expectException(NotFoundException::class);

            $validators[1]->validate();
        } finally {
            $conn->exec("DELETE FROM jobs WHERE id = $foreignJobId");
            $conn->exec("DELETE FROM projects WHERE id = $foreignProjectId");
        }
    }

    // ─── merge() public action ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function merge_returns_success_payload_and_sets_http_200(): void
    {
        $project = (new \Model\Projects\ProjectDao())->findByIdAndPassword(
            $this->projectId(self::BASE),
            self::PROJECT_PASSWORD
        );
        $jobList = (new \Model\Jobs\JobDao($this->controller->getDatabase()))
            ->getNotDeletedById($this->jobId(self::BASE));

        $this->setProp('project', $project);
        $this->setProp('jobList', $jobList);

        $this->bindRequest(['id_job' => (string) $this->jobId(self::BASE)]);

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(200);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('success', $data);
                $this->assertTrue($data['success']);

                return true;
            }));

        $this->controller->merge();
    }
}
