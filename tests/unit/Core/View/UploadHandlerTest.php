<?php

namespace Matecat\Core\View;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;
use UploadHandler;
use Utils\Logger\MatecatLogger;
use Utils\ServerCheck\ServerCheck;
use Utils\ServerCheck\UploadParams;

require_once AbstractTest::projectRoot() . '/lib/View/fileupload/UploadHandler.php';

/**
 * Stands in for the `die()` that ends the production flush(). Recording the payload without
 * stopping is not enough: post() calls flush() at :458 with no `return` after it, so execution
 * would fall through to :461 and `$info = []` at :463 would discard the payload before flush()
 * fired a second time at the end of the method. Throwing reproduces the real termination point.
 */
class FlushCalled extends RuntimeException
{
    public function __construct(public readonly mixed $info)
    {
        parent::__construct('flush() was called');
    }
}

class TestableUploadHandler extends UploadHandler
{
    /** @var list<mixed> every payload flush() was handed, in order */
    public array $flushCalls = [];

    public function __construct()
    {
        // Skip parent constructor to avoid $_COOKIE dependency
    }

    public function initForTest(array $options = []): void
    {
        $this->options = array_merge([
            'script_url' => 'http://localhost/',
            'upload_token' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            'upload_dir' => '/tmp/opencode/upload_test/',
            'upload_url' => 'http://localhost/files/',
            'param_name' => 'files',
            'delete_type' => 'DELETE',
            'max_tmx_file_size' => 300 * 1024 * 1024,
            'max_file_size' => 100 * 1024 * 1024,
            'min_file_size' => 1,
            'max_number_of_files' => 100,
            'discard_aborted_uploads' => true,
        ], $options);
    }

    /**
     * $logger and $database are typed and uninitialised under the neutered constructor;
     * handle_file_upload() and the delete helpers dereference them immediately.
     */
    public function setLoggerForTest(MatecatLogger $logger): void
    {
        $this->logger = $logger;
    }

    public function setDatabaseForTest(IDatabase $database): void
    {
        $this->database = $database;
    }

    public function flush(mixed $info): void
    {
        $this->flushCalls[] = $info;

        throw new FlushCalled($info);
    }
}

#[AllowMockObjectsWithoutExpectations]
class UploadHandlerTest extends AbstractTest
{
    private ReflectionClass $reflector;
    private TestableUploadHandler $handler;

    /**
     * @throws ReflectionException
     */
    private const string VALID_TOKEN = '3f2504e0-4f89-11d3-9a0c-0305e82c3301';

    /** @var array<string, mixed> */
    private array $serverBackup = [];
    /** @var array<string, mixed> */
    private array $requestBackup = [];
    /** @var array<string, mixed> */
    private array $cookieBackup = [];
    private bool $serverCheckSeeded = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = new TestableUploadHandler();
        $this->handler->initForTest();
        $this->reflector = new ReflectionClass(UploadHandler::class);

        // post()/delete()/get() read the superglobals directly; snapshot so tests cannot leak
        $this->serverBackup = $_SERVER;
        $this->requestBackup = $_REQUEST;
        $this->cookieBackup = $_COOKIE;

        if (!is_dir('/tmp/opencode/upload_test/')) {
            mkdir('/tmp/opencode/upload_test/', 0777, true);
        }
    }

    protected function tearDown(): void
    {
        $dir = '/tmp/opencode/upload_test/';
        if (is_dir($dir)) {
            array_map('unlink', glob($dir . '*') ?: []);
        }

        $_SERVER = $this->serverBackup;
        $_REQUEST = $this->requestBackup;
        $_COOKIE = $this->cookieBackup;

        if ($this->serverCheckSeeded) {
            // Drop the seeded singleton so the next getInstance() rebuilds from php.ini
            (new ReflectionProperty(ServerCheck::class, '_INSTANCE'))->setValue(null, null);
            $this->serverCheckSeeded = false;
        }

        parent::tearDown();
    }

    /**
     * @throws ReflectionException
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        $m = $this->reflector->getMethod($method);

        return $m->invoke($this->handler, ...$args);
    }

    /**
     * post() resolves its size limits through ServerCheck, which caches them from php.ini into a
     * process-wide singleton and hands out a *clone*, so mutating the returned object is useless.
     * post_max_size / upload_max_filesize are PHP_INI_PERDIR and cannot be moved with ini_set()
     * either, so the statics are seeded directly — the recipe ConnectedServiceStructTest uses for
     * OauthTokenEncryption::$instance.
     *
     * @throws ReflectionException
     */
    private function seedUploadParams(int $postMaxSize, int $uploadMaxFilesize): void
    {
        $params = new UploadParams();
        $params->setPostMaxSize($postMaxSize);
        $params->setUploadMaxFilesize($uploadMaxFilesize);

        (new ReflectionProperty(ServerCheck::class, 'uploadParams'))->setValue(null, $params);
        (new ReflectionProperty(ServerCheck::class, '_INSTANCE'))->setValue(
            null,
            (new ReflectionClass(ServerCheck::class))->newInstanceWithoutConstructor()
        );

        $this->serverCheckSeeded = true;
    }

    /**
     * $files is private and only ever populated by the real constructor, so post() otherwise sees
     * null through its `?? null` and can never enter an upload branch.
     *
     * @param array<string, mixed> $files
     *
     * @throws ReflectionException
     */
    private function setFiles(array $files): void
    {
        (new ReflectionProperty(UploadHandler::class, 'files'))->setValue($this->handler, $files);
    }

    /**
     * A $_FILES-shaped single-file entry under the default `files` param name, backed by a real
     * temp file so getMimeContentType() has something to inspect.
     *
     * @return array<string, mixed>
     */
    private function makeUploadedFiles(string $name, string $content, int $declaredSize, int $error = 0): array
    {
        $tmp = '/tmp/opencode/upload_test/tmp_' . uniqid() . '_' . $name;
        file_put_contents($tmp, $content);

        return [
            'files' => [
                'tmp_name' => [$tmp],
                'name' => [$name],
                'size' => [$declaredSize],
                'error' => [$error],
            ],
        ];
    }

    /**
     * Runs an endpoint method, capturing whatever it echoes and whatever it handed to flush().
     *
     * E_WARNING is masked for the duration. get()/delete() call header(), and the test bootstrap
     * echoes at tests/inc/functions.php:72, so headers are always "already sent" in this suite —
     * an artefact of the harness, not of the code under test. deleteSha() likewise warns from
     * sha1_file() when asked about a file that is legitimately absent. Only the return values and
     * the emitted body are under test here.
     *
     * @return array{0: string, 1: mixed} captured output and the flushed payload (null if none)
     */
    private function callAndCapture(callable $fn): array
    {
        $flushed = null;

        // Swallow only E_WARNING, and only here; anything else still reaches PHPUnit's handler.
        set_error_handler(static fn(): bool => true, E_WARNING);
        ob_start();
        try {
            $fn();
        } catch (FlushCalled $e) {
            $flushed = $e->info;
        } finally {
            $output = ob_get_clean();
            restore_error_handler();
        }

        return [$output, $flushed];
    }

    // ─── trim_file_name ───

    #[Test]
    public function trim_file_name_strips_path_with_custom_dirsep(): void
    {
        $result = $this->invokePrivate('trim_file_name', ['path//to//file.txt']);
        $this->assertSame('file.txt', $result);
    }

    #[Test]
    public function trim_file_name_preserves_normal_filename(): void
    {
        $result = $this->invokePrivate('trim_file_name', ['document.xliff']);
        $this->assertSame('document.xliff', $result);
    }

    #[Test]
    public function trim_file_name_strips_leading_dots(): void
    {
        $result = $this->invokePrivate('trim_file_name', ['.hidden']);
        $this->assertSame('hidden', $result);
    }

    // ─── up_count_name ───

    #[Test]
    public function up_count_name_appends_counter_on_first_collision(): void
    {
        $result = $this->invokePrivate('up_count_name', ['file.txt']);
        $this->assertSame('file_(1).txt', $result);
    }

    #[Test]
    public function up_count_name_increments_existing_counter(): void
    {
        $result = $this->invokePrivate('up_count_name', ['file_(3).txt']);
        $this->assertSame('file_(4).txt', $result);
    }

    #[Test]
    public function up_count_name_handles_no_extension(): void
    {
        $result = $this->invokePrivate('up_count_name', ['README']);
        $this->assertStringContainsString('_(1)', $result);
    }

    // ─── up_count_name_callback ───

    #[Test]
    public function up_count_name_callback_increments_index(): void
    {
        $result = $this->invokePrivate('up_count_name_callback', [['_full_match', '5', '.txt']]);
        $this->assertSame('_(6).txt', $result);
    }

    #[Test]
    public function up_count_name_callback_starts_at_1_when_no_index(): void
    {
        $result = $this->invokePrivate('up_count_name_callback', [['_full_match']]);
        $this->assertSame('_(1)', $result);
    }

    // ─── set_file_delete_url ───

    #[Test]
    public function set_file_delete_url_sets_url_and_type(): void
    {
        $file = new \stdClass();
        $file->name = 'test file.xliff';

        $this->invokePrivate('set_file_delete_url', [$file]);

        $this->assertStringContainsString('test%20file.xliff', $file->delete_url);
        $this->assertSame('DELETE', $file->delete_type);
    }

    #[Test]
    public function set_file_delete_url_appends_method_for_non_delete_type(): void
    {
        $this->handler->initForTest(['delete_type' => 'POST']);

        $file = new \stdClass();
        $file->name = 'test.xliff';

        $this->invokePrivate('set_file_delete_url', [$file]);

        $this->assertStringContainsString('&_method=DELETE', $file->delete_url);
        $this->assertSame('POST', $file->delete_type);
    }

    // ─── get_file_object ───

    #[Test]
    public function get_file_object_returns_null_for_nonexistent_file(): void
    {
        $result = $this->invokePrivate('get_file_object', ['nonexistent.txt']);
        $this->assertNull($result);
    }

    #[Test]
    public function get_file_object_returns_stdclass_for_existing_file(): void
    {
        file_put_contents('/tmp/opencode/upload_test/testfile.xliff', 'content');

        $result = $this->invokePrivate('get_file_object', ['testfile.xliff']);

        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertSame('testfile.xliff', $result->name);
        $this->assertSame(7, $result->size);
    }

    #[Test]
    public function get_file_object_returns_null_for_hidden_file(): void
    {
        file_put_contents('/tmp/opencode/upload_test/.hidden', 'secret');

        $result = $this->invokePrivate('get_file_object', ['.hidden']);
        $this->assertNull($result);
    }

    // ─── get_file_objects ───

    #[Test]
    public function get_file_objects_returns_list_of_files(): void
    {
        file_put_contents('/tmp/opencode/upload_test/a.txt', 'a');
        file_put_contents('/tmp/opencode/upload_test/b.txt', 'b');

        $result = $this->invokePrivate('get_file_objects');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $names = array_map(fn($f) => $f->name, $result);
        $this->assertContains('a.txt', $names);
        $this->assertContains('b.txt', $names);
    }

    // ─── getMimeContentType ───

    #[Test]
    public function getMimeContentType_returns_mime_for_valid_file(): void
    {
        $tmpFile = '/tmp/opencode/upload_test/test.txt';
        file_put_contents($tmpFile, 'Hello world');

        $result = $this->invokePrivate('getMimeContentType', [$tmpFile]);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    // ─── _isRightExtension ───

    #[Test]
    public function isRightExtension_accepts_xliff(): void
    {
        $file = new \stdClass();
        $file->name = 'document.xliff';

        $result = $this->invokePrivate('_isRightExtension', [$file]);
        $this->assertTrue($result);
    }

    #[Test]
    public function isRightExtension_rejects_unknown_extension(): void
    {
        $file = new \stdClass();
        $file->name = 'malware.exe';

        $result = $this->invokePrivate('_isRightExtension', [$file]);
        $this->assertFalse($result);
    }

    #[Test]
    public function isRightExtension_is_case_insensitive(): void
    {
        $file = new \stdClass();
        $file->name = 'document.XLIFF';

        $result = $this->invokePrivate('_isRightExtension', [$file]);
        $this->assertTrue($result);
    }

    // ─── _isRightMime ───

    #[Test]
    public function isRightMime_accepts_valid_mime(): void
    {
        $file = new \stdClass();
        $file->type = 'application/xml';

        $result = $this->invokePrivate('_isRightMime', [$file]);
        $this->assertTrue($result);
    }

    #[Test]
    public function isRightMime_rejects_invalid_mime(): void
    {
        $file = new \stdClass();
        $file->type = 'application/x-shockwave-flash';

        $result = $this->invokePrivate('_isRightMime', [$file]);
        $this->assertFalse($result);
    }

    #[Test]
    public function isRightMime_rejects_false_type(): void
    {
        $file = new \stdClass();
        $file->type = false;

        $result = $this->invokePrivate('_isRightMime', [$file]);
        $this->assertFalse($result);
    }

    #[Test]
    public function isRightMime_rejects_empty_string_type(): void
    {
        $file = new \stdClass();
        $file->type = '';

        $result = $this->invokePrivate('_isRightMime', [$file]);
        $this->assertFalse($result);
    }

    // ─── my_basename ───

    #[Test]
    public function my_basename_extracts_filename_after_dirsep(): void
    {
        $result = $this->invokePrivate('my_basename', ['path//to//file.txt']);
        $this->assertSame('file.txt', $result);
    }

    #[Test]
    public function my_basename_handles_no_separator(): void
    {
        $result = $this->invokePrivate('my_basename', ['file.txt']);
        $this->assertSame('file.txt', $result);
    }

    #[Test]
    public function my_basename_strips_suffix(): void
    {
        $result = $this->invokePrivate('my_basename', ['path//file.txt', '.txt']);
        $this->assertSame('file', $result);
    }

    // ─── validate ───

    #[Test]
    public function validate_returns_false_on_error_string(): void
    {
        $file = new \stdClass();
        $file->name = 'test.xliff';
        $file->size = 100;
        $file->type = 'application/xml';

        $result = $this->invokePrivate('validate', ['/tmp/test', $file, 'upload error']);

        $this->assertFalse($result);
        $this->assertSame('upload error', $file->error);
    }

    #[Test]
    public function validate_returns_false_for_too_large_file(): void
    {
        $this->handler->initForTest(['max_file_size' => 10]);

        $file = new \stdClass();
        $file->name = 'test.xliff';
        $file->size = 100;
        $file->type = 'application/xml';

        $_SERVER['CONTENT_LENGTH'] = 100;

        $result = $this->invokePrivate('validate', ['', $file, '']);

        $this->assertFalse($result);
        $this->assertSame('maxFileSize', $file->error);

        unset($_SERVER['CONTENT_LENGTH']);
    }

    #[Test]
    public function validate_returns_false_for_too_small_file(): void
    {
        $this->handler->initForTest(['min_file_size' => 50]);

        $file = new \stdClass();
        $file->name = 'test.xliff';
        $file->size = 10;
        $file->type = 'application/xml';

        $_SERVER['CONTENT_LENGTH'] = 10;

        $result = $this->invokePrivate('validate', ['', $file, '']);

        $this->assertFalse($result);
        $this->assertSame('minFileSize', $file->error);

        unset($_SERVER['CONTENT_LENGTH']);
    }

    #[Test]
    public function validate_returns_false_for_wrong_extension(): void
    {
        $file = new \stdClass();
        $file->name = 'malware.exe';
        $file->size = 100;
        $file->type = null;

        $_SERVER['CONTENT_LENGTH'] = 100;

        $result = $this->invokePrivate('validate', ['', $file, '']);

        $this->assertFalse($result);
        $this->assertSame('File extension not allowed', $file->error);

        unset($_SERVER['CONTENT_LENGTH']);
    }

    // ─── _validateToken ───

    #[Test]
    public function validateToken_throws_for_a_malformed_token(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid upload token.');

        $this->invokePrivate('_validateToken', ['not-a-uuid']);
    }

    #[Test]
    public function validateToken_throws_for_an_empty_token(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid upload token.');

        $this->invokePrivate('_validateToken', ['']);
    }

    #[Test]
    public function validateToken_accepts_a_well_formed_uuid(): void
    {
        $this->invokePrivate('_validateToken', ['3f2504e0-4f89-11d3-9a0c-0305e82c3301']);

        $this->addToAssertionCount(1); // no exception thrown is the assertion
    }

    #[Test]
    public function validate_returns_false_for_filename_too_long(): void
    {
        $file = new \stdClass();
        $file->name = str_repeat('a', 215) . '.xliff';
        $file->size = 100;
        $file->type = null;

        $_SERVER['CONTENT_LENGTH'] = 100;

        $result = $this->invokePrivate('validate', ['', $file, '']);

        $this->assertFalse($result);

        unset($_SERVER['CONTENT_LENGTH']);
    }

    // ─── validate() — remaining branches ───

    #[Test]
    public function validate_returns_false_when_the_upload_token_is_invalid(): void
    {
        $this->handler->initForTest(['upload_token' => 'not-a-uuid']);

        $file = new \stdClass();
        $file->name = 'document.xliff';
        $file->size = 100;
        $file->type = 'application/xml';

        $result = $this->invokePrivate('validate', ['', $file, '']);

        $this->assertFalse($result);
        $this->assertSame('Invalid upload token.', $file->error);
    }

    #[Test]
    public function validate_returns_false_when_too_many_files_are_already_present(): void
    {
        file_put_contents('/tmp/opencode/upload_test/already-there.xliff', 'x');
        $this->handler->initForTest(['max_number_of_files' => 1]);

        $file = new \stdClass();
        $file->name = 'document.xliff';
        $file->size = 100;
        $file->type = 'application/xml';

        $_SERVER['CONTENT_LENGTH'] = 100;

        $result = $this->invokePrivate('validate', ['', $file, '']);

        $this->assertFalse($result);
        $this->assertSame('Too many files uploaded. Please remove this file to continue.', $file->error);
    }

    /**
     * getRealFilesCount() skips the sha1_sha1|lang bookkeeping entries, so they must not count
     * toward max_number_of_files.
     */
    #[Test]
    public function validate_ignores_sha_bookkeeping_files_in_the_file_count(): void
    {
        $sha = str_repeat('a', 40) . '_' . str_repeat('b', 40) . '|en-US';
        file_put_contents('/tmp/opencode/upload_test/' . $sha, 'x');
        $this->handler->initForTest(['max_number_of_files' => 1]);

        $file = new \stdClass();
        $file->name = 'document.xliff';
        $file->size = 100;
        $file->type = 'application/xml';

        $_SERVER['CONTENT_LENGTH'] = 100;

        $this->assertTrue($this->invokePrivate('validate', ['', $file, '']));
    }

    #[Test]
    public function validate_returns_false_for_unsupported_mime_type(): void
    {
        $file = new \stdClass();
        $file->name = 'document.xliff';
        $file->size = 100;
        $file->type = 'application/x-shockwave-flash';

        $_SERVER['CONTENT_LENGTH'] = 100;

        $result = $this->invokePrivate('validate', ['', $file, '']);

        $this->assertFalse($result);
        $this->assertSame('File format not supported', $file->error);
    }

    #[Test]
    public function validate_returns_false_for_a_missing_file_name(): void
    {
        $file = new \stdClass();
        $file->name = '.';
        $file->size = 100;
        $file->type = null;

        $_SERVER['CONTENT_LENGTH'] = 100;

        $result = $this->invokePrivate('validate', ['', $file, '']);

        $this->assertFalse($result);
        $this->assertSame('Invalid file name: .', $file->error);
    }

    #[Test]
    public function validate_returns_true_for_an_acceptable_file(): void
    {
        $file = new \stdClass();
        $file->name = 'document.xliff';
        $file->size = 100;
        $file->type = 'application/xml';

        $_SERVER['CONTENT_LENGTH'] = 100;

        $this->assertTrue($this->invokePrivate('validate', ['', $file, '']));
    }

    // ─── trim_file_name — collision loop ───

    #[Test]
    public function trim_file_name_counts_up_when_the_target_name_is_taken(): void
    {
        file_put_contents('/tmp/opencode/upload_test/taken.xliff', 'x');

        $result = $this->invokePrivate('trim_file_name', ['taken.xliff']);

        $this->assertSame('taken_(1).xliff', $result);
    }

    // ─── get ───

    #[Test]
    public function get_returns_the_whole_listing_without_a_file_param(): void
    {
        file_put_contents('/tmp/opencode/upload_test/one.xliff', 'a');

        [$output] = $this->callAndCapture(fn() => $this->handler->get());

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertSame(['one.xliff'], array_column($decoded, 'name'));
    }

    #[Test]
    public function get_returns_a_single_file_object_when_asked_for_one(): void
    {
        file_put_contents('/tmp/opencode/upload_test/one.xliff', 'abc');
        $_REQUEST['file'] = 'one.xliff';

        [$output] = $this->callAndCapture(fn() => $this->handler->get());

        $decoded = json_decode($output, true);
        $this->assertSame('one.xliff', $decoded['name']);
        $this->assertSame(3, $decoded['size']);
    }

    // ─── delete ───

    /**
     * delete() does not go through flush(): it writes its own headers and echoes. The early-error
     * path returns before touching $_REQUEST['source'] or the database.
     */
    #[Test]
    public function delete_reports_an_invalid_token_without_touching_the_database(): void
    {
        $this->handler->initForTest(['upload_token' => 'not-a-uuid']);
        $_REQUEST['file'] = 'one.xliff';

        [$output] = $this->callAndCapture(fn() => $this->handler->delete());

        $this->assertSame(['code' => -1, 'error' => 'Invalid upload token.'], json_decode($output, true));
    }

    #[Test]
    public function delete_reports_an_invalid_file_name(): void
    {
        $_REQUEST['file'] = '.';

        [$output] = $this->callAndCapture(fn() => $this->handler->delete());

        $decoded = json_decode($output, true);
        $this->assertSame(-1, $decoded['code']);
        $this->assertStringContainsString('Invalid file name', $decoded['error']);
    }

    #[Test]
    public function delete_removes_a_normal_file(): void
    {
        $this->handler->setDatabaseForTest($this->createStub(IDatabase::class));
        file_put_contents('/tmp/opencode/upload_test/gone.xliff', 'bye');

        $_REQUEST['file'] = 'gone.xliff';
        $_REQUEST['source'] = 'en-US';
        $_REQUEST['segmentationRule'] = null;
        $_REQUEST['filtersTemplate'] = 0; // keeps deleteSha() off the database

        [$output] = $this->callAndCapture(fn() => $this->handler->delete());

        $this->assertSame(['gone.xliff' => true], json_decode($output, true));
        $this->assertFileDoesNotExist('/tmp/opencode/upload_test/gone.xliff');
    }

    #[Test]
    public function delete_reports_false_for_a_file_that_is_not_there(): void
    {
        $this->handler->setDatabaseForTest($this->createStub(IDatabase::class));

        $_REQUEST['file'] = 'never-existed.xliff';
        $_REQUEST['source'] = 'en-US';
        $_REQUEST['segmentationRule'] = null;
        $_REQUEST['filtersTemplate'] = 0;

        [$output] = $this->callAndCapture(fn() => $this->handler->delete());

        $this->assertSame(['never-existed.xliff' => false], json_decode($output, true));
    }

    #[Test]
    public function delete_routes_a_zip_to_the_zip_handler(): void
    {
        $this->handler->setDatabaseForTest($this->createStub(IDatabase::class));
        file_put_contents('/tmp/opencode/upload_test/bundle.zip', 'PK');

        $_REQUEST['file'] = 'bundle.zip';
        $_REQUEST['source'] = 'en-US';
        $_REQUEST['segmentationRule'] = null;
        $_REQUEST['filtersTemplate'] = 0;

        [$output] = $this->callAndCapture(fn() => $this->handler->delete());

        $this->assertArrayHasKey('bundle.zip', json_decode($output, true));
        $this->assertFileDoesNotExist('/tmp/opencode/upload_test/bundle.zip');
    }

    // ─── post ───

    #[Test]
    public function post_reports_an_invalid_upload_token_cookie(): void
    {
        $_COOKIE['upload_token'] = 'not-a-uuid';

        [, $flushed] = $this->callAndCapture(fn() => $this->handler->post());

        $this->assertNotNull($flushed, 'post() must flush and stop on a bad token');
        $this->assertStringContainsString(
            'Invalid upload token. Check your browser, cookies must be enabled for this domain.',
            $flushed[0]->error
        );
        // production die()s here, so exactly one flush must have happened
        $this->assertCount(1, $this->handler->flushCalls);
    }

    #[Test]
    public function post_delegates_to_delete_and_returns_without_flushing(): void
    {
        $this->handler->initForTest(['upload_token' => 'not-a-uuid']);
        $_COOKIE['upload_token'] = self::VALID_TOKEN;
        $_REQUEST['_method'] = 'DELETE';
        $_REQUEST['file'] = 'one.xliff';

        [$output, $flushed] = $this->callAndCapture(fn() => $this->handler->post());

        $this->assertNull($flushed);
        $this->assertSame([], $this->handler->flushCalls);
        $this->assertSame(['code' => -1, 'error' => 'Invalid upload token.'], json_decode($output, true));
    }

    /**
     * Regression guard for the endless multipart-preamble scan. PHP throws the request body away
     * once post_max_size is exceeded while CONTENT_LENGTH keeps the original size, so this branch is
     * entered with php://input already empty — and fread() answers '' rather than false there.
     * Before the fix the loop appended '' forever and this test would never return.
     */
    #[Test]
    public function post_reports_a_body_larger_than_post_max_size(): void
    {
        $_COOKIE['upload_token'] = self::VALID_TOKEN;
        $this->seedUploadParams(postMaxSize: 1_000, uploadMaxFilesize: 500);
        $_SERVER['CONTENT_LENGTH'] = 5_000;
        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data; boundary=----MatecatTestBoundary';

        [, $flushed] = $this->callAndCapture(fn() => $this->handler->post());

        $this->assertNotNull($flushed);
        $this->assertStringContainsString('The file is too large.', $flushed[0]->error);
        $this->assertStringContainsString('max header post size', $flushed[0]->error);
    }

    /**
     * Between upload_max_filesize and post_max_size the body is still readable, so the error is
     * attached to the file entry handle_file_upload() already produced.
     */
    #[Test]
    public function post_reports_a_file_larger_than_upload_max_filesize(): void
    {
        $this->handler->setLoggerForTest($this->createStub(MatecatLogger::class));
        $_COOKIE['upload_token'] = self::VALID_TOKEN;
        $this->seedUploadParams(postMaxSize: 10_000, uploadMaxFilesize: 100);
        $_SERVER['CONTENT_LENGTH'] = 500;

        // a non-empty upload error short-circuits validate(), so no file is written to disk
        $this->setFiles($this->makeUploadedFiles('document.xliff', '<?xml version="1.0"?><x/>', 500, 1));

        [, $flushed] = $this->callAndCapture(fn() => $this->handler->post());

        $this->assertNotNull($flushed);
        $this->assertStringContainsString('The file is too large.', $flushed[0]->error);
        $this->assertStringContainsString('max file upload', $flushed[0]->error);
    }

    /**
     * Walks the whole array-shaped upload branch and handle_file_upload()'s store path. Nothing can
     * be written from a unit test (is_uploaded_file() is always false in CLI and php://input is
     * empty), so the declared size never matches what lands on disk and the aborted-upload cleanup
     * runs — which is the behaviour asserted here.
     */
    #[Test]
    public function post_handles_an_uploaded_file_and_aborts_when_nothing_arrives(): void
    {
        $this->handler->setLoggerForTest($this->createStub(MatecatLogger::class));
        $_COOKIE['upload_token'] = self::VALID_TOKEN;
        $this->seedUploadParams(postMaxSize: 10_000, uploadMaxFilesize: 10_000);
        $_SERVER['CONTENT_LENGTH'] = 25;

        $this->setFiles($this->makeUploadedFiles('document.xliff', '<?xml version="1.0"?><x/>', 25));

        [, $flushed] = $this->callAndCapture(fn() => $this->handler->post());

        $this->assertNotNull($flushed);
        $this->assertCount(1, $flushed);
        $this->assertSame('document.xliff', $flushed[0]->name);
        $this->assertSame(
            'File upload failed. Refresh the page using CTRL+R (or CMD+R) and try again.',
            $flushed[0]->error
        );
        $this->assertTrue($flushed[0]->convert);
        $this->assertObjectHasProperty('delete_url', $flushed[0]);
        $this->assertFileDoesNotExist('/tmp/opencode/upload_test/document.xliff');
    }
}
