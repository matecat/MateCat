<?php

namespace unit\Controllers;

use Controller\API\V3\SegmentAnalysisController;
use Exception;
use Klein\Request;
use Klein\Response;
use Model\Analysis\Constants\StandardMatchTypeNamesConstants;
use Model\DataAccess\Database;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectDao;
use Model\Segments\SegmentDisabledService;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use TestHelpers\AbstractTest;

class TestableSegmentAnalysisController extends SegmentAnalysisController
{
    public function __construct()
    {
    }

    protected function afterConstruct(): void
    {
    }
}

class SegmentAnalysisControllerTest extends AbstractTest
{
    private const int TEST_PROJECT_ID = 9998001;
    private const int TEST_JOB_ID = 9998002;
    private const string TEST_JOB_PASSWORD = 'seganalysis_pw';
    private const int TEST_SEGMENT_1 = 9998003;
    private const int TEST_SEGMENT_2 = 9998004;
    private const int TEST_SEGMENT_3 = 9998005;
    private const int TEST_FILE_ID = 9998006;

    private TestableSegmentAnalysisController $controller;
    private ReflectionClass $reflector;

    /**
     * @throws ReflectionException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedTestData();

        $this->controller = new TestableSegmentAnalysisController();
        $this->reflector = new ReflectionClass(SegmentAnalysisController::class);

        $requestStub = $this->createStub(Request::class);
        $responseMock = $this->createStub(Response::class);

        $reqProp = $this->reflector->getProperty('request');
        $reqProp->setValue($this->controller, $requestStub);

        $resProp = $this->reflector->getProperty('response');
        $resProp->setValue($this->controller, $responseMock);

        $featureSet = new FeatureSet();
        $featureSetProp = $this->reflector->getProperty('featureSet');
        $featureSetProp->setValue($this->controller, $featureSet);

        $segmentDisabledServiceProp = $this->reflector->getProperty('segmentDisabledService');
        $segmentDisabledServiceProp->setValue($this->controller, new SegmentDisabledService());
    }

    protected function tearDown(): void
    {
        $this->cleanTestData();
        parent::tearDown();
    }

    private function seedTestData(): void
    {
        $conn = Database::obtain()->getConnection();
        $this->cleanTestData();

        $conn->exec("INSERT INTO projects (id, id_customer, password, name, create_date, status_analysis) VALUES (" . self::TEST_PROJECT_ID . ", 'test@example.org', 'projpw_sa', 'TestSegAnalysis', NOW(), 'DONE')");
        $conn->exec("INSERT INTO files (id, id_project, filename, source_language, mime_type) VALUES (" . self::TEST_FILE_ID . ", " . self::TEST_PROJECT_ID . ", 'analysis.xliff', 'en-US', 'application/xliff+xml')");
        $conn->exec("INSERT INTO jobs (id, password, id_project, source, target, job_first_segment, job_last_segment, owner, tm_keys, create_date, disabled) VALUES (" . self::TEST_JOB_ID . ", '" . self::TEST_JOB_PASSWORD . "', " . self::TEST_PROJECT_ID . ", 'en-US', 'it-IT', " . self::TEST_SEGMENT_1 . ", " . self::TEST_SEGMENT_3 . ", 'test@example.org', '[]', NOW(), 0)");
        $conn->exec("INSERT INTO segments (id, id_file, internal_id, segment, segment_hash, raw_word_count) VALUES
            (" . self::TEST_SEGMENT_1 . ", " . self::TEST_FILE_ID . ", '1', 'Hello world', 'hash1_sa_test', 2),
            (" . self::TEST_SEGMENT_2 . ", " . self::TEST_FILE_ID . ", '2', 'Good morning friend', 'hash2_sa_test', 3),
            (" . self::TEST_SEGMENT_3 . ", " . self::TEST_FILE_ID . ", '3', 'Goodbye', 'hash3_sa_test', 1)
        ");
        $conn->exec("INSERT INTO segment_translations (id_segment, id_job, segment_hash, translation, status, version_number, translation_date, match_type) VALUES
            (" . self::TEST_SEGMENT_1 . ", " . self::TEST_JOB_ID . ", 'hash1_sa_test', 'Ciao mondo', 'TRANSLATED', 0, NOW(), 'ICE'),
            (" . self::TEST_SEGMENT_2 . ", " . self::TEST_JOB_ID . ", 'hash2_sa_test', 'Buongiorno amico', 'TRANSLATED', 0, NOW(), '100%'),
            (" . self::TEST_SEGMENT_3 . ", " . self::TEST_JOB_ID . ", 'hash3_sa_test', 'Arrivederci', 'DRAFT', 0, NOW(), 'NO_MATCH')
        ");
    }

    private function cleanTestData(): void
    {
        $conn = Database::obtain()->getConnection();
        $conn->exec("DELETE FROM segment_translations WHERE id_job = " . self::TEST_JOB_ID);
        $conn->exec("DELETE FROM segments WHERE id_file = " . self::TEST_FILE_ID);
        $conn->exec("DELETE FROM segment_metadata WHERE id_segment IN (" . self::TEST_SEGMENT_1 . ", " . self::TEST_SEGMENT_2 . ", " . self::TEST_SEGMENT_3 . ")");
        $conn->exec("DELETE FROM jobs WHERE id = " . self::TEST_JOB_ID);
        $conn->exec("DELETE FROM files WHERE id = " . self::TEST_FILE_ID);
        $conn->exec("DELETE FROM projects WHERE id = " . self::TEST_PROJECT_ID);
    }

    private function invokePrivate(string $method, array $args = []): mixed
    {
        $m = $this->reflector->getMethod($method);
        return $m->invoke($this->controller, ...$args);
    }

    #[Test]
    public function formatSegmentThrowsRuntimeExceptionWhenJobNotFound(): void
    {
        $segment = new ShapelessConcreteStruct();
        $segment->id = 999999999;
        $segment->id_job = 999999999;
        $segment->job_password = 'nonexistent_password_xyz';
        $segment->source = 'en-US';
        $segment->target = 'it-IT';
        $segment->segment = 'test source';
        $segment->translation = 'test target';
        $segment->filename = 'test.xliff';
        $segment->tag_key = null;
        $segment->tag_value = null;
        $segment->match_type = 'default';
        $segment->source_page = null;
        $segment->status = 'TRANSLATED';
        $segment->last_edit = null;
        $segment->has_r1 = null;
        $segment->has_r2 = null;
        $segment->raw_word_count = 5;
        $segment->project_name = 'Test Project';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Job not found');

        $this->invokePrivate('formatSegment', [
            $segment, [], [], [], [], new StandardMatchTypeNamesConstants()
        ]);
    }

    #[Test]
    public function formatSegmentReturnsCorrectStructureForValidJob(): void
    {
        $segment = new ShapelessConcreteStruct();
        $segment->id = self::TEST_SEGMENT_1;
        $segment->id_job = self::TEST_JOB_ID;
        $segment->job_password = self::TEST_JOB_PASSWORD;
        $segment->source = 'en-US';
        $segment->target = 'it-IT';
        $segment->segment = 'Hello world';
        $segment->translation = 'Ciao mondo';
        $segment->filename = 'analysis.xliff';
        $segment->tag_key = null;
        $segment->tag_value = null;
        $segment->match_type = 'ICE';
        $segment->source_page = 2;
        $segment->status = 'TRANSLATED';
        $segment->last_edit = '2026-01-01 10:00:00';
        $segment->has_r1 = '1';
        $segment->has_r2 = null;
        $segment->raw_word_count = 2;
        $segment->project_name = 'TestSegAnalysis';

        $result = $this->invokePrivate('formatSegment', [
            $segment, [], [], [], [], new StandardMatchTypeNamesConstants()
        ]);

        $this->assertSame(self::TEST_SEGMENT_1, $result['id_segment']);
        $this->assertSame(self::TEST_JOB_ID, $result['id_chunk']);
        $this->assertSame(self::TEST_JOB_PASSWORD, $result['chunk_password']);
        $this->assertSame('Hello world', $result['source']);
        $this->assertSame('Ciao mondo', $result['target']);
        $this->assertSame('en-US', $result['source_lang']);
        $this->assertSame('it-IT', $result['target_lang']);
        $this->assertSame('TRANSLATED', $result['status']['translation_status']);
        $this->assertSame('r1', $result['status']['final_version']);
        $this->assertSame('analysis.xliff', $result['filename']);
        $this->assertIsInt($result['source_raw_word_count']);
        $this->assertIsInt($result['target_raw_word_count']);
        $this->assertNotNull($result['last_edit']);
        $this->assertFalse($result['disabled']);
    }

    #[Test]
    public function formatSegmentUsesOriginalFilenameFromTagKey(): void
    {
        $segment = new ShapelessConcreteStruct();
        $segment->id = self::TEST_SEGMENT_1;
        $segment->id_job = self::TEST_JOB_ID;
        $segment->job_password = self::TEST_JOB_PASSWORD;
        $segment->source = 'en-US';
        $segment->target = 'it-IT';
        $segment->segment = 'Hello world';
        $segment->translation = 'Ciao mondo';
        $segment->filename = 'analysis.xliff';
        $segment->tag_key = 'original';
        $segment->tag_value = 'real_original.docx';
        $segment->match_type = 'ICE';
        $segment->source_page = null;
        $segment->status = 'TRANSLATED';
        $segment->last_edit = null;
        $segment->has_r1 = null;
        $segment->has_r2 = null;
        $segment->raw_word_count = 2;
        $segment->project_name = 'TestSegAnalysis';

        $result = $this->invokePrivate('formatSegment', [
            $segment, [], [], [], [], new StandardMatchTypeNamesConstants()
        ]);

        $this->assertSame('real_original.docx', $result['original_filename']);
        $this->assertSame('analysis.xliff', $result['filename']);
    }

    #[Test]
    public function formatSegmentIncludesIssuesFromAggregate(): void
    {
        $segment = new ShapelessConcreteStruct();
        $segment->id = self::TEST_SEGMENT_1;
        $segment->id_job = self::TEST_JOB_ID;
        $segment->job_password = self::TEST_JOB_PASSWORD;
        $segment->source = 'en-US';
        $segment->target = 'it-IT';
        $segment->segment = 'Hello world';
        $segment->translation = 'Ciao mondo';
        $segment->filename = 'analysis.xliff';
        $segment->tag_key = null;
        $segment->tag_value = null;
        $segment->match_type = 'ICE';
        $segment->source_page = null;
        $segment->status = 'TRANSLATED';
        $segment->last_edit = null;
        $segment->has_r1 = null;
        $segment->has_r2 = null;
        $segment->raw_word_count = 2;
        $segment->project_name = 'TestSegAnalysis';

        $issuesAggregate = [
            self::TEST_JOB_ID => [
                self::TEST_SEGMENT_1 => [
                    ['source_page' => 'r1', 'id_category' => 1, 'category' => 'Accuracy', 'severity' => 'Minor', 'translation_version' => 0, 'penalty_points' => 1.0, 'created_at' => '2026-01-01T00:00:00+00:00']
                ]
            ]
        ];

        $result = $this->invokePrivate('formatSegment', [
            $segment, [], [], $issuesAggregate, [], new StandardMatchTypeNamesConstants()
        ]);

        $this->assertCount(1, $result['issues']);
        $this->assertSame('Accuracy', $result['issues'][0]['category']);
    }

    #[Test]
    public function getSegmentsFromIdJobAndPasswordThrowsWhenJobIdIsNull(): void
    {
        $jobStruct = new JobStruct();
        $jobStruct->id = null;
        $jobStruct->password = 'some_password';
        $jobStruct->id_project = 1;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Job ID must not be null');

        $this->invokePrivate('getSegmentsFromIdJobAndPassword', [$jobStruct, 1, 50, new StandardMatchTypeNamesConstants()]);
    }

    #[Test]
    public function getSegmentsFromIdJobAndPasswordThrowsWhenJobPasswordIsNull(): void
    {
        $jobStruct = new JobStruct();
        $jobStruct->id = 12345;
        $jobStruct->password = null;
        $jobStruct->id_project = 1;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Job password must not be null');

        $this->invokePrivate('getSegmentsFromIdJobAndPassword', [$jobStruct, 1, 50, new StandardMatchTypeNamesConstants()]);
    }

    #[Test]
    public function getSegmentsFromIdJobAndPasswordReturnsSegments(): void
    {
        $jobStruct = new JobStruct();
        $jobStruct->id = self::TEST_JOB_ID;
        $jobStruct->password = self::TEST_JOB_PASSWORD;
        $jobStruct->id_project = self::TEST_PROJECT_ID;

        $result = $this->invokePrivate('getSegmentsFromIdJobAndPassword', [$jobStruct, 1, 50, new StandardMatchTypeNamesConstants()]);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertSame(self::TEST_SEGMENT_1, $result[0]['id_segment']);
    }

    #[Test]
    public function getSegmentsForAJobReturnsPaginatedResult(): void
    {
        $jobStruct = new JobStruct();
        $jobStruct->id = self::TEST_JOB_ID;
        $jobStruct->password = self::TEST_JOB_PASSWORD;
        $jobStruct->id_project = self::TEST_PROJECT_ID;

        $result = $this->invokePrivate('getSegmentsForAJob', [$jobStruct, 1, 2, 3, new StandardMatchTypeNamesConstants()]);

        $this->assertArrayHasKey('workflow_type', $result);
        $this->assertArrayHasKey('_links', $result);
        $this->assertArrayHasKey('items', $result);
        $this->assertSame(1, $result['_links']['page']);
        $this->assertSame(2, $result['_links']['per_page']);
        $this->assertSame(3, $result['_links']['total_items']);
        $this->assertCount(2, $result['items']);
        $this->assertNotNull($result['_links']['next_page']);
        $this->assertNull($result['_links']['prev_page']);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function getSegmentsForAJobThrowsOnInvalidPage(): void
    {
        $jobStruct = new JobStruct();
        $jobStruct->id = self::TEST_JOB_ID;
        $jobStruct->password = self::TEST_JOB_PASSWORD;
        $jobStruct->id_project = self::TEST_PROJECT_ID;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Page number 5 is not valid');

        $this->invokePrivate('getSegmentsForAJob', [$jobStruct, 5, 50, 3, new StandardMatchTypeNamesConstants()]);
    }

    #[Test]
    public function getSegmentsForAProjectReturnsPaginatedResult(): void
    {
        $projectProp = $this->reflector->getProperty('project');
        $project = (new ProjectDao())->findById(self::TEST_PROJECT_ID);
        $projectProp->setValue($this->controller, $project);

        $projectDaoProp = $this->reflector->getProperty('projectDao');
        $projectDaoProp->setValue($this->controller, new ProjectDao());

        $result = $this->invokePrivate('getSegmentsForAProject', [self::TEST_PROJECT_ID, 'projpw_sa', 1, 50, 3, new StandardMatchTypeNamesConstants()]);

        $this->assertArrayHasKey('workflow_type', $result);
        $this->assertArrayHasKey('_links', $result);
        $this->assertArrayHasKey('items', $result);
        $this->assertSame(3, $result['_links']['total_items']);
    }

    #[Test]
    public function humanReadableSourcePageReturnsTranslationForPage1(): void
    {
        $this->assertSame('t', $this->invokePrivate('humanReadableSourcePage', [1]));
    }

    #[Test]
    public function humanReadableSourcePageReturnsR1ForPage2(): void
    {
        $this->assertSame('r1', $this->invokePrivate('humanReadableSourcePage', [2]));
    }

    #[Test]
    public function humanReadableSourcePageReturnsR2ForPage3(): void
    {
        $this->assertSame('r2', $this->invokePrivate('humanReadableSourcePage', [3]));
    }

    #[Test]
    public function humanReadableSourcePageReturnsNullForUnknown(): void
    {
        $this->assertNull($this->invokePrivate('humanReadableSourcePage', [99]));
    }

    #[Test]
    public function getStatusObjectReturnsCorrectStructureForTranslation(): void
    {
        $segment = new ShapelessConcreteStruct();
        $segment->source_page = 1;
        $segment->has_r1 = null;
        $segment->has_r2 = null;
        $segment->raw_word_count = 10;
        $segment->status = 'TRANSLATED';

        $result = $this->invokePrivate('getStatusObject', [$segment]);

        $this->assertSame('TRANSLATED', $result['translation_status']);
        $this->assertSame('t', $result['final_version']);
        $this->assertNull($result['counts']['r1']);
        $this->assertNull($result['counts']['r2']);
    }

    #[Test]
    public function getStatusObjectReturnsCorrectStructureForR1(): void
    {
        $segment = new ShapelessConcreteStruct();
        $segment->source_page = 2;
        $segment->has_r1 = '1';
        $segment->has_r2 = null;
        $segment->raw_word_count = 15;
        $segment->status = 'APPROVED';

        $result = $this->invokePrivate('getStatusObject', [$segment]);

        $this->assertSame('APPROVED', $result['translation_status']);
        $this->assertSame('r1', $result['final_version']);
        $this->assertSame(15, $result['counts']['r1']);
        $this->assertNull($result['counts']['r2']);
    }

    #[Test]
    public function getStatusObjectReturnsCorrectStructureForR2(): void
    {
        $segment = new ShapelessConcreteStruct();
        $segment->source_page = 3;
        $segment->has_r1 = '1';
        $segment->has_r2 = '1';
        $segment->raw_word_count = 20;
        $segment->status = 'APPROVED2';

        $result = $this->invokePrivate('getStatusObject', [$segment]);

        $this->assertSame('APPROVED2', $result['translation_status']);
        $this->assertSame('r2', $result['final_version']);
        $this->assertSame(20, $result['counts']['r1']);
        $this->assertSame(20, $result['counts']['r2']);
    }

    #[Test]
    public function getStatusObjectReturnsNullFinalVersionForUnknownSourcePage(): void
    {
        $segment = new ShapelessConcreteStruct();
        $segment->source_page = null;
        $segment->has_r1 = '1';
        $segment->has_r2 = '1';
        $segment->raw_word_count = 10;
        $segment->status = 'DRAFT';

        $result = $this->invokePrivate('getStatusObject', [$segment]);

        $this->assertSame('DRAFT', $result['translation_status']);
        $this->assertNull($result['final_version']);
        $this->assertSame(10, $result['counts']['r1']);
        $this->assertSame(10, $result['counts']['r2']);
    }

    #[Test]
    public function getJobUrlsReturnsUrlsWhenNoPasswordsMatch(): void
    {
        $segment = new ShapelessConcreteStruct();
        $segment->id = 100;
        $segment->id_job = 200;
        $segment->job_password = 'pwd123';
        $segment->project_name = 'Test Project';
        $segment->source = 'en-US';
        $segment->target = 'it-IT';

        $result = $this->invokePrivate('getJobUrls', [$segment, []]);

        $this->assertIsArray($result);
    }

    #[Test]
    public function getJobUrlsReturnsUrlsWhenPasswordsMatch(): void
    {
        $segment = new ShapelessConcreteStruct();
        $segment->id = self::TEST_SEGMENT_1;
        $segment->id_job = self::TEST_JOB_ID;
        $segment->job_password = self::TEST_JOB_PASSWORD;
        $segment->project_name = 'TestSegAnalysis';
        $segment->source = 'en-US';
        $segment->target = 'it-IT';

        $passwordsMap = [
            [
                'id_job' => self::TEST_JOB_ID,
                't_password' => self::TEST_JOB_PASSWORD,
                'r_password' => 'rev1pw',
                'r2_password' => 'rev2pw',
                'job_first_segment' => self::TEST_SEGMENT_1,
                'job_last_segment' => self::TEST_SEGMENT_3,
            ]
        ];

        $result = $this->invokePrivate('getJobUrls', [$segment, $passwordsMap]);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function getIssuesNotesAndIdRequestsReturnsAggregatesForSegments(): void
    {
        $segment = new ShapelessConcreteStruct();
        $segment->id = self::TEST_SEGMENT_1;

        $result = $this->invokePrivate('getIssuesNotesAndIdRequests', [[$segment]]);

        $this->assertArrayHasKey('notesAggregate', $result);
        $this->assertArrayHasKey('issuesAggregate', $result);
        $this->assertArrayHasKey('idRequestsAggregate', $result);
    }
}
