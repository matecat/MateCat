<?php

use FilesStorage\S3FilesStorage;

class S3FilesStorageTest extends PHPUnit_Framework_TestCase {

    /**
     * @var S3FilesStorage
     */
    private $fs;

    public function setUp() {
        parent::setUp();

        $this->fs = new S3FilesStorage();
    }

//    public function tearDown() {
//        parent::tearDown();
//
//        $this->fs->getS3Client()->deleteBucket();
//    }

    /**
     * @test
     */
    public function test_get_cache_package_bucket_name() {
        $filePath = __DIR__ . '/../../../support/files/docx/WhiteHouse.docx';
        $sha1     = sha1_file( $filePath );
        $lang     = 'it-IT';

        $cacheBucketName = $this->fs->getCachePackageBucketName( $sha1, $lang );

        $this->assertTrue( strlen( $cacheBucketName ) === 62 );
    }

    /**
     * @test
     */
    public function test_creation_of_cache_package_into_a_bucket() {
        $filePath        = __DIR__ . '/../../../support/files/txt/hello.txt';
        $xliffPath       = __DIR__ . '/../../../support/files/xliff/file-with-hello-world.xliff';
        $xliffPathTarget = __DIR__ . '/../../../support/files/xliff/file-with-hello-world(1).xliff';

        copy( $xliffPath, $xliffPathTarget ); // I copy the original xliff file because then

        $sha1 = sha1_file( $filePath );
        $lang = 'it-IT';

        $this->assertTrue( $this->fs->makeCachePackage( $sha1, $lang, $filePath, $xliffPathTarget ) );
    }

    /**
     * @test
     */
    public function test_get_project_bucket_name() {
        $projectBucketName = $this->fs->getProjectBucketName( '20191212', 13 );

        $this->assertEquals( 'matecat-project-20191212.13', $projectBucketName );
    }

    /**
     * @test
     */
    public function test_copying_a_file_from_cache_package_bucket_to_file_bucket() {
        $filePath     = __DIR__ . '/../../../support/files/txt/hello.txt';
        $sha1         = sha1_file( $filePath );
        $dateHashPath = '20191212' . DIRECTORY_SEPARATOR . $sha1;
        $lang         = 'it-IT';
        $idFile       = 13;

        $this->assertTrue( $this->fs->moveFromCacheToFileDir( $dateHashPath, $lang, $idFile, $filePath ) );
    }

    /**
     * @test
     */
    public function test_get_queue_bucket_name() {
        $uploadSession = '{CAD1B6E1-B312-8713-E8C3-97145410FD37}';

        $this->assertEquals( 'matecat-queue-cad1b6e1-b312-8713-e8c3-97145410fd37', S3FilesStorage::getQueueBucketName( $uploadSession ) );
    }

    /**
     * @test
     */
    public function test_uploading_files_from_queue_folder_to_queue_bucket() {

        // create a backup file from fixtures folder because the folder in upload folder is deleted every time
        $uploadSession     = '{CAD1B6E1-B312-8713-E8C3-97145410FD37}';
        $source            = __DIR__ . '/../../../support/files/queue/' . $uploadSession . '/test.txt';
        $destinationFolder = __DIR__ . '/../../../../local_storage/upload/' . $uploadSession;
        $destination       = $destinationFolder . '/test.txt';

        if ( !file_exists( $destinationFolder ) ) {
            mkdir( $destinationFolder, 0755 );
        }
        copy( $source, $destination );

        S3FilesStorage::moveFileFromUploadSessionToQueuePath( $uploadSession );
    }
}