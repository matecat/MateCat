<?php

namespace Matecat\Core\Controllers;

use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\V3\QualityReportControllerAPI;
use DomainException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\Analysis\Constants\InternalMatchesConstants;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\QualityReport\QualityReportModel;
use Model\QualityReport\QualityReportSegmentStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Utils\Logger\MatecatLogger;

class TestableQualityReportControllerAPI extends QualityReportControllerAPI
{
    public function __construct()
    {
    }

    protected function afterConstruct(): void
    {
    }
}

class TestableShowQualityReportControllerAPI extends QualityReportControllerAPI
{
    public function __construct()
    {
    }

    protected function afterConstruct(): void
    {
    }

    public ?QualityReportModel $mockModel = null;

    protected function createQualityReportModel(): QualityReportModel
    {
        if ($this->mockModel === null) {
            throw new \RuntimeException('mockModel must be set before calling show()');
        }

        return $this->mockModel;
    }
}

class TestableSegmentsQualityReportControllerAPI extends QualityReportControllerAPI
{
    public function __construct()
    {
    }

    protected function afterConstruct(): void
    {
    }

    public bool $renderCalled = false;
    public bool $receivedIsForUi = false;

    protected function renderSegments(bool $isForUI = false): void
    {
        $this->renderCalled = true;
        $this->receivedIsForUi = $isForUI;
    }
}

#[AllowMockObjectsWithoutExpectations]
class QualityReportControllerAPITest extends AbstractTest
{
    use ControllerSeedFragments;

    /** Reserved ID block 9028000 (base+1 project, +2 job, +3 segment, +4 file); owner ctrltest_9028000@example.org (§4). */
    private const int BASE = 9028000;

    private ReflectionClass $reflector;
    private TestableQualityReportControllerAPI $controller;
    private Request $requestStub;

    /** @var Response&MockObject */
    private Response&MockObject $responseMock;

    /** @throws ReflectionException */
    public function setUp(): void
    {
        parent::setUp();

        $this->reflector = new ReflectionClass(TestableQualityReportControllerAPI::class);
        $this->controller = $this->reflector->newInstanceWithoutConstructor();

        $this->requestStub = $this->createStub(Request::class);
        $this->responseMock = $this->createMock(Response::class);
        $this->responseMock->method('json')->willReturnSelf();
        $this->responseMock->method('code')->willReturnSelf();

        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->responseMock);
    }

    protected function tearDown(): void
    {
        $this->seedConnection()->exec('DELETE FROM files_job WHERE id_job = ' . $this->jobId(self::BASE));
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    /**
     * Seed a full project/file/job/segment/translation graph and return the
     * persisted JobStruct (the chunk the validators would otherwise resolve).
     *
     * @throws \Exception
     */
    private function seedAndLoadChunk(): JobStruct
    {
        $this->cleanFragments(self::BASE);
        $this->seedProject(self::BASE, $this->ownerEmail(self::BASE));
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $this->ownerEmail(self::BASE));
        $this->seedSegment(self::BASE);
        $this->seedSegmentTranslation(self::BASE);

        // files_job link is required by SegmentDao::getSegmentsForQr (RIGHT JOIN files_job).
        $this->seedConnection()->exec(
            'INSERT IGNORE INTO files_job (id_job, id_file) VALUES (' . $this->jobId(self::BASE) . ', ' . $this->fileId(self::BASE) . ')'
        );

        $job = (new JobDao())->getByIdAndPassword($this->jobId(self::BASE), 'jobpw');
        $this->assertInstanceOf(JobStruct::class, $job);

        return $job;
    }

    /**
     * Build a real-DB controller (no stubbed renderSegments) with the props the
     * action path reads (§7: request, response, logger, featureSet, chunk).
     *
     * @return array{0: QualityReportControllerAPI, 1: ReflectionClass, 2: Response&MockObject}
     * @throws ReflectionException
     */
    private function buildRealDbController(JobStruct $chunk, array $requestParams = []): array
    {
        $reflector  = new ReflectionClass(TestableQualityReportControllerAPI::class);
        $controller = $reflector->newInstanceWithoutConstructor();

        $serverParams = ['REQUEST_URI' => '/api/v3/quality-report', 'REQUEST_METHOD' => 'GET'];
        $request      = new Request($requestParams, [], [], $serverParams);
        $response     = $this->createMock(Response::class);
        $response->method('code')->willReturnSelf();

        $reflector->getProperty('request')->setValue($controller, $request);
        $reflector->getProperty('response')->setValue($controller, $response);
        $reflector->getProperty('logger')->setValue($controller, $this->createMock(MatecatLogger::class));
        $reflector->getProperty('featureSet')->setValue($controller, new FeatureSet());
        $reflector->getProperty('chunk')->setValue($controller, $chunk);

        return [$controller, $reflector, $response];
    }

    /** @throws ReflectionException */
    private function invokePrivate(string $name, array $args = []): mixed
    {
        return $this->reflector->getMethod($name)->invokeArgs($this->controller, $args);
    }

    #[Test]
    public function registerValidators_appends_login_and_chunk_password_validators(): void
    {
        $realReflector = new ReflectionClass(QualityReportControllerAPI::class);
        /** @var QualityReportControllerAPI $realController */
        $realController = $realReflector->newInstanceWithoutConstructor();

        $request = $this->createStub(Request::class);
        $response = $this->createMock(Response::class);
        $response->method('json')->willReturnSelf();
        $response->method('code')->willReturnSelf();

        $realReflector->getProperty('request')->setValue($realController, $request);
        $realReflector->getProperty('response')->setValue($realController, $response);
        $realController->params = [
            'id_job' => '1',
            'password' => 'abc123',
        ];

        $realReflector->getMethod('registerValidators')->invoke($realController);

        /** @var list<mixed> $validators */
        $validators = $realReflector->getProperty('validators')->getValue($realController);
        $this->assertCount(2, $validators);
        $this->assertInstanceOf(LoginValidator::class, $validators[0]);
        $this->assertInstanceOf(ChunkPasswordValidator::class, $validators[1]);

        $chunk = $this->createStub(JobStruct::class);
        $chunkValidatorReflector = new ReflectionClass($validators[1]);
        $chunkValidatorReflector->getProperty('chunk')->setValue($validators[1], $chunk);
        $chunkValidatorReflector->getMethod('_executeCallbacks')->invoke($validators[1]);

        $this->assertSame($chunk, $realReflector->getProperty('chunk')->getValue($realController));
    }

    #[Test]
    public function show_returns_quality_report_payload_from_model_structure(): void
    {
        $showReflector = new ReflectionClass(TestableShowQualityReportControllerAPI::class);
        /** @var TestableShowQualityReportControllerAPI $showController */
        $showController = $showReflector->newInstanceWithoutConstructor();

        $request = $this->createStub(Request::class);
        $response = $this->createMock(Response::class);
        $response->method('json')->willReturnSelf();
        $response->method('code')->willReturnSelf();
        $showReflector->getProperty('request')->setValue($showController, $request);
        $showReflector->getProperty('response')->setValue($showController, $response);

        $chunk = $this->createStub(JobStruct::class);
        $chunk->method('isDeleted')->willReturn(false);
        $showReflector->getProperty('chunk')->setValue($showController, $chunk);

        $model = $this->createMock(QualityReportModel::class);
        $model->expects($this->once())->method('setDateFormat')->with('c');
        $model->expects($this->once())->method('getStructure')->willReturn(['score' => 99]);
        $showController->mockModel = $model;

        $response
            ->expects($this->once())
            ->method('json')
            ->with(['quality-report' => ['score' => 99]])
            ->willReturnSelf();

        $showController->show();
    }

    #[Test]
    public function createQualityReportModel_returns_model_bound_to_chunk(): void
    {
        $chunk = $this->createStub(JobStruct::class);
        $this->reflector->getProperty('chunk')->setValue($this->controller, $chunk);

        $method = $this->reflector->getMethod('createQualityReportModel');
        /** @var QualityReportModel $model */
        $model = $method->invoke($this->controller);

        $this->assertInstanceOf(QualityReportModel::class, $model);
        $this->assertSame($chunk, $model->getChunk());
    }

    #[Test]
    public function segments_delegates_to_renderSegments_with_same_ui_flag(): void
    {
        $segmentsReflector = new ReflectionClass(TestableSegmentsQualityReportControllerAPI::class);
        /** @var TestableSegmentsQualityReportControllerAPI $segmentsController */
        $segmentsController = $segmentsReflector->newInstanceWithoutConstructor();

        $request = $this->createStub(Request::class);
        $response = $this->createMock(Response::class);
        $response->method('json')->willReturnSelf();
        $response->method('code')->willReturnSelf();
        $segmentsReflector->getProperty('request')->setValue($segmentsController, $request);
        $segmentsReflector->getProperty('response')->setValue($segmentsController, $response);

        $chunk = $this->createStub(JobStruct::class);
        $chunk->method('isDeleted')->willReturn(false);
        $segmentsReflector->getProperty('chunk')->setValue($segmentsController, $chunk);

        $segmentsController->segments(true);

        $this->assertTrue($segmentsController->renderCalled);
        $this->assertTrue($segmentsController->receivedIsForUi);
    }

    #[Test]
    public function segments_parent_method_invocation_is_covered_and_delegates(): void
    {
        $segmentsReflector = new ReflectionClass(TestableSegmentsQualityReportControllerAPI::class);
        /** @var TestableSegmentsQualityReportControllerAPI $segmentsController */
        $segmentsController = $segmentsReflector->newInstanceWithoutConstructor();

        $request = $this->createStub(Request::class);
        $response = $this->createMock(Response::class);
        $response->method('json')->willReturnSelf();
        $response->method('code')->willReturnSelf();
        $segmentsReflector->getProperty('request')->setValue($segmentsController, $request);
        $segmentsReflector->getProperty('response')->setValue($segmentsController, $response);

        $chunk = $this->createStub(JobStruct::class);
        $chunk->method('isDeleted')->willReturn(false);
        $segmentsReflector->getProperty('chunk')->setValue($segmentsController, $chunk);

        $parentReflector = new ReflectionClass(QualityReportControllerAPI::class);
        $parentReflector->getMethod('segments')->invoke($segmentsController, false);

        $this->assertTrue($segmentsController->renderCalled);
        $this->assertFalse($segmentsController->receivedIsForUi);
    }

    #[Test]
    public function renderSegments_throws_when_project_id_is_missing(): void
    {
        $chunk = $this->createStub(JobStruct::class);
        $chunk->method('getProject')->willReturn(new ProjectStruct());
        $this->reflector->getProperty('chunk')->setValue($this->controller, $chunk);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Project ID must not be null');

        $this->reflector->getMethod('renderSegments')->invoke($this->controller, false);
    }

    #[Test]
    public function general_returns_project_and_job_json_payload(): void
    {
        $chunk = $this->createStub(JobStruct::class);
        $project = new ProjectStruct();
        $chunk->method('getProject')->willReturn($project);
        $this->reflector->getProperty('chunk')->setValue($this->controller, $chunk);

        $this->responseMock
            ->expects($this->once())
            ->method('json')
            ->with([
                'project' => $project,
                'job' => $chunk,
            ])
            ->willReturnSelf();

        $this->controller->general();
    }

    #[Test]
    public function getPaginationLinks_returns_expected_metadata_and_navigation_links(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/v3/jobs/1/abc123/quality-report/segments?step=20';

        $chunk = $this->createStub(JobStruct::class);
        $chunk->method('getSegments')->willReturn(array_fill(0, 100, new \stdClass()));
        $chunk->job_first_segment = 1;
        $chunk->job_last_segment = 200;
        $this->reflector->getProperty('chunk')->setValue($this->controller, $chunk);

        $result = $this->invokePrivate('_getPaginationLinks', [[21, 22, 23, 24, 25], 20, ['severity' => 'major']]);

        $this->assertSame(25, $result['last_segment_id']);
        $this->assertSame(20, $result['items_per_page']);
        $this->assertSame(100, $result['total_items']);
        $this->assertSame($_SERVER['REQUEST_URI'], $result['self']);
        $this->assertSame(5.0, $result['pages']);
        $this->assertStringContainsString('ref_segment=25', (string)$result['next']);
        $this->assertStringNotContainsString('step=20', (string)$result['next']);
        $this->assertStringContainsString('filter%5Bseverity%5D=major', (string)$result['next']);
        $this->assertStringContainsString('ref_segment=0', (string)$result['prev']);
    }

    #[Test]
    public function getTteArrayForSegment_maps_sources_and_builds_total_with_defaults(): void
    {
        $tte1 = new \stdClass();
        $tte1->id_segment = 1;
        $tte1->source_page = '1';
        $tte1->tte = 5000;

        $tte2 = new \stdClass();
        $tte2->id_segment = 1;
        $tte2->source_page = '2';
        $tte2->tte = 1200;

        $tte3 = new \stdClass();
        $tte3->id_segment = 2;
        $tte3->source_page = '3';
        $tte3->tte = 9999;

        $result = $this->invokePrivate('getTteArrayForSegment', [[$tte1, $tte2, $tte3], 1]);

        $this->assertSame(5000, $result['translation']);
        $this->assertSame(1200, $result['revise']);
        $this->assertSame(0, $result['revise_2']);
        $this->assertSame(6200, $result['total']);
    }

    #[Test]
    public function getTteArrayForSegment_handles_revise2_and_default_source_page_as_translation(): void
    {
        $tteRevise2 = new \stdClass();
        $tteRevise2->id_segment = 9;
        $tteRevise2->source_page = '3';
        $tteRevise2->tte = 3000;

        $tteDefault = new \stdClass();
        $tteDefault->id_segment = 9;
        $tteDefault->source_page = '99';
        $tteDefault->tte = 7000;

        $result = $this->invokePrivate('getTteArrayForSegment', [[$tteRevise2, $tteDefault], 9]);

        $this->assertSame(7000, $result['translation']);
        $this->assertSame(0, $result['revise']);
        $this->assertSame(3000, $result['revise_2']);
        $this->assertSame(10000, $result['total']);
    }

    #[Test]
    public function getTteArrayForSegment_sets_translation_to_zero_when_only_revise_is_present(): void
    {
        $tteRevise = new \stdClass();
        $tteRevise->id_segment = 15;
        $tteRevise->source_page = '2';
        $tteRevise->tte = 1100;

        $result = $this->invokePrivate('getTteArrayForSegment', [[$tteRevise], 15]);

        $this->assertSame(0, $result['translation']);
        $this->assertSame(1100, $result['revise']);
        $this->assertSame(0, $result['revise_2']);
        $this->assertSame(1100, $result['total']);
    }

    #[Test]
    public function getSecsPerWord_returns_seconds_divided_by_raw_word_count(): void
    {
        $segment = new QualityReportSegmentStruct();
        $segment->time_to_edit = 10000;
        $segment->raw_word_count = 5;

        $result = $this->invokePrivate('getSecsPerWord', [$segment]);

        $this->assertEquals(2.0, $result);
    }

    #[Test]
    public function formatSegments_maps_struct_data_and_enriches_tte_file_and_revision_fields(): void
    {
        $segment = new QualityReportSegmentStruct();
        $segment->comments = [['id' => 1]];
        $segment->dataRefMap = ['a' => 'b'];
        $segment->edit_distance = 12;
        $segment->ice_locked = true;
        $segment->ice_modified = false;
        $segment->is_pre_translated = true;
        $segment->issues = [['severity' => 'major']];
        $segment->last_revisions = [['translation' => 'old']];
        $segment->last_translation = 'last';
        $segment->locked = false;
        $segment->match_type = InternalMatchesConstants::TM_ICE;
        $segment->parsed_time_to_edit = [1, 2, 3];
        $segment->pee = 0.11;
        $segment->pee_translation_revise = 0.22;
        $segment->pee_translation_suggestion = 0.33;
        $segment->raw_word_count = 4;
        $segment->segment = 'source';
        $segment->segment_hash = 'hash-1';
        $segment->sid = 1;
        $segment->source_page = 2;
        $segment->status = 'TRANSLATED';
        $segment->suggestion = 'suggestion';
        $segment->suggestion_match = 95;
        $segment->suggestion_source = 'tm';
        $segment->target = 'it-IT';
        $segment->translation = 'translated';
        $segment->version = 1715500000;
        $segment->version_number = 2;
        $segment->warnings = ['warn'];
        $segment->id_file = 1;
        $segment->time_to_edit = 8000;

        $tte = new \stdClass();
        $tte->id_segment = 1;
        $tte->source_page = '1';
        $tte->tte = 5000;

        $filesInfo = [
            'files' => [
                ['id' => 999, 'filename' => 'other.xliff'],
                ['id' => 1, 'filename' => 'test.xliff'],
            ],
            'first_segment' => 1,
            'last_segment' => 10,
        ];

        $result = $this->invokePrivate('_formatSegments', [[$segment], [$tte], $filesInfo, false]);

        $this->assertCount(1, $result);
        $first = $result[0];
        $this->assertSame(1, $first['id']);
        $this->assertSame('ice', $first['match_type']);
        $this->assertSame(['id' => 1, 'filename' => 'test.xliff'], $first['file']);
        $this->assertSame(5000, $first['time_to_edit']);
        $this->assertSame(5000, $first['time_to_edit_translation']);
        $this->assertSame(0, $first['time_to_edit_revise']);
        $this->assertSame(0, $first['time_to_edit_revise_2']);
        $this->assertEquals(2.0, $first['secs_per_word']);
        $this->assertSame(1, $first['revision_number']);
    }

    // ─── real-DB: renderSegments() full body (ID block 9028000) ───

    #[Test]
    public function renderSegments_emits_empty_segments_when_no_segment_ids_match(): void
    {
        $chunk = $this->seedAndLoadChunk();
        // ref_segment far beyond the seeded segment id → getSegmentsIdForQR returns []
        [$controller, $reflector, $response] = $this->buildRealDbController(
            $chunk,
            ['ref_segment' => (string) ($this->segmentId(self::BASE) + 100000), 'where' => 'after', 'step' => '20']
        );

        $response->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('segments', $data);
                $this->assertSame([], $data['segments']);
                return true;
            }))
            ->willReturnSelf();

        $reflector->getMethod('renderSegments')->invoke($controller, false);
    }

    #[Test]
    public function renderSegments_emits_populated_payload_for_seeded_segment(): void
    {
        $chunk = $this->seedAndLoadChunk();
        [$controller, $reflector, $response] = $this->buildRealDbController(
            $chunk,
            ['ref_segment' => '0', 'where' => 'after', 'step' => '20']
        );

        $segmentId = $this->segmentId(self::BASE);
        $response->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use ($segmentId): bool {
                $this->assertArrayHasKey('workflow_type', $data);
                $this->assertArrayHasKey('segments', $data);
                $this->assertArrayHasKey('_params', $data);
                $this->assertArrayHasKey('_links', $data);
                $this->assertNotEmpty($data['segments']);
                $this->assertSame($segmentId, $data['segments'][0]['id']);
                return true;
            }))
            ->willReturnSelf();

        $reflector->getMethod('renderSegments')->invoke($controller, false);
    }

    #[Test]
    public function renderSegments_caps_step_at_max_per_page(): void
    {
        $chunk = $this->seedAndLoadChunk();
        [$controller, $reflector, $response] = $this->buildRealDbController(
            $chunk,
            ['ref_segment' => '0', 'where' => 'after', 'step' => '10000']
        );

        $response->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                // _params.step normalises against MAX_PER_PAGE (200) when capped
                $this->assertArrayHasKey('_links', $data);
                $this->assertSame(200, $data['_links']['items_per_page']);
                return true;
            }))
            ->willReturnSelf();

        $reflector->getMethod('renderSegments')->invoke($controller, true);
    }

    #[Test]
    public function renderSegments_applies_default_where_and_step_when_params_absent(): void
    {
        $chunk = $this->seedAndLoadChunk();
        // No 'where' / 'step' params → controller defaults ('after' / DEFAULT_PER_PAGE).
        [$controller, $reflector, $response] = $this->buildRealDbController($chunk, ['ref_segment' => '0']);

        $response->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('_links', $data);
                $this->assertSame(QualityReportControllerAPI::DEFAULT_PER_PAGE, $data['_links']['items_per_page']);
                return true;
            }))
            ->willReturnSelf();

        $reflector->getMethod('renderSegments')->invoke($controller, false);
    }

    #[Test]
    public function getPaginationLinks_throws_on_invalid_request_uri(): void
    {
        $chunk = $this->seedAndLoadChunk();
        [$controller, $reflector] = $this->buildRealDbController($chunk);

        $original                = $_SERVER['REQUEST_URI'] ?? null;
        $_SERVER['REQUEST_URI']  = '///:';

        try {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('Invalid request URI');
            $reflector->getMethod('_getPaginationLinks')->invoke($controller, [$this->segmentId(self::BASE)], 20, []);
        } finally {
            if ($original !== null) {
                $_SERVER['REQUEST_URI'] = $original;
            }
        }
    }

    #[Test]
    public function show_emits_quality_report_structure_with_real_model(): void
    {
        $chunk = $this->seedAndLoadChunk();
        [$controller, $reflector, $response] = $this->buildRealDbController($chunk);

        $response->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('quality-report', $data);
                $this->assertIsArray($data['quality-report']);
                return true;
            }))
            ->willReturnSelf();

        $reflector->getMethod('show')->invoke($controller);
    }

    #[Test]
    public function segments_invokes_real_renderSegments_and_emits_payload(): void
    {
        $chunk = $this->seedAndLoadChunk();
        [$controller, $reflector, $response] = $this->buildRealDbController(
            $chunk,
            ['ref_segment' => '0', 'where' => 'after', 'step' => '20']
        );

        $response->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('segments', $data);
                return true;
            }))
            ->willReturnSelf();

        $reflector->getMethod('segments')->invoke($controller, false);
    }
}
