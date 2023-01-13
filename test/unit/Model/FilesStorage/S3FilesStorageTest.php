<?php

use FilesStorage\S3FilesStorage;
use Matecat\SimpleS3\Client;

class S3FilesStorageTest extends PHPUnit_Framework_TestCase {

    /**
     * @var S3FilesStorage
     */
    private $fs;

    /**
     * @var Client
     */
    private $s3Client;

    public function setUp() {
        parent::setUp();

        $this->fs       = new S3FilesStorage();
        $this->s3Client = S3FilesStorage::getStaticS3Client();
    }

    /**
     * @test
     * @throws Exception
     */
    public function test_creation_of_cache_package_into_a_bucket() {
        $filePath        = __DIR__ . '/../../../support/files/txt/hello.txt';
        $xliffPath       = __DIR__ . '/../../../support/files/xliff/file-with-hello-world.xliff';
        $xliffPathTarget = __DIR__ . '/../../../support/files/xliff/file-with-hello-world(1).xliff';

        copy( $xliffPath, $xliffPathTarget ); // I copy the original xliff file because then

        $sha1 = sha1_file( $filePath );
        $lang = 'it-it';

        $hashTree = S3FilesStorage::composeCachePath( $sha1 );
        $prefix   = S3FilesStorage::CACHE_PACKAGE_FOLDER . DIRECTORY_SEPARATOR;
        $prefix   .= $hashTree[ 'firstLevel' ] . DIRECTORY_SEPARATOR . $hashTree[ 'secondLevel' ] . DIRECTORY_SEPARATOR . $hashTree[ 'thirdLevel' ] . S3FilesStorage::OBJECTS_SAFE_DELIMITER . $lang;

        $this->assertTrue( $this->fs->makeCachePackage( $sha1, $lang, $filePath, $xliffPathTarget ) );
        $this->assertEquals( $prefix . '/orig/hello.txt', $this->fs->getOriginalFromCache( $sha1, $lang ) );
        $this->assertEquals( $prefix . '/work/hello.txt.sdlxliff', $this->fs->getXliffFromCache( $sha1, $lang ) );
    }

    /**
     * @test
     * @throws Exception
     */
    public function test_copying_a_file_from_cache_package_bucket_to_file_bucket() {
        $filePath     = __DIR__ . '/../../../support/files/txt/hello.txt';
        $sha1         = sha1_file( $filePath );
        $dateHashPath = '20191212' . DIRECTORY_SEPARATOR . $sha1;
        $lang         = 'it-IT';
        $idFile       = 13;

        $this->assertTrue( $this->fs->moveFromCacheToFileDir( $dateHashPath, $lang, $idFile, $filePath ) );
        $this->assertEquals( S3FilesStorage::FILES_FOLDER . '/20191212/13/orig/hello.txt', $this->fs->getOriginalFromFileDir( $idFile, $dateHashPath ) );
        $this->assertEquals( S3FilesStorage::FILES_FOLDER . '/20191212/13/xliff/hello.txt.sdlxliff', $this->fs->getXliffFromFileDir( $idFile, $dateHashPath ) );
    }

    /**
     * @test
     * @throws Exception
     */
    public function test_uploading_files_from_queue_folder_to_queue_bucket() {

        // create a backup file from fixtures folder because the folder in upload folder is deleted every time
        $uploadSession     = '{CAD1B6E1-B312-8713-E8C3-97145410FD37}';
        $source            = __DIR__ . '/../../../support/files/queue/' . $uploadSession . '/test.txt';
        $source2           = __DIR__ . '/../../../support/files/queue/' . $uploadSession . '/aad03b600bc4792b3dc4bf3a2d7191327a482d4a|it-IT';
        $destinationFolder = __DIR__ . '/../../../../local_storage/upload/' . $uploadSession;
        $destination       = $destinationFolder . '/test.txt';
        $destination2      = $destinationFolder . '/aad03b600bc4792b3dc4bf3a2d7191327a482d4a|it-IT';

        if ( !file_exists( $destinationFolder ) ) {
            mkdir( $destinationFolder, 0755 );
        }
        copy( $source, $destination );
        copy( $source2, $destination2 );

        S3FilesStorage::moveFileFromUploadSessionToQueuePath( $uploadSession );

        $items = $this->s3Client->getItemsInABucket( [ 'bucket' => S3FilesStorage::getFilesStorageBucket() ] );

        $this->assertGreaterThanOrEqual( 0, $items );
    }

    /**
     * @test
     * @throws Exception
     */
    public function test_get_hashes_from_dir() {
        $uploadSession = '{CAD1B6E1-B312-8713-E8C3-97145410FD37}';
        $dirToScan     = \INIT::$QUEUE_PROJECT_REPOSITORY . DIRECTORY_SEPARATOR . $uploadSession;

        $hashes = $this->fs->getHashesFromDir( $dirToScan );

        $this->assertArrayHasKey( 'conversionHashes', $hashes );
        $this->assertArrayHasKey( 'zipHashes', $hashes );

        $originalFileNames = $hashes[ 'conversionHashes' ][ 'fileName' ][ 'queue-projects/cad1b6e1-b312-8713-e8c3-97145410fd37/aad03b600bc4792b3dc4bf3a2d7191327a482d4a!!it-it' ];

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
        $source            = __DIR__ . '/../../../support/files/zip-with-model-json.zip';
        $destinationFolder = __DIR__ . '/../../../../local_storage/files_storage/originalZip';
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
        $filePath  = __DIR__ . '/../../../support/files/zip-with-model-json.zip';
        $sha1      = sha1_file( $filePath );
        $date      = '2019-12-12 10:00:00';
        $idProject = 13;

        $copied = $this->fs->linkZipToProject( $date, $sha1, $idProject );

        $this->assertTrue( $copied );
    }
}