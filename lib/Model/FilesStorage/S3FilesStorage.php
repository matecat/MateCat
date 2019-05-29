<?php

namespace FilesStorage;

use SimpleS3\Client;

/**
 * Class S3FilesStorage
 *
 * INDEX
 * -------------------------------------------------------------------------
 * 1. CACHE PACKAGE
 * 2. PROJECT
 * 3. QUEUE
 * 4. FAST ANALYSIS
 * 5. ZIP ARCHIVES HANDLING
 * 6. GENERAL METHODS
 *
 * @package FilesStorage
 */
class S3FilesStorage extends AbstractFilesStorage {

    const CACHE_PACKAGE_BUCKET = 'matecat-cache-bucket';
    const PROJECT_BUCKET       = 'matecat-project-bucket';
    const QUEUE_BUCKET         = 'matecat-queue-bucket';
    const ZIP_BUCKET           = 'matecat-zip-archive-bucket';
    const FAST_ANALYSIS_BUCKET = 'matecat-fast-analysis-bucket';

    const OBJECTS_SAFE_SEPARATOR = '!!';

    /**
     * @var Client
     */
    private $s3Client;

    /**
     * @var Client
     */
    private static $CLIENT;

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

        if ( empty( self::$CLIENT ) ) {
            // init the S3Client
            $awsAccessKeyId = \INIT::$AWS_ACCESS_KEY_ID;
            $awsSecretKey   = \INIT::$AWS_SECRET_KEY;
            $awsVersion     = \INIT::$AWS_VERSION;
            $awsRegion      = \INIT::$AWS_REGION;

            self::$CLIENT = new Client(
                    $awsAccessKeyId,
                    $awsSecretKey,
                    [
                            'version' => $awsVersion,
                            'region'  => $awsRegion,
                    ]
            );
        }

        return self::$CLIENT;

    }

    /**
     **********************************************************************************************
     * 1. CACHE PACKAGE
     **********************************************************************************************
     */

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

        $prefix = $this->getCachePackageHashFolder( $hash, $lang );

        if ( \INIT::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION !== false && $this->s3Client->hasItem( self::CACHE_PACKAGE_BUCKET, $prefix ) ) {
            return true;
        }

        $xliffDestination = $this->getXliffDestination( $prefix, $xliffPath, self::CACHE_PACKAGE_BUCKET, $originalPath );

        $this->tryToUploadAFile( self::CACHE_PACKAGE_BUCKET, $xliffDestination, $xliffPath );

        unlink( $xliffPath );

        return true;
    }

    /**
     * @param $hash
     * @param $lang
     *
     * @return string
     */
    private function getCachePackageHashFolder( $hash, $lang ) {
        $hashTree = self::composeCachePath( $hash );

        return $hashTree[ 'firstLevel' ] . DIRECTORY_SEPARATOR . $hashTree[ 'secondLevel' ] . DIRECTORY_SEPARATOR . $hashTree[ 'thirdLevel' ] . self::OBJECTS_SAFE_SEPARATOR . $lang;
    }

    /**
     * @param      $prefix
     * @param      $xliffPath
     * @param      $bucketName
     * @param bool $originalPath
     *
     * @return string
     */
    private function getXliffDestination( $prefix, $xliffPath, $bucketName, $originalPath = false ) {
        if ( !$originalPath ) {
            $fileType = \DetectProprietaryXliff::getInfo( $xliffPath );
            if ( !$fileType[ 'proprietary' ] && $fileType[ 'info' ][ 'extension' ] != 'sdlxliff' ) {
                $force_extension = '.sdlxliff';
            }

            return $prefix . DIRECTORY_SEPARATOR . 'work' . DIRECTORY_SEPARATOR . static::basename_fix( $xliffPath ) . @$force_extension;
        }

        $raw_file_path   = explode( DIRECTORY_SEPARATOR, $originalPath );
        $file_name       = array_pop( $raw_file_path );
        $origDestination = $prefix . DIRECTORY_SEPARATOR . 'orig' . DIRECTORY_SEPARATOR . $file_name;

        $this->tryToUploadAFile( $bucketName, $origDestination, $originalPath );

        $file_extension = '.sdlxliff';

        return $prefix . DIRECTORY_SEPARATOR . 'work' . DIRECTORY_SEPARATOR . $file_name . $file_extension;
    }

    /**
     * @param $hash
     * @param $lang
     *
     * @return mixed
     * @throws \Exception
     */
    public function getOriginalFromCache( $hash, $lang ) {
        return $this->findAKeyInCachePackageBucket( $hash, $lang, 'orig' );
    }

    /**
     * @param $hash
     * @param $lang
     *
     * @return mixed
     * @throws \Exception
     */
    public function getXliffFromCache( $hash, $lang ) {
        return $this->findAKeyInCachePackageBucket( $hash, $lang, 'work' );
    }

    /**
     * @param $hash
     * @param $lang
     * @param $keyToSearch
     *
     * @return mixed
     * @throws \Exception
     */
    private function findAKeyInCachePackageBucket( $hash, $lang, $keyToSearch ) {
        $prefix = $this->getCachePackageHashFolder( $hash, $lang ) . '/' . $keyToSearch . '/'; // example: c1/68/9bd71f45e76fd5e428f35c00d1f289a7e9e9!!it-IT/work/
        $items  = $this->s3Client->getItemsInABucket( self::CACHE_PACKAGE_BUCKET, $prefix );

        return ( isset( array_keys( $items )[ 0 ] ) ) ? array_keys( $items )[ 0 ] : null;
    }

    /**
     **********************************************************************************************
     * 2. PROJECT
     **********************************************************************************************
     */

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

        // 1. get the cache package item in bucket
        $hashes   = explode( DIRECTORY_SEPARATOR, $dateHashPath );
        $datePath = $hashes[ 0 ];
        $hash     = $hashes[ 1 ];

        // 2. create project bucket
        $this->s3Client->createBucketIfItDoesNotExist( self::PROJECT_BUCKET );

        // 3. copy orig file from cache package to project bucket
        $origPrefix = $this->getCachePackageHashFolder( $hash, $lang ) . '/orig/';
        $origItems  = $this->s3Client->getItemsInABucket( self::CACHE_PACKAGE_BUCKET, $origPrefix );

        foreach ( array_keys( $origItems ) as $key ) {

            $newKey = $datePath . DIRECTORY_SEPARATOR . $idFile . '/orig/' . $this->getTheLastPartOfKey( $key );
            $copied = $this->s3Client->copyItem( self::CACHE_PACKAGE_BUCKET, $key, self::PROJECT_BUCKET, $newKey );

            if ( false === $copied ) {
                \Log::doJsonLog( 'project id ' . $idFile . ': ' . $key . ' was copied from ' . self::CACHE_PACKAGE_BUCKET . ' to ' . self::CACHE_PACKAGE_BUCKET );
                $errors[] = $key . ' was not copied';
            }
        }

        // 4. copy work file from cache package to project bucket
        $workPrefix = $this->getCachePackageHashFolder( $hash, $lang ) . '/work/';
        $workItems  = $this->s3Client->getItemsInABucket( self::CACHE_PACKAGE_BUCKET, $workPrefix );

        foreach ( array_keys( $workItems ) as $key ) {

            $newKey = $datePath . DIRECTORY_SEPARATOR . $idFile . '/xliff/' . $this->getTheLastPartOfKey( $key );
            $copied = $this->s3Client->copyItem( self::CACHE_PACKAGE_BUCKET, $key, self::PROJECT_BUCKET, $newKey );

            if ( false === $copied ) {
                \Log::doJsonLog( 'project id ' . $idFile . ': ' . $key . ' was copied from ' . self::CACHE_PACKAGE_BUCKET . ' to ' . self::CACHE_PACKAGE_BUCKET );
                $errors[] = $key . ' was not copied';
            }
        }

        return ( count( $errors ) === 0 );
    }

    /**
     * @param $id
     * @param $dateHashPath
     *
     * @return bool|mixed|string
     * @throws \Exception
     */
    public function getOriginalFromFileDir( $id, $dateHashPath ) {
        return $this->findAKeyInProjectBucket( $id, $dateHashPath, 'orig' );
    }

    /**
     * @param $id
     * @param $dateHashPath
     *
     * @return mixed
     * @throws \Exception
     */
    public function getXliffFromFileDir( $id, $dateHashPath ) {
        return $this->findAKeyInProjectBucket( $id, $dateHashPath, 'xliff' );
    }

    /**
     * @param $hash
     * @param $lang
     * @param $keyToSearch
     *
     * @return mixed
     * @throws \Exception
     */
    private function findAKeyInProjectBucket( $id, $dateHashPath, $keyToSearch ) {
        $hashes   = explode( DIRECTORY_SEPARATOR, $dateHashPath );
        $datePath = $hashes[ 0 ];

        $prefix = $datePath . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . $keyToSearch . DIRECTORY_SEPARATOR; // example: 20181212/13/work/
        $items  = $this->s3Client->getItemsInABucket( self::PROJECT_BUCKET, $prefix );

        return ( isset( array_keys( $items )[ 0 ] ) ) ? array_keys( $items )[ 0 ] : null;
    }

    /**
     **********************************************************************************************
     * 3. QUEUE
     **********************************************************************************************
     */

    /**
     * @param $uploadSession
     *
     * @return mixed|void
     * @throws \Exception
     */
    public static function moveFileFromUploadSessionToQueuePath( $uploadSession ) {

        $s3Client = self::getStaticS3Client();
        $s3Client->createBucketIfItDoesNotExist( self::QUEUE_BUCKET );

        foreach (
                $iterator = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator( \INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $uploadSession, \RecursiveDirectoryIterator::SKIP_DOTS ),
                        \RecursiveIteratorIterator::SELF_FIRST ) as $item
        ) {
            $prefix      = self::getUploadSessionSafeName( $uploadSession ); // Example: {CAD1B6E1-B312-8713-E8C3-97145410FD37}} --> cad1b6e1-b312-8713-e8c3-97145410fd37}
            $subPathName = str_replace( '|', self::OBJECTS_SAFE_SEPARATOR, strtolower( $iterator->getSubPathName() ) );
            // Example: aad03b600bc4792b3dc4bf3a2d7191327a482d4a|it-IT --> aad03b600bc4792b3dc4bf3a2d7191327a482d4a!!it-it

            if ( $item->isDir() ) {
                // create folder
                $s3Client->createFolder( self::QUEUE_BUCKET, $prefix . DIRECTORY_SEPARATOR . $subPathName );
            } else {
                // upload file
                $s3Client->uploadItem( self::QUEUE_BUCKET, $prefix . DIRECTORY_SEPARATOR . $subPathName, $item->getPathName() );
            }
        }

        \Utils::deleteDir( \INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $uploadSession );
    }

    /**
     * @param $dirToScan
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function getHashesFromDir( $dirToScan ) {
        $folder        = self::getUploadSessionSafeName( $this->getTheLastPartOfKey( $dirToScan ) ) . DIRECTORY_SEPARATOR;
        $zipFilesHash  = [];
        $filesHashInfo = [];

        $linkFiles = $this->s3Client->getItemsInABucket( self::QUEUE_BUCKET, $folder );

        foreach ( $linkFiles as $k => $linkFile ) {
            if ( strpos( $k, self::ORIGINAL_ZIP_PLACEHOLDER ) !== false ) {
                $zipFilesHash[] = $k;
            } elseif ( strpos( $k, '.' ) !== false or strpos( $k, self::OBJECTS_SAFE_SEPARATOR ) === false ) {
                unset( $linkFiles[ $k ] );
            } else {
                $filesHashInfo[ 'sha' ][]          = $k;
                $filesHashInfo[ 'fileName' ][ $k ] = file_get_contents($this->s3Client->getPublicItemLink(self::QUEUE_BUCKET, $k));
            }
        }

        return [
                'conversionHashes' => $filesHashInfo,
                'zipHashes'        => $zipFilesHash
        ];
    }

    /**
     * @param $uploadSession
     *
     * @return mixed
     */
    private static function getUploadSessionSafeName( $uploadSession ) {
        return str_replace( [ '{', '}' ], '', strtolower( $uploadSession ) );
    }

    /**
     **********************************************************************************************
     * 4. FAST ANALYSIS
     **********************************************************************************************
     */

    /**
     * @param       $id_project
     * @param array $segments_metadata
     *
     * @throws \Exception
     */
    public static function storeFastAnalysisFile( $id_project, Array $segments_metadata = [] ) {

        $upload = self::getStaticS3Client()->uploadItemFromBody( self::FAST_ANALYSIS_BUCKET, 'waiting_analysis_' . $id_project . '.ser', serialize( $segments_metadata ) );

        if ( false === $upload ) {
            throw new \UnexpectedValueException( 'Internal Error: Failed to store segments for fast analysis on Amazon S3 bucket.', -14 );
        }
    }

    /**
     * @param $id_project
     *
     * @return mixed
     * @throws \Exception
     */
    public static function getFastAnalysisData( $id_project ) {

        $analysisData = unserialize( file_get_contents( self::getStaticS3Client()->getPublicItemLink( self::FAST_ANALYSIS_BUCKET, 'waiting_analysis_' . $id_project . '.ser' ) ) );

        if ( false === $analysisData ) {
            throw new \UnexpectedValueException( 'Internal Error: Failed to retrieve analysis information from Amazon S3 bucket.', -15 );
        }

        return $analysisData;
    }

    /**
     * @param $id_project
     *
     * @return bool
     * @throws \Exception
     */
    public static function deleteFastAnalysisFile( $id_project ) {
        self::getStaticS3Client()->deleteFile( self::FAST_ANALYSIS_BUCKET, 'waiting_analysis_' . $id_project . '.ser' );
    }

    /**
     **********************************************************************************************
     * 5. ZIP ARCHIVES HANDLING
     **********************************************************************************************
     */

    /**
     * Make a temporary cache copy for the original zip file
     *
     * @param $hash
     * @param $zipPath
     *
     * @return bool
     * @throws \Exception
     */
    public function cacheZipArchive( $hash, $zipPath ) {
        // create bucket if does not exist
        $this->s3Client->createBucketIfItDoesNotExist( self::ZIP_BUCKET );

        // upload item
        $prefix  = 'cache' . DIRECTORY_SEPARATOR . $hash . $this->getOriginalZipPlaceholder();
        $outcome = $this->s3Client->uploadItem( self::ZIP_BUCKET, $prefix . DIRECTORY_SEPARATOR . static::basename_fix( $zipPath ), $zipPath );

        if ( !$outcome ) {
            //Original directory deleted!!!
            //CLEAR ALL CACHE
            \Utils::deleteDir( $this->zipDir . DIRECTORY_SEPARATOR . $hash . $this->getOriginalZipPlaceholder() );

            return $outcome;
        }

        unlink( $zipPath );

        return true;
    }

    /**
     * @param $create_date
     * @param $zipHash
     * @param $projectID
     *
     * @return bool
     * @throws \Exception
     */
    public function linkZipToProject( $create_date, $zipHash, $projectID ) {
        $cacheZipPackage = 'cache' . DIRECTORY_SEPARATOR . $zipHash . $this->getOriginalZipPlaceholder() . DIRECTORY_SEPARATOR;

        foreach ( array_keys( $this->s3Client->getItemsInABucket( self::ZIP_BUCKET, $cacheZipPackage ) ) as $key ) {
            $destination = $this->getOriginalZipPath( $create_date, $projectID, $this->getTheLastPartOfKey( $key ) );

            $copied = $this->s3Client->copyItem( self::ZIP_BUCKET, $key, self::ZIP_BUCKET, $destination );

            if ( !$copied ) {
                return $copied;
            }

            $delete = $this->s3Client->deleteFile( self::ZIP_BUCKET, $key );

            if ( !$delete ) {
                return $delete;
            }
        }

        return true;
    }

    /**
     * @param $projectDate
     * @param $projectID
     * @param $zipName
     *
     * @return string
     */
    public function getOriginalZipPath( $projectDate, $projectID, $zipName ) {
        return $this->getOriginalZipDir( $projectDate, $projectID ) . DIRECTORY_SEPARATOR . $zipName;
    }

    /**
     * @param $projectDate
     * @param $projectID
     *
     * @return string
     */
    public function getOriginalZipDir( $projectDate, $projectID ) {
        return 'work/' . $this->getDatePath( $projectDate ) . DIRECTORY_SEPARATOR . $projectID;
    }

    /**
     **********************************************************************************************
     * 6. GENERAL METHODS
     **********************************************************************************************
     */

    /**
     * Get the last part of key (exploded by /) from an S3 complete key.
     *
     * Example:
     * c1/68/9bd71f45e76fd5e428f35c00d1f289a7e9e9.it-IT/orig/hello.txt --> hello.txt
     *
     * @param $key
     *
     * @return mixed
     */
    private function getTheLastPartOfKey( $key ) {
        $explode = explode( DIRECTORY_SEPARATOR, $key );

        return end( $explode );
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
     * Return safe S3 object safe name
     *
     * @return mixed
     */
    private function getOriginalZipPlaceholder() {
        return str_replace( '#', '', self::ORIGINAL_ZIP_PLACEHOLDER );
    }
}
