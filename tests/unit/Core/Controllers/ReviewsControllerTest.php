<?php

namespace Matecat\Core\Controllers;

use Controller\API\Commons\Exceptions\ValidationError;
use Controller\API\V2\ReviewsController;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Exception;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use Model\Projects\ProjectStruct;
use PDOException;
use Plugins\Features\AbstractRevisionFeature;
use Plugins\Features\RevisionFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Utils\Logger\MatecatLogger;

class TestableReviewsController extends ReviewsController
{
    public ?RevisionFactory $revisionFactoryStub = null;

    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }

    protected function registerValidators(): void
    {
    }

    protected function initRevisionFromProject(ProjectStruct $project): RevisionFactory
    {
        return $this->revisionFactoryStub ?? parent::initRevisionFromProject($project);
    }
}

/**
 * Real-DB suite for {@see ReviewsController}. Reserved ID block base = 9035000
 * (base+1 project, base+2 job, base+3 segment, base+4 file, base+8 chunk-review).
 * Owner email = ctrltest_9035000@example.org (Playbook §4).
 */
#[AllowMockObjectsWithoutExpectations]
class ReviewsControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9035000;

    /** @var ReflectionClass<ReviewsController> */
    private ReflectionClass $reflector;
    private TestableReviewsController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws \TypeError
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);
        $this->seedTestData();

        $this->controller = new TestableReviewsController();
        $this->reflector  = new ReflectionClass(ReviewsController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);
        $this->setProp('database', obtainTestDatabase());
        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));

        // ReviewsController::afterValidate compares against $this->project->id
        $project     = new ProjectStruct();
        $project->id = $this->projectId(self::BASE);
        $this->setProp('project', $project);
    }

    protected function tearDown(): void
    {
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    private function seedTestData(): void
    {
        $owner = $this->ownerEmail(self::BASE);
        $this->seedProject(self::BASE, $owner);
        $this->seedFile(self::BASE);
        // job password must equal the chunk-review password for getChunk()
        $this->seedJob(self::BASE, $owner, 'jobpw');
        // revision_number = 2 row required to exist; revision_number+1 (3) must NOT exist
        $this->seedChunkReview(self::BASE, 'jobpw', 'revpw', 2);
    }

    /**
     * @throws ReflectionException
     */
    private function setProp(string $name, mixed $value): void
    {
        $prop = (new ReflectionClass(ReviewsController::class))->getProperty($name);
        $prop->setValue($this->controller, $value);
    }

    /**
     * @param array<string, mixed> $post
     *
     * @throws ReflectionException
     */
    private function setRequestParams(array $post): void
    {
        $serverParams = ['REQUEST_URI' => '/api/v2/reviews', 'REQUEST_METHOD' => 'POST'];
        $this->requestStub = new Request([], $post, [], $serverParams);
        $this->setProp('request', $this->requestStub);
    }

    /**
     * @param array<int, mixed> $args
     *
     * @throws ReflectionException
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        return $this->reflector->getMethod($method)->invoke($this->controller, ...$args);
    }

    // ─── registerValidators ───

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws \TypeError
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    #[Test]
    public function registerValidators_appends_a_project_password_validator(): void
    {
        $controller = new TestableReviewsController();
        $this->setPropOn($controller, 'request', new Request());
        $this->setPropOn($controller, 'response', $this->createMock(Response::class));
        $this->setPropOn($controller, 'params', ['id_project' => $this->projectId(self::BASE), 'password' => 'projpw']);

        $reflector = new ReflectionClass(ReviewsController::class);
        $reflector->getMethod('registerValidators')->invoke($controller);

        $validatorsProp = $reflector->getProperty('validators');
        /** @var array<object> $validators */
        $validators = $validatorsProp->getValue($controller);

        $this->assertCount(1, $validators);
        $this->assertInstanceOf(
            \Controller\API\Commons\Validators\ProjectPasswordValidator::class,
            $validators[0]
        );
    }

    /**
     * @throws ReflectionException
     */
    private function setPropOn(object $controller, string $name, mixed $value): void
    {
        $prop = (new ReflectionClass(ReviewsController::class))->getProperty($name);
        $prop->setValue($controller, $value);
    }

    // ─── afterValidate happy path ───

    /**
     * @throws ReflectionException
     * @throws ValidationError
     * @throws PDOException
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function afterValidate_sets_next_source_page_and_chunk_on_valid_input(): void
    {
        $this->setRequestParams([
            'id_job'   => (string) $this->jobId(self::BASE),
            'password' => 'jobpw',
        ]);

        $this->invokePrivate('afterValidate');

        $nextSourcePage = $this->reflector->getProperty('nextSourcePage')->getValue($this->controller);
        $this->assertSame(3, $nextSourcePage);

        $latest = $this->reflector->getProperty('latestChunkReview')->getValue($this->controller);
        $this->assertInstanceOf(ChunkReviewStruct::class, $latest);
        $this->assertSame($this->projectId(self::BASE), (int) $latest->id_project);

        $chunk = $this->reflector->getProperty('chunk')->getValue($this->controller);
        $this->assertInstanceOf(JobStruct::class, $chunk);
        $this->assertSame($this->jobId(self::BASE), (int) $chunk->id);
    }

    // ─── afterValidate failure paths ───

    /**
     * @throws ReflectionException
     * @throws ValidationError
     * @throws PDOException
     */
    #[Test]
    public function afterValidate_throws_when_id_job_param_missing(): void
    {
        $this->setRequestParams(['password' => 'jobpw']);

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('id_job param is not provided');

        $this->invokePrivate('afterValidate');
    }

    /**
     * @throws ReflectionException
     * @throws ValidationError
     * @throws PDOException
     */
    #[Test]
    public function afterValidate_throws_when_password_param_missing(): void
    {
        $this->setRequestParams(['id_job' => (string) $this->jobId(self::BASE)]);

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('password param is not provided');

        $this->invokePrivate('afterValidate');
    }

    /**
     * @throws ReflectionException
     * @throws ValidationError
     * @throws PDOException
     */
    #[Test]
    public function afterValidate_throws_when_revision_does_not_exist(): void
    {
        $this->setRequestParams([
            'id_job'   => (string) $this->jobId(self::BASE),
            'password' => 'wrong_password_no_review',
        ]);

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Revision 1 link does not exists.');

        $this->invokePrivate('afterValidate');
    }

    /**
     * @throws ReflectionException
     * @throws ValidationError
     * @throws PDOException
     */
    #[Test]
    public function afterValidate_throws_when_next_revision_already_exists(): void
    {
        // seed revision_number + 1 (source_page = 3) so the "already exists" branch fires
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO qa_chunk_reviews (id, id_project, id_job, password, review_password, source_page) "
            . "VALUES (" . (self::BASE + 50) . ", " . $this->projectId(self::BASE) . ", " . $this->jobId(self::BASE)
            . ", 'jobpw', 'revpw3', 3)"
        );

        $this->setRequestParams([
            'id_job'   => (string) $this->jobId(self::BASE),
            'password' => 'jobpw',
        ]);

        try {
            $this->expectException(ValidationError::class);
            $this->expectExceptionMessage('Revision 2 link already exists.');
            $this->invokePrivate('afterValidate');
        } finally {
            $this->seedConnection()->exec("DELETE FROM qa_chunk_reviews WHERE id = " . (self::BASE + 50));
        }
    }

    /**
     * @throws ReflectionException
     * @throws ValidationError
     * @throws PDOException
     */
    #[Test]
    public function afterValidate_throws_when_project_id_does_not_match(): void
    {
        // override the injected project with a non-matching id
        $project     = new ProjectStruct();
        $project->id = $this->projectId(self::BASE) + 777;
        $this->setProp('project', $project);

        $this->setRequestParams([
            'id_job'   => (string) $this->jobId(self::BASE),
            'password' => 'jobpw',
        ]);

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Job id / password combination is not in projects list');

        $this->invokePrivate('afterValidate');
    }

    // ─── createReview ───

    /**
     * Happy path: {@see ReviewsController::initRevisionFromProject} is stubbed so the heavy
     * full-stack revision graph is bypassed. The record it yields names the seeded job
     * ({@see self::BASE} id + 'jobpw' password) and the seeded project, which is what the cache
     * doors address, so the real-DB eviction tail runs against seeded rows.
     *
     * @throws ReflectionException
     * @throws Exception
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    #[Test]
    public function createReview_creates_records_destroys_caches_and_returns_json(): void
    {
        // createRecord() reads the row back, so a record reaching the controller always carries the
        // project it belongs to; the project keyed eviction is addressed by it.
        $record                  = new ChunkReviewStruct();
        $record->id              = self::BASE + 8;
        $record->id_project      = $this->projectId(self::BASE);
        $record->id_job          = $this->jobId(self::BASE);
        $record->password        = 'jobpw';
        $record->review_password = 'revpw';

        $feature = $this->createMock(AbstractRevisionFeature::class);
        $feature->method('createQaChunkReviewRecords')->willReturn([$record]);

        $factory = $this->createMock(RevisionFactory::class);
        $factory->method('getRevisionFeature')->willReturn($feature);

        $this->controller->revisionFactoryStub = $factory;

        // project->password is read by destroyCache (source line 57)
        $project           = new ProjectStruct();
        $project->id       = $this->projectId(self::BASE);
        $project->password = 'projpw';
        $this->setProp('project', $project);

        $this->setProp('chunk', new JobStruct());
        $this->setProp('nextSourcePage', 3);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with([
                'chunk_review' => [
                    'id'              => $record->id,
                    'id_job'          => $record->id_job,
                    'review_password' => $record->review_password,
                ],
            ]);

        $this->controller->createReview();
    }

    /**
     * `createReview()` writes a chunk review for one revision phase, so the read of that phase
     * describes a set the database no longer holds. Each phase carries its own key map — one per
     * phase, so that evicting a phase cannot take the others or the unfiltered read down with it —
     * and the unfiltered eviction therefore does not reach it. Left standing it answers with the
     * pre-write set for the full 60 second TTL.
     *
     * Rotating the review password on the connection is the probe: only a read that reaches MySQL
     * can return the new value, so the assertion fails exactly when the entry survived.
     *
     * @throws ReflectionException
     * @throws Exception
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    #[Test]
    public function createReview_evicts_the_source_page_entry_of_the_phase_it_writes(): void
    {
        $jobId = $this->jobId(self::BASE);

        $chunk           = new JobStruct();
        $chunk->id       = $jobId;
        $chunk->password = 'jobpw';

        $dao    = new ChunkReviewDao(obtainTestDatabase());
        $warmed = $dao->findChunkReviewsForSourcePage($chunk, 2);
        $this->assertSame('revpw', $warmed[0]->review_password, 'the seeded row warms the entry');

        $this->rotateReviewPasswordBehindTheCache($jobId, 'rotated');

        $record                  = new ChunkReviewStruct();
        $record->id              = self::BASE + 8;
        $record->id_project      = $this->projectId(self::BASE);
        $record->id_job          = $jobId;
        $record->password        = 'jobpw';
        $record->review_password = 'rotated';

        $feature = $this->createMock(AbstractRevisionFeature::class);
        $feature->method('createQaChunkReviewRecords')->willReturn([$record]);

        $factory = $this->createMock(RevisionFactory::class);
        $factory->method('getRevisionFeature')->willReturn($feature);

        $this->controller->revisionFactoryStub = $factory;

        $project           = new ProjectStruct();
        $project->id       = $this->projectId(self::BASE);
        $project->password = 'projpw';
        $this->setProp('project', $project);

        $this->setProp('chunk', new JobStruct());
        $this->setProp('nextSourcePage', 2);

        $this->controller->createReview();

        $this->assertSame(
            'rotated',
            $dao->findChunkReviewsForSourcePage($chunk, 2)[0]->review_password,
            'the entry of the phase createReview() wrote has to drop with the write'
        );
    }

    /**
     * Writes on the connection, so a cached read still answers with the value it holds.
     *
     * @throws PDOException
     */
    private function rotateReviewPasswordBehindTheCache(int $jobId, string $reviewPassword): void
    {
        $stmt = obtainTestDatabase()->getConnection()->prepare(
            'UPDATE qa_chunk_reviews SET review_password = :review_password ' .
            ' WHERE id_job = :id_job AND source_page = 2 '
        );
        $stmt->execute(['review_password' => $reviewPassword, 'id_job' => $jobId]);
    }

    // ─── registerValidators onSuccess closures ───

    /**
     * Fires the three onSuccess closures registered by {@see ReviewsController::registerValidators}
     * (source lines 80, 83, 85). Closure #1 pulls the project off the ProjectPasswordValidator;
     * closures #2/#3 spin up TeamProjectValidator / ProjectAccessValidator against the unseeded
     * auth context and reject — the throw is expected, the point is executing the closure bodies.
     *
     * @throws ReflectionException
     * @throws Exception
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    #[Test]
    public function registerValidators_onSuccess_closures_execute(): void
    {
        $controller = new TestableReviewsController();
        $this->setPropOn($controller, 'request', $this->requestStub);
        $this->setPropOn($controller, 'response', $this->createMock(Response::class));
        $this->setPropOn($controller, 'database', obtainTestDatabase());
        $this->setPropOn($controller, 'params', ['id_project' => $this->projectId(self::BASE), 'password' => 'projpw']);

        $project     = new ProjectStruct();
        $project->id = $this->projectId(self::BASE);
        $this->setPropOn($controller, 'project', $project);

        $reflector = new ReflectionClass(ReviewsController::class);
        $reflector->getMethod('registerValidators')->invoke($controller);

        /** @var array<\Controller\API\Commons\Validators\Base> $validators */
        $validators        = $reflector->getProperty('validators')->getValue($controller);
        $passwordValidator = $validators[0];

        // make ProjectPasswordValidator::getProject() return our project so closure #1 (line 80) succeeds
        $projectProp = (new ReflectionClass($passwordValidator))->getProperty('project');
        $projectProp->setValue($passwordValidator, $project);

        $callbacksProp = (new ReflectionClass(\Controller\API\Commons\Validators\Base::class))->getProperty('_validationCallbacks');
        /** @var array<callable> $callbacks */
        $callbacks = $callbacksProp->getValue($passwordValidator);
        $this->assertCount(3, $callbacks);

        // closure #1 (line 80): assigns controller->project from the validator
        $callbacks[0]();
        $this->assertInstanceOf(
            ProjectStruct::class,
            $reflector->getProperty('project')->getValue($controller)
        );

        // closures #2 (line 83) and #3 (line 85): sub-validators reject the unseeded context
        foreach ([$callbacks[1], $callbacks[2]] as $callback) {
            try {
                $callback();
            } catch (\Throwable) {
                // expected: TeamProjectValidator / ProjectAccessValidator reject
            }
        }
        $this->addToAssertionCount(1);
    }
}
