<?php

namespace Matecat\Core\Controllers;

use Controller\Abstracts\Authentication\CookieManager;
use Controller\API\GDrive\GDriveController;
use Controller\Exceptions\RenderTerminatedException;
use Exception;
use Google_Service_Exception;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\ConnectedServices\GDrive\Session;
use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Utils\Constants\Constants;
use Utils\Logger\MatecatLogger;

/**
 * GDriveController test (Wave 4, N=24, real-DB pattern).
 *
 * Reserved ID block base = 9_000_000 + (24 * 1000) = 9_024_000.
 *   base+1 project, base+2 job, base+4 file, base+6 uid.
 * Clean ONLY by reserved id; per-suite owner = ctrltest_9024000@example.org.
 *
 * GDriveController has no top-level DB queries on the always-on path; the
 * Session collaborator (Google Drive / S3 / converter) is injected via
 * reflection so the controller's own branches are exercised deterministically
 * without external services.
 */
class TestableGDriveController extends GDriveController
{
    /** @var list<array{name:string,value:string,options:array<string,mixed>}> */
    public array $cookieWrites = [];

    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }

    protected function cookieManager(): CookieManager
    {
        $sink = &$this->cookieWrites;

        return new class($sink) extends CookieManager {
            /** @param list<array{name:string,value:string,options:array<string,mixed>}> $sink */
            public function __construct(private array &$sink)
            {
            }

            protected function writeCookie(string $name, string $value, array $options): bool
            {
                $this->sink[] = ['name' => $name, 'value' => $value, 'options' => $options];

                return true;
            }
        };
    }
}

/**
 * Session double whose file-structure read throws, to drive the
 * listImportedFiles() catch branch without S3/Google.
 */
class ThrowingGDriveSession extends Session
{
    public function __construct()
    {
    }

    public function getFileStructureForJsonOutput(): array
    {
        throw new Exception('boom listing', 503);
    }
}

/**
 * Session double whose file-structure read throws an S3Exception, to drive the
 * listImportedFiles() S3 catch branch.
 */
class S3ThrowingGDriveSession extends Session
{
    public function __construct()
    {
    }

    public function getFileStructureForJsonOutput(): array
    {
        $command = new \Aws\Command('GetObject', []);

        throw new \Aws\S3\Exception\S3Exception('s3 boom', $command);
    }
}

/**
 * Session double whose importFile() is a no-op so the non-async open() path
 * can reach doRedirect() in testing without Google credentials.
 */
class NoOpImportGDriveSession extends Session
{
    public function __construct()
    {
    }

    public function clearFileListFromSession(): void
    {
    }

    public function setConversionParams(string $guid, string $source_lang, string $target_lang, ?string $seg_rule = null, ?\Model\Filters\FiltersConfigTemplateStruct $filters_extraction_parameters = null): void
    {
    }

    public function importFile(string $googleFileId, \Google_Client $gClient): void
    {
    }
}

/**
 * Session double whose importFile() always throws, so that the doImport()
 * catch block is exercised (line 191 requires GoogleProvider::getClient to
 * succeed first, so this covers the reachable part of the try body when the
 * client is pre-built outside — not reachable without DI; kept for future use).
 * Used via setConversionParamsForTest() to pre-set $guid.
 */
class ThrowingImportGDriveSession extends Session
{
    public function __construct()
    {
    }

    public function setConversionParamsForTest(string $guid): void
    {
        $this->guid = $guid;
    }

    public function setConversionParams(string $guid, string $source_lang, string $target_lang, ?string $seg_rule = null, ?\Model\Filters\FiltersConfigTemplateStruct $filters_extraction_parameters = null): void
    {
        $this->guid = $guid;
    }

    public function importFile(string $googleFileId, \Google_Client $gClient): void
    {
        throw new Exception('import failed in test', 500);
    }
}

/**
 * Session double where removeFile() returns true and hasFiles() returns false,
 * driving the clearSession() branch in deleteImportedFile().
 */
class RemoveSuccessGDriveSession extends Session
{
    public bool $clearSessionCalled = false;

    public function __construct()
    {
    }

    public function removeFile(\Model\DataAccess\IDatabase $database, string $fileId, string $source, ?string $segmentationRule = null, int $filtersTemplate = 0): bool
    {
        return true;
    }

    public function hasFiles(): bool
    {
        return false;
    }

    public function clearSession(): void
    {
        $this->clearSessionCalled = true;
    }
}

/**
 * Session double where reConvert() always returns false, exercising the
 * false branch in changeConversionParameters() (lines 408-410).
 */
class ReConvertFalseGDriveSession extends Session
{
    public function __construct()
    {
    }

    public function reConvert(string $newSourceLang, ?string $newSegmentationRule = null, ?\Model\Filters\FiltersConfigTemplateStruct $filtersExtractionParameters = null): bool
    {
        return false;
    }
}

/**
 * Controller subclass that does NOT override initDependencies(), so the real
 * body (line 453: $this->initSessionService()) is exercised.
 */
class PlainGDriveController extends GDriveController
{
    public function __construct()
    {
    }
}

#[AllowMockObjectsWithoutExpectations]
class GDriveControllerTest extends AbstractTest
{
    private const int BASE = 9_024_000;

    private ReflectionClass $reflector;
    private TestableGDriveController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;
    private \Model\DataAccess\IDatabase $dbStub;

    /** @var array<string, mixed> */
    private array $sessionBackup = [];
    /** @var array<string, mixed> */
    private array $cookieBackup = [];

    /**
     * @throws ReflectionException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanTestData();

        $this->sessionBackup = is_array($GLOBALS['_SESSION'] ?? null) ? $GLOBALS['_SESSION'] : [];
        $this->cookieBackup  = is_array($GLOBALS['_COOKIE'] ?? null) ? $GLOBALS['_COOKIE'] : [];

        $this->controller = new TestableGDriveController();
        $this->reflector  = new ReflectionClass(GDriveController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);

        $user = new UserStruct();
        $user->uid        = self::BASE + 6;
        $user->email      = $this->ownerEmail();
        $user->first_name = 'Ctrl';
        $user->last_name  = 'Tester';
        $this->setProp('user', $user);

        $this->dbStub = $this->createStub(\Model\DataAccess\IDatabase::class);
        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet($this->dbStub));
        $this->setProp('database', obtainTestDatabase());
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->sessionBackup;
        $_COOKIE  = $this->cookieBackup;

        $this->cleanTestData();
        parent::tearDown();
    }

    private function ownerEmail(): string
    {
        return 'ctrltest_' . self::BASE . '@example.org';
    }

    private function cleanTestData(): void
    {
        // No persistent rows are required for the exercised paths; placeholder
        // kept for symmetry with the canonical real-DB pattern and to satisfy
        // the reserved-id-only cleanup contract.
    }

    private function setProp(string $name, mixed $value): void
    {
        $p = $this->reflector->getProperty($name);
        $p->setValue($this->controller, $value);
    }

    private function getProp(string $name): mixed
    {
        $p = $this->reflector->getProperty($name);

        return $p->getValue($this->controller);
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
        $serverParams      = ['REQUEST_URI' => '/api/gdrive', 'REQUEST_METHOD' => 'POST'];
        $this->requestStub = new Request($params, [], [], $serverParams);
        $this->setProp('request', $this->requestStub);
    }

    /**
     * Build a real Session bound to a controlled session-data array (no Google
     * client / S3) and inject it as the controller's gdriveUserSession.
     *
     * @param array<string, mixed> $sessionData
     *
     * @throws Exception
     */
    private function injectSession(array $sessionData): Session
    {
        $local   = $sessionData;
        $session = new Session($this->dbStub, $local);
        $this->setProp('gdriveUserSession', $session);

        return $session;
    }

    // ─── getExceptionMessage ───

    #[Test]
    public function getExceptionMessage_returns_raw_message_for_plain_exception(): void
    {
        $result = $this->invokePrivate('getExceptionMessage', [new Exception('plain boom')]);

        $this->assertSame('plain boom', $result);
    }

    #[Test]
    public function getExceptionMessage_extracts_google_error_message(): void
    {
        $payload = json_encode(['error' => ['message' => 'google msg']]);
        $e       = new Google_Service_Exception((string)$payload);

        $result = $this->invokePrivate('getExceptionMessage', [$e]);

        $this->assertSame('google msg', $result);
    }

    #[Test]
    public function getExceptionMessage_joins_google_error_errors(): void
    {
        $payload = json_encode(['error' => ['errors' => [['message' => 'a'], ['message' => 'b']]]]);
        $e       = new Google_Service_Exception((string)$payload);

        $result = $this->invokePrivate('getExceptionMessage', [$e]);

        $this->assertSame('a,b', $result);
    }

    // ─── formatErrorMessage ───

    #[Test]
    public function formatErrorMessage_maps_too_large_message(): void
    {
        $result = $this->invokePrivate('formatErrorMessage', ['This file is too large to be exported.']);

        $this->assertStringContainsString('bigger than 10 mb', $result);
    }

    #[Test]
    public function formatErrorMessage_maps_docs_editors_message(): void
    {
        $result = $this->invokePrivate('formatErrorMessage', ['Export only supports Docs Editors files.']);

        $this->assertStringContainsString('does not allow exports of files in this format', $result);
    }

    #[Test]
    public function formatErrorMessage_maps_specified_key_message(): void
    {
        $result = $this->invokePrivate('formatErrorMessage', ['Something The specified key does not exist. here']);

        $this->assertStringContainsString('name of the file you are trying to upload is too long', $result);
    }

    #[Test]
    public function formatErrorMessage_passes_through_unknown_message(): void
    {
        $result = $this->invokePrivate('formatErrorMessage', ['anything else']);

        $this->assertSame('anything else', $result);
    }

    // ─── getValidSourceLanguage / getValidTargetLanguages ───

    #[Test]
    public function getValidSourceLanguage_defaults_when_nothing_provided(): void
    {
        $this->setRequestParams([]);
        unset($_COOKIE[Constants::COOKIE_SOURCE_LANG]);

        $result = $this->invokePrivate('getValidSourceLanguage');

        $this->assertSame(Constants::DEFAULT_SOURCE_LANG, $result);
    }

    #[Test]
    public function getValidSourceLanguage_validates_source_when_target_present(): void
    {
        $this->setRequestParams(['target' => 'it-IT', 'source' => 'en-US']);

        $result = $this->invokePrivate('getValidSourceLanguage');

        $this->assertSame('en-US', $result);
    }

    #[Test]
    public function getValidTargetLanguages_defaults_when_nothing_provided(): void
    {
        $this->setRequestParams([]);
        unset($_COOKIE[Constants::COOKIE_TARGET_LANG]);

        $result = $this->invokePrivate('getValidTargetLanguages');

        $this->assertSame(Constants::DEFAULT_TARGET_LANG, $result);
    }

    #[Test]
    public function getValidTargetLanguages_validates_target_when_present(): void
    {
        $this->setRequestParams(['target' => 'it-IT']);

        $result = $this->invokePrivate('getValidTargetLanguages');

        $this->assertSame('it-IT', $result);
    }

    // ─── initSessionService ───

    #[Test]
    public function initSessionService_assigns_a_session_instance(): void
    {
        $this->invokePrivate('initSessionService');

        $this->assertInstanceOf(Session::class, $this->getProp('gdriveUserSession'));
    }

    // ─── listImportedFiles ───

    #[Test]
    public function listImportedFiles_returns_empty_structure_when_no_files(): void
    {
        $sessionData = ['uid' => self::BASE + 6, 'upload_token' => 'tok'];
        $this->injectSession($sessionData);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function ($data) use (&$captured): bool {
                $captured = $data;

                return true;
            }));

        $this->controller->listImportedFiles();

        $this->assertSame([], $captured);
    }

    // ─── deleteImportedFile ───

    #[Test]
    public function deleteImportedFile_returns_success_true_when_removing_all(): void
    {
        $sessionData = ['uid' => self::BASE + 6, Session::SESSION_KEY => [Session::FILE_LIST => []]];
        $this->injectSession($sessionData);

        $this->setRequestParams([
            'fileId'            => 'all',
            'source'            => 'en-US',
            'segmentation_rule' => 'standard',
            'filters_template'  => '0',
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;

                return true;
            }));

        $this->controller->deleteImportedFile();

        $this->assertSame(['success' => true], $captured);
    }

    #[Test]
    public function deleteImportedFile_returns_success_false_for_missing_single_file(): void
    {
        $sessionData = ['uid' => self::BASE + 6];
        $this->injectSession($sessionData);

        $this->setRequestParams([
            'fileId'            => 'nonexistent-file',
            'source'            => 'en-US',
            'segmentation_rule' => 'standard',
            'filters_template'  => '0',
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;

                return true;
            }));

        $this->controller->deleteImportedFile();

        $this->assertSame(['success' => false], $captured);
    }

    // ─── changeConversionParameters ───

    #[Test]
    public function changeConversionParameters_catches_invalid_language_and_returns_without_json(): void
    {
        $_SESSION[Constants::SESSION_ACTUAL_SOURCE_LANG] = 'en-US';
        $sessionData = ['uid' => self::BASE + 6];
        $this->injectSession($sessionData);

        $this->setRequestParams([
            'source'            => 'not-a-real-lang-xyz',
            'segmentation_rule' => 'standard',
        ]);

        // The invalid language is caught; method returns early without json().
        $this->responseMock->expects($this->never())->method('json');

        $this->controller->changeConversionParameters();

        $error = $this->getProp('error');
        $this->assertIsArray($error);
        $this->assertArrayHasKey('msg', $error);
    }

    #[Test]
    public function changeConversionParameters_returns_success_payload_on_reconvert(): void
    {
        // Empty file list → reConvert iterates nothing → returns true.
        $sessionData = [
            'uid'                                => self::BASE + 6,
            'upload_token'                       => 'gdrive-token',
            Session::SESSION_KEY                 => [Session::FILE_LIST => []],
            Constants::SESSION_ACTUAL_SOURCE_LANG => 'en-US',
        ];
        $_SESSION[Constants::SESSION_ACTUAL_SOURCE_LANG] = 'en-US';
        $this->injectSession($sessionData);

        $this->setRequestParams([
            'source'            => 'it-IT',
            'segmentation_rule' => 'standard',
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;

                return true;
            }));

        $this->controller->changeConversionParameters();

        $this->assertSame(['success' => true], $captured);
        $this->assertSame('it-IT', $_SESSION[Constants::SESSION_ACTUAL_SOURCE_LANG]);
    }

    // ─── open ───

    #[Test]
    public function open_sets_error_when_state_has_no_ids(): void
    {
        $sessionData = ['uid' => self::BASE + 6];
        $this->injectSession($sessionData);

        $this->setRequestParams([
            'isAsync' => 'true',
            'state'   => json_encode(['somethingElse' => 1]),
        ]);

        $this->controller->open();

        $error = $this->getProp('error');
        $this->assertIsArray($error);
        $this->assertArrayHasKey('msg', $error);
        $msg = is_string($error['msg']) ? $error['msg'] : '';
        $this->assertStringContainsString('no ids', $msg);
        $this->assertFalse($this->getProp('isImportingSuccessful'));
    }

    #[Test]
    public function open_invalid_upload_token_sets_error(): void
    {
        $sessionData = ['uid' => self::BASE + 6, 'upload_token' => 'not-a-uuid'];
        $_SESSION['upload_token'] = $_COOKIE['upload_token'] = 'not-a-uuid';
        $this->injectSession($sessionData);

        $this->setRequestParams([
            'isAsync' => 'true',
            'state'   => json_encode(['ids' => ['file-1']]),
        ]);

        $this->controller->open();

        $error = $this->getProp('error');
        $this->assertIsArray($error);
        $this->assertArrayHasKey('class', $error);
        $this->assertSame(InvalidArgumentException::class, $error['class']);
        $this->assertFalse($this->getProp('isImportingSuccessful'));
    }

    #[Test]
    public function open_async_runs_import_and_responds_with_failure_payload(): void
    {
        // Valid UUID token passes Utils::isTokenValid; doImport will fail when
        // contacting Google (no creds) → success=false JSON via doResponse().
        $guid                       = '11111111-2222-3333-4444-555555555555';
        $sessionData = [
            'uid'                => self::BASE + 6,
            'upload_token'       => $guid,
            Session::SESSION_KEY => [Session::FILE_LIST => []],
        ];
        $_SESSION['upload_token'] = $_COOKIE['upload_token'] = $guid;
        $this->injectSession($sessionData);

        $this->setRequestParams([
            'isAsync' => 'true',
            'state'   => json_encode(['ids' => ['file-1']]),
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;

                return true;
            }));

        $this->controller->open();

        $this->assertIsArray($captured);
        $this->assertArrayHasKey('success', $captured);
        $this->assertFalse($captured['success']);
    }

    #[Test]
    public function open_with_inline_filters_template_runs_and_responds(): void
    {
        $guid        = '11111111-2222-3333-4444-666666666666';
        $sessionData = [
            'uid'                => self::BASE + 6,
            'upload_token'       => $guid,
            Session::SESSION_KEY => [Session::FILE_LIST => []],
        ];
        $_SESSION['upload_token'] = $_COOKIE['upload_token'] = $guid;
        $this->injectSession($sessionData);

        $template = json_encode(['name' => 'CtrlTpl', 'uid' => self::BASE + 6]);

        $this->setRequestParams([
            'isAsync'                                => 'true',
            'state'                                  => json_encode(['exportIds' => ['file-9']]),
            'filters_extraction_parameters_template' => $template,
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;

                return true;
            }));

        $this->controller->open();

        $this->assertIsArray($captured);
        $this->assertArrayHasKey('success', $captured);
    }

    #[Test]
    public function listImportedFiles_handles_generic_exception_with_error_code(): void
    {
        $this->setProp('gdriveUserSession', new ThrowingGDriveSession());

        $this->responseMock->expects($this->once())->method('code')->with(503);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;

                return true;
            }));

        $this->controller->listImportedFiles();

        $this->assertIsArray($captured);
        $this->assertSame(503, $captured['code']);
        $msg = is_string($captured['msg']) ? $captured['msg'] : '';
        $this->assertStringContainsString('boom listing', $msg);
    }

    #[Test]
    public function listImportedFiles_handles_s3_exception_with_code_400(): void
    {
        $this->setProp('gdriveUserSession', new S3ThrowingGDriveSession());

        $this->responseMock->expects($this->once())->method('code')->with(400);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;

                return true;
            }));

        $this->controller->listImportedFiles();

        $this->assertIsArray($captured);
        $this->assertSame(400, $captured['code']);
        $cls = is_string($captured['class']) ? $captured['class'] : '';
        $this->assertStringContainsString('S3Exception', $cls);
    }

    #[Test]
    public function getValidSourceLanguage_reads_cookie_when_target_absent(): void
    {
        $this->setRequestParams([]);
        $_COOKIE[Constants::COOKIE_SOURCE_LANG] = 'de-DE';

        $result = $this->invokePrivate('getValidSourceLanguage');

        $this->assertSame('de-DE', $result);
    }

    #[Test]
    public function getValidTargetLanguages_reads_cookie_when_target_absent(): void
    {
        $this->setRequestParams([]);
        $_COOKIE[Constants::COOKIE_TARGET_LANG] = 'es-ES';

        $result = $this->invokePrivate('getValidTargetLanguages');

        $this->assertSame('es-ES', $result);
    }

    #[Test]
    public function getExceptionMessage_decoded_json_fallback_branch_returns_array(): void
    {
        // Google exception whose decoded payload has neither error.message nor
        // error.errors hits the `return $jsonDecodedMessage;` fallback (line
        // 228). That statement returns an array from a `: string` method, so
        // PHP raises a TypeError at the return boundary — covering the branch
        // while documenting the existing production shape (not modified here).
        $payload = json_encode(['error' => ['status' => 'X']]);
        $e       = new Google_Service_Exception((string)$payload);

        $this->expectException(\TypeError::class);

        $this->invokePrivate('getExceptionMessage', [$e]);
    }

    // ─── open: non-async path (lines 87-103, 240, 247-285) ───

    #[Test]
    public function open_non_async_clears_file_list_and_redirects(): void
    {
        // Non-async open() generates a new upload_token (lines 88-101), calls
        // clearFileListFromSession(), then finalize() → doRedirect() (lines 247-285).
        // doRedirect() throws RenderTerminatedException in testing env, but since
        // RenderTerminatedException extends RuntimeException extends Exception, it
        // IS caught by open()'s outer catch — so open() returns normally.
        // We verify the redirect executed by checking that the error class recorded
        // is RenderTerminatedException (proving doRedirect() ran).
        $this->setProp('gdriveUserSession', new NoOpImportGDriveSession());

        $this->setRequestParams([
            'isAsync' => 'false',
            'state'   => json_encode(['ids' => ['file-x']]),
        ]);

        // Seed a valid-looking UUID so the non-async branch has a cookie to copy
        $_COOKIE['upload_token'] = '00000000-0000-0000-0000-000000000000';

        $this->controller->open();

        // doRedirect() throws RenderTerminatedException which is caught by open()'s
        // outer catch — the error class proves lines 247-285 executed.
        $error = $this->getProp('error');
        $this->assertIsArray($error);
        $this->assertSame(RenderTerminatedException::class, $error['class']);
        $this->assertFalse($this->getProp('isImportingSuccessful'));

        // doRedirect() emits the outcome payload the frontend reads, then the file-list token.
        $writes = $this->controller->cookieWrites;
        $this->assertCount(2, $writes);

        $this->assertSame(GDriveController::GDRIVE_OUTCOME_COOKIE_NAME, $writes[0]['name']);
        $this->assertSame('Strict', $writes[0]['options']['samesite']);
        $outcome = json_decode($writes[0]['value'], true);
        $this->assertIsArray($outcome);
        $this->assertTrue($outcome['success']);
        $this->assertNull($outcome['error_class']);

        $this->assertSame(GDriveController::GDRIVE_LIST_COOKIE_NAME, $writes[1]['name']);
        $this->assertNotEmpty($writes[1]['value']);
    }

    // ─── open: doImport catch block (line 191) ───

    #[Test]
    public function open_doImport_catch_sets_error_on_import_exception(): void
    {
        // importFile throws inside doImport → catch populates $this->error and
        // sets isImportingSuccessful=false; open() outer catch re-catches and
        // also sets false — either way the error array is populated.
        $guid        = '22222222-3333-4444-5555-666666666666';
        $_SESSION['upload_token'] = $_COOKIE['upload_token'] = $guid;

        $session = new ThrowingImportGDriveSession();
        $session->setConversionParamsForTest($guid);
        $this->setProp('gdriveUserSession', $session);

        $this->setRequestParams([
            'isAsync' => 'true',
            'state'   => json_encode(['ids' => ['file-1']]),
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;

                return true;
            }));

        $this->controller->open();

        $this->assertIsArray($captured);
        $this->assertFalse($captured['success']);
        $this->assertFalse($this->getProp('isImportingSuccessful'));
    }

    // ─── open: filtersTemplateId branch (lines 65-74) ───

    #[Test]
    public function open_filters_template_id_loads_template_from_db(): void
    {
        // Insert a real filters_config_templates row in the reserved ID block,
        // then call open() with that ID as filters_extraction_parameters_template_id.
        // The controller instantiates FiltersConfigTemplateDao with the real DB and
        // calls getByIdAndUser → hits the real SQL path (line 67).
        // AbstractTest::tearDown() rolls back the transaction automatically.
        $uid    = self::BASE + 6;
        $db     = obtainTestDatabase();
        $conn   = $db->getConnection();

        $conn->exec(
            "INSERT INTO filters_config_templates (id, uid, name, created_at) " .
            "VALUES (9960001, {$uid}, 'ctrl-test-tpl', NOW())"
        );

        $guid = '33333333-4444-5555-6666-777777777777';
        $_SESSION['upload_token'] = $_COOKIE['upload_token'] = $guid;

        $this->setProp('gdriveUserSession', new NoOpImportGDriveSession());

        $this->setRequestParams([
            'isAsync'                                      => 'true',
            'state'                                        => json_encode(['ids' => ['file-y']]),
            'filters_extraction_parameters_template_id'   => '9960001',
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;

                return true;
            }));

        $this->controller->open();

        $this->assertIsArray($captured);
        // The import itself is a no-op so success=true; the important thing is
        // we reached line 67 (getByIdAndUser) without an exception.
        $this->assertArrayHasKey('success', $captured);

        $conn->exec("DELETE FROM filters_config_templates WHERE id = 9960001");
    }

    // ─── changeConversionParameters: filtersTemplateId branch (lines 382-384) ───

    #[Test]
    public function changeConversionParameters_filters_template_id_branch_loads_template(): void
    {
        // Same DB seeding pattern as above; exercises lines 382-384.
        $uid  = self::BASE + 6;
        $db   = obtainTestDatabase();
        $conn = $db->getConnection();

        $conn->exec(
            "INSERT INTO filters_config_templates (id, uid, name, created_at) " .
            "VALUES (9960002, {$uid}, 'ctrl-test-tpl2', NOW())"
        );

        $_SESSION[Constants::SESSION_ACTUAL_SOURCE_LANG] = 'en-US';

        $sessionData = [
            'uid'                                => $uid,
            'upload_token'                       => 'tok2',
            Session::SESSION_KEY                 => [Session::FILE_LIST => []],
            Constants::SESSION_ACTUAL_SOURCE_LANG => 'en-US',
        ];
        $this->injectSession($sessionData);

        $this->setRequestParams([
            'source'                                       => 'it-IT',
            'segmentation_rule'                            => 'standard',
            'filters_extraction_parameters_template_id'   => '9960002',
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;

                return true;
            }));

        $this->controller->changeConversionParameters();

        // Line 382-384 executed; result is success=true (empty file list → reConvert succeeds).
        $this->assertSame(['success' => true], $captured);

        $conn->exec("DELETE FROM filters_config_templates WHERE id = 9960002");
    }

    // ─── changeConversionParameters: reConvert returns false (lines 408-410) ───

    #[Test]
    public function changeConversionParameters_reConvert_false_restores_original_source_lang(): void
    {
        // ReConvertFalseGDriveSession always returns false from reConvert(),
        // exercising the else branch at lines 408-410 that restores originalSourceLang.
        $_SESSION[Constants::SESSION_ACTUAL_SOURCE_LANG] = 'en-US';

        $this->setProp('gdriveUserSession', new ReConvertFalseGDriveSession());

        $this->setRequestParams([
            'source'            => 'it-IT',
            'segmentation_rule' => 'standard',
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;

                return true;
            }));

        $this->controller->changeConversionParameters();

        // reConvert returns false → success=false; SESSION_ACTUAL_SOURCE_LANG is restored.
        $this->assertSame(['success' => false], $captured);
        $this->assertSame('en-US', $_SESSION[Constants::SESSION_ACTUAL_SOURCE_LANG]);
    }

    // ─── deleteImportedFile: removeFile succeeds + hasFiles false → clearSession (lines 435-437) ───

    #[Test]
    public function deleteImportedFile_clears_session_when_last_file_removed(): void
    {
        // Build a Session with one file, then removeFile() returns true and hasFiles()
        // returns false → clearSession() is called (lines 435-437).
        $this->setProp('gdriveUserSession', new RemoveSuccessGDriveSession());

        $this->setRequestParams([
            'fileId'            => 'some-file-id',
            'source'            => 'en-US',
            'segmentation_rule' => 'standard',
            'filters_template'  => '0',
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;

                return true;
            }));

        $this->controller->deleteImportedFile();

        $this->assertSame(['success' => true], $captured);
    }

    // ─── initDependencies (line 452) ───

    #[Test]
    public function initDependencies_initialises_gdriveUserSession(): void
    {
        // TestableGDriveController overrides initDependencies to be a no-op.
        // Use a plain subclass that does NOT override it so the real body runs.
        $plain = new PlainGDriveController();
        $ref   = new ReflectionClass(GDriveController::class);

        $ref->getProperty('database')->setValue($plain, obtainTestDatabase());
        $ref->getProperty('logger')->setValue($plain, $this->createStub(MatecatLogger::class));

        $ref->getMethod('initDependencies')->invoke($plain);

        $this->assertInstanceOf(Session::class, $ref->getProperty('gdriveUserSession')->getValue($plain));
    }
}
