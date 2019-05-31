<?php

namespace FilesStorage;

use INIT;
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

    // S3 BUCKET AND FOLDERS NAMES
    const FILES_STORAGE_BUCKET   = 'matecat-files-storage';
    const CACHE_PACKAGE_FOLDER   = 'cache-package';
    const FILES_FOLDER           = 'files';
    const QUEUE_FOLDER           = 'queue-projects';
    const ZIP_FOLDER             = 'original-zip';
    const FAST_ANALYSIS_FOLDER   = 'fast-analysis';
    const OBJECTS_SAFE_DELIMITER = '!!';

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
     *
     * Create the bucket if not exists
     *
     * @throws \Exception
     */
    public function __construct() {
        $this->s3Client = self::getStaticS3Client();

        // disable SSL verify from configuration
        if ( false === INIT::$AWS_SSL_VERIFY ) {
            $this->s3Client->disableSslVerify();
        }

        $this->createAndSetupBucket();
    }

    /**
     * Create the bucket storage if does not exists and setup the folders
     *
     * Cache-package items > 2 days of lifecycle
     */
    private function createAndSetupBucket() {
        $this->s3Client->createBucketIfItDoesNotExist( [ 'bucket' => self::FILES_STORAGE_BUCKET, ] );

        $rules = [
                [
                        "ID"          => "Move cache package to Glacier",
                        "Prefix"      => self::CACHE_PACKAGE_FOLDER . DIRECTORY_SEPARATOR,
                        "Status"      => "Enabled",
                        "Transitions" => [
                                [
                                        "Days"         => 2,
                                        "StorageClass" => "GLACIER"
                                ]
                        ]
                ],
                [
                        "ID"                           => "Move old versions to Glacier",
                        "Status"                       => "Enabled",
                        "Prefix"                       => "",
                        "NoncurrentVersionTransitions" => [
                                [
                                        "NoncurrentDays" => 2,
                                        "StorageClass"   => "GLACIER"
                                ]
                        ],
                ]
        ];

        $this->s3Client->setBucketLifecycleConfiguration( [ 'bucket' => self::FILES_STORAGE_BUCKET, 'rules' => $rules ] );
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

        // get the prefix
        $prefix = $this->getCachePackageHashFolder( $hash, $lang );

        if ( \INIT::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION !== false && $this->s3Client->hasItem( [ 'bucket' => self::CACHE_PACKAGE_FOLDER, 'prefix' => $prefix ] ) ) {
            return true;
        }

        $xliffDestination = $this->getXliffDestination( $prefix, $xliffPath, self::FILES_STORAGE_BUCKET, $originalPath );

        $this->tryToUploadAFile( self::FILES_STORAGE_BUCKET, $xliffDestination, $xliffPath );

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

        return self::CACHE_PACKAGE_FOLDER . DIRECTORY_SEPARATOR . $hashTree[ 'firstLevel' ] . DIRECTORY_SEPARATOR . $hashTree[ 'secondLevel' ] . DIRECTORY_SEPARATOR . $hashTree[ 'thirdLevel' ] .
                self::OBJECTS_SAFE_DELIMITER . strtolower( $lang );
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
        $items  = $this->s3Client->getItemsInABucket( [ 'bucket' => self::FILES_STORAGE_BUCKET, 'prefix' => $prefix ] );

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

        // 2. copy orig file from cache package to project bucket
        $origPrefix = $this->getCachePackageHashFolder( $hash, $lang ) . '/orig/';
        $origItems  = $this->s3Client->getItemsInABucket( [ 'bucket' => self::FILES_STORAGE_BUCKET, 'prefix' => $origPrefix ] );

        foreach ( array_keys( $origItems ) as $key ) {

            $newKey = self::FILES_FOLDER . DIRECTORY_SEPARATOR . $datePath . DIRECTORY_SEPARATOR . $idFile . '/orig/' . $this->getTheLastPartOfKey( $key );
            $copied = $this->s3Client->copyItem( [
                    'source_bucket' => self::FILES_STORAGE_BUCKET,
                    'source'        => $key,
                    'target_bucket' => self::FILES_STORAGE_BUCKET,
                    'target'        => $newKey
            ] );

            if ( false === $copied ) {
                \Log::doJsonLog( 'project id ' . $idFile . ': ' . $key . ' was copied from ' . self::FILES_STORAGE_BUCKET . ' to ' . self::FILES_STORAGE_BUCKET );
                $errors[] = $key . ' was not copied';
            }
        }

        // 3. copy work file from cache package to project bucket
        $workPrefix = $this->getCachePackageHashFolder( $hash, $lang ) . '/work/';
        $workItems  = $this->s3Client->getItemsInABucket( [ 'bucket' => self::FILES_STORAGE_BUCKET, 'prefix' => $workPrefix ] );

        foreach ( array_keys( $workItems ) as $key ) {

            $newKey = self::FILES_FOLDER . DIRECTORY_SEPARATOR . $datePath . DIRECTORY_SEPARATOR . $idFile . '/xliff/' . $this->getTheLastPartOfKey( $key );
            $copied = $this->s3Client->copyItem( [
                    'source_bucket' => self::FILES_STORAGE_BUCKET,
                    'source'        => $key,
                    'target_bucket' => self::FILES_STORAGE_BUCKET,
                    'target'        => $newKey
            ] );

            if ( false === $copied ) {
                \Log::doJsonLog( 'project id ' . $idFile . ': ' . $key . ' was copied from ' . self::FILES_STORAGE_BUCKET . ' to ' . self::FILES_STORAGE_BUCKET );
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

        $prefix = self::FILES_FOLDER . DIRECTORY_SEPARATOR . $datePath . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . $keyToSearch . DIRECTORY_SEPARATOR; // example: 20181212/13/work/
        $items  = $this->s3Client->getItemsInABucket( [ 'bucket' => self::FILES_STORAGE_BUCKET, 'prefix' => $prefix ] );

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

        foreach (
                $iterator = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator( \INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $uploadSession, \RecursiveDirectoryIterator::SKIP_DOTS ),
                        \RecursiveIteratorIterator::SELF_FIRST ) as $item
        ) {
            // Example: {CAD1B6E1-B312-8713-E8C3-97145410FD37}} --> cad1b6e1-b312-8713-e8c3-97145410fd37}
            $prefix = self::QUEUE_FOLDER . DIRECTORY_SEPARATOR . self::getUploadSessionSafeName( $uploadSession );

            // Example: aad03b600bc4792b3dc4bf3a2d7191327a482d4a|it-IT --> aad03b600bc4792b3dc4bf3a2d7191327a482d4a!!it-it
            $subPathName = str_replace( '|', self::OBJECTS_SAFE_DELIMITER, strtolower( $iterator->getSubPathName() ) );

            if ( $item->isDir() ) {
                // create folder
                $s3Client->createFolder( [ 'bucket' => self::FILES_STORAGE_BUCKET, 'key' => $prefix . DIRECTORY_SEPARATOR . $subPathName ] );
            } else {
                // upload file
                $s3Client->uploadItem( [
                        'bucket' => self::FILES_STORAGE_BUCKET,
                        'key'    => $prefix . DIRECTORY_SEPARATOR . $subPathName,
                        'source' => $item->getPathName()
                ] );
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
        $folder        = self::QUEUE_FOLDER . DIRECTORY_SEPARATOR . self::getUploadSessionSafeName( $this->getTheLastPartOfKey( $dirToScan ) ) . DIRECTORY_SEPARATOR;
        $zipFilesHash  = [];
        $filesHashInfo = [];

        $linkFiles = $this->s3Client->getItemsInABucket( [ 'bucket' => self::FILES_STORAGE_BUCKET, 'key' => $folder ] );

        foreach ( $linkFiles as $k => $linkFile ) {
            if ( strpos( $k, self::ORIGINAL_ZIP_PLACEHOLDER ) !== false ) {
                $zipFilesHash[] = $k;
            } elseif ( strpos( $k, '.' ) !== false or strpos( $k, self::OBJECTS_SAFE_DELIMITER ) === false ) {
                unset( $linkFiles[ $k ] );
            } else {
                $filesHashInfo[ 'sha' ][] = $k;

                // this method get the content from the hashes map file and convert it into an array of original file names
                // Example:
                //
                // 'file.txt'
                // 'file2.txt'
                // ==>
                // [
                //     0 => 'file.txt',
                //     1 => 'file2.txt'
                // ]
                $filesHashInfo[ 'fileName' ][ $k ] =
                $result = array_filter( array_map( 'trim', explode( "\n", $this->s3Client->openItem( [ 'bucket' => self::FILES_STORAGE_BUCKET, 'key' => $k ] ) ) ) );
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

        $upload = self::getStaticS3Client()->uploadItemFromBody( [
                'bucket' => self::FILES_STORAGE_BUCKET,
                'key'    => self::getFastAnalysisFileName( $id_project ),
                'body'   => serialize( $segments_metadata )
        ] );

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

        $analysisData = unserialize( self::getStaticS3Client()->openItem( [ 'bucket' => self::FILES_STORAGE_BUCKET, 'key' => self::getFastAnalysisFileName( $id_project ) ] ) );

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
        self::getStaticS3Client()->deleteItem( [ 'bucket' => self::FILES_STORAGE_BUCKET, 'key' => self::getFastAnalysisFileName( $id_project ) ] );
    }

    /**
     * @param $id_project
     *
     * @return string
     */
    private static function getFastAnalysisFileName( $id_project ) {
        return self::FAST_ANALYSIS_FOLDER . DIRECTORY_SEPARATOR . 'waiting_analysis_' . $id_project . '.ser';
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

        $prefix  = self::ZIP_FOLDER . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . $hash . $this->getOriginalZipPlaceholder();
        $outcome = $this->s3Client->uploadItem( [
                'bucket' => self::FILES_STORAGE_BUCKET,
                'key'    => $prefix . DIRECTORY_SEPARATOR . static::basename_fix( $zipPath ),
                'source' => $zipPath
        ] );

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
        $cacheZipPackage = self::ZIP_FOLDER . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . $zipHash . $this->getOriginalZipPlaceholder() . DIRECTORY_SEPARATOR;

        foreach ( array_keys( $this->s3Client->getItemsInABucket( [ 'bucket' => self::FILES_STORAGE_BUCKET, 'key' => $cacheZipPackage ] ) ) as $key ) {
            $destination = self::ZIP_FOLDER . DIRECTORY_SEPARATOR . $this->getOriginalZipPath( $create_date, $projectID, $this->getTheLastPartOfKey( $key ) );

            $copied = $this->s3Client->copyItem( [
                    'source_bucket' => self::FILES_STORAGE_BUCKET,
                    'source'        => $key,
                    'target_bucket' => self::FILES_STORAGE_BUCKET,
                    'target'        => $destination
            ] );

            if ( !$copied ) {
                return $copied;
            }

            $delete = $this->s3Client->deleteItem( [ 'bucket' => self::FILES_STORAGE_BUCKET, 'key' => $key ] );

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
            $this->s3Client->uploadItem( [
                    'bucket' => $bucketName,
                    'key'    => $destination,
                    'source' => $origPath
            ] );
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
