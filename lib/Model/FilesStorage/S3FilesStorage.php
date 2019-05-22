<?php

namespace FilesStorage;

use SimpleS3\Client;

/**
 * Class S3FilesStorage
 *
 * This class handles files to S3 buckets:
 * Upload, retrieve, delete
 *
 * -------------------------------------------------------------------------
 *
 * @package FilesStorage
 */
class S3FilesStorage extends AbstractFilesStorage {

    /**
     * @var Client
     */
    private $s3Client;

    /**
     * S3FilesStorage constructor.
     */
    public function __construct() {
        $this->s3Client = self::getStaticS3Client();
    }

    /**
     * This static method gives
     * an access to Client instance
     * to all static methods like moveFileFromUploadSessionToQueuePath()
     *
     * @return Client
     */
    public static function getStaticS3Client() {
        // init the S3Client
        $awsAccessKeyId = \INIT::$AWS_ACCESS_KEY_ID;
        $awsSecretKey   = \INIT::$AWS_SECRET_KEY;
        $awsVersion     = \INIT::$AWS_VERSION;
        $awsRegion      = \INIT::$AWS_REGION;

        return new Client(
                $awsAccessKeyId,
                $awsSecretKey,
                [
                        'version' => $awsVersion,
                        'region'  => $awsRegion,
                ]
        );
    }

    /**
     * Get a cache bucket name
     *
     * Example:
     * matecat-cache-d9-e7-590837d3861ad723879f2d63154e7eb690b1-it-it
     *
     * @param $hash
     * @param $lang
     *
     * @return string
     */
    public function getCachePackageBucketName( $hash, $lang ) {
        return 'matecat-cache-' . implode( '-', self::composeCachePath( $hash ) ) . '.' . strtolower( $lang );
    }

    /**
     * Get a project bucket name
     *
     * Example:
     * matecat-project-20191212.{id}
     *
     * @param $datestring
     * @param $id
     *
     * @return string
     */
    public function getProjectBucketName( $datestring, $id ) {
        return 'matecat-project-' . $datestring . '.' . $id;
    }

    /**
     * Create the cache bucket on S3 and store the files
     *
     * @param      $hash
     * @param      $lang
     * @param bool $originalPath
     * @param      $xliffPath
     *
     * @return bool|mixed
     * @throws \Exception
     */
    public function makeCachePackage( $hash, $lang, $originalPath = false, $xliffPath ) {
        $cachePackageBucketName = $this->getCachePackageBucketName( $hash, $lang );

        if ( \INIT::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION !== false && $this->s3Client->hasBucket( $cachePackageBucketName ) ) {
            return true;
        }

        $xliffDestination = $this->getXliffDestination( $xliffPath, $cachePackageBucketName, $originalPath );

        $this->tryToUploadAFile( $cachePackageBucketName, $xliffDestination, $xliffPath );

        unlink( $xliffPath );

        return true;
    }

    /**
     * @param      $xliffPath
     * @param      $bucketName
     * @param bool $originalPath
     *
     * @return string
     */
    private function getXliffDestination( $xliffPath, $bucketName, $originalPath = false ) {
        if ( !$originalPath ) {
            $fileType = \DetectProprietaryXliff::getInfo( $xliffPath );
            if ( !$fileType[ 'proprietary' ] && $fileType[ 'info' ][ 'extension' ] != 'sdlxliff' ) {
                $force_extension = '.sdlxliff';
            }

            return "work" . DIRECTORY_SEPARATOR . static::basename_fix( $xliffPath ) . @$force_extension;
        }

        $raw_file_path   = explode( DIRECTORY_SEPARATOR, $originalPath );
        $file_name       = array_pop( $raw_file_path );
        $origDestination = 'orig' . DIRECTORY_SEPARATOR . $file_name;

        $this->tryToUploadAFile( $bucketName, $origDestination, $originalPath );

        $file_extension = '.sdlxliff';

        return 'work' . DIRECTORY_SEPARATOR . $file_name . $file_extension;
    }

    /**
     * @param $bucketName
     * @param $destination
     * @param $origPath
     *
     * @return bool
     */
    private function tryToUploadAFile( $bucketName, $destination, $origPath ) {
        try {
            $this->s3Client->uploadItem( $bucketName, $destination, $origPath );
            \Log::doJsonLog( 'Successfully uploaded file ' . $destination . ' into ' . $bucketName . ' bucket.' );
        } catch ( \Exception $e ) {
            \Log::doJsonLog( 'Error in uploading a file ' . $destination . ' into ' . $bucketName . ' bucket. ERROR: ' . $e->getMessage() );

            return false;
        }
    }

    /**
     * @param $uploadSession
     *
     * @return mixed|void
     * @throws \Exception
     */
    public static function moveFileFromUploadSessionToQueuePath( $uploadSession ) {

        $s3Client = self::getStaticS3Client();

        // 1. get the queue bucket name
        $queueBucketName = self::getQueueBucketName($uploadSession);

        // 2. create queue bucket
        $s3Client->createBucketIfItDoesNotExist( $queueBucketName );

        foreach (
                $iterator = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator( \INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $uploadSession, \RecursiveDirectoryIterator::SKIP_DOTS ),
                        \RecursiveIteratorIterator::SELF_FIRST ) as $item
        ) {
            if ( $item->isDir() ) {
                // create folder
                $s3Client->createFolder($queueBucketName, $iterator->getSubPathName());
            } else {
                // upload file
                $s3Client->uploadItem($queueBucketName, $iterator->getSubPathName(), $item);
            }
        }

        \Utils::deleteDir( \INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $uploadSession );
    }

    /**
     * @param $uploadSession
     *
     * @return string
     */
    public static function getQueueBucketName($uploadSession){
        return 'matecat-queue-'.str_replace(['{','}'], '', strtolower(urldecode($uploadSession)));
    }

    /**
     * Copies the files from cache bucket package to project bucket identified by $idFile
     *
     * @param      $dateHashPath
     * @param      $lang
     * @param      $idFile
     * @param null $newFileName
     *
     * @return bool|mixed
     * @throws \Exception
     */
    public function moveFromCacheToFileDir( $dateHashPath, $lang, $idFile, $newFileName = null ) {

        $errors = [];

        // 1. get the cache package bucket  name
        $hashes                 = explode( DIRECTORY_SEPARATOR, $dateHashPath );
        $datePath               = $hashes[ 0 ];
        $hash                   = $hashes[ 1 ];
        $bucketCachePackageName = $this->getCachePackageBucketName( $hash, $lang );

        // 2. create project bucket
        $bucketProjectName = $this->getProjectBucketName( $datePath, $idFile );
        $this->s3Client->createBucketIfItDoesNotExist( $bucketProjectName );

        $bucketCachePackageFiles = $this->s3Client->getItemsInABucket( $bucketCachePackageName );
        foreach ( $bucketCachePackageFiles as $key => $file ) {

            // 3. create package/orig and package/work empty folders
            $folder1 = $this->s3Client->createFolder($bucketProjectName, 'package/orig');
            $folder2 = $this->s3Client->createFolder($bucketProjectName, 'package/work');

            if(false === $folder1){
                $errors[] = 'package/orig was not created';
            }

            if(false === $folder2){
                $errors[] = 'package/work was not created';
            }

            // 4. copy orig file from cache package to project bucket
            if ( false !== strpos( $key, 'orig/' ) ) {
                $copied = $this->s3Client->copyItem( $bucketCachePackageName, $key, $bucketProjectName, $key );

                if(false === $copied){
                    \Log::doJsonLog( 'project id ' . $idFile . ': ' . $key . ' was copied from ' . $bucketCachePackageName . ' to ' . $bucketProjectName );
                    $errors[] = $key . ' was not copied';
                }
            }

            // 5. copy work file from cache package to project bucket
            if ( false !== strpos( $key, 'work/' ) ) {
                $newKey = substr_replace( $key, "xliff/", 0, 5 );
                $copied = $this->s3Client->copyItem( $bucketCachePackageName, $key, $bucketProjectName, $newKey );

                if(false === $copied){
                    \Log::doJsonLog( 'project id ' . $idFile . ': ' . $key . ' was copied from ' . $bucketCachePackageName . ' to ' . $bucketProjectName );
                    $errors[] = $key . ' was not copied';
                }
            }
        }

        return (count($errors) === 0);
    }
}
