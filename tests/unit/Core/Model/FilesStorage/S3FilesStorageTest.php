<?php


namespace Matecat\Core\Model\FilesStorage;
use Exception;
use Matecat\SimpleS3\Client;
use Matecat\TestHelpers\AbstractTest;
use Model\FilesStorage\S3FilesStorage;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionException;
use Utils\Registry\AppConfig;

/**
 * @group ExternalServices
 * @group S3
 */
#[Group('ExternalServices')]
#[Group('S3')]
class S3FilesStorageTest extends AbstractTest
{

    /**
     * @var S3FilesStorage
     */
    private $fs;

    /**
     * @var Client
     */
    private $s3Client;

    /**
     * The bucket is shared by every CI run, and the lookups return the first key in alphabetical
     * order. Example: `c1689bd71f45e76fd5e428f35c00d1f289a7e9e9.txt` left next to `hello.txt` came
     * back in its place. These tests write to the same keys on every run, so each run first deletes
     * whatever is under them; a run that crashes leaves nothing the next run can trip on.
     */
    private const int ID_FILE = 13;

    private const string UPLOAD_SESSION = '{CAD1B6E1-B312-8713-E8C3-97145410FD37}';

    private static string $helloPath;

    private static string $helloSha1;

    /**
     * @throws Exception
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$helloPath = AppConfig::$ROOT . '/tests/resources/files/txt/hello.txt';
        self::$helloSha1 = (string)sha1_file(self::$helloPath);

        $client = S3FilesStorage::getStaticS3Client();
        $bucket = S3FilesStorage::getFilesStorageBucket();
        $client->deleteFolder(['bucket' => $bucket, 'prefix' => (new S3FilesStorage())->getCachePackageHashFolder(self::$helloSha1, 'it-IT')]);
        $client->deleteFolder(['bucket' => $bucket, 'prefix' => S3FilesStorage::FILES_FOLDER . '/20191212/' . self::ID_FILE]);
    }

    /**
     * @throws ReflectionException
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->fs = new S3FilesStorage();
        $this->s3Client = S3FilesStorage::getStaticS3Client();
    }

    /**
     * @test
     * @throws Exception
     */
    #[Test]
    public function test_creation_of_cache_package_into_a_bucket()
    {
        $filePath = self::$helloPath;
        $xliffPath = AppConfig::$ROOT . '/tests/resources/files/xliff/file-with-hello-world.xliff';
        $xliffPathTarget = AppConfig::$STORAGE_DIR . '/file-with-hello-world(1).xliff';

        copy($xliffPath, $xliffPathTarget); // I copy the original xliff file because then

        $sha1 = self::$helloSha1;
        $lang = 'it-IT';

        $hashTree = S3FilesStorage::composeCachePath($sha1);
        $prefix = S3FilesStorage::CACHE_PACKAGE_FOLDER . DIRECTORY_SEPARATOR;
        $prefix .= $hashTree['firstLevel'] . DIRECTORY_SEPARATOR . $hashTree['secondLevel'] . DIRECTORY_SEPARATOR . $hashTree['thirdLevel'] . S3FilesStorage::OBJECTS_SAFE_DELIMITER . $lang;

        $this->assertTrue($this->fs->makeCachePackage($sha1, $lang, $filePath, $xliffPathTarget));
        $this->assertEquals($prefix . '/orig/hello.txt', $this->fs->getOriginalFromCache($sha1, $lang));
        $this->assertEquals($prefix . '/work/hello.txt.sdlxliff', $this->fs->getXliffFromCache($sha1, $lang));
    }

    /**
     * @test
     * @depends test_creation_of_cache_package_into_a_bucket
     * @throws Exception
     */
    #[Test]
    #[Depends('test_creation_of_cache_package_into_a_bucket')]
    public function test_copying_a_file_from_cache_package_bucket_to_file_bucket()
    {
        $filePath = self::$helloPath;
        $dateHashPath = '20191212' . DIRECTORY_SEPARATOR . self::$helloSha1;
        $lang = 'it-IT';
        $idFile = self::ID_FILE;
        $fileDir = S3FilesStorage::FILES_FOLDER . '/20191212/' . $idFile;

        $this->assertTrue($this->fs->moveFromCacheToFileDir($dateHashPath, $lang, $idFile, $filePath));
        $this->assertEquals($fileDir . '/orig/hello.txt', $this->fs->getOriginalFromFileDir($idFile, $dateHashPath));
        $this->assertEquals($fileDir . '/xliff/hello.txt.sdlxliff', $this->fs->getXliffFromFileDir($idFile, $dateHashPath));
    }

    /**
     * Lists the cache package that test_creation_of_cache_package_into_a_bucket uploads. The prefix
     * comes from getCachePackageHashFolder(), so it keeps the language as it is written ("it-IT"):
     * a hardcoded "__it-it" only matched an object left in the bucket by the code before 2019,
     * which lowercased the language.
     *
     * @throws ReflectionException
     */
    #[Test]
    #[Depends('test_creation_of_cache_package_into_a_bucket')]
    public function testListItems()
    {
        $origPrefix = $this->fs->getCachePackageHashFolder(self::$helloSha1, 'it-IT') . '/orig';

        $origItems = S3FilesStorage::getStaticS3Client()->getItemsInABucket([
            'bucket' => S3FilesStorage::getFilesStorageBucket(),
            'prefix' => $origPrefix
        ]);

        $this->assertContains($origPrefix . '/hello.txt', $origItems);
    }

    /**
     * @test
     * @throws Exception
     */
    #[Test]
    public function test_uploading_files_from_queue_folder_to_queue_bucket()
    {
        $uploadSession = self::UPLOAD_SESSION;
        $this->stageUploadSession();

        // TEST
        $this->fs->moveFileFromUploadSessionToQueuePath($uploadSession);

        $items = $this->s3Client->getItemsInABucket([
            'bucket' => S3FilesStorage::getFilesStorageBucket(),
            'prefix' => S3FilesStorage::QUEUE_FOLDER . DIRECTORY_SEPARATOR . S3FilesStorage::getUploadSessionSafeName($uploadSession)
        ]);

        $this->assertGreaterThan(0, $items);

        // TEST
        $this->fs->deleteQueue(S3FilesStorage::getUploadSessionSafeName($uploadSession));

        $items = $this->s3Client->getItemsInABucket([
            'bucket' => S3FilesStorage::getFilesStorageBucket(),
            'prefix' => S3FilesStorage::QUEUE_FOLDER . DIRECTORY_SEPARATOR . S3FilesStorage::getUploadSessionSafeName($uploadSession)
        ]);

        $this->assertEmpty($items);
    }

    /**
     * Uploads its own session: the file map it reads lives only until deleteQueue(), so it cannot
     * read the one test_uploading_files_from_queue_folder_to_queue_bucket leaves behind.
     *
     * @throws Exception
     */
    #[Test]
    public function test_get_hashes_from_dir()
    {
        $uploadSession = self::UPLOAD_SESSION;
        $dirToScan = AppConfig::$QUEUE_PROJECT_REPOSITORY . DIRECTORY_SEPARATOR . $uploadSession;

        $this->stageUploadSession();
        $this->fs->moveFileFromUploadSessionToQueuePath($uploadSession);

        try {
            $hashes = $this->fs->getHashesFromDir($dirToScan);
        } finally {
            $this->fs->deleteQueue($dirToScan);
        }

        $this->assertArrayHasKey('conversionHashes', $hashes);
        $this->assertArrayHasKey('zipHashes', $hashes);

        $originalFileNames = $hashes['conversionHashes']['fileName']['queue-projects/cad1b6e1-b312-8713-e8c3-97145410fd37/61d1a18ea9eb9e6c63de278388abd39410b5911c__it-IT'];

        $this->assertEquals('test.txt', $originalFileNames[0]);
        $this->assertSame([], $this->fs->getHashesFromDir($dirToScan)['conversionHashes']);
    }

    /**
     * Copy the fixture files of the upload session into the upload folder, as the upload controller
     * does: moveFileFromUploadSessionToQueuePath() deletes that folder every time.
     */
    private function stageUploadSession(): void
    {
        $source = AppConfig::$ROOT . '/tests/resources/files/queue/' . self::UPLOAD_SESSION;
        $destinationFolder = AppConfig::$STORAGE_DIR . '/upload/' . self::UPLOAD_SESSION;

        if (!file_exists($destinationFolder)) {
            mkdir($destinationFolder, 0755, true);
        }

        foreach (['test.txt', 'aad03b600bc4792b3dc4bf3a2d7191327a482d4a|it-IT'] as $file) {
            copy($source . '/' . $file, $destinationFolder . '/' . $file);
        }
    }

    /**
     * @test
     * @throws Exception
     */
    #[Test]
    public function test_handling_files_in_fast_analysys_bucket()
    {
        $id_project = 13;
        $segments_metadata = [
            'meta' => [
                'node-1' => [
                    'value-1,',
                    'value-2,',
                    'value-3,',
                    'value-4,',
                ],
                'node-2' => [
                    'value-1,',
                    'value-2,',
                    'value-3,',
                    'value-4,',
                ],
                'node-3' => [
                    'value-1,',
                    'value-2,',
                    'value-3,',
                    'value-4,',
                ],
            ]
        ];

        $this->fs->storeFastAnalysisFile($id_project, $segments_metadata);

        $this->assertEquals($this->fs->getFastAnalysisData($id_project), $segments_metadata);

        $this->fs->deleteFastAnalysisFile($id_project);
    }

    /**
     * @test
     * @throws Exception
     */
    #[Test]
    public function test_cache_zip_archive()
    {
        // create a backup file from fixtures folder because the folder in upload folder is deleted every time
        $source = AppConfig::$ROOT . '/tests/resources/files/zip-with-model-json.zip';
        $destinationFolder = AppConfig::$STORAGE_DIR . "/files_storage/originalZip";
        $destination = $destinationFolder . '/zip-with-model-json.zip';

        if (!file_exists($destinationFolder)) {
            mkdir($destinationFolder, 0755);
        }
        copy($source, $destination);

        $archived = $this->fs->cacheZipArchive(sha1_file($destination), $destination);

        $this->assertTrue($archived);
    }

    /**
     * @test
     * @throws Exception
     */
    #[Test]
    public function test_link_zip_to_project()
    {
        $filePath = AppConfig::$ROOT . '/tests/resources/files/zip-with-model-json.zip';
        $sha1 = sha1_file($filePath);
        $date = '2019-12-12 10:00:00';
        $idProject = 13;

        $copied = $this->fs->linkZipToProject($date, $sha1, $idProject);

        $this->assertTrue($copied);
    }
}