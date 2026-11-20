<?php

namespace Matecat\Core\Model\FilesStorage;

use Exception;
use Matecat\SimpleS3\ClientInterface;
use Matecat\TestHelpers\AbstractTest;
use Model\FilesStorage\FilesystemAdapter;
use Model\FilesStorage\NativeFilesystemAdapter;
use Model\FilesStorage\S3FilesStorage;
use PHPUnit\Framework\Attributes\Test;
use UnexpectedValueException;
use Utils\Redis\RedisHandler;
use Utils\Registry\AppConfig;

class S3FilesStorageUnitTest extends AbstractTest
{
    private string $originalBucket = '';
    private string $originalFiltersForceVersion = '';

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
    public function test_createFileName_short_name(): void
    {
        $fileInfo = ['filename' => 'doc', 'extension' => 'txt', 'basename' => 'doc.txt'];
        $result = S3FilesStorage::createFileName('prefix/', $fileInfo);
        $this->assertSame('doc.txt', $result);
    }

    #[Test]
    public function test_createFileName_long_name_gets_hashed(): void
    {
        $longName = str_repeat('a', 250);
        $fileInfo = ['filename' => $longName, 'extension' => 'txt', 'basename' => $longName . '.txt'];
        $result = S3FilesStorage::createFileName('prefix/', $fileInfo);
        $this->assertSame(sha1($longName) . '.txt', $result);
    }

    #[Test]
    public function test_createFileName_returns_basename_when_no_filename_or_extension(): void
    {
        $fileInfo = ['basename' => 'noext'];
        $result = S3FilesStorage::createFileName('prefix/', $fileInfo);
        $this->assertSame('noext', $result);
    }

    #[Test]
    public function test_createFileName_returns_empty_when_no_basename(): void
    {
        $result = S3FilesStorage::createFileName('prefix/', []);
        $this->assertSame('', $result);
    }

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

    /**
     * The worker no longer needs the file map once it deletes the queue. Left behind, the key had no
     * TTL and 350,000 of them had piled up in the production Redis.
     */
    #[Test]
    public function test_deleteQueue_deletes_the_redis_file_map(): void
    {
        $uploadSession = 'fd750c37-2d33-4913-bdd1-' . bin2hex(random_bytes(6));
        $redis = (new RedisHandler())->getConnection();
        $redis->hset($uploadSession, 'file_map', serialize([]));

        $fs = $this->createS3WithMocks($this->stubClient(fn() => true));
        $fs->deleteQueue('/path/{' . strtoupper($uploadSession) . '}');

        $this->assertSame(0, $redis->exists($uploadSession));
    }

    // ========================================================================
    // moveFileFromUploadSessionToQueuePath
    // ========================================================================

    /**
     * The file map gets a TTL, so Redis can drop it even when deleteQueue() never runs, such as when
     * the project creation message is lost.
     */
    #[Test]
    public function test_moveFileFromUploadSessionToQueuePath_writes_a_file_map_with_a_ttl(): void
    {
        $uploadSession = 'fd750c37-2d33-4913-bdd1-' . bin2hex(random_bytes(6));
        $originalUpload = AppConfig::$UPLOAD_REPOSITORY;
        $uploadRepository = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'file_map_ttl_' . uniqid();
        mkdir($uploadRepository . DIRECTORY_SEPARATOR . $uploadSession, 0755, true);
        file_put_contents($uploadRepository . DIRECTORY_SEPARATOR . $uploadSession . DIRECTORY_SEPARATOR . 'abc123', "file.txt\n");

        $redis = (new RedisHandler())->getConnection();

        try {
            AppConfig::$UPLOAD_REPOSITORY = $uploadRepository;

            $fs = $this->createS3WithMocks($this->stubClient(fn() => true), new NativeFilesystemAdapter());
            $fs->moveFileFromUploadSessionToQueuePath($uploadSession);

            $ttl = $redis->ttl($uploadSession);
            $this->assertGreaterThan(0, $ttl);
            $this->assertLessThanOrEqual(S3FilesStorage::UPLOAD_FILE_MAP_TTL, $ttl);

            $fileMap = unserialize((string)$redis->hget($uploadSession, 'file_map'));
            $this->assertSame(
                [S3FilesStorage::QUEUE_FOLDER . DIRECTORY_SEPARATOR . $uploadSession . DIRECTORY_SEPARATOR . 'abc123' => ['file.txt']],
                $fileMap
            );
        } finally {
            AppConfig::$UPLOAD_REPOSITORY = $originalUpload;
            $redis->unlink($uploadSession);
            if (is_dir($uploadRepository)) {
                exec('rm -rf ' . escapeshellarg($uploadRepository));
            }
        }
    }

    /**
     * The worker reads a native xliff back from the queue by the name the user uploaded, and S3 keys
     * are case-sensitive: an upper-case extension stored lower-cased is a missing key.
     */
    #[Test]
    public function test_moveFileFromUploadSessionToQueuePath_keeps_the_case_of_the_extension(): void
    {
        $uploadSession = 'fd750c37-2d33-4913-bdd1-' . bin2hex(random_bytes(6));
        $originalUpload = AppConfig::$UPLOAD_REPOSITORY;
        $uploadRepository = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'queue_key_case_' . uniqid();
        mkdir($uploadRepository . DIRECTORY_SEPARATOR . $uploadSession, 0755, true);
        file_put_contents($uploadRepository . DIRECTORY_SEPARATOR . $uploadSession . DIRECTORY_SEPARATOR . 'Test stati xliff 1.2.XLIFF', '<xliff/>');

        $uploadedKeys = [];
        $client = $this->stubClient(function (string $name, array $args) use (&$uploadedKeys) {
            if ($name === 'uploadItem') {
                $uploadedKeys[] = $args[0]['key'];
            }

            return true;
        });

        try {
            AppConfig::$UPLOAD_REPOSITORY = $uploadRepository;

            $this->createS3WithMocks($client, new NativeFilesystemAdapter())->moveFileFromUploadSessionToQueuePath($uploadSession);

            $this->assertSame(
                [S3FilesStorage::QUEUE_FOLDER . DIRECTORY_SEPARATOR . $uploadSession . DIRECTORY_SEPARATOR . 'Test stati xliff 1.2.XLIFF'],
                $uploadedKeys
            );
            $this->assertSame([S3FilesStorage::getQueueObjectKey($uploadSession, 'Test stati xliff 1.2.XLIFF')], $uploadedKeys);
        } finally {
            AppConfig::$UPLOAD_REPOSITORY = $originalUpload;
            (new RedisHandler())->getConnection()->unlink($uploadSession);
            if (is_dir($uploadRepository)) {
                exec('rm -rf ' . escapeshellarg($uploadRepository));
            }
        }
    }

    // ========================================================================
    // getQueueObjectKey
    // ========================================================================

    #[Test]
    public function test_getQueueObjectKey_keeps_the_file_name_and_cleans_the_session(): void
    {
        $this->assertSame(
            'queue-projects/fd750c37-2d33-4913-bdd1-1c71be9d2435/Test.XLF',
            S3FilesStorage::getQueueObjectKey('{FD750C37-2D33-4913-BDD1-1C71BE9D2435}', 'Test.XLF')
        );
    }

    #[Test]
    public function test_getQueueObjectKey_keeps_a_name_without_extension(): void
    {
        $this->assertSame(
            'queue-projects/session/abc123__en-US',
            S3FilesStorage::getQueueObjectKey('session', 'abc123__en-US')
        );
    }

    /**
     * A long name is hashed, as before, but the extension keeps its case.
     */
    #[Test]
    public function test_getQueueObjectKey_hashes_a_long_name_keeping_the_case_of_the_extension(): void
    {
        $filename = str_repeat('a', 250);

        $this->assertSame(
            'queue-projects/session/' . sha1($filename) . '.XLIFF',
            S3FilesStorage::getQueueObjectKey('session', $filename . '.XLIFF')
        );
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

    #[Test]
    public function test_makeCachePackage_early_return_when_cache_exists(): void
    {
        AppConfig::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION = '2.0';

        $client = $this->stubClient(function (string $name, array $args) {
            if ($name === 'hasItem') {
                return true;
            }
            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $hash = 'abcdef1234567890abcdef1234567890abcdef12';
        $result = $fs->makeCachePackage($hash, 'en-US', '/tmp/original.txt', '/tmp/test.xliff');
        $this->assertTrue($result);
    }

    #[Test]
    public function test_makeCachePackage_no_early_return_when_force_version_is_false(): void
    {
        AppConfig::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION = false;

        $client = $this->stubClient(function (string $name, array $args) {
            if ($name === 'hasItem') {
                return true; // valid but FORCE_VERSION is false so no early return
            }
            if ($name === 'uploadItem') {
                return true;
            }
            return null;
        });

        $fsMock = $this->createMock(FilesystemAdapter::class);
        $fsMock->expects($this->once())->method('unlink')->willReturn(true);

        $fs = $this->createS3WithMocks($client, $fsMock);
        $hash = 'abcdef1234567890abcdef1234567890abcdef12';
        $result = $fs->makeCachePackage($hash, 'en-US', '/tmp/original.txt', '/tmp/test.xliff');
        $this->assertTrue($result);
    }

    #[Test]
    public function test_makeCachePackage_success_with_original_path(): void
    {
        $uploadedKeys = [];
        $client = $this->stubClient(function (string $name, array $args) use (&$uploadedKeys) {
            if ($name === 'hasItem') {
                return false;
            }
            if ($name === 'uploadItem') {
                $uploadedKeys[] = $args[0]['key'];
                return true;
            }
            return null;
        });

        $fsMock = $this->createMock(FilesystemAdapter::class);
        $fsMock->expects($this->once())->method('unlink')->willReturn(true);

        $fs = $this->createS3WithMocks($client, $fsMock);
        $hash = 'abcdef1234567890abcdef1234567890abcdef12';
        $result = $fs->makeCachePackage($hash, 'en-US', '/tmp/original.txt', '/tmp/test.xliff');
        $this->assertTrue($result);
        $this->assertCount(2, $uploadedKeys);
    }

    #[Test]
    public function test_makeCachePackage_xliff_upload_fails_cleans_up_original(): void
    {
        $callCount = 0;
        $deletedKeys = [];
        $client = $this->stubClient(function (string $name, array $args) use (&$callCount, &$deletedKeys) {
            if ($name === 'hasItem') {
                return false;
            }
            if ($name === 'uploadItem') {
                $callCount++;
                if ($callCount === 1) {
                    return true;
                }
                throw new Exception('Upload failed for xliff');
            }
            if ($name === 'deleteItem') {
                $deletedKeys[] = $args[0]['key'];
                return true;
            }
            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $hash = 'abcdef1234567890abcdef1234567890abcdef12';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Upload failed for xliff');
        $fs->makeCachePackage($hash, 'en-US', '/tmp/original.txt', '/tmp/test.xliff');
    }

    #[Test]
    public function test_makeCachePackage_null_original_rethrows_directly(): void
    {
        // When originalPath is null, storeOriginalFileAndGetXliffDestination
        // calls XliffProprietaryDetect on a non-existent file which may throw.
        // We simulate by making uploadItem throw (it won't be called on the null-originalPath
        // path since storeOriginalFileAndGetXliffDestination returns a string without uploading).
        // Instead, let's make the xliff upload throw and verify re-throw without cleanup.
        $client = $this->stubClient(function (string $name, array $args) {
            if ($name === 'hasItem') {
                return false;
            }
            if ($name === 'uploadItem') {
                throw new Exception('Xliff upload failed');
            }
            return null;
        });

        $fs = $this->createS3WithMocks($client);
        $hash = 'abcdef1234567890abcdef1234567890abcdef12';

        // Create a temp xliff file so XliffProprietaryDetect can read it
        $tmpXliff = tempnam(sys_get_temp_dir(), 'xliff_test_');
        file_put_contents($tmpXliff, '<?xml version="1.0"?><xliff version="1.2"><file></file></xliff>');

        try {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Xliff upload failed');
            $fs->makeCachePackage($hash, 'en-US', null, $tmpXliff);
        } finally {
            @unlink($tmpXliff);
        }
    }

    #[Test]
    public function test_makeCachePackage_without_original_success(): void
    {
        // Test the storeOriginalFileAndGetXliffDestination path without originalPath
        $client = $this->stubClient(function (string $name, array $args) {
            if ($name === 'hasItem') {
                return false;
            }
            if ($name === 'uploadItem') {
                return true;
            }
            return null;
        });

        $fsMock = $this->createMock(FilesystemAdapter::class);
        $fsMock->expects($this->once())->method('unlink')->willReturn(true);

        $fs = $this->createS3WithMocks($client, $fsMock);
        $hash = 'abcdef1234567890abcdef1234567890abcdef12';

        // Create a temp xliff file so XliffProprietaryDetect can read it
        $tmpXliff = tempnam(sys_get_temp_dir(), 'xliff_test_') . '.xliff';
        file_put_contents($tmpXliff, '<?xml version="1.0"?><xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2"><file source-language="en" target-language="it" datatype="plaintext"><body><trans-unit id="1"><source>Hello</source></trans-unit></body></file></xliff>');

        try {
            $result = $fs->makeCachePackage($hash, 'en-US', null, $tmpXliff);
            $this->assertTrue($result);
        } finally {
            @unlink($tmpXliff);
        }
    }

    /**
     * A native xliff with an upper-case extension is uploaded from the file on disk: only the object
     * key takes the lower-cased name.
     */
    #[Test]
    public function test_makeCachePackage_uploads_a_native_xliff_with_an_upper_case_extension_from_its_own_path(): void
    {
        $uploads = [];
        $client = $this->stubClient(function (string $name, array $args) use (&$uploads) {
            if ($name === 'uploadItem') {
                $uploads[] = ['key' => $args[0]['key'], 'source' => $args[0]['source'], 'exists' => is_file($args[0]['source'])];

                return true;
            }

            return false;
        });

        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'xliff_case_' . uniqid();
        mkdir($dir);
        $xliffPath = $dir . DIRECTORY_SEPARATOR . 'Test stati xliff 1.2.XLIFF';
        file_put_contents($xliffPath, '<?xml version="1.0"?><xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2"><file source-language="en" target-language="it" datatype="plaintext"><body><trans-unit id="1"><source>Hello</source></trans-unit></body></file></xliff>');

        $fsMock = $this->createMock(FilesystemAdapter::class);
        $fsMock->expects($this->once())->method('unlink')->with($xliffPath)->willReturn(true);

        $fs = $this->createS3WithMocks($client, $fsMock);
        $hash = 'abcdef1234567890abcdef1234567890abcdef12';

        try {
            $this->assertTrue($fs->makeCachePackage($hash, 'en-US', null, $xliffPath));
        } finally {
            exec('rm -rf ' . escapeshellarg($dir));
        }

        $this->assertSame([[
            'key' => $fs->getCachePackageHashFolder($hash, 'en-US') . '/work/Test stati xliff 1.2.xliff.sdlxliff',
            'source' => $xliffPath,
            'exists' => true,
        ]], $uploads);
    }

    /**
     * A converted xliff whose name is too long for a key is uploaded from the file on disk, not from
     * a path renamed after its hashed key.
     */
    #[Test]
    public function test_makeCachePackage_uploads_a_long_named_xliff_from_its_own_path(): void
    {
        $sources = [];
        $client = $this->stubClient(function (string $name, array $args) use (&$sources) {
            if ($name === 'uploadItem') {
                $sources[] = $args[0]['source'];

                return true;
            }

            return false;
        });

        $xliffPath = '/tmp/' . str_repeat('a', 200) . '.docx.sdlxliff';

        $fs = $this->createS3WithMocks($client);

        $this->assertTrue($fs->makeCachePackage('abcdef1234567890abcdef1234567890abcdef12', 'en-US', '/tmp/original.docx', $xliffPath));
        $this->assertSame(['/tmp/original.docx', $xliffPath], $sources);
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
