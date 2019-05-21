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

    /*
     * Cache Handling Methods --- START
     */

    /**
     * Get a cache bucket name
     *
     * Example:
     * matecat-cache-d9-e7-590837d3861ad723879f2d63154e7eb690b1|it-IT
     *
     * @param $hash
     * @param $lang
     *
     * @return string
     */
    public function getCachePackageBucketName( $hash, $lang ) {
        return 'matecat-cache-' . implode( '-', self::composeCachePath( $hash ) ) . '-' . strtolower($lang);
    }

    /**
     * Get all files from a cache bucket
     *
     * @param $hash
     */
    public function getCachePackage( $hash ) {

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

        $xliffDestination = $this->getXliffDestination( $xliffPath, $bucketName, $hash, $lang, $originalPath );

        $this->tryToUploadAFile( $bucketName, $xliffDestination, $xliffPath, $hash, $lang );

        unlink( $xliffPath );

        return true;
    }

    /**
     * @param      $xliffPath
     * @param      $bucketName
     * @param      $hash
     * @param      $lang
     * @param bool $originalPath
     *
     * @return string
     */
    private function getXliffDestination( $xliffPath, $bucketName, $hash, $lang, $originalPath = false ) {
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

        $this->tryToUploadAFile( $bucketName, $origDestination, $originalPath, $hash, $lang );

        $file_extension = '.sdlxliff';

        return 'work' . DIRECTORY_SEPARATOR . $file_name . $file_extension;
    }

    /**
     * @param $bucketName
     * @param $destination
     * @param $origPath
     * @param $hash
     * @param $lang
     *
     * @return bool
     */
    private function tryToUploadAFile( $bucketName, $destination, $origPath, $hash, $lang ) {
        try {
            $this->s3Client->uploadFile( $bucketName, $destination, $origPath );
        } catch ( \Exception $e ) {

            var_dump($e->getMessage());

            return false;
        }
    }

    /*
     * Cache Handling Methods --- END
     */


    /*
     * Cache Handling Methods --- START
     */

    public function moveFromCacheToFileDir( $dateHashPath, $lang, $idFile, $newFileName = null ) {
        // TODO: Implement moveFromCacheToFileDir() method.
    }

    /*
     * Cache Handling Methods --- START
     */

}
