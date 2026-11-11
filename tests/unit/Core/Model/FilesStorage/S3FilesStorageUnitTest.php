<?php

namespace Matecat\Core\Model\FilesStorage;

use Exception;
use Matecat\SimpleS3\ClientInterface;
use Matecat\TestHelpers\AbstractTest;
use Model\FilesStorage\FilesystemAdapter;
use Model\FilesStorage\NativeFilesystemAdapter;
use Model\FilesStorage\S3FilesStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use UnexpectedValueException;
use Utils\Redis\RedisHandler;
use Utils\Registry\AppConfig;

class S3FilesStorageUnitTest extends AbstractTest
{
    private string $originalBucket = '';
    private bool $originalFiltersForceVersion = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalBucket = AppConfig::$AWS_STORAGE_BASE_BUCKET;
        $this->originalFiltersForceVersion = AppConfig::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION;
    }

    protected function tearDown(): void
    {
        AppConfig::$AWS_STORAGE_BASE_BUCKET = $this->originalBucket;
        AppConfig::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION = $this->originalFiltersForceVersion;
        parent::tearDown();
    }

    /**
     * Helper: create a ClientInterface stub with a __call callback.
     *
     * @param callable(string, array): mixed $callback
     * @return ClientInterface
     */
    private function stubClient(callable $callback): ClientInterface
    {
        $stub = $this->createStub(ClientInterface::class);
        $stub->method('__call')->willReturnCallback($callback);

        return $stub;
    }

    private function createS3WithMocks(?ClientInterface $client = null, ?FilesystemAdapter $fs = null): S3FilesStorage
    {
        if (empty(AppConfig::$AWS_STORAGE_BASE_BUCKET)) {
            AppConfig::$AWS_STORAGE_BASE_BUCKET = 'test-bucket';
        }

        return new S3FilesStorage(
            $fs ?? $this->createStub(FilesystemAdapter::class),
            $client ?? $this->createStub(ClientInterface::class)
        );
    }

    // ========================================================================
    // Pure-logic tests (no S3 interaction)
    // ========================================================================

    #[Test]
    public function test_getUploadSessionSafeName_removes_braces_and_lowercases(): void
    {
        $result = S3FilesStorage::getUploadSessionSafeName('{CAD1B6E1-B312}');
        $this->assertSame('cad1b6e1-b312', $result);
    }

    #[Test]
    public function test_getTheLastPartOfKey_normal_path(): void
    {
        $fs = $this->createS3WithMocks();
        $this->assertSame('hello.txt', $fs->getTheLastPartOfKey('c1/68/orig/hello.txt'));
    }

    #[Test]
    public function test_getTheLastPartOfKey_single_segment(): void
    {
        $fs = $this->createS3WithMocks();
        $this->assertSame('file.txt', $fs->getTheLastPartOfKey('file.txt'));
    }

    #[Test]
    public function test_getTheLastPartOfKey_empty_string(): void
    {
        $fs = $this->createS3WithMocks();
        $this->assertSame('', $fs->getTheLastPartOfKey(''));
    }

    #[Test]
    public function test_getTheLastPartOfKey_with_directory_separator(): void
    {
        $fs = $this->createS3WithMocks();
        $this->assertSame('last', $fs->getTheLastPartOfKey('first' . DIRECTORY_SEPARATOR . 'second' . DIRECTORY_SEPARATOR . 'last'));
    }

    #[Test]
    public function test_getCachePackageHashFolder_format(): void
    {
        $fs = $this->createS3WithMocks();
        $hash = 'abcdef1234567890abcdef1234567890abcdef12';
        $result = $fs->getCachePackageHashFolder($hash, 'en-US');
        $expected = 'cache-package' . DIRECTORY_SEPARATOR . 'ab' . DIRECTORY_SEPARATOR . 'cd'
            . DIRECTORY_SEPARATOR . 'ef1234567890abcdef1234567890abcdef12' . '__en-US';
        $this->assertSame($expected, $result);
    }

    #[Test]
    public function test_getCachePackageHashFolder_different_lang(): void
    {
        $fs = $this->createS3WithMocks();
        $hash = '1234567890abcdef1234567890abcdef12345678';
        $result = $fs->getCachePackageHashFolder($hash, 'it-IT');
        $this->assertStringEndsWith('__it-IT', $result);
        $this->assertStringStartsWith('cache-package' . DIRECTORY_SEPARATOR . '12' . DIRECTORY_SEPARATOR . '34', $result);
    }

    #[Test]
    public function test_getOriginalZipPath_valid_date(): void
    {
        $fs = $this->createS3WithMocks();
        $result = $fs->getOriginalZipPath('2023-01-15', '42', 'archive.zip');
        $this->assertSame('originalZip' . DIRECTORY_SEPARATOR . '20230115' . DIRECTORY_SEPARATOR . '42' . DIRECTORY_SEPARATOR . 'archive.zip', $result);
    }

    #[Test]
    public function test_getOriginalZipPath_returns_the_hashed_zip_when_stored(): void
    {
        $checkedKeys = [];
        $client = $this->stubClient(function (string $name, array $args) use (&$checkedKeys) {
            if ($name === 'hasItem') {
                $checkedKeys[] = $args[0]['key'];

                return true;
            }

            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $expected = 'originalZip' . DIRECTORY_SEPARATOR . '20230115' . DIRECTORY_SEPARATOR . '42' . DIRECTORY_SEPARATOR . sha1('archive.zip') . '.zip';

        $this->assertSame($expected, $fs->getOriginalZipPath('2023-01-15', '42', 'archive.zip'));
        $this->assertSame([$expected], $checkedKeys);
    }

    #[Test]
    public function test_getOriginalZipPath_returns_the_legacy_zip_when_not_hashed(): void
    {
        $client = $this->stubClient(function (string $name) {
            return $name === 'hasItem' ? false : null;
        });

        $fs = $this->createS3WithMocks($client);

        $this->assertSame(
            'originalZip' . DIRECTORY_SEPARATOR . '20230115' . DIRECTORY_SEPARATOR . '42' . DIRECTORY_SEPARATOR . 'архив.zip',
            $fs->getOriginalZipPath('2023-01-15', '42', 'архив.zip')
        );
    }

    #[Test]
    public function test_getOriginalZipDir_valid_date(): void
    {
        $fs = $this->createS3WithMocks();
        $result = $fs->getOriginalZipDir('2023-01-15', '42');
        $this->assertSame('work' . DIRECTORY_SEPARATOR . '20230115' . DIRECTORY_SEPARATOR . '42', $result);
    }

    #[Test]
    public function test_getOriginalZipPath_invalid_date_throws(): void
    {
        $fs = $this->createS3WithMocks();
        $this->expectException(\InvalidArgumentException::class);
        $fs->getOriginalZipPath('not-a-date', '42', 'archive.zip');
    }

    #[Test]
    public function test_getOriginalZipDir_invalid_date_throws(): void
    {
        $fs = $this->createS3WithMocks();
        $this->expectException(\InvalidArgumentException::class);
        $fs->getOriginalZipDir('not-a-date', '42');
    }

    #[Test]
    public function test_getFilesStorageBucket_returns_bucket(): void
    {
        $this->createS3WithMocks();
        $bucket = S3FilesStorage::getFilesStorageBucket();
        $this->assertNotEmpty($bucket);
    }

    #[Test]
    public function test_constructor_throws_when_bucket_empty(): void
    {
        AppConfig::$AWS_STORAGE_BASE_BUCKET = '';
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('$AWS_STORAGE_BASE_BUCKET param is missing');
        new S3FilesStorage($this->createStub(FilesystemAdapter::class), $this->createStub(ClientInterface::class));
    }

    #[Test]
    public function test_constructor_sets_bucket(): void
    {
        AppConfig::$AWS_STORAGE_BASE_BUCKET = 'my-custom-bucket';
        new S3FilesStorage($this->createStub(FilesystemAdapter::class), $this->createStub(ClientInterface::class));
        $this->assertSame('my-custom-bucket', S3FilesStorage::getFilesStorageBucket());
    }

    #[Test]
    public function test_constants_are_defined(): void
    {
        $this->assertSame('__originalZip', S3FilesStorage::ORIGINAL_ZIP_PLACEHOLDER);
        $this->assertSame('cache-package', S3FilesStorage::CACHE_PACKAGE_FOLDER);
        $this->assertSame('files', S3FilesStorage::FILES_FOLDER);
        $this->assertSame('queue-projects', S3FilesStorage::QUEUE_FOLDER);
        $this->assertSame('originalZip', S3FilesStorage::ZIP_FOLDER);
        $this->assertSame('fast-analysis', S3FilesStorage::FAST_ANALYSIS_FOLDER);
    }

    // ========================================================================
    // getOriginalFromCache / getXliffFromCache
    // ========================================================================

    #[Test]
    public function test_getOriginalFromCache_returns_false_for_empty_lang(): void
    {
        $fs = $this->createS3WithMocks();
        $this->assertFalse($fs->getOriginalFromCache('somehash', ''));
    }

    #[Test]
    public function test_getXliffFromCache_returns_false_for_empty_lang(): void
    {
        $fs = $this->createS3WithMocks();
        $this->assertFalse($fs->getXliffFromCache('somehash', ''));
    }

    #[Test]
    public function test_getOriginalFromCache_returns_item_when_found(): void
    {
        $client = $this->stubClient(function (string $name, array $args) {
            if ($name === 'getItemsInABucket') {
                return ['cache-package/ab/cd/rest__en-US/orig/file.txt'];
            }
            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $result = $fs->getOriginalFromCache('abcdef1234567890abcdef1234567890abcdef12', 'en-US');
        $this->assertSame('cache-package/ab/cd/rest__en-US/orig/file.txt', $result);
    }

    #[Test]
    public function test_getOriginalFromCache_returns_false_when_empty(): void
    {
        $client = $this->stubClient(function (string $name, array $args) {
            if ($name === 'getItemsInABucket') {
                return [];
            }
            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $result = $fs->getOriginalFromCache('abcdef1234567890abcdef1234567890abcdef12', 'en-US');
        $this->assertFalse($result);
    }

    #[Test]
    public function test_getXliffFromCache_returns_item_when_found(): void
    {
        $client = $this->stubClient(function (string $name, array $args) {
            if ($name === 'getItemsInABucket') {
                return ['cache-package/ab/cd/rest__en-US/work/file.xliff'];
            }
            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $result = $fs->getXliffFromCache('abcdef1234567890abcdef1234567890abcdef12', 'en-US');
        $this->assertSame('cache-package/ab/cd/rest__en-US/work/file.xliff', $result);
    }

    /**
     * A cache package written before the names were hashed, then written again: the content-hash objects win.
     */
    #[Test]
    public function test_cache_readers_prefer_the_content_hash_objects(): void
    {
        $hash = 'abcdef1234567890abcdef1234567890abcdef12';
        $client = $this->stubClient(function (string $name, array $args) use ($hash) {
            if ($name === 'getItemsInABucket') {
                $folder = str_contains($args[0]['prefix'], '/orig') ? 'orig' : 'work';
                $ext = $folder === 'orig' ? '.docx' : '.docx.sdlxliff';

                return [
                    "cache-package/ab/cd/rest__en-US/$folder/0-legacy$ext",
                    "cache-package/ab/cd/rest__en-US/$folder/$hash$ext",
                ];
            }

            return null;
        });

        $fs = $this->createS3WithMocks($client);

        $this->assertSame("cache-package/ab/cd/rest__en-US/orig/$hash.docx", $fs->getOriginalFromCache($hash, 'en-US'));
        $this->assertSame("cache-package/ab/cd/rest__en-US/work/$hash.docx.sdlxliff", $fs->getXliffFromCache($hash, 'en-US'));
    }

    #[Test]
    public function test_getXliffFromCache_returns_false_when_empty(): void
    {
        $client = $this->stubClient(function (string $name, array $args) {
            if ($name === 'getItemsInABucket') {
                return [];
            }
            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $result = $fs->getXliffFromCache('abcdef1234567890abcdef1234567890abcdef12', 'en-US');
        $this->assertFalse($result);
    }

    // ========================================================================
    // getOriginalFromFileDir / getXliffFromFileDir
    // ========================================================================

    #[Test]
    public function test_getOriginalFromFileDir_returns_item_when_found(): void
    {
        $client = $this->stubClient(function (string $name, array $args) {
            if ($name === 'getItemsInABucket') {
                return ['files/20230115/13/orig/file.txt'];
            }
            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $result = $fs->getOriginalFromFileDir('13', '20230115');
        $this->assertSame('files/20230115/13/orig/file.txt', $result);
    }

    #[Test]
    public function test_getOriginalFromFileDir_returns_false_when_empty(): void
    {
        $client = $this->stubClient(function (string $name, array $args) {
            if ($name === 'getItemsInABucket') {
                return [];
            }
            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $result = $fs->getOriginalFromFileDir('13', '20230115');
        $this->assertFalse($result);
    }

    #[Test]
    public function test_getXliffFromFileDir_returns_item_when_found(): void
    {
        $client = $this->stubClient(function (string $name, array $args) {
            if ($name === 'getItemsInABucket') {
                return ['files/20230115/13/xliff/file.xliff'];
            }
            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $result = $fs->getXliffFromFileDir('13', '20230115');
        $this->assertSame('files/20230115/13/xliff/file.xliff', $result);
    }

    #[Test]
    public function test_getXliffFromFileDir_returns_false_when_empty(): void
    {
        $client = $this->stubClient(function (string $name, array $args) {
            if ($name === 'getItemsInABucket') {
                return [];
            }
            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $result = $fs->getXliffFromFileDir('13', '20230115');
        $this->assertFalse($result);
    }

    // ========================================================================
    // deleteQueue
    // ========================================================================

    #[Test]
    public function test_deleteQueue_calls_deleteFolder(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('__call')
            ->with('deleteFolder', $this->callback(function (array $args) {
                return isset($args[0]['bucket']) && isset($args[0]['prefix'])
                    && str_contains($args[0]['prefix'], 'queue-projects');
            }))
            ->willReturn(true);

        $fs = $this->createS3WithMocks($client);
        $fs->deleteQueue('/some/path/session1');
    }

    #[Test]
    public function test_deleteQueue_with_braces(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('__call')
            ->with('deleteFolder', $this->anything())
            ->willReturn(true);

        $fs = $this->createS3WithMocks($client);
        $fs->deleteQueue('/path/{ABC-123}');
    }

    // ========================================================================
    // storeFastAnalysisFile
    // ========================================================================

    #[Test]
    public function test_storeFastAnalysisFile_success(): void
    {
        $client = $this->stubClient(function (string $name, array $args) {
            if ($name === 'uploadItemFromBody') {
                return true;
            }
            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $fs->storeFastAnalysisFile('123', ['seg1' => 'data']);
        $this->assertTrue(true);
    }

    #[Test]
    public function test_storeFastAnalysisFile_failure_throws(): void
    {
        $client = $this->stubClient(function (string $name, array $args) {
            if ($name === 'uploadItemFromBody') {
                return false;
            }
            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $this->expectException(UnexpectedValueException::class);
        $fs->storeFastAnalysisFile('123', ['seg1' => 'data']);
    }

    // ========================================================================
    // getFastAnalysisData
    // ========================================================================

    #[Test]
    public function test_getFastAnalysisData_success(): void
    {
        $data = ['seg1' => 'data', 'seg2' => 'more'];
        $client = $this->stubClient(function (string $name, array $args) use ($data) {
            if ($name === 'openItem') {
                return serialize($data);
            }
            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $result = $fs->getFastAnalysisData(123);
        $this->assertSame($data, $result);
    }

    #[Test]
    public function test_getFastAnalysisData_failure_throws(): void
    {
        $client = $this->stubClient(function (string $name, array $args) {
            if ($name === 'openItem') {
                return 'not-serialized-data';
            }
            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $this->expectException(UnexpectedValueException::class);
        $fs->getFastAnalysisData(123);
    }

    // ========================================================================
    // deleteFastAnalysisFile
    // ========================================================================

    #[Test]
    public function test_deleteFastAnalysisFile_returns_true(): void
    {
        $client = $this->stubClient(function (string $name, array $args) {
            if ($name === 'deleteItem') {
                return true;
            }
            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $this->assertTrue($fs->deleteFastAnalysisFile('123'));
    }

    // ========================================================================
    // transferFiles
    // ========================================================================

    #[Test]
    public function test_transferFiles_returns_true(): void
    {
        $client = $this->stubClient(function (string $name, array $args) {
            if ($name === 'transfer') {
                return true;
            }
            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $this->assertTrue($fs->transferFiles('s3://source', 's3://dest'));
    }

    // ========================================================================
    // makeCachePackage
    // ========================================================================

    private const string HASH = 'abcdef1234567890abcdef1234567890abcdef12';

    /**
     * A client recording every call; uploadItem calls $onUpload, when given, with the key.
     *
     * @param list<array{0: string, 1: array<string, mixed>}> $calls
     */
    private function recordingClient(array &$calls, bool $hasItem = false, ?callable $onUpload = null): ClientInterface
    {
        return $this->stubClient(function (string $name, array $args) use (&$calls, $hasItem, $onUpload) {
            $calls[] = [$name, $args[0]];
            if ($name === 'hasItem') {
                return $hasItem;
            }
            if ($name === 'uploadItem' && $onUpload !== null) {
                $onUpload($args[0]['key']);
            }

            return true;
        });
    }

    /**
     * @param list<array{0: string, 1: array<string, mixed>}> $calls
     *
     * @return list<array<string, mixed>>
     */
    private function argsOf(array $calls, string $method): array
    {
        return array_values(array_map(fn(array $call) => $call[1], array_filter($calls, fn(array $call) => $call[0] === $method)));
    }

    private function nativeXliff(string $dir, string $name, string $fixture): string
    {
        $path = $dir . '/' . $name;
        copy(AppConfig::$ROOT . '/tests/resources/files/' . $fixture, $path);

        return $path;
    }

    /**
     * B10: the guard still looks for the legacy key, built from the xliff name as before.
     */
    #[Test]
    public function test_makeCachePackage_forced_filter_version_skips_the_upload_when_the_legacy_key_exists(): void
    {
        AppConfig::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION = true;
        $calls = [];

        $fs = $this->createS3WithMocks($this->recordingClient($calls, true));

        $this->assertTrue($fs->makeCachePackage(self::HASH, 'en-US', '/tmp/original.txt', '/tmp/test.xliff'));
        $prefix = $fs->getCachePackageHashFolder(self::HASH, 'en-US');
        $this->assertSame([$prefix . '/work/test.xliff'], array_column($this->argsOf($calls, 'hasItem'), 'key'));
        $this->assertSame([], $this->argsOf($calls, 'uploadItem'));
    }

    #[Test]
    public function test_makeCachePackage_forced_filter_version_checks_a_long_name_under_its_legacy_hashed_key(): void
    {
        AppConfig::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION = true;
        $calls = [];
        $stem = str_repeat('a', 250);

        $fs = $this->createS3WithMocks($this->recordingClient($calls, true));

        $this->assertTrue($fs->makeCachePackage(self::HASH, 'en-US', '/tmp/original.txt', '/tmp/' . $stem . '.XLIFF'));
        $prefix = $fs->getCachePackageHashFolder(self::HASH, 'en-US');
        $this->assertSame([$prefix . '/work/' . sha1($stem) . '.xliff'], array_column($this->argsOf($calls, 'hasItem'), 'key'));
    }

    #[Test]
    public function test_makeCachePackage_without_a_forced_filter_version_never_checks_the_bucket(): void
    {
        AppConfig::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION = false;
        $calls = [];

        $fs = $this->createS3WithMocks($this->recordingClient($calls, true));

        $this->assertTrue($fs->makeCachePackage(self::HASH, 'en-US', '/tmp/original.txt', '/tmp/test.xliff'));
        $this->assertSame([], $this->argsOf($calls, 'hasItem'));
        $this->assertCount(2, $this->argsOf($calls, 'uploadItem'));
    }

    /**
     * B2: the local paths are uploaded as they are, whatever the length of the name.
     */
    #[Test]
    public function test_makeCachePackage_uploads_a_converted_file_under_the_content_hash(): void
    {
        AppConfig::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION = false;
        $calls = [];
        $original = '/upload/session/' . str_repeat('ب', 120) . '.DOCX';

        $fsMock = $this->createMock(FilesystemAdapter::class);
        $fsMock->expects($this->once())->method('unlink')->with('/tmp/MAT_XLF1234')->willReturn(true);

        $fs = $this->createS3WithMocks($this->recordingClient($calls), $fsMock);

        $this->assertTrue($fs->makeCachePackage(self::HASH, 'en-US', $original, '/tmp/MAT_XLF1234'));
        $prefix = $fs->getCachePackageHashFolder(self::HASH, 'en-US');
        $this->assertSame([
            ['bucket' => S3FilesStorage::getFilesStorageBucket(), 'key' => $prefix . '/orig/' . self::HASH . '.docx', 'source' => $original],
            ['bucket' => S3FilesStorage::getFilesStorageBucket(), 'key' => $prefix . '/work/' . self::HASH . '.docx.sdlxliff', 'source' => '/tmp/MAT_XLF1234'],
        ], $this->argsOf($calls, 'uploadItem'));
        $this->assertSame([], $this->argsOf($calls, 'deleteItem'));
    }

    /**
     * B1: the cleanup deletes the key that was written.
     */
    #[Test]
    public function test_makeCachePackage_deletes_the_hashed_original_when_the_xliff_upload_fails(): void
    {
        AppConfig::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION = false;
        $calls = [];
        $client = $this->recordingClient($calls, false, function (string $key) {
            if (str_contains($key, '/work/')) {
                throw new Exception('Upload failed for xliff');
            }
        });

        $fs = $this->createS3WithMocks($client);
        $prefix = $fs->getCachePackageHashFolder(self::HASH, 'en-US');

        try {
            $fs->makeCachePackage(self::HASH, 'en-US', '/upload/session/' . str_repeat('a', 250) . '.txt', '/tmp/MAT_XLF');
            $this->fail('The upload failure must reach the caller');
        } catch (Exception $e) {
            $this->assertSame('Upload failed for xliff', $e->getMessage());
        }

        $this->assertSame(
            [['bucket' => S3FilesStorage::getFilesStorageBucket(), 'key' => $prefix . '/orig/' . self::HASH . '.txt']],
            $this->argsOf($calls, 'deleteItem')
        );
    }

    #[Test]
    public function test_makeCachePackage_original_upload_failure_reaches_the_caller_with_nothing_deleted(): void
    {
        AppConfig::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION = false;
        $calls = [];
        $client = $this->recordingClient($calls, false, function () {
            throw new Exception('Upload failed for original');
        });

        $fs = $this->createS3WithMocks($client);

        try {
            $fs->makeCachePackage(self::HASH, 'en-US', '/upload/session/a.txt', '/tmp/MAT_XLF');
            $this->fail('The upload failure must reach the caller');
        } catch (Exception $e) {
            $this->assertSame('Upload failed for original', $e->getMessage());
        }

        $this->assertCount(1, $this->argsOf($calls, 'uploadItem'));
        $this->assertSame([], $this->argsOf($calls, 'deleteItem'));
    }

    #[Test]
    public function test_makeCachePackage_uploads_a_plain_native_xliff_with_the_forced_sdlxliff_extension(): void
    {
        AppConfig::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION = false;
        $dir = sys_get_temp_dir() . '/s3_native_' . uniqid();
        mkdir($dir);
        try {
            $xliff = $this->nativeXliff($dir, str_repeat('x', 240) . '.xlf', 'test-propagation.xlf');
            $calls = [];
            $fsMock = $this->createMock(FilesystemAdapter::class);
            $fsMock->expects($this->once())->method('unlink')->with($xliff)->willReturn(true);

            $fs = $this->createS3WithMocks($this->recordingClient($calls), $fsMock);

            $this->assertTrue($fs->makeCachePackage(self::HASH, 'en-US', null, $xliff));
            $prefix = $fs->getCachePackageHashFolder(self::HASH, 'en-US');
            $this->assertSame([
                ['bucket' => S3FilesStorage::getFilesStorageBucket(), 'key' => $prefix . '/work/' . self::HASH . '.xlf.sdlxliff', 'source' => $xliff],
            ], $this->argsOf($calls, 'uploadItem'));
        } finally {
            array_map('unlink', glob($dir . '/*') ?: []);
            rmdir($dir);
        }
    }

    #[Test]
    public function test_makeCachePackage_uploads_a_native_sdlxliff_with_no_extra_extension(): void
    {
        AppConfig::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION = false;
        $dir = sys_get_temp_dir() . '/s3_native_' . uniqid();
        mkdir($dir);
        try {
            $xliff = $this->nativeXliff($dir, 'спасибо.sdlxliff', 'small-with-notes.sdlxliff');
            $calls = [];

            $fs = $this->createS3WithMocks($this->recordingClient($calls));

            $this->assertTrue($fs->makeCachePackage(self::HASH, 'en-US', '', $xliff));
            $prefix = $fs->getCachePackageHashFolder(self::HASH, 'en-US');
            $this->assertSame([$prefix . '/work/' . self::HASH . '.sdlxliff'], array_column($this->argsOf($calls, 'uploadItem'), 'key'));
        } finally {
            array_map('unlink', glob($dir . '/*') ?: []);
            rmdir($dir);
        }
    }

    #[Test]
    public function test_makeCachePackage_native_xliff_upload_failure_reaches_the_caller_with_nothing_deleted(): void
    {
        AppConfig::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION = false;
        $dir = sys_get_temp_dir() . '/s3_native_' . uniqid();
        mkdir($dir);
        try {
            $xliff = $this->nativeXliff($dir, 'a.xlf', 'test-propagation.xlf');
            $calls = [];
            $client = $this->recordingClient($calls, false, function () {
                throw new Exception('Xliff upload failed');
            });

            $fs = $this->createS3WithMocks($client);

            try {
                $fs->makeCachePackage(self::HASH, 'en-US', null, $xliff);
                $this->fail('The upload failure must reach the caller');
            } catch (Exception $e) {
                $this->assertSame('Xliff upload failed', $e->getMessage());
            }
            $this->assertSame([], $this->argsOf($calls, 'deleteItem'));
        } finally {
            array_map('unlink', glob($dir . '/*') ?: []);
            rmdir($dir);
        }
    }

    // ========================================================================
    // moveFileFromUploadSessionToQueuePath
    // ========================================================================

    /**
     * A real file goes to the queue under its fixed-length name; the hash-link file and the zip placeholder, named by
     * MateCat, keep theirs, and the Redis file map still lists the hash-link file with the original names it holds.
     */
    #[Test]
    public function test_moveFileFromUploadSessionToQueuePath_uploads_real_files_under_the_hashed_name(): void
    {
        $originalUpload = AppConfig::$UPLOAD_REPOSITORY;
        $uploadRepository = sys_get_temp_dir() . '/s3_queue_' . uniqid();
        $session = '{AB12CD34-0000-4000-8000-' . strtoupper(substr(uniqid(), 0, 12)) . '}';
        $sessionDir = $uploadRepository . '/' . $session;
        mkdir($sessionDir, 0755, true);

        $longName = str_repeat('ب', 120) . '.DOCX';
        $zipEntry = 'archive.zip___SEP___dir1___SEP___doc.docx';
        $linkFile = sha1('a') . '_' . sha1('b') . '|it-IT';
        $zipPlaceholder = sha1('zip') . '__originalZip';
        file_put_contents($sessionDir . '/' . $longName, 'docx');
        file_put_contents($sessionDir . '/' . $zipEntry, 'zipped docx');
        file_put_contents($sessionDir . '/' . $linkFile, $longName . "\n" . $zipEntry . "\n");
        file_put_contents($sessionDir . '/' . $zipPlaceholder, '');

        $redisKey = S3FilesStorage::getUploadSessionSafeName($session);
        $redis = (new RedisHandler())->getConnection();

        try {
            AppConfig::$UPLOAD_REPOSITORY = $uploadRepository;
            $calls = [];
            $fs = $this->createS3WithMocks($this->recordingClient($calls), new NativeFilesystemAdapter());

            $fs->moveFileFromUploadSessionToQueuePath($session);

            $prefix = S3FilesStorage::QUEUE_FOLDER . '/' . $redisKey;
            $linkKey = $prefix . '/' . sha1($linkFile) . '__it-IT';
            $this->assertEqualsCanonicalizing([
                $prefix . '/' . sha1($longName) . '.docx' => $sessionDir . '/' . $longName,
                $prefix . '/' . sha1($zipEntry) . '.docx' => $sessionDir . '/' . $zipEntry,
                $linkKey => $sessionDir . '/' . $linkFile,
                $prefix . '/' . $zipPlaceholder => $sessionDir . '/' . $zipPlaceholder,
            ], array_column($this->argsOf($calls, 'uploadItem'), 'source', 'key'));

            $fileMap = unserialize((string)$redis->hget($redisKey, 'file_map'));
            $this->assertEqualsCanonicalizing([$linkKey, $prefix . '/' . $zipPlaceholder], array_keys($fileMap));
            $this->assertSame([$longName, $zipEntry], $fileMap[$linkKey]);
            $this->assertDirectoryDoesNotExist($sessionDir);
        } finally {
            AppConfig::$UPLOAD_REPOSITORY = $originalUpload;
            $redis->del([$redisKey]);
            array_map('unlink', glob($sessionDir . '/*') ?: []);
            @rmdir($sessionDir);
            @rmdir($uploadRepository);
        }
    }

    /**
     * The fixed-length queue name is the one the project creation looks for first.
     */
    #[Test]
    public function test_a_queued_file_is_downloaded_from_the_key_it_was_uploaded_to(): void
    {
        $originalUpload = AppConfig::$UPLOAD_REPOSITORY;
        $uploadRepository = sys_get_temp_dir() . '/s3_queue_' . uniqid();
        $session = '{AB12CD34-0000-4000-8000-' . strtoupper(substr(uniqid(), 0, 12)) . '}';
        mkdir($uploadRepository . '/' . $session, 0755, true);
        $name = 'спасибо ' . str_repeat('x', 200) . '.xlf';
        file_put_contents($uploadRepository . '/' . $session . '/' . $name, 'xliff');
        $redisKey = S3FilesStorage::getUploadSessionSafeName($session);

        try {
            AppConfig::$UPLOAD_REPOSITORY = $uploadRepository;
            $stored = [];
            $downloaded = [];
            $client = $this->stubClient(function (string $method, array $args) use (&$stored, &$downloaded) {
                if ($method === 'uploadItem') {
                    $stored[] = $args[0]['key'];
                }
                if ($method === 'hasItem') {
                    return in_array($args[0]['key'], $stored, true);
                }
                if ($method === 'downloadItem') {
                    $downloaded[] = $args[0]['key'];
                }

                return true;
            });
            $fs = $this->createS3WithMocks($client, new NativeFilesystemAdapter());

            $fs->moveFileFromUploadSessionToQueuePath($session);
            $fs->downloadQueueFile($uploadRepository . '/' . $session, $name);

            $this->assertCount(1, $stored);
            $this->assertSame($stored, $downloaded);
        } finally {
            AppConfig::$UPLOAD_REPOSITORY = $originalUpload;
            (new RedisHandler())->getConnection()->del([$redisKey]);
            @rmdir($uploadRepository);
        }
    }

    // ========================================================================
    // moveFromCacheToFileDir
    // ========================================================================

    #[Test]
    public function test_moveFromCacheToFileDir_success(): void
    {
        $client = $this->stubClient(function (string $name, array $args) {
            if ($name === 'getItemsInABucket') {
                $prefix = $args[0]['prefix'];
                if (str_contains($prefix, '/orig')) {
                    return ['cache-package/ab/cd/rest__en-US/orig/file.txt'];
                }
                if (str_contains($prefix, '/work')) {
                    return ['cache-package/ab/cd/rest__en-US/work/file.xliff'];
                }
                return [];
            }
            if ($name === 'copyInBatch') {
                return true;
            }
            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $hash = 'abcdef1234567890abcdef1234567890abcdef12';
        $result = $fs->moveFromCacheToFileDir('20230115' . DIRECTORY_SEPARATOR . $hash, 'en-US', '13');
        $this->assertTrue($result);
    }

    /**
     * @return array<string, array{string, bool, string}>
     */
    public static function queueFileProvider(): array
    {
        $prefix = 'queue-projects/0f8fad5b-d9cb-469f-a165-70867728950e/';
        $longStem = str_repeat('a', 220);

        return [
            'hashed name' => ['File.XLF', true, $prefix . sha1('File.XLF') . '.xlf'],
            'legacy name lowercases the extension' => ['File.XLF', false, $prefix . 'File.xlf'],
            'legacy long name is the sha1 of its stem' => [$longStem . '.sdlxliff', false, $prefix . sha1($longStem) . '.sdlxliff'],
            'legacy name without an extension' => ['README', false, $prefix . 'README'],
        ];
    }

    #[Test]
    #[DataProvider('queueFileProvider')]
    public function test_downloadQueueFile(string $fileName, bool $hashedExists, string $expectedKey): void
    {
        $downloads = [];
        $client = $this->stubClient(function (string $name, array $args) use ($hashedExists, &$downloads) {
            if ($name === 'hasItem') {
                return $hashedExists;
            }
            if ($name === 'downloadItem') {
                $downloads[] = $args[0];

                return true;
            }

            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $fs->downloadQueueFile('/queue/{0F8FAD5B-D9CB-469F-A165-70867728950E}', $fileName);

        $this->assertSame([[
            'bucket' => AppConfig::$AWS_STORAGE_BASE_BUCKET,
            'key' => $expectedKey,
            'save_as' => '/queue/{0F8FAD5B-D9CB-469F-A165-70867728950E}/' . $fileName,
        ]], $downloads);
    }

    #[Test]
    public function test_getItem_reads_the_object_from_the_bucket(): void
    {
        $client = $this->stubClient(function (string $name, array $args) {
            return $name === 'getItem' ? ['ContentLength' => 10, 'key' => $args[0]['key'], 'bucket' => $args[0]['bucket']] : null;
        });

        $fs = $this->createS3WithMocks($client);

        $this->assertSame(['ContentLength' => 10, 'key' => 'files/x.docx', 'bucket' => AppConfig::$AWS_STORAGE_BASE_BUCKET], $fs->getItem('files/x.docx'));
    }

    /**
     * A package holding a legacy object next to a content-hash one: the project gets exactly one of each, the hashed.
     */
    #[Test]
    public function test_moveFromCacheToFileDir_copies_one_preferred_object_per_folder(): void
    {
        $hash = 'abcdef1234567890abcdef1234567890abcdef12';
        $copied = [];
        $client = $this->stubClient(function (string $name, array $args) use ($hash, &$copied) {
            if ($name === 'getItemsInABucket') {
                if (str_contains($args[0]['prefix'], '/orig')) {
                    return ['cache-package/ab/cd/rest__en-US/orig/legacy.docx', "cache-package/ab/cd/rest__en-US/orig/$hash.docx"];
                }

                return ["cache-package/ab/cd/rest__en-US/work/$hash.docx.sdlxliff", 'cache-package/ab/cd/rest__en-US/work/legacy.docx.sdlxliff'];
            }
            if ($name === 'copyInBatch') {
                $copied = $args[0]['files'];

                return true;
            }

            return null;
        });

        $fs = $this->createS3WithMocks($client);

        $this->assertTrue($fs->moveFromCacheToFileDir('20230115' . DIRECTORY_SEPARATOR . $hash, 'en-US', '13'));
        $this->assertSame(
            ["cache-package/ab/cd/rest__en-US/orig/$hash.docx", "cache-package/ab/cd/rest__en-US/work/$hash.docx.sdlxliff"],
            $copied['source']
        );
        $this->assertSame(
            ["files/20230115/13/orig/$hash.docx", "files/20230115/13/xliff/$hash.docx.sdlxliff"],
            $copied['target']
        );
    }

    #[Test]
    public function test_moveFromCacheToFileDir_empty_items_retry_without_cache(): void
    {
        $callIndex = 0;
        $client = $this->stubClient(function (string $name, array $args) use (&$callIndex) {
            if ($name === 'getItemsInABucket') {
                $callIndex++;
                if ($callIndex <= 2) {
                    return [];
                }
                if ($callIndex === 3) {
                    return ['cache-package/ab/cd/rest__en-US/orig/file.txt'];
                }
                if ($callIndex === 4) {
                    return ['cache-package/ab/cd/rest__en-US/work/file.xliff'];
                }
                return [];
            }
            if ($name === 'copyInBatch') {
                return true;
            }
            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $hash = 'abcdef1234567890abcdef1234567890abcdef12';
        $result = $fs->moveFromCacheToFileDir('20230115' . DIRECTORY_SEPARATOR . $hash, 'en-US', '13');
        $this->assertTrue($result);
    }

    #[Test]
    public function test_moveFromCacheToFileDir_empty_items_both_calls_returns_false(): void
    {
        $client = $this->stubClient(function (string $name, array $args) {
            if ($name === 'getItemsInABucket') {
                return [];
            }
            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $hash = 'abcdef1234567890abcdef1234567890abcdef12';
        $result = $fs->moveFromCacheToFileDir('20230115' . DIRECTORY_SEPARATOR . $hash, 'en-US', '13');
        $this->assertFalse($result);
    }

    #[Test]
    public function test_moveFromCacheToFileDir_copyInBatch_throws_deletes_sources(): void
    {
        $deletedKeys = [];
        $client = $this->stubClient(function (string $name, array $args) use (&$deletedKeys) {
            if ($name === 'getItemsInABucket') {
                $prefix = $args[0]['prefix'];
                if (str_contains($prefix, '/orig')) {
                    return ['cache-package/ab/cd/rest__en-US/orig/file.txt'];
                }
                if (str_contains($prefix, '/work')) {
                    return ['cache-package/ab/cd/rest__en-US/work/file.xliff'];
                }
                return [];
            }
            if ($name === 'copyInBatch') {
                throw new Exception('Copy failed');
            }
            if ($name === 'deleteItem') {
                $deletedKeys[] = $args[0]['key'];
                return true;
            }
            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $hash = 'abcdef1234567890abcdef1234567890abcdef12';

        try {
            $fs->moveFromCacheToFileDir('20230115' . DIRECTORY_SEPARATOR . $hash, 'en-US', '13');
            $this->fail('Expected exception');
        } catch (Exception $e) {
            $this->assertSame('Copy failed', $e->getMessage());
            $this->assertCount(2, $deletedKeys);
        }
    }

    #[Test]
    public function test_moveFromCacheToFileDir_with_new_filename(): void
    {
        $client = $this->stubClient(function (string $name, array $args) {
            if ($name === 'getItemsInABucket') {
                $prefix = $args[0]['prefix'];
                if (str_contains($prefix, '/orig')) {
                    return ['cache-package/ab/cd/rest__en-US/orig/file.txt'];
                }
                if (str_contains($prefix, '/work')) {
                    return [];
                }
                return [];
            }
            if ($name === 'copyInBatch') {
                return true;
            }
            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $hash = 'abcdef1234567890abcdef1234567890abcdef12';
        $result = $fs->moveFromCacheToFileDir('20230115' . DIRECTORY_SEPARATOR . $hash, 'en-US', '13', 'newname.txt');
        $this->assertTrue($result);
    }

    // ========================================================================
    // cacheZipArchive
    // ========================================================================

    #[Test]
    public function test_cacheZipArchive_success(): void
    {
        $client = $this->stubClient(function (string $name, array $args) {
            if ($name === 'uploadItem') {
                return true;
            }
            return null;
        });

        $fsMock = $this->createMock(FilesystemAdapter::class);
        $fsMock->expects($this->once())->method('unlink')->willReturn(true);
        $fsMock->expects($this->once())->method('touch')->willReturn(true);

        $fs = $this->createS3WithMocks($client, $fsMock);
        $result = $fs->cacheZipArchive('abc123hash', '/tmp/archive.zip');
        $this->assertTrue($result);
    }

    /**
     * B3: the zip is stored under its fixed-length name, the one the original download looks for.
     */
    #[Test]
    public function test_cacheZipArchive_uploads_the_zip_under_the_hashed_name(): void
    {
        $calls = [];
        $zipName = 'Архив ' . str_repeat('z', 200) . '.ZIP';

        $fs = $this->createS3WithMocks($this->recordingClient($calls, true));

        $this->assertTrue($fs->cacheZipArchive(self::HASH, '/upload/session/' . $zipName));
        $this->assertSame([[
            'bucket' => S3FilesStorage::getFilesStorageBucket(),
            'key' => 'originalZip/cache/' . self::HASH . '__originalZip/' . sha1($zipName) . '.zip',
            'source' => '/upload/session/' . $zipName,
        ]], $this->argsOf($calls, 'uploadItem'));

        // the original download finds it from the zip name kept in the database
        $this->assertStringEndsWith('/' . sha1($zipName) . '.zip', $fs->getOriginalZipPath('2026-11-11', '9', $zipName));
    }

    #[Test]
    public function test_cacheZipArchive_failure_calls_deleteDir(): void
    {
        $client = $this->stubClient(function (string $name, array $args) {
            if ($name === 'uploadItem') {
                return false;
            }
            return null;
        });

        $fsMock = $this->createMock(FilesystemAdapter::class);
        $fsMock->expects($this->once())->method('deleteDir')->willReturn(true);

        $fs = $this->createS3WithMocks($client, $fsMock);
        $result = $fs->cacheZipArchive('abc123hash', '/tmp/archive.zip');
        $this->assertFalse($result);
    }

    // ========================================================================
    // linkZipToProject
    // ========================================================================

    #[Test]
    public function test_linkZipToProject_success(): void
    {
        $client = $this->stubClient(function (string $name, array $args) {
            if ($name === 'getItemsInABucket') {
                return ['originalZip/cache/hash__originalZip/archive.zip'];
            }
            if ($name === 'copyItem') {
                return true;
            }
            if ($name === 'deleteItem') {
                return true;
            }
            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $result = $fs->linkZipToProject('2023-01-15', 'originalZip/cache/hash__originalZip', '42');
        $this->assertTrue($result);
    }

    #[Test]
    public function test_linkZipToProject_copy_fails_returns_false(): void
    {
        $client = $this->stubClient(function (string $name, array $args) {
            if ($name === 'getItemsInABucket') {
                return ['originalZip/cache/hash__originalZip/archive.zip'];
            }
            if ($name === 'copyItem') {
                return false;
            }
            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $result = $fs->linkZipToProject('2023-01-15', 'originalZip/cache/hash__originalZip', '42');
        $this->assertFalse($result);
    }

    #[Test]
    public function test_linkZipToProject_delete_fails_returns_false(): void
    {
        $client = $this->stubClient(function (string $name, array $args) {
            if ($name === 'getItemsInABucket') {
                return ['originalZip/cache/hash__originalZip/archive.zip'];
            }
            if ($name === 'copyItem') {
                return true;
            }
            if ($name === 'deleteItem') {
                return false;
            }
            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $result = $fs->linkZipToProject('2023-01-15', 'originalZip/cache/hash__originalZip', '42');
        $this->assertFalse($result);
    }

    #[Test]
    public function test_linkZipToProject_empty_items_returns_true(): void
    {
        $client = $this->stubClient(function (string $name, array $args) {
            if ($name === 'getItemsInABucket') {
                return [];
            }
            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $result = $fs->linkZipToProject('2023-01-15', 'someprefix', '42');
        $this->assertTrue($result);
    }
}
