<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\CopyAllSourceToTargetController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\FeaturesBase\FeatureSet;
use Model\Projects\MetadataDao;
use Model\Users\UserStruct;
use PDOException;
use PHPUnit\Event\NoPreviousThrowableException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Exception as PHPUnitException;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\InvalidArgumentException as PHPUnitInvalidArgumentException;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use TypeError;
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
        $this->setProp('database', \Model\DataAccess\Database::obtain());

        $user        = new UserStruct();
        $user->uid   = $this->userId(self::BASE);
        $user->email = $this->ownerEmail(self::BASE);
        $this->setProp('user', $user);

        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));
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
     * @param array<int, mixed> $args
     *
     * @throws ReflectionException
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        return $this->reflector->getMethod($method)->invoke($this->controller, ...$args);
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
        (new MetadataDao())->set($this->projectId(self::BASE), 'features', 'translation_versions');
    }

    // ─── validateTheRequest ───

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateTheRequest_throws_when_id_job_is_empty(): void
    {
        $this->setRequestParams(['pass' => self::JOB_PASSWORD]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-1);

        $this->invokePrivate('validateTheRequest');
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateTheRequest_throws_when_pass_is_empty(): void
    {
        $this->setRequestParams(['id_job' => (string) $this->jobId(self::BASE)]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-2);

        $this->invokePrivate('validateTheRequest');
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateTheRequest_throws_when_job_password_couple_not_found(): void
    {
        $this->setRequestParams([
            'id_job' => (string) $this->jobId(self::BASE),
            'pass'   => 'wrong_password_xyz',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-3);

        $this->invokePrivate('validateTheRequest');
    }

    /**
     * @throws ReflectionException
     * @throws PHPUnitException
     * @throws ExpectationFailedException
     */
    #[Test]
    public function validateTheRequest_returns_expected_structure_for_valid_couple(): void
    {
        $this->setRequestParams([
            'id_job'          => (string) $this->jobId(self::BASE),
            'pass'            => self::JOB_PASSWORD,
            'revision_number' => '1',
        ]);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertIsArray($result);
        $this->assertSame((string) $this->jobId(self::BASE), $result['id_job']);
        $this->assertSame(self::JOB_PASSWORD, $result['pass']);
        $this->assertSame('1', $result['revision_number']);
        $this->assertSame($this->jobId(self::BASE), (int) $result['job_data']->id);
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
        $this->setRequestParams([
            'id_job'          => (string) $this->jobId(self::BASE),
            'pass'            => self::JOB_PASSWORD,
            'revision_number' => '1',
        ]);

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

        $this->setRequestParams([
            'id_job'          => (string) $this->jobId(self::BASE),
            'pass'            => self::JOB_PASSWORD,
            'revision_number' => '1',
        ]);

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

        $this->setRequestParams([
            'id_job'          => (string) $this->jobId(self::BASE),
            'pass'            => self::JOB_PASSWORD,
            'revision_number' => '1',
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame(1, $data['code']);
                $this->assertSame(1, $data['segments_modified']);
                return true;
            }));

        $this->controller->copy();
    }

    /**
     * @throws ReflectionException
     * @throws PHPUnitException
     */
    #[Test]
    public function registerValidators_appends_the_login_validator(): void
    {
        $controller = $this->reflector->newInstanceWithoutConstructor();

        $reqProp = $this->reflector->getProperty('request');
        $reqProp->setValue($controller, new Request());

        $method = $this->reflector->getMethod('registerValidators');
        $method->invoke($controller);

        /** @var array<int, object> $validators */
        $validators = $this->reflector->getProperty('validators')->getValue($controller);

        $this->assertCount(1, $validators);
        $this->assertInstanceOf(LoginValidator::class, $validators[0]);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function copy_throws_when_job_not_found(): void
    {
        $this->setRequestParams([
            'id_job'          => (string) $this->jobId(self::BASE),
            'pass'            => 'definitely_wrong_pass',
            'revision_number' => '1',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-3);

        $this->controller->copy();
    }
}
