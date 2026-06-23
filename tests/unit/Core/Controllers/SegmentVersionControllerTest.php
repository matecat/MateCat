<?php

namespace Matecat\Core\Controllers;

use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\SegmentValidator;
use Controller\API\V2\SegmentVersionController;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\FeaturesBase\FeatureSet;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Throwable;
use Utils\Logger\MatecatLogger;

class TestableSegmentVersionController extends SegmentVersionController
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
 * Real-DB suite for SegmentVersionController (plan N=40).
 * Reserved ID block base = 9_040_000 (base+1 project, +2 job, +3 segment,
 * +4 file, +9 segment_translation_version). Clean ONLY by reserved id.
 */
#[AllowMockObjectsWithoutExpectations]
class SegmentVersionControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_040_000;
    private const string JOB_PASSWORD = 'jobpw';

    /** @var ReflectionClass<SegmentVersionController> */
    private ReflectionClass $reflector;
    private TestableSegmentVersionController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedConnection()->exec(
            "DELETE FROM segment_translation_versions WHERE id_job = " . $this->jobId(self::BASE)
        );
        $this->cleanFragments(self::BASE);
        $this->seedFragments();

        $this->controller = new TestableSegmentVersionController();
        $this->reflector = new ReflectionClass(SegmentVersionController::class);

        $this->requestStub = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);
        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet(\Model\DataAccess\Database::obtain()));
        $this->setProp('database', Database::obtain());
    }

    /**
     * @throws Throwable
     */
    protected function tearDown(): void
    {
        $this->seedConnection()->exec(
            "DELETE FROM segment_translation_versions WHERE id_job = " . $this->jobId(self::BASE)
        );
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    /**
     * @throws Throwable
     */
    private function seedFragments(): void
    {
        $owner = $this->ownerEmail(self::BASE);
        $this->seedProject(self::BASE, $owner);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $owner, self::JOB_PASSWORD);
        $this->seedSegment(self::BASE);
        $this->seedSegmentTranslation(self::BASE);
        $this->seedSegmentTranslationVersion(self::BASE, 1, 'Ciao mondo v1');
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
    private function setRequestAndParams(array $params): void
    {
        $serverParams = ['REQUEST_URI' => '/api/v2/jobs/version', 'REQUEST_METHOD' => 'GET'];
        $this->requestStub = new Request($params, [], [], $serverParams);
        $this->setProp('request', $this->requestStub);
        $this->setProp('params', $params);
    }

    // ─── index() ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function index_returns_json_with_versions_key_and_seeded_version(): void
    {
        $this->setRequestAndParams([
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
        $this->assertCount(1, $captured['versions']);
        $this->assertSame($this->versionId(self::BASE), $captured['versions'][0]['id']);
        $this->assertSame($this->segmentId(self::BASE), $captured['versions'][0]['id_segment']);
        $this->assertSame($this->jobId(self::BASE), $captured['versions'][0]['id_job']);
        $this->assertSame(1, $captured['versions'][0]['version_number']);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function index_throws_not_found_for_wrong_password(): void
    {
        $this->setRequestAndParams([
            'id_job' => (string) $this->jobId(self::BASE),
            'id_segment' => (string) $this->segmentId(self::BASE),
            'password' => 'wrong_password_xyz',
        ]);

        $this->expectException(NotFoundException::class);

        $this->controller->index();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function index_throws_not_found_for_nonexistent_job(): void
    {
        $this->setRequestAndParams([
            'id_job' => '99999999',
            'id_segment' => (string) $this->segmentId(self::BASE),
            'password' => 'any_password',
        ]);

        $this->expectException(NotFoundException::class);

        $this->controller->index();
    }

    // ─── detail() ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function detail_returns_json_with_only_the_requested_version_number(): void
    {
        // add a second version so the version_number filter is meaningful
        $this->seedSegmentTranslationVersionExtra(2, 'Ciao mondo v2');

        $this->setRequestAndParams([
            'id_job' => (string) $this->jobId(self::BASE),
            'id_segment' => (string) $this->segmentId(self::BASE),
            'version_number' => '1',
            'password' => self::JOB_PASSWORD,
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->detail();

        $this->assertIsArray($captured);
        $this->assertArrayHasKey('versions', $captured);
        $this->assertCount(1, $captured['versions']);
        $this->assertSame(1, $captured['versions'][0]['version_number']);
        $this->assertSame($this->versionId(self::BASE), $captured['versions'][0]['id']);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function detail_throws_not_found_for_wrong_password(): void
    {
        $this->setRequestAndParams([
            'id_job' => (string) $this->jobId(self::BASE),
            'id_segment' => (string) $this->segmentId(self::BASE),
            'version_number' => '1',
            'password' => 'wrong_password_xyz',
        ]);

        $this->expectException(NotFoundException::class);

        $this->controller->detail();
    }

    // ─── registerValidators() ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function registerValidators_appends_login_and_segment_validators(): void
    {
        // Build a real (non-Testable) controller without invoking the klein
        // constructor, then drive the real registerValidators() so its body is
        // exercised (the Testable subclass overrides it with an empty stub).
        $real = $this->reflector->newInstanceWithoutConstructor();
        $this->reflector->getProperty('request')->setValue($real, new Request());

        $method = $this->reflector->getMethod('registerValidators');
        $method->invoke($real);

        $prop = $this->reflector->getProperty('validators');
        $validators = $prop->getValue($real);

        // LoginValidator + SegmentValidator are appended; JobPasswordValidator
        // is only wired via onSuccess() and is intentionally NOT appended.
        $this->assertIsArray($validators);
        $this->assertCount(2, $validators);
        $this->assertInstanceOf(LoginValidator::class, $validators[0]);
        $this->assertInstanceOf(SegmentValidator::class, $validators[1]);
    }

    /**
     * @throws Throwable
     */
    private function seedSegmentTranslationVersionExtra(int $versionNumber, string $translation): void
    {
        $id = $this->versionId(self::BASE) + 100 + $versionNumber;
        $segmentId = $this->segmentId(self::BASE);
        $jobId = $this->jobId(self::BASE);
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO segment_translation_versions (id, id_segment, id_job, translation, version_number, creation_date) "
            . "VALUES ($id, $segmentId, $jobId, '$translation', $versionNumber, NOW())"
        );
    }
}
