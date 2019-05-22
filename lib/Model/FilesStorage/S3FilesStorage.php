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
        // init the S3Client
        $awsAccessKeyId = \INIT::$AWS_ACCESS_KEY_ID;
        $awsSecretKey   = \INIT::$AWS_SECRET_KEY;
        $awsVersion     = \INIT::$AWS_VERSION;
        $awsRegion      = \INIT::$AWS_REGION;

        $this->s3Client = new Client(
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
        $bucketName = $this->getCachePackageBucketName( $hash, $lang );

        if ( \INIT::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION !== false && $this->s3Client->hasBucket( $bucketName ) ) {
            return true;
        }

        $xliffDestination = $this->getXliffDestination( $xliffPath, $bucketName, $originalPath );

        $this->tryToUploadAFile( $bucketName, $xliffDestination, $xliffPath );

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
            $this->s3Client->uploadFile( $bucketName, $destination, $origPath );
            \Log::doJsonLog( 'Successfully uploaded file ' . $destination . ' into ' . $bucketName . ' bucket.' );
        } catch ( \Exception $e ) {
            \Log::doJsonLog( 'Error in uploading a file ' . $destination . ' into ' . $bucketName . ' bucket. ERROR: ' . $e->getMessage() );

            return false;
        }
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
     * @param      $dateHashPath
     * @param      $lang
     * @param      $idFile
     * @param null $newFileName
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function moveFromCacheToFileDir( $dateHashPath, $lang, $idFile, $newFileName = null ) {

        // 1. get the bucket cache package
        $hashes                 = explode( DIRECTORY_SEPARATOR, $dateHashPath );
        $datePath               = $hashes[ 0 ];
        $hash                   = $hashes[ 1 ];
        $bucketCachePackageName = $this->getCachePackageBucketName( $hash, $lang );

        // 2. create project bucket
        $bucketProjectName = $this->getProjectBucketName( $datePath, $idFile );
        $this->s3Client->createBucketIfItDoesNotExist( $bucketProjectName );

        $bucketCachePackageFiles = $this->s3Client->getFilesInABucket( $bucketCachePackageName );
        foreach ( $bucketCachePackageFiles as $key => $file ) {

            // 3. create package/orig and package/work blank folders
            $this->s3Client->copyFile($bucketCachePackageName, $key, $bucketProjectName, 'package/orig');
            $this->s3Client->copyFile($bucketCachePackageName, $key, $bucketProjectName, 'package/work');

            // 4. copy orig file from cache package to project
            if(false !== strpos($key, 'orig/') ){
                $this->s3Client->copyFile($bucketCachePackageName, $key, $bucketProjectName, $key);
            }

            // 5. copy work file from cache package to project
            if(false !== strpos($key, 'work/')){
                $newKey = substr_replace($key,"xliff/",0, 5);
                $this->s3Client->copyFile($bucketCachePackageName, $key, $bucketProjectName, $newKey);
            }
        }
    }
}
