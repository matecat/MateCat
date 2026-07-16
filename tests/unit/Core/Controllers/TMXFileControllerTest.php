<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\TMXFileController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;
use Utils\TMS\TMSService;

/**
 * Overrides the createTMSService() seam (added to {@see TMXFileController})
 * so import()/importStatus() can be exercised without constructing a real
 * TMSService — which would otherwise reach the MyMemory engine's
 * addTmxInMyMemory()/tmxUploadStatus() -> AbstractEngine::_call() ->
 * MultiCurlHandler, a real outbound HTTP call with no test-env guard. This
 * mirrors the campaign's sanctioned "refactor construction behind an
 * overridable protected seam, return a createStub" pattern used by
 * RequestExportTMXController.
 */
class TestableTMXFileController extends TMXFileController
{
    public ?TMSService $tmsServiceStub = null;

    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }

    protected function registerValidators(): void
    {
    }

    protected function createTMSService(): TMSService
    {
        return $this->tmsServiceStub ?? parent::createTMSService();
    }
}

/**
 * Real-DB suite for {@see TMXFileController}.
 *
 * Reserved ID block (Playbook §4): base 9_010_000 (task N=10).
 *   base+1 project, base+2 job, base+3 segment, base+4 file, base+6 user/uid.
 * Per-suite owner email: ctrltest_9010000@example.org.
 *
 * The memory_keys row exercised by import()'s KeyRing-update branch must
 * carry uid = the authenticated test user (FK-correct), so it reuses this
 * suite's existing userId(BASE) = 9_010_006 rather than a disjoint block;
 * cleaned up by cleanMemoryKey() alongside cleanFragments(). A second
 * reserved ID block, 9_983_000..9_983_999, is available to this pass but
 * unused since no independent row (not tied to the authenticated uid) is
 * needed here.
 *
 * The public actions import()/importStatus() both delegate to
 * Utils\TMS\TMSService, which performs external MyMemory engine HTTP calls
 * (addTmxInMyMemory()/tmxUploadStatus() -> AbstractEngine::_call() ->
 * MultiCurlHandler). Construction of TMSService is now routed through the
 * createTMSService() seam (added to TMXFileController, mirroring
 * RequestExportTMXController) so those calls can be stubbed in the tests
 * below; the network call itself is never exercised.
 */
#[AllowMockObjectsWithoutExpectations]
class TMXFileControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_010_000;

    /** @var list<string> Temp upload files created by a test, removed in tearDown(). */
    private array $tempUploadFiles = [];

    /** @var ReflectionClass<TMXFileController> */
    private ReflectionClass $reflector;
    private TestableTMXFileController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;
    private string $defaultTmKeyBackup = '';

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultTmKeyBackup = AppConfig::$DEFAULT_TM_KEY;

        $this->cleanFragments(self::BASE);
        $owner = $this->ownerEmail(self::BASE);
        $this->seedUser(self::BASE, $owner);
        $this->seedProject(self::BASE, $owner);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $owner);

        $this->controller = new TestableTMXFileController();
        $this->reflector  = new ReflectionClass(TMXFileController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->responseMock);

        $user            = new UserStruct();
        $user->uid       = $this->userId(self::BASE);
        $user->email     = $owner;
        $user->first_name = 'Ctrl';
        $user->last_name  = 'Tester';
        $this->reflector->getProperty('user')->setValue($this->controller, $user);

        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createMock(MatecatLogger::class));
        $this->reflector->getProperty('featureSet')->setValue($this->controller, new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));
        $this->reflector->getProperty('database')->setValue($this->controller, obtainTestDatabase());
    }

    protected function tearDown(): void
    {
        AppConfig::$DEFAULT_TM_KEY = $this->defaultTmKeyBackup;
        $this->cleanFragments(self::BASE);
        $this->cleanMemoryKey();

        foreach ($this->tempUploadFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->tempUploadFiles = [];

        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $params
     */
    private function setRequestParams(array $params): void
    {
        $serverParams      = ['REQUEST_URI' => '/api/app/tmx', 'REQUEST_METHOD' => 'POST'];
        $this->requestStub = new Request($params, [], [], $serverParams);
        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
    }

    /**
     * Builds a Klein request whose files() collection contains one real,
     * on-disk upload file (Klein's Request::files() just wraps the raw
     * $_FILES-shaped array, so a real temp file drives Upload::uploadFiles()
     * through its genuine mime/extension/copy logic without any HTTP layer).
     *
     * @param array<string, mixed> $params
     */
    private function setRequestWithUploadedFile(string $fileName, string $contents, array $params = []): void
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'tmxctrl_');
        if ($tmpPath === false) {
            $this->fail('Unable to create temp upload file.');
        }
        file_put_contents($tmpPath, $contents);
        $this->tempUploadFiles[] = $tmpPath;

        $files = [
            'files' => [
                'name'     => [$fileName],
                'type'     => ['application/xml'],
                'tmp_name' => [$tmpPath],
                'error'    => [0],
                'size'     => [strlen($contents)],
            ],
        ];

        $serverParams       = ['REQUEST_URI' => '/api/app/tmx', 'REQUEST_METHOD' => 'POST'];
        $this->requestStub  = new Request($params, [], [], $serverParams, $files);
        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
    }

    private function seedMemoryKey(string $keyValue, string $keyName = ''): void
    {
        $uid = $this->userId(self::BASE);
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO memory_keys (uid, key_value, key_name, key_tm, key_glos, creation_date) "
            . "VALUES ($uid, '$keyValue', '$keyName', 1, 1, NOW())"
        );
    }

    private function cleanMemoryKey(): void
    {
        $this->seedConnection()->exec("DELETE FROM memory_keys WHERE uid = " . $this->userId(self::BASE));
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

    // ─── registerValidators ───

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function registerValidators_appends_a_login_validator(): void
    {
        $instance = $this->reflector->newInstanceWithoutConstructor();

        $this->reflector->getProperty('request')->setValue($instance, new Request());
        $this->reflector->getProperty('response')->setValue($instance, $this->responseMock);
        $this->reflector->getProperty('validators')->setValue($instance, []);

        $this->reflector->getMethod('registerValidators')->invoke($instance);

        $validators = $this->reflector->getProperty('validators')->getValue($instance);

        $this->assertIsArray($validators);
        $this->assertCount(1, $validators);
        $this->assertInstanceOf(LoginValidator::class, $validators[0]);
    }

    // ─── validateTheRequest ───

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateTheRequest_returns_expected_shape_with_explicit_tm_key(): void
    {
        AppConfig::$DEFAULT_TM_KEY = '';

        $this->setRequestParams([
            'name'                 => 'MyMemory.tmx',
            'tm_key'               => 'abc123key',
            'uuid'                 => 'uuid-xyz-1',
            'disable_upload_limit' => 'true',
        ]);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertIsArray($result);
        $this->assertSame('MyMemory.tmx', $result['name']);
        $this->assertSame('abc123key', $result['tm_key']);
        $this->assertSame('uuid-xyz-1', $result['uuid']);
        $this->assertTrue($result['disable_upload_limit']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateTheRequest_disable_upload_limit_defaults_to_false(): void
    {
        AppConfig::$DEFAULT_TM_KEY = '';

        $this->setRequestParams([
            'tm_key' => 'somekey',
        ]);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertFalse($result['disable_upload_limit']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateTheRequest_falls_back_to_default_tm_key_when_empty(): void
    {
        AppConfig::$DEFAULT_TM_KEY = 'default-mm-key';

        $this->setRequestParams([
            'name' => 'file.tmx',
        ]);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertSame('default-mm-key', $result['tm_key']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateTheRequest_throws_when_no_tm_key_and_no_default(): void
    {
        AppConfig::$DEFAULT_TM_KEY = '';

        $this->setRequestParams([
            'name' => 'file.tmx',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-2);

        $this->invokePrivate('validateTheRequest');
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateTheRequest_sanitizes_uuid_stripping_high_and_low_chars(): void
    {
        AppConfig::$DEFAULT_TM_KEY = 'fallback';

        $this->setRequestParams([
            'uuid' => "clean-uuid\x01\x7f",
        ]);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertSame('clean-uuid', $result['uuid']);
    }

    // ─── import() ───

    /**
     * @throws Exception
     */
    #[Test]
    public function import_throws_no_files_received_when_no_file_uploaded(): void
    {
        AppConfig::$DEFAULT_TM_KEY = 'fallback-key';

        $this->setRequestParams([
            'tm_key' => 'somekey',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No files received.');

        $this->controller->import();
    }

    /**
     * A real, successfully-uploaded file with a non-tmx extension reaches
     * the controller's own extension guard (import()'s first throw), via
     * the createTMSService() seam so no TMSService is ever constructed for
     * this branch.
     *
     * @throws ReflectionException
     */
    #[Test]
    public function import_throws_when_uploaded_file_is_not_a_tmx(): void
    {
        AppConfig::$DEFAULT_TM_KEY = 'fallback-key';

        $this->setRequestWithUploadedFile('notatmx.xml', '<root/>', [
            'tm_key' => 'somekey',
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Please upload a TMX.');
        $this->expectExceptionCode(-8);

        $this->controller->import();
    }

    /**
     * Full success path for a non-default tm_key with an existing,
     * name-less memory_keys row: covers the KeyRing branch that updates the
     * key's name (import() lines guarded by `$request['tm_key'] !=
     * AppConfig::$DEFAULT_TM_KEY` and the empty-name atomicUpdate branch).
     *
     * @throws ReflectionException
     */
    #[Test]
    public function import_success_updates_memory_key_name_when_previously_empty(): void
    {
        AppConfig::$DEFAULT_TM_KEY = 'the-default-key';
        $this->seedMemoryKey('non-default-key');

        $this->setRequestWithUploadedFile('MyMemory.tmx', '<tmx version="1.4"/>', [
            'tm_key' => 'non-default-key',
        ]);
        $this->reflector->getProperty('response')->setValue($this->controller, new Response());

        $tmsServiceStub = $this->createStub(TMSService::class);
        $tmsServiceStub->method('addTmxInMyMemory')->willReturn([]);
        $this->controller->tmsServiceStub = $tmsServiceStub;

        // import() calls Response::json(), which send()s the body to stdout and pollutes
        // the test-runner output. Swallow that echo; the body is still recorded on the
        // Response object, so the assertions below are unaffected.
        ob_start();
        try {
            $this->controller->import();
        } finally {
            ob_end_clean();
        }

        /** @var Response $response */
        $response = $this->reflector->getProperty('response')->getValue($this->controller);
        $body     = json_decode((string) $response->body(), true);

        $this->assertIsArray($body);
        $this->assertSame([], $body['errors']);
        $this->assertSame('MyMemory.tmx', $body['data']['uuids'][0]['name']);

        $stmt = $this->seedConnection()->prepare(
            "SELECT key_name FROM memory_keys WHERE uid = :uid AND key_value = :key_value"
        );
        $stmt->execute(['uid' => $this->userId(self::BASE), 'key_value' => 'non-default-key']);
        $keyName = $stmt->fetchColumn();

        $this->assertSame('MyMemory.tmx', $keyName);
    }

    /**
     * When tm_key equals the configured default, the KeyRing-update branch
     * is skipped entirely (import() line `if ($request['tm_key'] !=
     * AppConfig::$DEFAULT_TM_KEY)` short-circuits false) and no
     * MemoryKeyDao is touched, yet the response is still built correctly.
     *
     * @throws ReflectionException
     */
    #[Test]
    public function import_success_skips_memory_key_update_for_default_tm_key(): void
    {
        AppConfig::$DEFAULT_TM_KEY = 'the-default-key';

        $this->setRequestWithUploadedFile('Default.tmx', '<tmx version="1.4"/>', [
            'tm_key' => 'the-default-key',
        ]);
        $this->reflector->getProperty('response')->setValue($this->controller, new Response());

        $tmsServiceStub = $this->createStub(TMSService::class);
        $tmsServiceStub->method('addTmxInMyMemory')->willReturn([]);
        $this->controller->tmsServiceStub = $tmsServiceStub;

        // import() calls Response::json(), which send()s the body to stdout and pollutes
        // the test-runner output. Swallow that echo; the body is still recorded on the
        // Response object, so the assertions below are unaffected.
        ob_start();
        try {
            $this->controller->import();
        } finally {
            ob_end_clean();
        }

        /** @var Response $response */
        $response = $this->reflector->getProperty('response')->getValue($this->controller);
        $body     = json_decode((string) $response->body(), true);

        $this->assertIsArray($body);
        $this->assertSame([], $body['errors']);
        $this->assertSame('Default.tmx', $body['data']['uuids'][0]['name']);
    }

    // ─── importStatus() ───

    /**
     * Full success path via the createTMSService() seam: no real MyMemory
     * engine construction, no outbound HTTP.
     *
     * @throws ReflectionException
     */
    #[Test]
    public function importStatus_returns_json_response_with_status_data(): void
    {
        $this->setRequestParams([
            'uuid' => 'uuid-xyz-1',
        ]);
        $this->reflector->getProperty('response')->setValue($this->controller, new Response());

        $tmsServiceStub = $this->createStub(TMSService::class);
        $tmsServiceStub->method('tmxUploadStatus')->willReturn(['data' => ['status' => 'DONE']]);
        $this->controller->tmsServiceStub = $tmsServiceStub;

        // importStatus() calls Response::json(), which send()s the body to stdout and pollutes
        // the test-runner output. Swallow that echo; the body is still recorded on the
        // Response object, so the assertions below are unaffected.
        ob_start();
        try {
            $this->controller->importStatus();
        } finally {
            ob_end_clean();
        }

        /** @var Response $response */
        $response = $this->reflector->getProperty('response')->getValue($this->controller);
        $body     = json_decode((string) $response->body(), true);

        $this->assertIsArray($body);
        $this->assertSame([], $body['errors']);
        $this->assertSame(['status' => 'DONE'], $body['data']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function importStatus_uses_empty_string_uuid_when_param_sanitizes_to_false(): void
    {
        $this->setRequestParams([
            'uuid' => ['a', 'b'],
        ]);
        $this->reflector->getProperty('response')->setValue($this->controller, new Response());

        $tmsServiceStub = $this->createStub(TMSService::class);
        $tmsServiceStub->method('tmxUploadStatus')->willReturn(['data' => []]);
        $this->controller->tmsServiceStub = $tmsServiceStub;

        // importStatus() calls Response::json(), which send()s the body to stdout and pollutes
        // the test-runner output. Swallow that echo; the body is still recorded on the
        // Response object, so the assertions below are unaffected.
        ob_start();
        try {
            $this->controller->importStatus();
        } finally {
            ob_end_clean();
        }

        /** @var Response $response */
        $response = $this->reflector->getProperty('response')->getValue($this->controller);
        $body     = json_decode((string) $response->body(), true);

        $this->assertIsArray($body);
        $this->assertSame([], $body['data']);
    }
}
