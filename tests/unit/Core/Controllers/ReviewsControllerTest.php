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
use Model\LQA\ChunkReviewStruct;
use Model\Projects\ProjectStruct;
use PDOException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Utils\Logger\MatecatLogger;

class TestableReviewsController extends ReviewsController
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
        $this->setProp('database', Database::obtain());
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
}
