<?php

namespace Matecat\Core\Controllers;

use Controller\API\GDrive\GDriveController;
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
    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
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

#[AllowMockObjectsWithoutExpectations]
class GDriveControllerTest extends AbstractTest
{
    private const int BASE = 9_024_000;

    private ReflectionClass $reflector;
    private TestableGDriveController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

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

        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet());
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
        $session = new Session($local);
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
}
