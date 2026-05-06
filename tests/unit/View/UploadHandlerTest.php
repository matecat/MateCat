<?php

namespace unit\View;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionException;
use TestHelpers\AbstractTest;
use UploadHandler;

require_once __DIR__ . '/../../../lib/View/fileupload/UploadHandler.php';

class TestableUploadHandler extends UploadHandler
{
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
}

#[AllowMockObjectsWithoutExpectations]
class UploadHandlerTest extends AbstractTest
{
    private ReflectionClass $reflector;
    private TestableUploadHandler $handler;

    /**
     * @throws ReflectionException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = new TestableUploadHandler();
        $this->handler->initForTest();
        $this->reflector = new ReflectionClass(UploadHandler::class);

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
        $this->assertSame('File Extension Not Allowed', $file->error);

        unset($_SERVER['CONTENT_LENGTH']);
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
}
