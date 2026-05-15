<?php

namespace unit\Controllers;

use Controller\API\App\GetSegmentsController;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao as FilesMetadataDao;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao as JobMetadataDao;
use Model\Projects\MetadataDao as ProjectMetadataDao;
use Model\Projects\MetadataStruct;
use Model\Projects\ProjectStruct;
use Model\Segments\ContextStruct;
use Model\Segments\SegmentDao;
use Model\Segments\SegmentMetadataDao;
use Model\Segments\SegmentUIStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use TestHelpers\AbstractTest;

/**
 * Testable subclass: skips constructor validators, overrides DB-dependent
 * helpers so unit tests never hit the database.
 */
class TestableGetSegmentsController extends GetSegmentsController
{
    public ?JobStruct $fakeJob = null;
    public ?SegmentDao $fakeSegmentDao = null;
    public ?ProjectMetadataDao $fakeProjectMetadataDao = null;
    public ?FilesMetadataDao $fakeFilesMetadataDao = null;
    public ?JobMetadataDao $fakeJobMetadataDao = null;
    public ?SegmentMetadataDao $fakeSegmentMetadataDao = null;

    public function __construct()
    {
    }

    protected function afterConstruct(): void
    {
    }

    public function callValidateTheRequest(): array
    {
        return $this->validateTheRequest();
    }

    protected function findJob(int $jid, string $password): JobStruct
    {
        return $this->fakeJob ?? throw new \RuntimeException('fakeJob not set');
    }

    protected function createSegmentDao(): SegmentDao
    {
        return $this->fakeSegmentDao ?? new SegmentDao();
    }

    protected function createProjectMetadataDao(): ProjectMetadataDao
    {
        return $this->fakeProjectMetadataDao ?? new ProjectMetadataDao();
    }

    protected function createFilesMetadataDao(): FilesMetadataDao
    {
        return $this->fakeFilesMetadataDao ?? new FilesMetadataDao();
    }

    protected function createJobMetadataDao(): JobMetadataDao
    {
        return $this->fakeJobMetadataDao ?? new JobMetadataDao();
    }

    protected function createSegmentMetadataDao(): SegmentMetadataDao
    {
        return $this->fakeSegmentMetadataDao ?? new SegmentMetadataDao();
    }
}

/**
 * Minimal subclass that does NOT override prepareNotes / getContextGroups,
 * so we can test the real empty-array path.
 */
class DirectGetSegmentsController extends GetSegmentsController
{
    public function __construct()
    {
    }

    protected function afterConstruct(): void
    {
    }
}

class GetSegmentsControllerTest extends AbstractTest
{
    private TestableGetSegmentsController $controller;
    private ReflectionClass $reflector;
    private Request $requestStub;
    private Response $responseMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new TestableGetSegmentsController();
        $this->reflector  = new ReflectionClass(GetSegmentsController::class);

        $this->requestStub  = $this->createStub(Request::class);
        $this->responseMock = $this->createStub(Response::class);

        $reqProp = $this->reflector->getProperty('request');
        $reqProp->setValue($this->controller, $this->requestStub);

        $resProp = $this->reflector->getProperty('response');
        $resProp->setValue($this->controller, $this->responseMock);
    }

    // ─── validateTheRequest ──────────────────────────────────────────

    #[Test]
    public function validateTheRequest_returns_shaped_array_on_valid_input(): void
    {
        $this->stubRequestParams([
            'jid'      => '42',
            'step'     => '20',
            'segment'  => '100',
            'password' => 'abc123',
            'where'    => 'after',
        ]);

        $result = $this->controller->callValidateTheRequest();

        self::assertSame(42, $result['jid']);
        self::assertSame('100', $result['id_segment']);
        self::assertSame('abc123', $result['password']);
        self::assertSame('after', $result['where']);
        self::assertSame(20, $result['step']);
    }

    #[Test]
    public function validateTheRequest_throws_on_missing_jid(): void
    {
        $this->stubRequestParams([
            'password' => 'x',
            'segment'  => '1',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-1);
        $this->controller->callValidateTheRequest();
    }

    #[Test]
    public function validateTheRequest_throws_on_missing_password(): void
    {
        $this->stubRequestParams([
            'jid'     => '1',
            'segment' => '1',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-2);
        $this->controller->callValidateTheRequest();
    }

    #[Test]
    public function validateTheRequest_throws_on_missing_segment(): void
    {
        $this->stubRequestParams([
            'jid'      => '1',
            'password' => 'x',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-3);
        $this->controller->callValidateTheRequest();
    }

    #[Test]
    public function validateTheRequest_caps_step_at_max(): void
    {
        $this->stubRequestParams([
            'jid'      => '1',
            'step'     => '999',
            'segment'  => '1',
            'password' => 'x',
            'where'    => 'after',
        ]);

        $result = $this->controller->callValidateTheRequest();

        self::assertSame(GetSegmentsController::MAX_PER_PAGE, $result['step']);
    }

    #[Test]
    public function validateTheRequest_where_defaults_to_null_when_empty(): void
    {
        $this->stubRequestParams([
            'jid'      => '1',
            'step'     => '10',
            'segment'  => '1',
            'password' => 'x',
        ]);

        $result = $this->controller->callValidateTheRequest();

        self::assertNull($result['where']);
    }

    #[Test]
    public function validateTheRequest_step_defaults_to_zero_when_missing(): void
    {
        $this->stubRequestParams([
            'jid'      => '1',
            'segment'  => '1',
            'password' => 'x',
            'where'    => 'center',
        ]);

        $result = $this->controller->callValidateTheRequest();

        self::assertSame(0, $result['step']);
    }

    #[Test]
    public function validateTheRequest_casts_jid_to_int(): void
    {
        $this->stubRequestParams([
            'jid'      => '123',
            'step'     => '5',
            'segment'  => '50',
            'password' => 'pass',
            'where'    => 'before',
        ]);

        $result = $this->controller->callValidateTheRequest();

        self::assertIsInt($result['jid']);
        self::assertSame(123, $result['jid']);
    }

    #[Test]
    public function validateTheRequest_casts_step_to_int(): void
    {
        $this->stubRequestParams([
            'jid'      => '1',
            'step'     => '15',
            'segment'  => '1',
            'password' => 'x',
            'where'    => 'after',
        ]);

        $result = $this->controller->callValidateTheRequest();

        self::assertIsInt($result['step']);
        self::assertSame(15, $result['step']);
    }

    #[Test]
    public function validateTheRequest_where_is_before(): void
    {
        $this->stubRequestParams([
            'jid'      => '1',
            'step'     => '10',
            'segment'  => '1',
            'password' => 'x',
            'where'    => 'before',
        ]);

        $result = $this->controller->callValidateTheRequest();

        self::assertSame('before', $result['where']);
    }

    #[Test]
    public function validateTheRequest_step_at_boundary_not_capped(): void
    {
        $this->stubRequestParams([
            'jid'      => '1',
            'step'     => '200',
            'segment'  => '1',
            'password' => 'x',
            'where'    => 'after',
        ]);

        $result = $this->controller->callValidateTheRequest();

        self::assertSame(200, $result['step']);
    }

    // ─── prepareNotes ────────────────────────────────────────────────

    #[Test]
    public function prepareNotes_returns_empty_array_when_no_segments(): void
    {
        $controller = new DirectGetSegmentsController();
        $reflector  = new ReflectionClass(GetSegmentsController::class);
        $method     = $reflector->getMethod('prepareNotes');

        $result = $method->invoke($controller, []);

        self::assertSame([], $result);
    }

    // ─── getContextGroups ────────────────────────────────────────────

    #[Test]
    public function getContextGroups_returns_empty_array_when_no_segments(): void
    {
        $controller = new DirectGetSegmentsController();
        $reflector  = new ReflectionClass(GetSegmentsController::class);
        $method     = $reflector->getMethod('getContextGroups');

        $result = $method->invoke($controller, []);

        self::assertSame([], $result);
    }

    // ─── attachNotes ─────────────────────────────────────────────────

    #[Test]
    public function attachNotes_sets_null_when_no_matching_note(): void
    {
        $segment = new SegmentUIStruct();
        $segment->sid = 999;

        $this->setFeatureSet();
        $this->callPrivate('attachNotes', $segment, []);

        self::assertNull($segment['notes']);
    }

    #[Test]
    public function attachNotes_attaches_matching_notes(): void
    {
        $segment = new SegmentUIStruct();
        $segment->sid = 42;

        $notes          = [['id' => 1, 'note' => 'Test note']];
        $segment_notes  = [42 => $notes];

        $this->setFeatureSet();
        $this->callPrivate('attachNotes', $segment, $segment_notes);

        self::assertSame($notes, $segment['notes']);
    }

    #[Test]
    public function attachNotes_dispatches_filter_event(): void
    {
        $segment = new SegmentUIStruct();
        $segment->sid = 10;

        $notes         = [['id' => 5, 'note' => 'Hello']];
        $segment_notes = [10 => $notes];

        $featureSet = $this->createMock(FeatureSet::class);
        $featureSet->expects(self::once())->method('dispatchFilter');
        $this->setFeatureSetInstance($featureSet);

        $this->callPrivate('attachNotes', $segment, $segment_notes);

        self::assertSame($notes, $segment['notes']);
    }

    // ─── attachContexts ──────────────────────────────────────────────

    #[Test]
    public function attachContexts_sets_null_when_no_matching_context(): void
    {
        $segment = new SegmentUIStruct();
        $segment->sid = 999;

        $this->callPrivate('attachContexts', $segment, []);

        self::assertNull($segment['context_groups']);
    }

    #[Test]
    public function attachContexts_attaches_matching_context(): void
    {
        $segment = new SegmentUIStruct();
        $segment->sid = 42;

        $ctx = new ContextStruct(['id_segment' => 42, 'context_json' => '{}']);
        $contexts = [42 => $ctx];

        $this->callPrivate('attachContexts', $segment, $contexts);

        self::assertSame($ctx, $segment['context_groups']);
    }

    // ─── constants ───────────────────────────────────────────────────

    #[Test]
    public function default_per_page_constant(): void
    {
        self::assertSame(40, GetSegmentsController::DEFAULT_PER_PAGE);
    }

    #[Test]
    public function max_per_page_constant(): void
    {
        self::assertSame(200, GetSegmentsController::MAX_PER_PAGE);
    }

    // ─── segments (empty data path) ──────────────────────────────────

    #[Test]
    public function segments_returns_empty_files_when_no_data(): void
    {
        $this->stubRequestParams([
            'jid'      => '10',
            'step'     => '20',
            'segment'  => '100',
            'password' => 'pw',
            'where'    => 'after',
        ]);

        $job = $this->createStub(JobStruct::class);
        $job->id       = 10;
        $job->password = 'pw';
        $job->source   = 'en-US';
        $job->target   = 'it-IT';

        $project = $this->createStub(ProjectStruct::class);
        $project->id = 1;
        $job->method('getProject')->willReturn($project);

        $this->controller->fakeJob = $job;

        $segmentDao = $this->createStub(SegmentDao::class);
        $segmentDao->method('getPaginationSegments')->willReturn([]);
        $this->controller->fakeSegmentDao = $segmentDao;

        $icuStruct = $this->createStub(MetadataStruct::class);
        $icuStruct->value = null;

        $projectMetaDao = $this->createStub(ProjectMetadataDao::class);
        $projectMetaDao->method('setCacheTTL')->willReturn($projectMetaDao);
        $projectMetaDao->method('get')->willReturn($icuStruct);
        $this->controller->fakeProjectMetadataDao = $projectMetaDao;

        $filesMetaDao = $this->createStub(FilesMetadataDao::class);
        $filesMetaDao->method('setCacheTTL')->willReturn($filesMetaDao);
        $this->controller->fakeFilesMetadataDao = $filesMetaDao;

        $segmentMetaDao = $this->createStub(SegmentMetadataDao::class);
        $segmentMetaDao->method('getAllInRange')->willReturn([]);
        $this->controller->fakeSegmentMetadataDao = $segmentMetaDao;

        $this->setFeatureSet();

        $captured = null;
        $responseMock = $this->createMock(Response::class);
        $responseMock->expects(self::once())
            ->method('json')
            ->willReturnCallback(function ($data) use (&$captured, $responseMock) {
                $captured = $data;

                return $responseMock;
            });

        $resProp = $this->reflector->getProperty('response');
        $resProp->setValue($this->controller, $responseMock);

        $this->controller->segments();

        self::assertIsArray($captured);
        self::assertArrayHasKey('data', $captured);
        self::assertSame([], $captured['data']['files']);
        self::assertSame('after', $captured['data']['where']);
    }

    // ─── helpers ─────────────────────────────────────────────────────

    private function stubRequestParams(array $params): void
    {
        $this->requestStub
            ->method('param')
            ->willReturnCallback(static fn(string $key) => $params[$key] ?? null);
    }

    private function callPrivate(string $method, mixed ...$args): mixed
    {
        $m = $this->reflector->getMethod($method);

        return $m->invoke($this->controller, ...$args);
    }

    private function setFeatureSet(): void
    {
        $featureSet = $this->createStub(FeatureSet::class);
        $this->setFeatureSetInstance($featureSet);
    }

    private function setFeatureSetInstance(FeatureSet $featureSet): void
    {
        $fsProp = $this->reflector->getProperty('featureSet');
        $fsProp->setValue($this->controller, $featureSet);
    }
}
