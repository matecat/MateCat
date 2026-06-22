<?php

namespace Matecat\Core\Controllers;

use Model\DataAccess\Database;
use Controller\API\V2\ChunkTranslationVersionController;
use Exception;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Exception as PHPUnitException;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use TypeError;
use Utils\Logger\MatecatLogger;

/**
 * Reserved ID block (Playbook §4): base = 9046000
 *   9046001 project, 9046002 job, 9046003 segment, 9046004 file,
 *   9046009 segment_translation_version. Cleaned ONLY by reserved id.
 *   Per-suite owner email: ctrltest_9046000@example.org.
 */
class TestableChunkTranslationVersionController extends ChunkTranslationVersionController
{
    public function __construct()
    {
    }

    protected function registerValidators(): void
    {
    }
}

#[AllowMockObjectsWithoutExpectations]
class ChunkTranslationVersionControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9046000;

    /** @var ReflectionClass<ChunkTranslationVersionController> */
    private ReflectionClass $reflector;
    private TestableChunkTranslationVersionController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws Exception
     * @throws TypeError
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);
        $this->seedTestData();

        $this->controller = new TestableChunkTranslationVersionController();
        $this->reflector  = new ReflectionClass(ChunkTranslationVersionController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);
        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet(Database::obtain()));
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
        $this->seedJob(self::BASE, $owner);
        $this->seedSegment(self::BASE);
        $this->seedSegmentTranslation(self::BASE);
        $this->seedSegmentTranslationVersion(self::BASE, 1, 'Ciao mondo versione uno');
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
     * @throws PHPUnitException
     * @throws Exception
     */
    private function loadChunk(): JobStruct
    {
        $chunk = (new JobDao())->getByIdAndPassword($this->jobId(self::BASE), 'jobpw');
        $this->assertInstanceOf(JobStruct::class, $chunk);
        $this->setProp('chunk', $chunk);

        return $chunk;
    }

    // ─── index() happy path ───

    /**
     * @throws Exception
     * @throws ReflectionException
     * @throws PHPUnitException
     */
    #[Test]
    public function index_returns_json_with_versions_key(): void
    {
        $this->loadChunk();

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
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     * @throws PHPUnitException
     */
    #[Test]
    public function index_renders_seeded_version_payload(): void
    {
        $this->loadChunk();

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->index();

        $this->assertIsArray($captured);
        $this->assertNotEmpty($captured['versions']);
        $first = $captured['versions'][0];
        $this->assertSame($this->jobId(self::BASE), (int)$first['id_job']);
        $this->assertSame($this->segmentId(self::BASE), (int)$first['id_segment']);
        $this->assertSame(1, (int)$first['version_number']);
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     * @throws PHPUnitException
     */
    #[Test]
    public function index_returns_empty_versions_when_no_versions_exist(): void
    {
        $this->seedConnection()->exec(
            "DELETE FROM segment_translation_versions WHERE id = " . $this->versionId(self::BASE)
        );

        $this->loadChunk();

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
        $this->assertSame([], $captured['versions']);
    }

    // ─── registerValidators() wiring ───

    /**
     * @throws ReflectionException
     * @throws PHPUnitException
     */
    #[Test]
    public function registerValidators_appends_login_and_chunk_password_validators(): void
    {
        $this->setProp('params', [
            'id_job'   => (string)$this->jobId(self::BASE),
            'password' => 'jobpw',
        ]);

        $validatorsProp = $this->reflector->getProperty('validators');
        $validatorsProp->setValue($this->controller, []);

        $method = $this->reflector->getMethod('registerValidators');
        $method->invoke($this->controller);

        $validators = $validatorsProp->getValue($this->controller);
        $this->assertIsArray($validators);
        $this->assertCount(2, $validators);
    }
}
