<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\V3\DownloadQRController;
use Controller\Exceptions\RenderTerminatedException;
use Exception;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\LQA\ModelStruct;
use Model\Projects\ProjectStruct;
use Model\QualityReport\QualityReportSegmentModel;
use Model\QualityReport\QualityReportSegmentStruct;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use ReflectionClass;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

class TestableDownloadQRController extends DownloadQRController
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

class DownloadQRControllerTest extends AbstractTest
{
    private ReflectionClass $reflector;
    private TestableDownloadQRController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        AppConfig::$SKIP_SQL_CACHE = true;

        $this->controller = new TestableDownloadQRController();
        $this->reflector  = new ReflectionClass(DownloadQRController::class);

        $this->reflector->getProperty('response')->setValue($this->controller, $this->createStub(Response::class));
        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createStub(MatecatLogger::class));
        $this->reflector->getProperty('jobDao')->setValue($this->controller, $this->createStub(JobDao::class));
        $this->reflector->getProperty('database')->setValue($this->controller, $this->createStub(IDatabase::class));
        $this->reflector->getProperty('segmentsPerFile')->setValue($this->controller, 20);
    }

    private function injectQrModel(QualityReportSegmentModel $model): void
    {
        $this->reflector->getProperty('qrSegmentModel')->setValue($this->controller, $model);
    }

    /**
     * JobStruct stub whose getProject() yields a ProjectStruct with the given LQA model.
     */
    private function makeChunkStub(?ModelStruct $lqaModel): JobStruct
    {
        $project = $this->createStub(ProjectStruct::class);
        $project->id_qa_model = $lqaModel !== null ? 1 : null;

        if ($lqaModel !== null) {
            $stmtStub = $this->createStub(\PDOStatement::class);
            $stmtStub->queryString = '';
            $stmtStub->method('execute')->willReturn(true);
            $stmtStub->method('fetchAll')->willReturn([$lqaModel]);

            $pdoStub = $this->createStub(\PDO::class);
            $pdoStub->method('prepare')->willReturn($stmtStub);

            $dbStub = $this->createStub(IDatabase::class);
            $dbStub->method('getConnection')->willReturn($pdoStub);

            $this->reflector->getProperty('database')->setValue($this->controller, $dbStub);
        }

        $chunk = $this->createStub(JobStruct::class);
        $chunk->method('getProject')->willReturn($project);

        return $chunk;
    }

    protected function tearDown(): void
    {
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
    }

    private function setFormat(string $format): void
    {
        $this->reflector->getProperty('format')->setValue($this->controller, $format);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function setRequestParams(array $params): void
    {
        $request = $this->createStub(Request::class);
        $request->method('param')->willReturnCallback(
            static function (string $key, $default = null) use ($params) {
                return $params[$key] ?? $default;
            }
        );
        $this->reflector->getProperty('request')->setValue($this->controller, $request);
    }

    /**
     * @param string $name
     * @param array<int, mixed> $args
     */
    private function invoke(string $name, array $args)
    {
        $method = $this->reflector->getMethod($name);
        return $method->invokeArgs($this->controller, $args);
    }

    /**
     * One fully-populated data row in the indexed format produced by
     * buildFileContentFromArrayOfSegmentIds(). Index 30 = issues map, 31 = comments map.
     *
     * @return array<int, mixed>
     */
    private function makeDataRow(): array
    {
        return [
            1,                  // 0  sid
            'ciao',             // 1  target
            'hello',            // 2  segment
            2,                  // 3  raw_word_count
            'ciao',             // 4  translation
            123456,             // 5  version
            false,              // 6  ice_locked
            'TRANSLATED',       // 7  status
            5000,               // 8  time_to_edit
            'file.txt',         // 9  filename
            10,                 // 10 id_file
            false,              // 11 warning
            90,                 // 12 suggestion_match
            'TM',               // 13 suggestion_source
            'ciao',             // 14 suggestion
            0,                  // 15 edit_distance
            false,              // 16 locked
            'TM',               // 17 match_type
            0.0,                // 18 pee
            false,              // 19 ice_modified
            0.0,                // 20 secs_per_word
            '0:0:0.0',          // 21 parsed_time_to_edit
            'ciao',             // 22 last_translation
            'rev',              // 23 revision
            'rev2',             // 24 second_pass_revision
            0.0,                // 25 pee_translation_revise
            0.0,                // 26 pee_translation_suggestion
            1,                  // 27 version_number
            1,                  // 28 source_page
            false,              // 29 is_pre_translated
            ['Accuracy [minor]' => 1],  // 30 issues map
            [                            // 31 comments map
                'Accuracy [minor]' => [
                    [
                        'id'          => 7,
                        'uid'         => 3,
                        'id_qa_entry' => 11,
                        'create_date' => '2026-06-11',
                        'comment'     => 'fix this',
                        'source_page' => 1,
                    ],
                ],
            ],
        ];
    }

    // ── fileMimeType ─────────────────────────────────────────────────────

    #[Test]
    public function fileMimeType_returns_json(): void
    {
        $this->setFormat('json');
        self::assertSame('application/json', $this->invoke('fileMimeType', []));
    }

    #[Test]
    public function fileMimeType_returns_csv(): void
    {
        $this->setFormat('csv');
        self::assertSame('text/csv', $this->invoke('fileMimeType', []));
    }

    #[Test]
    public function fileMimeType_returns_xml(): void
    {
        $this->setFormat('xml');
        self::assertSame('text/xml', $this->invoke('fileMimeType', []));
    }

    #[Test]
    public function fileMimeType_returns_octet_stream_for_unknown(): void
    {
        $this->setFormat('pdf');
        self::assertSame('application/octet-stream', $this->invoke('fileMimeType', []));
    }

    // ── download() validation ────────────────────────────────────────────

    #[Test]
    public function download_throws_on_invalid_format(): void
    {
        $this->setRequestParams(['jid' => 1, 'password' => 'pwd', 'format' => 'pdf', 'segmentsPerFile' => 20]);

        $this->expectException(AuthorizationError::class);

        $this->controller->download();
    }

    #[Test]
    public function download_caps_segments_per_file_at_100(): void
    {
        // format 'pdf' invalid → throws AFTER the cap is applied, so we can read the capped value
        $this->setRequestParams(['jid' => 1, 'password' => 'pwd', 'format' => 'pdf', 'segmentsPerFile' => 9999]);

        try {
            $this->controller->download();
            self::fail('Expected AuthorizationError');
        } catch (AuthorizationError $e) {
            // ignore — we only assert the side effect on segmentsPerFile
        }

        self::assertSame(100, $this->reflector->getProperty('segmentsPerFile')->getValue($this->controller));
    }

    // ── createCSVFile ────────────────────────────────────────────────────

    #[Test]
    public function createCSVFile_produces_headings_and_rows(): void
    {
        $csv = $this->invoke('createCSVFile', [[$this->makeDataRow()], ['Accuracy [minor]']]);

        self::assertIsString($csv);
        self::assertStringContainsString('sid', $csv);
        self::assertStringContainsString('Accuracy [minor]', $csv);
        self::assertStringContainsString('fix this', $csv);
    }

    #[Test]
    public function createCSVFile_handles_empty_data(): void
    {
        $csv = $this->invoke('createCSVFile', [[], []]);
        self::assertIsString($csv);
        self::assertStringContainsString('sid', $csv);
    }

    // ── createXMLFile ────────────────────────────────────────────────────

    #[Test]
    public function createXMLFile_produces_valid_xml(): void
    {
        $xml = $this->invoke('createXMLFile', [[$this->makeDataRow()], ['Accuracy [minor]']]);

        self::assertIsString($xml);
        self::assertStringContainsString('<segments>', $xml);
        self::assertStringContainsString('<sid>1</sid>', $xml);
        self::assertStringContainsString('<label>Accuracy [minor]</label>', $xml);
        self::assertStringContainsString('<comment>fix this</comment>', $xml);

        $dom = new \DOMDocument();
        self::assertTrue($dom->loadXML($xml));
    }

    // ── createJsonFile ───────────────────────────────────────────────────

    #[Test]
    public function createJsonFile_produces_valid_json(): void
    {
        $json = $this->invoke('createJsonFile', [[$this->makeDataRow()], ['Accuracy [minor]']]);

        self::assertIsString($json);
        $decoded = json_decode($json, true);
        self::assertIsArray($decoded);
        self::assertSame(1, $decoded[0]['sid']);
        self::assertSame(1, $decoded[0]['issues']['Accuracy [minor]']['count']);
    }

    // ── buildArrayOfSegmentIds ───────────────────────────────────────────

    #[Test]
    public function buildArrayOfSegmentIds_collects_pages_until_empty(): void
    {
        $model = $this->createStub(QualityReportSegmentModel::class);
        $model->method('getSegmentsIdForQR')
            ->willReturnOnConsecutiveCalls(['10', '20'], []);

        $ids = [];
        $this->invoke('buildArrayOfSegmentIds', [$model, 20, 0, &$ids]);

        self::assertSame([[10, 20]], $ids);
    }

    // ── buildFileContentFromArrayOfSegmentIds ────────────────────────────

    #[Test]
    public function buildFileContent_returns_empty_for_no_segments(): void
    {
        $model = $this->createStub(QualityReportSegmentModel::class);
        $model->method('getSegmentsForQR')->willReturn([]);

        $result = $this->invoke('buildFileContentFromArrayOfSegmentIds', [$model, [1, 2]]);

        self::assertSame([], $result);
    }

    #[Test]
    public function buildFileContent_maps_segment_with_issues(): void
    {
        $segment = $this->makeSegmentStruct();
        $segment->issues = [
            (object)['issue_category' => 'Accuracy', 'issue_severity' => 'minor', 'comments' => [['comment' => 'fix']]],
        ];

        $model = $this->createStub(QualityReportSegmentModel::class);
        $model->method('getSegmentsForQR')->willReturn([$segment]);

        /** @var array<int, array<int, mixed>> $result */
        $result = $this->invoke('buildFileContentFromArrayOfSegmentIds', [$model, [1]]);

        self::assertCount(1, $result);
        self::assertSame(1, $result[0][0]);                       // sid
        self::assertSame(['Accuracy [minor]' => 1], $result[0][30]); // issues map
        self::assertArrayHasKey('Accuracy [minor]', $result[0][31]); // comments map
    }

    // ── composeFileContent (DI: injected model + chunk) ──────────────────

    #[Test]
    public function composeFileContent_builds_csv_with_no_segments(): void
    {
        $this->setFormat('csv');

        $model = $this->createStub(QualityReportSegmentModel::class);
        $model->method('getSegmentsIdForQR')->willReturn([]);
        $this->injectQrModel($model);

        $result = $this->invoke('composeFileContent', [$this->makeChunkStub(null)]);

        self::assertIsString($result);
        self::assertStringContainsString('sid', $result);
    }

    #[Test]
    public function composeFileContent_includes_category_issues_from_lqa_model(): void
    {
        $this->setFormat('json');

        $lqaModel = $this->createStub(ModelStruct::class);
        $lqaModel->method('getCategoriesAndSeverities')->willReturn([
            ['label' => 'Accuracy', 'severities' => [['label' => 'minor']]],
        ]);

        $model = $this->createStub(QualityReportSegmentModel::class);
        $model->method('getSegmentsIdForQR')->willReturn([]);
        $this->injectQrModel($model);

        $result = $this->invoke('composeFileContent', [$this->makeChunkStub($lqaModel)]);

        self::assertIsString($result);
        $decoded = json_decode($result, true);
        self::assertSame([], $decoded); // no segments → empty json array
    }

    #[Test]
    public function composeFileContent_throws_when_format_unmergeable(): void
    {
        $this->setFormat('pdf'); // not csv/json/xml → $uniqueFile never set

        $model = $this->createStub(QualityReportSegmentModel::class);
        $model->method('getSegmentsIdForQR')->willReturn([]);
        $this->injectQrModel($model);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Merging files for download failed.');

        $this->invoke('composeFileContent', [$this->makeChunkStub(null)]);
    }

    // ── download() end-to-end (DI + RenderTerminatedException) ─────────

    #[Test]
    public function download_streams_file_and_terminates_in_testing_env(): void
    {
        $this->setRequestParams(['jid' => 1, 'password' => 'pwd', 'format' => 'csv', 'segmentsPerFile' => 20]);

        $jobDao = $this->createStub(JobDao::class);
        $jobDao->method('getByIdAndPasswordOrFail')->willReturn($this->makeChunkStub(null));
        $this->reflector->getProperty('jobDao')->setValue($this->controller, $jobDao);

        $model = $this->createStub(QualityReportSegmentModel::class);
        $model->method('getSegmentsIdForQR')->willReturn([]);
        $this->injectQrModel($model);

        ob_start();
        try {
            $this->expectException(RenderTerminatedException::class);
            $this->controller->download();
        } finally {
            ob_end_clean();
        }
    }

    // ── initDependencies (real parent body) ──────────────────────────────

    #[Test]
    public function initDependencies_assigns_job_dao(): void
    {
        // The TestableDownloadQRController overrides initDependencies() to a no-op,
        // so invoke the real parent body to cover the JobDao assignment.
        $fresh  = new TestableDownloadQRController();
        $ref    = new ReflectionClass(DownloadQRController::class);
        $ref->getProperty('database')->setValue($fresh, obtainTestDatabase());
        $method = $ref->getMethod('initDependencies');
        $method->invoke($fresh);

        self::assertInstanceOf(
            JobDao::class,
            (new ReflectionClass(DownloadQRController::class))
                ->getProperty('jobDao')
                ->getValue($fresh)
        );
    }

    // ── downloadFile (missing file → empty output, terminates) ───────────

    #[Test]
    public function downloadFile_handles_unreadable_file_and_terminates(): void
    {
        // A non-existent path makes file_get_contents() return false, exercising
        // the `$outputContent = ''` guard, then the testing-env termination branch.
        $missing = sys_get_temp_dir() . '/qr_does_not_exist_' . uniqid() . '.csv';

        ob_start();
        try {
            $this->expectException(RenderTerminatedException::class);
            @$this->invoke('downloadFile', ['text/csv', 'out.csv', $missing]);
        } finally {
            ob_end_clean();
        }
    }

    private function makeSegmentStruct(): QualityReportSegmentStruct
    {
        $s                            = new QualityReportSegmentStruct();
        $s->sid                       = 1;
        $s->target                    = 'ciao';
        $s->segment                   = 'hello';
        $s->raw_word_count            = 2;
        $s->translation               = 'ciao';
        $s->version                   = 123456;
        $s->ice_locked                = false;
        $s->status                    = 'TRANSLATED';
        $s->time_to_edit              = 5000;
        $s->filename                  = 'file.txt';
        $s->id_file                   = 10;
        $s->warning                   = false;
        $s->suggestion_match          = 90;
        $s->suggestion_source         = 'TM';
        $s->suggestion                = 'ciao';
        $s->edit_distance             = 0;
        $s->locked                    = false;
        $s->match_type                = 'TM';
        $s->pee                       = 0.0;
        $s->ice_modified              = false;
        $s->secs_per_word             = 0.0;
        $s->parsed_time_to_edit       = [0, 0, 0, 0];
        $s->last_translation          = 'ciao';
        $s->last_revisions            = [];
        $s->pee_translation_revise    = 0.0;
        $s->pee_translation_suggestion = 0.0;
        $s->version_number            = 1;
        $s->source_page               = 1;
        $s->is_pre_translated         = false;
        $s->issues                    = [];
        $s->comments                  = [];

        return $s;
    }
}
