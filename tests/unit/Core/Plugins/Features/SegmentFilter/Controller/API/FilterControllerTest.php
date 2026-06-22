<?php

namespace Matecat\Core\Plugins\Features\SegmentFilter\Controller\API;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Plugins\Features\SegmentFilter\Controller\API\FilterController;
use Plugins\Features\SegmentFilter\Model\FilterDefinition;
use ReflectionClass;
use Utils\Logger\MatecatLogger;

/**
 * Neutered-ctor subclass per the canonical real-DB test pattern
 * (tests/unit/Core/Controllers/GetWarningControllerTest.php).
 */
class TestableFilterController extends FilterController
{
    public function __construct()
    {
    }

    protected function registerValidators(): void
    {
    }
}

/**
 * Real-DB suite. Reserved ID block base = 9_900_000 (project +1, file +4, job +2, segment +3).
 */
#[AllowMockObjectsWithoutExpectations]
class FilterControllerTest extends AbstractTest
{
    private const int B = 9_900_000;
    private const int PROJECT_ID = self::B + 1;
    private const int JOB_ID = self::B + 2;
    private const int SEGMENT_ID = self::B + 3;
    private const int FILE_ID = self::B + 4;
    private const string JOB_PASSWORD = 'filt_pw_9900000';
    private const string OWNER = 'ctrltest_9900000@example.org';

    private TestableFilterController $controller;
    private ReflectionClass $reflector;
    private Response&MockObject $responseMock;

    protected function setUp(): void
    {
        parent::setUp();

        // clean-then-seed (guards against rows leaked by a crashed prior run)
        $this->cleanTestData();
        $this->seedTestData();

        $this->controller = new TestableFilterController();
        $this->reflector = new ReflectionClass(FilterController::class);

        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', new Request());
        $this->setProp('response', $this->responseMock);
        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));
    }

    protected function tearDown(): void
    {
        $this->cleanTestData();
        parent::tearDown();
    }

    private function setProp(string $name, mixed $value): void
    {
        $prop = $this->findProp($name);
        $prop->setValue($this->controller, $value);
    }

    private function findProp(string $name): \ReflectionProperty
    {
        $c = $this->reflector;
        while ($c !== false) {
            if ($c->hasProperty($name)) {
                $p = $c->getProperty($name);
                $p->setAccessible(true);

                return $p;
            }
            $c = $c->getParentClass();
        }
        $this->fail("property \$$name not found");
    }

    private function seedTestData(): void
    {
        $conn = Database::obtain()->getConnection();
        $conn->exec("INSERT INTO projects (id, id_customer, password, name, create_date, status_analysis) VALUES (" . self::PROJECT_ID . ", '" . self::OWNER . "', 'projpw', 'FilterTestProject', NOW(), 'DONE')");
        $conn->exec("INSERT INTO files (id, id_project, filename, source_language, mime_type) VALUES (" . self::FILE_ID . ", " . self::PROJECT_ID . ", 'f.xliff', 'en-US', 'application/xliff+xml')");
        $conn->exec("INSERT INTO jobs (id, password, id_project, source, target, job_first_segment, job_last_segment, owner, tm_keys, create_date, disabled) VALUES (" . self::JOB_ID . ", '" . self::JOB_PASSWORD . "', " . self::PROJECT_ID . ", 'en-US', 'it-IT', " . self::SEGMENT_ID . ", " . self::SEGMENT_ID . ", '" . self::OWNER . "', '[]', NOW(), 0)");
        $conn->exec("INSERT INTO segments (id, id_file, internal_id, segment, segment_hash, raw_word_count, show_in_cattool) VALUES (" . self::SEGMENT_ID . ", " . self::FILE_ID . ", '1', 'Hello', 'filt_hash_9900000', 1, 1)");
        $conn->exec("INSERT INTO segment_translations (id_segment, id_job, segment_hash, translation, status, version_number, translation_date) VALUES (" . self::SEGMENT_ID . ", " . self::JOB_ID . ", 'filt_hash_9900000', 'Ciao', 'TRANSLATED', 0, NOW())");
    }

    private function cleanTestData(): void
    {
        $conn = Database::obtain()->getConnection();
        $conn->exec("DELETE FROM segment_translations WHERE id_job = " . self::JOB_ID);
        $conn->exec("DELETE FROM segments WHERE id_file = " . self::FILE_ID);
        $conn->exec("DELETE FROM jobs WHERE id = " . self::JOB_ID);
        $conn->exec("DELETE FROM files WHERE id = " . self::FILE_ID);
        $conn->exec("DELETE FROM projects WHERE id = " . self::PROJECT_ID);
    }

    private function makeChunk(): JobStruct
    {
        $chunk = new JobStruct();
        $chunk->id = self::JOB_ID;
        $chunk->password = self::JOB_PASSWORD;
        $chunk->job_first_segment = self::SEGMENT_ID;
        $chunk->job_last_segment = self::SEGMENT_ID;

        return $chunk;
    }

    /**
     * Build a REAL (non-neutered) FilterController with props set and registerValidators() invoked,
     * so the appended validators + their onSuccess closure are exercisable.
     *
     * @return array{0: FilterController, 1: ChunkPasswordValidator, 2: callable}
     */
    private function buildRealControllerWithRegisteredValidators(Request $request): array
    {
        $ref = new ReflectionClass(FilterController::class);
        $ctrl = $ref->newInstanceWithoutConstructor();

        $props = [
            'request' => $request,
            'response' => $this->responseMock,
            'logger' => $this->createMock(MatecatLogger::class),
            'featureSet' => new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)),
            // ChunkPasswordValidator's ctor reads $controller->getParams() (not the request)
            'params' => ['id_job' => self::JOB_ID, 'password' => self::JOB_PASSWORD],
        ];
        foreach ($props as $name => $val) {
            $this->propOf($ref, $name)->setValue($ctrl, $val);
        }

        $ref->getMethod('registerValidators')->invoke($ctrl);

        $vProp = new \ReflectionProperty(KleinController::class, 'validators');
        $vProp->setAccessible(true);
        $validators = $vProp->getValue($ctrl);

        /** @var ChunkPasswordValidator $chunkValidator */
        $chunkValidator = $validators[1];
        // give the validator a chunk so the closure's $Validator->getChunk() resolves
        $cProp = $this->propOf(new ReflectionClass(ChunkPasswordValidator::class), 'chunk');
        $cProp->setValue($chunkValidator, $this->makeChunk());

        $cbProp = $this->propOf(new ReflectionClass($chunkValidator), '_validationCallbacks');
        $callbacks = $cbProp->getValue($chunkValidator);

        return [$ctrl, $chunkValidator, $callbacks[0]];
    }

    private function propOf(ReflectionClass $ref, string $name): \ReflectionProperty
    {
        $c = $ref;
        while ($c !== false) {
            if ($c->hasProperty($name)) {
                $p = $c->getProperty($name);
                $p->setAccessible(true);

                return $p;
            }
            $c = $c->getParentClass();
        }
        $this->fail("property \$$name not found");
    }

    private function requestWithFilter(?array $filter): Request
    {
        // id_job + password are required by ChunkPasswordValidator's constructor (typed int $id_job)
        $params = ['id_job' => self::JOB_ID, 'password' => self::JOB_PASSWORD];
        if ($filter !== null) {
            $params['filter'] = $filter;
        }

        return new Request($params, [], [], ['REQUEST_URI' => '/api/v2/jobs/x/y/segment-filter', 'REQUEST_METHOD' => 'GET']);
    }

    // ─── registerValidators ───

    #[Test]
    public function registerValidators_appends_login_and_chunk_password_validators(): void
    {
        [$ctrl, $chunkValidator] = $this->buildRealControllerWithRegisteredValidators($this->requestWithFilter(['status' => 'TRANSLATED']));

        $vProp = new \ReflectionProperty(KleinController::class, 'validators');
        $vProp->setAccessible(true);
        $validators = $vProp->getValue($ctrl);

        $this->assertCount(2, $validators);
        $this->assertInstanceOf(LoginValidator::class, $validators[0]);
        $this->assertInstanceOf(ChunkPasswordValidator::class, $validators[1]);
        $this->assertSame($validators[1], $chunkValidator);
    }

    // ─── onSuccess closure (filter validation) ───

    #[Test]
    public function validator_closure_throws_when_filter_param_missing(): void
    {
        [, , $closure] = $this->buildRealControllerWithRegisteredValidators($this->requestWithFilter(null));

        $this->expectException(\Controller\API\Commons\Exceptions\ValidationError::class);
        $this->expectExceptionMessage('Filter is null');

        $closure();
    }

    #[Test]
    public function validator_closure_throws_when_filter_invalid(): void
    {
        // empty status + not sampled => FilterDefinition::isValid() === false
        [, , $closure] = $this->buildRealControllerWithRegisteredValidators($this->requestWithFilter(['status' => '']));

        $this->expectException(\Controller\API\Commons\Exceptions\ValidationError::class);
        $this->expectExceptionMessage('Filter is invalid');

        $closure();
    }

    #[Test]
    public function validator_closure_sets_chunk_and_filter_and_marks_review_when_revision(): void
    {
        [$ctrl, , $closure] = $this->buildRealControllerWithRegisteredValidators(
            $this->requestWithFilter(['status' => 'TRANSLATED', 'revision' => 1])
        );

        $closure();

        $ref = new ReflectionClass(FilterController::class);
        $chunk = $this->propOf($ref, 'chunk')->getValue($ctrl);
        $filter = $this->propOf($ref, 'filter')->getValue($ctrl);

        $this->assertInstanceOf(FilterDefinition::class, $filter);
        $this->assertInstanceOf(JobStruct::class, $chunk);
        $this->assertTrue($chunk->getIsReview());
    }

    // ─── index() ───

    #[Test]
    public function index_returns_matching_segment_ids_and_count(): void
    {
        $this->setProp('chunk', $this->makeChunk());
        $this->setProp('filter', new FilterDefinition(['status' => 'TRANSLATED']));

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('segment_ids', $data);
                $this->assertArrayHasKey('count', $data);
                $this->assertArrayHasKey('grouping', $data);
                $this->assertSame(1, $data['count']);
                $this->assertSame([(string) self::SEGMENT_ID], $data['segment_ids']);

                return true;
            }));

        $this->controller->index();
    }

    #[Test]
    public function index_returns_empty_when_no_segment_matches_status(): void
    {
        $this->setProp('chunk', $this->makeChunk());
        // NEW status: the only seeded translation is TRANSLATED, so the filter matches nothing.
        $this->setProp('filter', new FilterDefinition(['status' => 'NEW']));

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame(0, $data['count']);
                $this->assertSame([], $data['segment_ids']);
                $this->assertSame([], $data['grouping']);

                return true;
            }));

        $this->controller->index();
    }
}
