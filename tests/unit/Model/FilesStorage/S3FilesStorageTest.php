<?php

use FilesStorage\S3FilesStorage;
use Matecat\SimpleS3\Client;
use Predis\Connection\ConnectionException;
use TestHelpers\AbstractTest;

class S3FilesStorageTest extends AbstractTest {

    /**
     * @var S3FilesStorage
     */
    private $fs;

    /**
     * @var Client
     */
    private $s3Client;

    /**
     * @throws ReflectionException
     * @throws ConnectionException
     */
    public function setUp(): void {
        parent::setUp();

        $this->fs       = new S3FilesStorage();
        $this->s3Client = S3FilesStorage::getStaticS3Client();
    }

    /**
     * @test
     * @throws Exception
     */
    public function test_creation_of_cache_package_into_a_bucket() {
        $filePath        = INIT::$ROOT . '/tests/resources/files/txt/hello.txt';
        $xliffPath       = INIT::$ROOT . '/tests/resources/files/xliff/file-with-hello-world.xliff';
        $xliffPathTarget = INIT::$STORAGE_DIR . '/file-with-hello-world(1).xliff';

        copy( $xliffPath, $xliffPathTarget ); // I copy the original xliff file because then

        $sha1 = sha1_file( $filePath );
        $lang = 'it-IT';

        $hashTree = S3FilesStorage::composeCachePath( $sha1 );
        $prefix   = S3FilesStorage::CACHE_PACKAGE_FOLDER . DIRECTORY_SEPARATOR;
        $prefix   .= $hashTree[ 'firstLevel' ] . DIRECTORY_SEPARATOR . $hashTree[ 'secondLevel' ] . DIRECTORY_SEPARATOR . $hashTree[ 'thirdLevel' ] . S3FilesStorage::OBJECTS_SAFE_DELIMITER . $lang;

        $this->assertTrue( $this->fs->makeCachePackage( $sha1, $lang, $filePath, $xliffPathTarget ) );
        $this->assertEquals( $prefix . '/orig/hello.txt', $this->fs->getOriginalFromCache( $sha1, $lang ) );
        $this->assertEquals( $prefix . '/work/hello.txt.sdlxliff', $this->fs->getXliffFromCache( $sha1, $lang ) );
    }

    /**
     * @test
     * @depends test_creation_of_cache_package_into_a_bucket
     * @throws Exception
     */
    public function test_copying_a_file_from_cache_package_bucket_to_file_bucket() {
        $filePath     = INIT::$ROOT . '/tests/resources/files/txt/hello.txt';
        $sha1         = sha1_file( $filePath );
        $dateHashPath = '20191212' . DIRECTORY_SEPARATOR . $sha1;
        $lang         = 'it-IT';
        $idFile       = 13;

        $this->assertTrue( $this->fs->moveFromCacheToFileDir( $dateHashPath, $lang, $idFile, $filePath ) );
        $this->assertEquals( S3FilesStorage::FILES_FOLDER . '/20191212/13/orig/hello.txt', $this->fs->getOriginalFromFileDir( $idFile, $dateHashPath ) );
        $this->assertEquals( S3FilesStorage::FILES_FOLDER . '/20191212/13/xliff/hello.txt.sdlxliff', $this->fs->getXliffFromFileDir( $idFile, $dateHashPath ) );
    }

    /**
     * @throws ReflectionException
     * @throws ConnectionException
     */
    public function testListItems() {
        $workItems = S3FilesStorage::getStaticS3Client()->getItemsInABucket( [
                'bucket' => S3FilesStorage::getFilesStorageBucket(),
                'prefix' => 'cache-package/c1/68/9bd71f45e76fd5e428f35c00d1f289a7e9e9__it-it/orig'
        ] );

        $this->assertNotEmpty( $workItems );
    }

    /**
     * @test
     * @throws Exception
     */
    public function test_uploading_files_from_queue_folder_to_queue_bucket() {

        // create a backup file from fixtures folder because the folder in upload folder is deleted every time
        $uploadSession     = '{CAD1B6E1-B312-8713-E8C3-97145410FD37}';
        $source            = INIT::$ROOT . '/tests/resources/files/queue/' . $uploadSession . '/test.txt';
        $source2           = INIT::$ROOT . '/tests/resources/files/queue/' . $uploadSession . '/aad03b600bc4792b3dc4bf3a2d7191327a482d4a|it-IT';
        $destinationFolder = INIT::$STORAGE_DIR . "/upload/" . $uploadSession;
        $destination       = $destinationFolder . '/test.txt';
        $destination2      = $destinationFolder . '/aad03b600bc4792b3dc4bf3a2d7191327a482d4a|it-IT';

        if ( !file_exists( $destinationFolder ) ) {
            mkdir( $destinationFolder, 0755 );
        }
        copy( $source, $destination );
        copy( $source2, $destination2 );

        // TEST
        S3FilesStorage::moveFileFromUploadSessionToQueuePath( $uploadSession );

        $items = $this->s3Client->getItemsInABucket( [
                'bucket' => S3FilesStorage::getFilesStorageBucket(),
                'prefix' => S3FilesStorage::QUEUE_FOLDER . DIRECTORY_SEPARATOR . S3FilesStorage::getUploadSessionSafeName( $uploadSession )
        ] );

        $this->assertGreaterThan( 0, $items );

        // TEST
        $this->fs->deleteQueue( S3FilesStorage::getUploadSessionSafeName( $uploadSession ) );

        $items = $this->s3Client->getItemsInABucket( [
                'bucket' => S3FilesStorage::getFilesStorageBucket(),
                'prefix' => S3FilesStorage::QUEUE_FOLDER . DIRECTORY_SEPARATOR . S3FilesStorage::getUploadSessionSafeName( $uploadSession )
        ] );

        $this->assertEmpty( $items );

    }

    /**
     * @depends test_uploading_files_from_queue_folder_to_queue_bucket
     * @test
     * @throws Exception
     */
    public function test_get_hashes_from_dir() {
        $uploadSession = '{CAD1B6E1-B312-8713-E8C3-97145410FD37}';
        $dirToScan     = INIT::$QUEUE_PROJECT_REPOSITORY . DIRECTORY_SEPARATOR . $uploadSession;

        $hashes = $this->fs->getHashesFromDir( $dirToScan );

        $this->assertArrayHasKey( 'conversionHashes', $hashes );
        $this->assertArrayHasKey( 'zipHashes', $hashes );

        $originalFileNames = $hashes[ 'conversionHashes' ][ 'fileName' ][ 'queue-projects/cad1b6e1-b312-8713-e8c3-97145410fd37/61d1a18ea9eb9e6c63de278388abd39410b5911c__it-IT' ];

        $this->assertEquals( 'test.txt', $originalFileNames[ 0 ] );
    }

    /**
     * @test
     * @throws Exception
     */
    public function test_handling_files_in_fast_analysys_bucket() {

        $id_project        = 13;
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

        S3FilesStorage::storeFastAnalysisFile( $id_project, $segments_metadata );

        $this->assertEquals( S3FilesStorage::getFastAnalysisData( $id_project ), $segments_metadata );

        S3FilesStorage::deleteFastAnalysisFile( $id_project );
    }

    /**
     * @test
     * @throws Exception
     */
    public function test_cache_zip_archive() {

        // create a backup file from fixtures folder because the folder in upload folder is deleted every time
        $source            = INIT::$ROOT . '/tests/resources/files/zip-with-model-json.zip';
        $destinationFolder = INIT::$STORAGE_DIR . "/files_storage/originalZip";
        $destination       = $destinationFolder . '/zip-with-model-json.zip';

        if ( !file_exists( $destinationFolder ) ) {
            mkdir( $destinationFolder, 0755 );
        }
        copy( $source, $destination );

        $archived = $this->fs->cacheZipArchive( sha1_file( $destination ), $destination );

        $this->assertTrue( $archived );
    }

    /**
     * @test
     * @throws Exception
     */
    public function test_link_zip_to_project() {
        $filePath  = INIT::$ROOT . '/tests/resources/files/zip-with-model-json.zip';
        $sha1      = sha1_file( $filePath );
        $date      = '2019-12-12 10:00:00';
        $idProject = 13;

        $copied = $this->fs->linkZipToProject( $date, $sha1, $idProject );

        $this->assertTrue( $copied );
    }
}