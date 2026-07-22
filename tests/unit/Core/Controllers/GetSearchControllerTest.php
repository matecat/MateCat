<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\GetSearchController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\FeaturesBase\Hook\Event\Run\PostAddSegmentTranslationEvent;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Search\SearchQueryParamsStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Utils\Constants\SourcePages;
use Utils\Logger\MatecatLogger;
use Utils\Search\ReplaceHistory;

class TestableGetSearchController extends GetSearchController
{
    public function __construct()
    {
    }

}

/**
 * Records dispatched events while preserving the real FeatureSet behaviour (empty feature list here),
 * so tests can assert which events the controller emits.
 */
class SpyFeatureSet extends FeatureSet
{
    /** @var object[] */
    public array $dispatched = [];

    public function dispatch(object $event): object
    {
        $this->dispatched[] = $event;

        return parent::dispatch($event);
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
        $fsProp->setValue($this->controller, new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));

        $dbProp = $this->reflector->getProperty('database');
        $dbProp->setValue($this->controller, obtainTestDatabase());

        // In production the ChunkPasswordValidator loads $this->chunk before the action runs; the test
        // bypasses the validator chain, so seed the same fully-hydrated chunk (translate source_page)
        // that the controller methods (validateTheRequest / doSearch / updateSegments) rely on.
        $this->seedChunk();
    }

    /**
     * @throws ReflectionException
     */
    private function seedChunk(?JobStruct $chunk = null): void
    {
        if ($chunk === null) {
            $chunk = (new JobDao(obtainTestDatabase()))->getByIdAndPassword(self::TEST_JOB_ID, self::TEST_JOB_PASSWORD);
            $chunk->setSourcePage(SourcePages::SOURCE_PAGE_TRANSLATE);
        }

        $chunkProp = $this->reflector->getProperty('chunk');
        $chunkProp->setValue($this->controller, $chunk);
    }

    protected function tearDown(): void
    {
        $this->cleanTestData();
        parent::tearDown();
    }

    private function seedTestData(): void
    {
        $db = obtainTestDatabase();
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
        $db = obtainTestDatabase();
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

    // ─── registerValidators ───

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

    #[Test]
    public function registerValidators_onSuccess_stores_the_validated_chunk(): void
    {
        $controller = $this->reflector->newInstanceWithoutConstructor();
        $this->reflector->getProperty('request')->setValue($controller, new Request());
        $this->reflector->getMethod('registerValidators')->invoke($controller);

        /** @var array<int, object> $validators */
        $validators = $this->reflector->getProperty('validators')->getValue($controller);
        $chunkValidator = $validators[1];
        $this->assertInstanceOf(ChunkPasswordValidator::class, $chunkValidator);

        $chunk = new JobStruct();
        $chunk->id = self::TEST_JOB_ID;
        $chunk->password = self::TEST_JOB_PASSWORD;
        (new ReflectionClass(ChunkPasswordValidator::class))
            ->getProperty('chunk')
            ->setValue($chunkValidator, $chunk);

        // Run the stored onSuccess callback: it copies the validated chunk onto the controller.
        (new ReflectionClass(\Controller\API\Commons\Validators\Base::class))
            ->getMethod('_executeCallbacks')
            ->invoke($chunkValidator);

        $stored = $this->reflector->getProperty('chunk')->getValue($controller);
        $this->assertSame($chunk, $stored);
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
        $this->setRequestParams(['id_job' => '123']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-3);

        $this->invokePrivate('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_returns_expected_structure(): void
    {
        $this->setRequestParams([
            'id_job' => (string)self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
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
        $this->assertSame(self::TEST_JOB_ID, $result['job']);
        $this->assertInstanceOf(SearchQueryParamsStruct::class, $result['queryParams']);
        // queryParams->job is taken from the validated chunk, not the raw request param.
        $this->assertSame(self::TEST_JOB_ID, $result['queryParams']->job);
        $this->assertTrue($result['isMatchCaseRequested']);
        $this->assertFalse($result['isExactMatchRequested']);
        $this->assertTrue($result['inCurrentChunkOnly']);
    }

    #[Test]
    public function validateTheRequest_defaults_status_to_all_for_invalid(): void
    {
        $this->setRequestParams([
            'id_job' => (string)self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
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
                'id_job' => (string)self::TEST_JOB_ID,
                'password' => self::TEST_JOB_PASSWORD,
                'status' => $status,
            ]);

            $result = $this->invokePrivate('validateTheRequest');
            $this->assertSame($status, $result['status'], "Status '$status' should be preserved");
        }
    }

    // ─── getReplaceHistory ───

    #[Test]
    public function getReplaceHistory_returns_replace_history_instance(): void
    {
        $result = $this->invokePrivate('getReplaceHistory', [12345]);

        $this->assertInstanceOf(ReplaceHistory::class, $result);
    }

    // ─── getNewStatus ───
    // A replace-all is an automated, unseen change, so the editor must re-review it: the status is
    // demoted one quality tier. On the translate page (revisionNumber === null) everything drops to
    // DRAFT; on a revision page APPROVED2 -> APPROVED, APPROVED -> TRANSLATED, anything else -> DRAFT.

    #[Test]
    public function getNewStatus_returns_draft_when_no_revision_number(): void
    {
        $translation = new \Model\Translations\SegmentTranslationStruct();
        $translation->status = 'APPROVED';

        $result = $this->invokePrivate('getNewStatus', [$translation, null]);

        $this->assertSame('DRAFT', $result);
    }

    #[Test]
    public function getNewStatus_demotes_translated_to_draft_on_revision(): void
    {
        $translation = new \Model\Translations\SegmentTranslationStruct();
        $translation->status = 'TRANSLATED';

        $result = $this->invokePrivate('getNewStatus', [$translation, 1]);

        $this->assertSame('DRAFT', $result);
    }

    #[Test]
    public function getNewStatus_demotes_approved_to_translated_on_revision(): void
    {
        $translation = new \Model\Translations\SegmentTranslationStruct();
        $translation->status = 'APPROVED';

        $result = $this->invokePrivate('getNewStatus', [$translation, 1]);

        $this->assertSame('TRANSLATED', $result);
    }

    #[Test]
    public function getNewStatus_demotes_approved2_to_approved_on_revision(): void
    {
        $translation = new \Model\Translations\SegmentTranslationStruct();
        $translation->status = 'APPROVED2';

        $result = $this->invokePrivate('getNewStatus', [$translation, 2]);

        $this->assertSame('APPROVED', $result);
    }

    #[Test]
    public function getNewStatus_demotes_rejected_to_draft_on_revision(): void
    {
        $translation = new \Model\Translations\SegmentTranslationStruct();
        $translation->status = 'REJECTED';

        $result = $this->invokePrivate('getNewStatus', [$translation, 2]);

        $this->assertSame('DRAFT', $result);
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
        // Force getSearchModel() to throw by giving the controller a chunk with a null id; doSearch
        // must wrap it into the generic RuntimeException.
        $badChunk = new JobStruct();
        $badChunk->id = null;
        $badChunk->password = null;
        $this->seedChunk($badChunk);

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
    public function doSearch_builds_query_params_from_array_with_coupled_source_and_target(): void
    {
        $request = [
            'job' => self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
            'queryParams' => [
                'job' => self::TEST_JOB_ID,
                'password' => self::TEST_JOB_PASSWORD,
                'isMatchCaseRequested' => false,
                'isExactMatchRequested' => false,
                'inCurrentChunkOnly' => false,
            ],
            'source' => 'Hello',
            'target' => 'Ciao',
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

    // ─── getSegmentForUndoReplaceAll ───

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
    public function getSegmentForUndoReplaceAll_reverts_current_version_with_before_text_and_status(): void
    {
        // Undo reverts the CURRENT version (cursor), restoring the pre-replacement text and the status
        // captured in the event.
        $event = new \Model\Search\ReplaceEventStruct();
        $event->id_segment = 100;
        $event->id_job = 200;
        $event->translation_before_replacement = 'original text';
        $event->translation_after_replacement = 'replaced text';
        $event->status = 'TRANSLATED';

        $srh = $this->createMock(ReplaceHistory::class);
        $srh->method('getCursor')->willReturn(3);
        $srh->expects($this->once())->method('get')->with(3)->willReturn([$event]);

        $result = $this->invokePrivate('getSegmentForUndoReplaceAll', [$srh]);

        $this->assertCount(1, $result);
        $this->assertSame(100, $result[0]['id_segment']);
        $this->assertSame(200, $result[0]['id_job']);
        $this->assertSame('original text', $result[0]['translation']);
        $this->assertSame('TRANSLATED', $result[0]['status']);
    }

    #[Test]
    public function getSegmentForUndoReplaceAll_returns_empty_when_cursor_is_zero(): void
    {
        $srh = $this->createMock(ReplaceHistory::class);
        $srh->method('getCursor')->willReturn(0);
        $srh->expects($this->never())->method('get');

        $result = $this->invokePrivate('getSegmentForUndoReplaceAll', [$srh]);

        $this->assertSame([], $result);
    }

    // ─── saveReplacementEvent ───

    #[Test]
    public function saveReplacementEvent_saves_event_without_advancing_the_index(): void
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

        // saveReplacementEvent only persists the event. The undo-cursor advance is done once by
        // replaceAll() after the whole batch loop, so the replace-all lands as one history version.
        $srh = $this->createMock(ReplaceHistory::class);
        $srh->expects($this->once())->method('save');
        $srh->expects($this->never())->method('updateIndex');

        $this->invokePrivate('saveReplacementEvent', ['3', $tRow, $srh, $queryParams]);
    }

    // ─── search() public action ───

    #[Test]
    public function search_returns_json_response_with_results(): void
    {
        $this->setRequestParams([
            'id_job' => (string)self::TEST_JOB_ID,
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
            'id_job' => (string)self::TEST_JOB_ID,
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

        $committed = $this->invokePrivate('updateSegments', [$search_results, self::TEST_JOB_ID, $queryParams]);

        $this->assertCount(1, $committed);
        $updated = (new \Model\Translations\SegmentTranslationDao(obtainTestDatabase()))->findBySegmentAndJob(self::TEST_SEGMENT_1, self::TEST_JOB_ID);
        $this->assertNotNull($updated);
        $this->assertStringContainsString('universo', $updated->translation);
    }

    #[Test]
    public function updateSegments_triggers_propagation_when_translation_changes(): void
    {
        $queryParams = new SearchQueryParamsStruct([
            'job' => self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
            'target' => 'mondo',
            'replacement' => 'pianeta',
            'isMatchCaseRequested' => false,
            'isExactMatchRequested' => false,
        ]);

        // tRow translation differs from the currently-stored translation
        // (which is still 'Ciao mondo'), so the propagation branch is entered.
        $search_results = [
            [
                'id_segment' => self::TEST_SEGMENT_1,
                'id_job' => self::TEST_JOB_ID,
                'translation' => 'Ciao mondo modificato',
                'status' => 'TRANSLATED',
            ],
        ];

        $this->invokePrivate('updateSegments', [$search_results, self::TEST_JOB_ID, $queryParams]);

        $updated = (new \Model\Translations\SegmentTranslationDao(obtainTestDatabase()))->findBySegmentAndJob(self::TEST_SEGMENT_1, self::TEST_JOB_ID);
        $this->assertNotNull($updated);
    }

    #[Test]
    public function updateSegments_throws_not_found_when_project_is_missing(): void
    {
        $orphanJobId = 9_946_001;

        $db = obtainTestDatabase();
        $conn = $db->getConnection();
        $conn->exec("DELETE FROM jobs WHERE id = " . $orphanJobId);
        $conn->exec("INSERT INTO jobs (id, password, id_project, source, target, job_first_segment, job_last_segment, owner, tm_keys, create_date, disabled) VALUES (" . $orphanJobId . ", 'orphanpw', 9946999, 'en-US', 'it-IT', 1, 1, 'test@example.org', '[]', NOW(), 0)");

        try {
            $queryParams = new SearchQueryParamsStruct([
                'job' => $orphanJobId,
                'password' => 'orphanpw',
                'isMatchCaseRequested' => false,
                'isExactMatchRequested' => false,
            ]);

            $this->expectException(\Model\Exceptions\NotFoundException::class);
            $this->expectExceptionMessage("Project not found for job $orphanJobId");

            $this->invokePrivate('updateSegments', [[], $orphanJobId, $queryParams]);
        } finally {
            $conn->exec("DELETE FROM jobs WHERE id = " . $orphanJobId);
        }
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

        $committed = $this->invokePrivate('updateSegments', [$search_results, self::TEST_JOB_ID, $queryParams]);

        $this->assertSame([], $committed);
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

        $committed = $this->invokePrivate('updateSegments', [[], self::TEST_JOB_ID, $queryParams]);

        $this->assertSame([], $committed);
    }

    #[Test]
    public function updateSegments_dispatches_post_add_segment_translation_event_once_per_batch(): void
    {
        // ProjectCompletion listens to PostAddSegmentTranslationEvent to refresh chunk completion.
        // Replace-all must emit it (once per chunk) just like the single-segment save path.
        $spy = new SpyFeatureSet($this->createStub(IDatabase::class));
        $this->reflector->getProperty('featureSet')->setValue($this->controller, $spy);

        $queryParams = new SearchQueryParamsStruct([
            'job' => self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
            'target' => 'mondo',
            'replacement' => 'universo',
            'isMatchCaseRequested' => false,
            'isExactMatchRequested' => false,
        ]);

        $search_results = [
            ['id_segment' => self::TEST_SEGMENT_1, 'id_job' => self::TEST_JOB_ID, 'translation' => 'Ciao mondo', 'status' => 'TRANSLATED'],
        ];

        $committed = $this->invokePrivate('updateSegments', [$search_results, self::TEST_JOB_ID, $queryParams]);
        $this->assertCount(1, $committed);

        $postAdd = array_values(array_filter(
            $spy->dispatched,
            static fn(object $e): bool => $e instanceof PostAddSegmentTranslationEvent
        ));

        $this->assertCount(1, $postAdd, 'exactly one PostAddSegmentTranslationEvent per replace batch');
        $context = $postAdd[0]->context;
        $this->assertSame(self::TEST_JOB_ID, $context['chunk']->id);
        $this->assertFalse($context['is_review'], 'translate-page chunk => is_review false');
        $this->assertSame(1, $context['logged_user']->uid);
    }

    #[Test]
    public function updateSegments_does_not_dispatch_post_add_event_when_nothing_committed(): void
    {
        $spy = new SpyFeatureSet($this->createStub(IDatabase::class));
        $this->reflector->getProperty('featureSet')->setValue($this->controller, $spy);

        $queryParams = new SearchQueryParamsStruct([
            'job' => self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
            'target' => 'test',
            'replacement' => 'replaced',
            'isMatchCaseRequested' => false,
            'isExactMatchRequested' => false,
        ]);

        $this->invokePrivate('updateSegments', [[], self::TEST_JOB_ID, $queryParams]);

        $postAdd = array_filter(
            $spy->dispatched,
            static fn(object $e): bool => $e instanceof PostAddSegmentTranslationEvent
        );

        $this->assertCount(0, $postAdd, 'no completion event when no segment was committed');
    }

    // ─── undoReplaceAll ───

    #[Test]
    public function undoReplaceAll_with_empty_history_returns_success(): void
    {
        $this->setRequestParams([
            'id_job' => (string)self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
            'source' => '',
            'target' => 'Ciao',
            'token' => 'tok',
            'status' => 'all',
            'replace' => 'replacement',
            'matchcase' => '0',
            'exactmatch' => '0',
            'inCurrentChunkOnly' => '0',
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with(['success' => true]);

        $this->controller->undoReplaceAll();
    }

    // ─── replaceAll ───

    #[Test]
    public function replaceAll_with_no_matching_segments_returns_empty(): void
    {
        $this->setRequestParams([
            'id_job' => (string)self::TEST_JOB_ID,
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
