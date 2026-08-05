<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\CopyAllSourceToTargetController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use DomainException;
use Exception;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Projects\MetadataDao;
use Model\Users\UserStruct;
use PDOException;
use PHPUnit\Event\NoPreviousThrowableException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Exception as PHPUnitException;
use PHPUnit\Framework\InvalidArgumentException as PHPUnitInvalidArgumentException;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use TypeError;
use Utils\Constants\SourcePages;
use Utils\Logger\MatecatLogger;

/**
 * Real-DB suite for {@see CopyAllSourceToTargetController}.
 *
 * Reserved ID block (Playbook §4): base = 9_007_000 (task N=7).
 *   9007001 project, 9007002 job, 9007003 segment, 9007004 file.
 * Owner email: ctrltest_9007000@example.org (never the shared test@example.org).
 * Clean ONLY by reserved id; clean-then-seed in setUp(); parent::tearDown() last.
 */
class TestableCopyAllSourceToTargetController extends CopyAllSourceToTargetController
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

#[AllowMockObjectsWithoutExpectations]
class CopyAllSourceToTargetControllerTest extends AbstractTest
{
    use ControllerSeedFragments {
        cleanFragments as private cleanReservedFragments;
    }

    private const int BASE = 9_007_000;
    private const string JOB_PASSWORD = 'jobpw';

    /** @var ReflectionClass<CopyAllSourceToTargetController> */
    private ReflectionClass $reflector;
    private TestableCopyAllSourceToTargetController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws NoPreviousThrowableException
     * @throws PHPUnitInvalidArgumentException
     * @throws MockObjectException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);
        $this->seedTestData();

        $this->controller = new TestableCopyAllSourceToTargetController();
        $this->reflector  = new ReflectionClass(CopyAllSourceToTargetController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);
        $this->setProp('database', obtainTestDatabase());

        $user        = new UserStruct();
        $user->uid   = $this->userId(self::BASE);
        $user->email = $this->ownerEmail(self::BASE);
        $this->setProp('user', $user);

        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));

        // In production the ChunkPasswordValidator loads $this->chunk before copy() runs; the
        // TestableController skips the validator chain, so seed the same chunk (translate source_page).
        $this->seedChunk();
    }

    /**
     * @throws PDOException
     */
    protected function tearDown(): void
    {
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    /**
     * @throws PDOException
     */
    private function seedTestData(): void
    {
        $owner = $this->ownerEmail(self::BASE);

        $this->seedProject(self::BASE, $owner);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $owner, self::JOB_PASSWORD);
        $this->seedSegment(self::BASE);
        // STATUS_NEW so copy() promotes it to DRAFT and counts it.
        $this->seedSegmentTranslation(self::BASE, 'NEW', 'Hello world');

        // getByChunkId joins files_job; link file <-> job.
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO files_job (id_job, id_file) VALUES ("
            . $this->jobId(self::BASE) . ", " . $this->fileId(self::BASE) . ")"
        );
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
    private function seedChunk(): void
    {
        $chunk = (new JobDao(obtainTestDatabase()))->getByIdAndPassword($this->jobId(self::BASE), self::JOB_PASSWORD);
        $chunk->setSourcePage(SourcePages::SOURCE_PAGE_TRANSLATE);
        $this->setProp('chunk', $chunk);
    }

    /**
     * @param array<string, string> $params
     *
     * @throws ReflectionException
     */
    private function setRequestParams(array $params): void
    {
        $serverParams       = ['REQUEST_URI' => '/api/app/copyAllSource2Target', 'REQUEST_METHOD' => 'POST'];
        $this->requestStub  = new Request($params, [], [], $serverParams);
        $this->setProp('request', $this->requestStub);
    }

    /**
     * @throws PDOException
     */
    private function cleanFilesJob(): void
    {
        $this->seedConnection()->exec(
            "DELETE FROM files_job WHERE id_job = " . $this->jobId(self::BASE)
        );
        $this->seedConnection()->exec(
            "DELETE FROM project_metadata WHERE id_project = " . $this->projectId(self::BASE)
        );
    }

    /**
     * @throws PDOException
     */
    private function cleanFragments(int $base): void
    {
        $this->cleanFilesJob();
        $this->cleanReservedFragments($base);
    }

    /**
     * Enable the feature via the DAO so its metadata cache is busted (a raw
     * INSERT would leave a stale cached read from an earlier in-process test).
     *
     * @throws ReflectionException
     * @throws PDOException
     */
    private function enableTranslationVersionsFeature(): void
    {
        (new MetadataDao(obtainTestDatabase()))->set($this->projectId(self::BASE), 'features', 'translation_versions');
    }

    // ─── copy() public action ───

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function copy_promotes_new_segment_and_reports_modified_count(): void
    {
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('code', $data);
                $this->assertArrayHasKey('segments_modified', $data);
                $this->assertSame(1, $data['code']);
                $this->assertSame(1, $data['segments_modified']);
                return true;
            }));

        $this->controller->copy();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws PDOException
     */
    #[Test]
    public function copy_skips_already_translated_segments_and_reports_zero(): void
    {
        // Flip the seeded translation away from STATUS_NEW so copy() skips it.
        $this->seedConnection()->exec(
            "UPDATE segment_translations SET status = 'TRANSLATED' WHERE id_job = "
            . $this->jobId(self::BASE)
        );

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame(1, $data['code']);
                $this->assertSame(0, $data['segments_modified']);
                return true;
            }));

        $this->controller->copy();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function copy_creates_translation_event_when_versions_feature_enabled(): void
    {
        $this->enableTranslationVersionsFeature();

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame(1, $data['code']);
                $this->assertSame(1, $data['segments_modified']);
                return true;
            }));

        $this->controller->copy();
    }

    // ─── registerValidators ───

    /**
     * @throws ReflectionException
     * @throws PHPUnitException
     */
    #[Test]
    public function registerValidators_appends_login_and_chunk_password_validators(): void
    {
        $controller = $this->reflector->newInstanceWithoutConstructor();

        $this->reflector->getProperty('request')->setValue($controller, new Request());

        $this->reflector->getMethod('registerValidators')->invoke($controller);

        /** @var array<int, object> $validators */
        $validators = $this->reflector->getProperty('validators')->getValue($controller);

        $this->assertCount(2, $validators);
        $this->assertInstanceOf(LoginValidator::class, $validators[0]);
        $this->assertInstanceOf(ChunkPasswordValidator::class, $validators[1]);
    }

    /**
     * The ChunkPasswordValidator onSuccess callback rejects a chunk opened with a REVIEW password:
     * copying source→target is not allowed during the revision phase.
     *
     * @throws ReflectionException
     */
    #[Test]
    public function registerValidators_onSuccess_rejects_review_chunk(): void
    {
        $chunkValidator = $this->buildChunkPasswordValidatorWithChunk($isReview = true);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('The source cannot be fully copied to the target while in the revision phase.');

        $this->executeValidatorCallbacks($chunkValidator);
    }

    /**
     * For a translate-password chunk the same callback stores the chunk on the controller and does
     * not throw.
     *
     * @throws ReflectionException
     */
    #[Test]
    public function registerValidators_onSuccess_stores_chunk_for_translate_password(): void
    {
        $chunkValidator = $this->buildChunkPasswordValidatorWithChunk($isReview = false);

        $this->executeValidatorCallbacks($chunkValidator);

        $chunk = $this->reflector->getProperty('chunk')->getValue($this->registeredController);
        $this->assertInstanceOf(JobStruct::class, $chunk);
        $this->assertFalse($chunk->isReview());
    }

    private ?CopyAllSourceToTargetController $registeredController = null;

    /**
     * Runs the real registerValidators() on a fresh controller and returns its ChunkPasswordValidator
     * with a stubbed chunk (review or translate), so the onSuccess closure can be exercised in isolation.
     *
     * @throws ReflectionException
     */
    private function buildChunkPasswordValidatorWithChunk(bool $isReview): ChunkPasswordValidator
    {
        $controller = $this->reflector->newInstanceWithoutConstructor();
        $this->reflector->getProperty('request')->setValue($controller, new Request());
        $this->reflector->getMethod('registerValidators')->invoke($controller);
        $this->registeredController = $controller;

        /** @var array<int, object> $validators */
        $validators     = $this->reflector->getProperty('validators')->getValue($controller);
        $chunkValidator = $validators[1];
        $this->assertInstanceOf(ChunkPasswordValidator::class, $chunkValidator);

        $chunk           = new JobStruct();
        $chunk->id       = $this->jobId(self::BASE);
        $chunk->password = self::JOB_PASSWORD;
        $chunk->setIsReview($isReview);

        (new ReflectionClass(ChunkPasswordValidator::class))
            ->getProperty('chunk')
            ->setValue($chunkValidator, $chunk);

        return $chunkValidator;
    }

    /**
     * @throws ReflectionException
     */
    private function executeValidatorCallbacks(ChunkPasswordValidator $validator): void
    {
        (new ReflectionClass(\Controller\API\Commons\Validators\Base::class))
            ->getMethod('_executeCallbacks')
            ->invoke($validator);
    }
}
