<?php

namespace unit\Controllers;

use Controller\API\App\GetSearchController;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Search\SearchQueryParamsStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;
use Utils\Search\ReplaceHistory;

class TestableGetSearchController extends GetSearchController
{
    public function __construct()
    {
    }

    protected function afterConstruct(): void
    {
    }
}

#[AllowMockObjectsWithoutExpectations]
class GetSearchControllerTest extends AbstractTest
{
    private const int TEST_PROJECT_ID = 9999001;
    private const int TEST_JOB_ID = 9999002;
    private const string TEST_JOB_PASSWORD = 'test_pw_abc';
    private const int TEST_SEGMENT_1 = 9999003;
    private const int TEST_SEGMENT_2 = 9999004;
    private const int TEST_SEGMENT_3 = 9999005;
    private const int TEST_FILE_ID = 9999006;

    private ReflectionClass $reflector;
    private TestableGetSearchController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws ReflectionException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedTestData();

        $this->controller = new TestableGetSearchController();
        $this->reflector = new ReflectionClass(GetSearchController::class);

        $this->requestStub = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $reqProp = $this->reflector->getProperty('request');
        $reqProp->setValue($this->controller, $this->requestStub);

        $resProp = $this->reflector->getProperty('response');
        $resProp->setValue($this->controller, $this->responseMock);

        $user = new UserStruct();
        $user->uid = 1;
        $user->email = 'test@example.org';
        $user->first_name = 'Test';
        $user->last_name = 'User';

        $userProp = $this->reflector->getProperty('user');
        $userProp->setValue($this->controller, $user);

        $logProp = $this->reflector->getProperty('logger');
        $logProp->setValue($this->controller, $this->createMock(MatecatLogger::class));

        $fsProp = $this->reflector->getProperty('featureSet');
        $fsProp->setValue($this->controller, new FeatureSet());
    }

    protected function tearDown(): void
    {
        $this->cleanTestData();
        parent::tearDown();
    }

    private function seedTestData(): void
    {
        $db = Database::obtain();
        $conn = $db->getConnection();

        $this->cleanTestData();

        $conn->exec("INSERT INTO projects (id, id_customer, password, name, create_date, status_analysis) VALUES (" . self::TEST_PROJECT_ID . ", 'test@example.org', 'projpw', 'TestSearchProject', NOW(), 'DONE')");

        $conn->exec("INSERT INTO files (id, id_project, filename, source_language, mime_type) VALUES (" . self::TEST_FILE_ID . ", " . self::TEST_PROJECT_ID . ", 'test.xliff', 'en-US', 'application/xliff+xml')");

        $conn->exec("INSERT INTO jobs (id, password, id_project, source, target, job_first_segment, job_last_segment, owner, tm_keys, create_date, disabled) VALUES (" . self::TEST_JOB_ID . ", '" . self::TEST_JOB_PASSWORD . "', " . self::TEST_PROJECT_ID . ", 'en-US', 'it-IT', " . self::TEST_SEGMENT_1 . ", " . self::TEST_SEGMENT_3 . ", 'test@example.org', '[]', NOW(), 0)");

        $conn->exec("INSERT INTO segments (id, id_file, internal_id, segment, segment_hash, raw_word_count) VALUES
            (" . self::TEST_SEGMENT_1 . ", " . self::TEST_FILE_ID . ", '1', 'Hello world', 'hash1_test_search', 2),
            (" . self::TEST_SEGMENT_2 . ", " . self::TEST_FILE_ID . ", '2', 'Good morning', 'hash2_test_search', 2),
            (" . self::TEST_SEGMENT_3 . ", " . self::TEST_FILE_ID . ", '3', 'Goodbye', 'hash3_test_search', 1)
        ");

        $conn->exec("INSERT INTO segment_translations (id_segment, id_job, segment_hash, translation, status, version_number, translation_date) VALUES
            (" . self::TEST_SEGMENT_1 . ", " . self::TEST_JOB_ID . ", 'hash1_test_search', 'Ciao mondo', 'TRANSLATED', 0, NOW()),
            (" . self::TEST_SEGMENT_2 . ", " . self::TEST_JOB_ID . ", 'hash2_test_search', 'Buongiorno amico', 'TRANSLATED', 0, NOW()),
            (" . self::TEST_SEGMENT_3 . ", " . self::TEST_JOB_ID . ", 'hash3_test_search', 'Arrivederci', 'TRANSLATED', 0, NOW())
        ");
    }

    private function cleanTestData(): void
    {
        $db = Database::obtain();
        $conn = $db->getConnection();

        $conn->exec("DELETE FROM segment_translations WHERE id_job = " . self::TEST_JOB_ID);
        $conn->exec("DELETE FROM segments WHERE id_file = " . self::TEST_FILE_ID);
        $conn->exec("DELETE FROM jobs WHERE id = " . self::TEST_JOB_ID);
        $conn->exec("DELETE FROM files WHERE id = " . self::TEST_FILE_ID);
        $conn->exec("DELETE FROM projects WHERE id = " . self::TEST_PROJECT_ID);
    }

    /**
     * @throws ReflectionException
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        $m = $this->reflector->getMethod($method);

        return $m->invoke($this->controller, ...$args);
    }

    private function setRequestParams(array $params): void
    {
        $serverParams = ['REQUEST_URI' => '/api/app/search', 'REQUEST_METHOD' => 'POST'];
        $this->requestStub = new Request($params, [], [], $serverParams);
        $reqProp = $this->reflector->getProperty('request');
        $reqProp->setValue($this->controller, $this->requestStub);
    }

    // ─── validateTheRequest ───

    #[Test]
    public function validateTheRequest_throws_on_missing_job(): void
    {
        $this->setRequestParams(['password' => 'abc123']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-2);

        $this->invokePrivate('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_throws_on_missing_password(): void
    {
        $this->setRequestParams(['job' => '123']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-3);

        $this->invokePrivate('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_returns_expected_structure(): void
    {
        $this->setRequestParams([
            'job' => '999',
            'password' => 'mypass',
            'token' => 'tok123',
            'source' => 'hello',
            'target' => 'ciao',
            'status' => 'translated',
            'replace' => 'world',
            'matchcase' => '1',
            'exactmatch' => '0',
            'inCurrentChunkOnly' => '1',
            'revision_number' => '2',
        ]);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertIsArray($result);
        $this->assertSame(999, $result['job']);
        $this->assertInstanceOf(SearchQueryParamsStruct::class, $result['queryParams']);
        $this->assertSame(999, $result['queryParams']->job);
        $this->assertTrue($result['isMatchCaseRequested']);
        $this->assertFalse($result['isExactMatchRequested']);
        $this->assertTrue($result['inCurrentChunkOnly']);
    }

    #[Test]
    public function validateTheRequest_defaults_status_to_all_for_invalid(): void
    {
        $this->setRequestParams([
            'job' => '100',
            'password' => 'pw',
            'status' => 'invalid_status',
        ]);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertSame('all', $result['status']);
    }

    #[Test]
    public function validateTheRequest_accepts_valid_statuses(): void
    {
        $validStatuses = ['translated', 'approved', 'approved2', 'rejected', 'draft', 'new'];

        foreach ($validStatuses as $status) {
            $this->setRequestParams([
                'job' => '100',
                'password' => 'pw',
                'status' => $status,
            ]);

            $result = $this->invokePrivate('validateTheRequest');
            $this->assertSame($status, $result['status'], "Status '$status' should be preserved");
        }
    }

    // ─── getJobData ───

    #[Test]
    public function getJobData_returns_job_struct(): void
    {
        $job = $this->invokePrivate('getJobData', [self::TEST_JOB_ID, self::TEST_JOB_PASSWORD]);

        $this->assertInstanceOf(JobStruct::class, $job);
        $this->assertSame(self::TEST_JOB_ID, $job->id);
    }

    // ─── getReplaceHistory ───

    #[Test]
    public function getReplaceHistory_returns_replace_history_instance(): void
    {
        $result = $this->invokePrivate('getReplaceHistory', [12345]);

        $this->assertInstanceOf(ReplaceHistory::class, $result);
    }

    // ─── getNewStatus ───

    #[Test]
    public function getNewStatus_returns_translated_when_no_revision_number(): void
    {
        $translation = new \Model\Translations\SegmentTranslationStruct();
        $translation->status = 'APPROVED';

        $result = $this->invokePrivate('getNewStatus', [$translation, null]);

        $this->assertSame('TRANSLATED', $result);
    }

    #[Test]
    public function getNewStatus_returns_translated_when_status_is_translated(): void
    {
        $translation = new \Model\Translations\SegmentTranslationStruct();
        $translation->status = 'TRANSLATED';

        $result = $this->invokePrivate('getNewStatus', [$translation, 1]);

        $this->assertSame('TRANSLATED', $result);
    }

    #[Test]
    public function getNewStatus_returns_approved_when_revision_number_set_and_not_translated(): void
    {
        $translation = new \Model\Translations\SegmentTranslationStruct();
        $translation->status = 'APPROVED';

        $result = $this->invokePrivate('getNewStatus', [$translation, 1]);

        $this->assertSame('APPROVED', $result);
    }

    #[Test]
    public function getNewStatus_returns_approved_for_rejected_status_with_revision(): void
    {
        $translation = new \Model\Translations\SegmentTranslationStruct();
        $translation->status = 'REJECTED';

        $result = $this->invokePrivate('getNewStatus', [$translation, 2]);

        $this->assertSame('APPROVED', $result);
    }

    // ─── getReplacedSegmentTranslation ───

    #[Test]
    public function getReplacedSegmentTranslation_replaces_text(): void
    {
        $queryParams = new SearchQueryParamsStruct([
            'job' => 1,
            'password' => 'pw',
            'target' => 'hello',
            'replacement' => 'world',
            'isMatchCaseRequested' => false,
            'isExactMatchRequested' => false,
        ]);

        $result = $this->invokePrivate('getReplacedSegmentTranslation', ['say hello to all', $queryParams]);

        $this->assertSame('say world to all', $result);
    }

    #[Test]
    public function getReplacedSegmentTranslation_returns_original_when_no_match(): void
    {
        $queryParams = new SearchQueryParamsStruct([
            'job' => 1,
            'password' => 'pw',
            'target' => 'xyz_not_found',
            'replacement' => 'replaced',
            'isMatchCaseRequested' => false,
            'isExactMatchRequested' => false,
        ]);

        $result = $this->invokePrivate('getReplacedSegmentTranslation', ['original text', $queryParams]);

        $this->assertSame('original text', $result);
    }

    #[Test]
    public function getReplacedSegmentTranslation_handles_null_target_gracefully(): void
    {
        $queryParams = new SearchQueryParamsStruct([
            'job' => 1,
            'password' => 'pw',
            'target' => null,
            'replacement' => 'replaced',
            'isMatchCaseRequested' => false,
            'isExactMatchRequested' => false,
        ]);

        $result = $this->invokePrivate('getReplacedSegmentTranslation', ['original text', $queryParams]);

        $this->assertSame('original text', $result);
    }

    #[Test]
    public function getReplacedSegmentTranslation_handles_case_sensitive(): void
    {
        $queryParams = new SearchQueryParamsStruct([
            'job' => 1,
            'password' => 'pw',
            'target' => 'Hello',
            'replacement' => 'World',
            'isMatchCaseRequested' => true,
            'isExactMatchRequested' => false,
        ]);

        $result = $this->invokePrivate('getReplacedSegmentTranslation', ['Hello there', $queryParams]);
        $this->assertSame('World there', $result);

        $resultNoMatch = $this->invokePrivate('getReplacedSegmentTranslation', ['hello there', $queryParams]);
        $this->assertSame('hello there', $resultNoMatch);
    }

    // ─── getSearchModel ───

    #[Test]
    public function getSearchModel_throws_on_null_job_id(): void
    {
        $queryParams = new SearchQueryParamsStruct([
            'job' => 1,
            'password' => 'pw',
            'isMatchCaseRequested' => false,
            'isExactMatchRequested' => false,
        ]);

        $jobStruct = new JobStruct();
        $jobStruct->id = null;
        $jobStruct->password = 'pw';
        $jobStruct->source = 'en-US';
        $jobStruct->target = 'it-IT';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Job struct has null id or password");

        $this->invokePrivate('getSearchModel', [$queryParams, $jobStruct]);
    }

    #[Test]
    public function getSearchModel_returns_search_model_with_valid_job(): void
    {
        $queryParams = new SearchQueryParamsStruct([
            'job' => self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
            'isMatchCaseRequested' => false,
            'isExactMatchRequested' => false,
        ]);

        $jobStruct = new JobStruct();
        $jobStruct->id = self::TEST_JOB_ID;
        $jobStruct->password = self::TEST_JOB_PASSWORD;
        $jobStruct->source = 'en-US';
        $jobStruct->target = 'it-IT';

        $result = $this->invokePrivate('getSearchModel', [$queryParams, $jobStruct]);

        $this->assertInstanceOf(\Model\Search\SearchModel::class, $result);
    }

    // ─── doSearch ───

    #[Test]
    public function doSearch_throws_runtime_exception_on_internal_error(): void
    {
        $request = [
            'job' => 99999999,
            'password' => 'nonexistent',
            'queryParams' => new SearchQueryParamsStruct([
                'job' => 99999999,
                'password' => 'nonexistent',
                'isMatchCaseRequested' => false,
                'isExactMatchRequested' => false,
            ]),
            'source' => '',
            'target' => '',
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("internal error: see the log");

        $this->invokePrivate('doSearch', [$request]);
    }

    #[Test]
    public function doSearch_finds_target_in_seeded_translations(): void
    {
        $queryParams = new SearchQueryParamsStruct([
            'job' => self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
            'isMatchCaseRequested' => false,
            'isExactMatchRequested' => false,
            'inCurrentChunkOnly' => false,
        ]);

        $request = [
            'job' => self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
            'queryParams' => $queryParams,
            'source' => '',
            'target' => 'Ciao',
        ];

        $result = $this->invokePrivate('doSearch', [$request]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('count', $result);
        $this->assertArrayHasKey('sid_list', $result);
    }

    #[Test]
    public function doSearch_returns_zero_when_no_match(): void
    {
        $queryParams = new SearchQueryParamsStruct([
            'job' => self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
            'isMatchCaseRequested' => false,
            'isExactMatchRequested' => false,
            'inCurrentChunkOnly' => false,
        ]);

        $request = [
            'job' => self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
            'queryParams' => $queryParams,
            'source' => '',
            'target' => 'nonexistent_xyz_999',
        ];

        $result = $this->invokePrivate('doSearch', [$request]);

        $this->assertSame(0, $result['count']);
    }

    #[Test]
    public function doSearch_sets_key_to_source_when_only_source_provided(): void
    {
        $queryParams = new SearchQueryParamsStruct([
            'job' => self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
            'isMatchCaseRequested' => false,
            'isExactMatchRequested' => false,
            'inCurrentChunkOnly' => false,
        ]);

        $request = [
            'job' => self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
            'queryParams' => $queryParams,
            'source' => 'Hello',
            'target' => '',
        ];

        $result = $this->invokePrivate('doSearch', [$request]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('count', $result);
        $this->assertArrayHasKey('sid_list', $result);
    }

    #[Test]
    public function doSearch_status_only_mode(): void
    {
        $queryParams = new SearchQueryParamsStruct([
            'job' => self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
            'isMatchCaseRequested' => false,
            'isExactMatchRequested' => false,
            'inCurrentChunkOnly' => false,
            'status' => 'TRANSLATED',
        ]);

        $request = [
            'job' => self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
            'queryParams' => $queryParams,
            'source' => '',
            'target' => '',
        ];

        $result = $this->invokePrivate('doSearch', [$request]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('count', $result);
        $this->assertArrayHasKey('sid_list', $result);
    }

    // ─── getSegmentForRedoReplaceAll / getSegmentForUndoReplaceAll ───

    #[Test]
    public function getSegmentForRedoReplaceAll_returns_empty_array_when_no_events(): void
    {
        $srh = $this->createMock(ReplaceHistory::class);
        $srh->method('getCursor')->willReturn(0);
        $srh->method('get')->willReturn([]);

        $result = $this->invokePrivate('getSegmentForRedoReplaceAll', [$srh]);

        $this->assertSame([], $result);
    }

    #[Test]
    public function getSegmentForUndoReplaceAll_returns_empty_array_when_no_events(): void
    {
        $srh = $this->createMock(ReplaceHistory::class);
        $srh->method('getCursor')->willReturn(0);
        $srh->method('get')->willReturn([]);

        $result = $this->invokePrivate('getSegmentForUndoReplaceAll', [$srh]);

        $this->assertSame([], $result);
    }

    #[Test]
    public function getSegmentForUndoReplaceAll_uses_cursor_minus_one_for_cursor_above_one(): void
    {
        $event = new \Model\Search\ReplaceEventStruct();
        $event->id_segment = 100;
        $event->id_job = 200;
        $event->translation_after_replacement = 'replaced text';
        $event->status = 'TRANSLATED';

        $srh = $this->createMock(ReplaceHistory::class);
        $srh->method('getCursor')->willReturn(3);
        $srh->expects($this->once())->method('get')->with(2)->willReturn([$event]);

        $result = $this->invokePrivate('getSegmentForUndoReplaceAll', [$srh]);

        $this->assertCount(1, $result);
        $this->assertSame(100, $result[0]['id_segment']);
        $this->assertSame(200, $result[0]['id_job']);
        $this->assertSame('replaced text', $result[0]['translation']);
    }

    #[Test]
    public function getSegmentForUndoReplaceAll_uses_cursor_1_when_cursor_is_1(): void
    {
        $event = new \Model\Search\ReplaceEventStruct();
        $event->id_segment = 10;
        $event->id_job = 20;
        $event->translation_after_replacement = 'after';
        $event->status = 'TRANSLATED';

        $srh = $this->createMock(ReplaceHistory::class);
        $srh->method('getCursor')->willReturn(1);
        $srh->expects($this->once())->method('get')->with(1)->willReturn([$event]);

        $result = $this->invokePrivate('getSegmentForUndoReplaceAll', [$srh]);

        $this->assertCount(1, $result);
        $this->assertSame(10, $result[0]['id_segment']);
    }

    #[Test]
    public function getSegmentForRedoReplaceAll_maps_events_correctly(): void
    {
        $event = new \Model\Search\ReplaceEventStruct();
        $event->id_segment = 50;
        $event->id_job = 60;
        $event->translation_before_replacement = 'before text';
        $event->status = 'APPROVED';

        $srh = $this->createMock(ReplaceHistory::class);
        $srh->method('getCursor')->willReturn(1);
        $srh->expects($this->once())->method('get')->with(2)->willReturn([$event]);

        $result = $this->invokePrivate('getSegmentForRedoReplaceAll', [$srh]);

        $this->assertCount(1, $result);
        $this->assertSame(50, $result[0]['id_segment']);
        $this->assertSame(60, $result[0]['id_job']);
        $this->assertSame('before text', $result[0]['translation']);
        $this->assertSame('APPROVED', $result[0]['status']);
    }

    // ─── saveReplacementEvent ───

    #[Test]
    public function saveReplacementEvent_calls_srh_save_and_update_index(): void
    {
        $queryParams = new SearchQueryParamsStruct([
            'job' => 100,
            'password' => 'pw',
            'source' => 'src',
            'target' => 'hello',
            'replacement' => 'world',
            'isMatchCaseRequested' => false,
            'isExactMatchRequested' => false,
        ]);

        $tRow = [
            'id_segment' => 55,
            'translation' => 'say hello',
            'status' => 'TRANSLATED',
        ];

        $srh = $this->createMock(ReplaceHistory::class);
        $srh->expects($this->once())->method('save');
        $srh->expects($this->once())->method('updateIndex')->with('3');

        $this->invokePrivate('saveReplacementEvent', ['3', $tRow, $srh, $queryParams]);
    }

    // ─── search() public action ───

    #[Test]
    public function search_returns_json_response_with_results(): void
    {
        $this->setRequestParams([
            'job' => (string)self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
            'source' => '',
            'target' => 'Ciao',
            'token' => 'mytoken',
            'status' => 'all',
            'matchcase' => '0',
            'exactmatch' => '0',
            'inCurrentChunkOnly' => '0',
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('total', $data);
                $this->assertArrayHasKey('segments', $data);
                return true;
            }));

        $this->controller->search();
    }

    #[Test]
    public function search_throws_on_missing_job(): void
    {
        $this->setRequestParams(['password' => 'abc']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-2);

        $this->controller->search();
    }

    #[Test]
    public function search_with_no_match_returns_zero(): void
    {
        $this->setRequestParams([
            'job' => (string)self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
            'source' => '',
            'target' => 'nonexistent_unique_xyz_99',
            'token' => 'tok',
            'status' => 'all',
            'matchcase' => '0',
            'exactmatch' => '0',
            'inCurrentChunkOnly' => '0',
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame(0, $data['total']);
                return true;
            }));

        $this->controller->search();
    }

    // ─── updateSegments ───

    #[Test]
    public function updateSegments_performs_replacement_on_seeded_segment(): void
    {
        $queryParams = new SearchQueryParamsStruct([
            'job' => self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
            'target' => 'mondo',
            'replacement' => 'universo',
            'isMatchCaseRequested' => false,
            'isExactMatchRequested' => false,
        ]);

        $search_results = [
            [
                'id_segment' => self::TEST_SEGMENT_1,
                'id_job' => self::TEST_JOB_ID,
                'translation' => 'Ciao mondo',
                'status' => 'TRANSLATED',
            ],
        ];

        $this->invokePrivate('updateSegments', [
            $search_results,
            self::TEST_JOB_ID,
            self::TEST_JOB_PASSWORD,
            $queryParams,
            null,
            null
        ]);

        $updated = \Model\Translations\SegmentTranslationDao::findBySegmentAndJob(self::TEST_SEGMENT_1, self::TEST_JOB_ID);
        $this->assertNotNull($updated);
        $this->assertStringContainsString('universo', $updated->translation);
    }

    #[Test]
    public function updateSegments_skips_when_old_translation_is_null(): void
    {
        $queryParams = new SearchQueryParamsStruct([
            'job' => self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
            'target' => 'test',
            'replacement' => 'replaced',
            'isMatchCaseRequested' => false,
            'isExactMatchRequested' => false,
        ]);

        $search_results = [
            ['id_segment' => 888888888, 'id_job' => self::TEST_JOB_ID, 'translation' => 'text', 'status' => 'TRANSLATED'],
        ];

        $this->invokePrivate('updateSegments', [$search_results, self::TEST_JOB_ID, self::TEST_JOB_PASSWORD, $queryParams, null, null]);

        $this->assertTrue(true);
    }

    #[Test]
    public function updateSegments_empty_search_results_does_nothing(): void
    {
        $queryParams = new SearchQueryParamsStruct([
            'job' => self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
            'target' => 'test',
            'replacement' => 'replaced',
            'isMatchCaseRequested' => false,
            'isExactMatchRequested' => false,
        ]);

        $this->invokePrivate('updateSegments', [[], self::TEST_JOB_ID, self::TEST_JOB_PASSWORD, $queryParams, null, null]);

        $this->assertTrue(true);
    }

    // ─── replaceAll ───

    #[Test]
    public function replaceAll_with_no_matching_segments_returns_empty(): void
    {
        $this->setRequestParams([
            'job' => (string)self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
            'source' => '',
            'target' => 'nonexistent_unique_xyz_99',
            'token' => 'tok',
            'status' => 'all',
            'replace' => 'replacement_text',
            'matchcase' => '0',
            'exactmatch' => '0',
            'inCurrentChunkOnly' => '0',
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame(0, $data['total']);
                return true;
            }));

        $this->controller->replaceAll();
    }
}
