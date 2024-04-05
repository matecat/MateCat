<?php

namespace FilesStorage;

use DirectoryIterator;
use DomainException;
use Exception;
use FilesystemIterator;
use INIT;
use Log;
use Matecat\SimpleS3\Client;
use Matecat\SimpleS3\Components\Cache\RedisCache;
use Matecat\XliffParser\XliffUtils\XliffProprietaryDetect;
use Predis\Connection\ConnectionException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RedisHandler;
use ReflectionException;
use UnexpectedValueException;
use Utils;

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

    const ORIGINAL_ZIP_PLACEHOLDER = "__originalZip";

    const CACHE_PACKAGE_FOLDER   = 'cache-package';
    const FILES_FOLDER           = 'files';
    const QUEUE_FOLDER           = 'queue-projects';
    const ZIP_FOLDER             = 'originalZip';
    const FAST_ANALYSIS_FOLDER   = 'fast-analysis';
    const OBJECTS_SAFE_DELIMITER = '__';

    /**
     * @var Client
     */
    protected $s3Client;

    /**
     * @var Client
     */
    protected static $CLIENT;

    /**
     * @var string
     */
    protected static $FILES_STORAGE_BUCKET;


    /**
     * S3FilesStorage constructor.
     *
     * Create the bucket if not exists
     *
     * @throws Exception
     */
    public function __construct() {
        $this->s3Client = self::getStaticS3Client();
        self::setFilesStorageBucket();
    }

    /**
     * This static method gives
     * access to Client instance
     * to all static methods like moveFileFromUploadSessionToQueuePath()
     *
     * @return Client
     * @throws ConnectionException
     * @throws ReflectionException
     */
    public static function getStaticS3Client() {

        if ( empty( self::$CLIENT ) ) {
            // init the S3Client
            $awsVersion = INIT::$AWS_VERSION;
            $awsRegion  = INIT::$AWS_REGION;

            $config = [
                    'version' => $awsVersion,
                    'region'  => $awsRegion,
            ];

            if ( null !== INIT::$AWS_ACCESS_KEY_ID and null !== INIT::$AWS_SECRET_KEY ) {
                $config[ 'credentials' ] = [
                        'key'    => INIT::$AWS_ACCESS_KEY_ID,
                        'secret' => INIT::$AWS_SECRET_KEY,
                ];
            }

            self::$CLIENT = new Client( $config );

            // add caching
            if ( INIT::$AWS_CACHING ) {
                $redis = new RedisHandler();
                self::$CLIENT->addCache( new RedisCache( $redis->getConnection() ) );
            }

            // disable SSL verify from configuration
            if ( false === INIT::$AWS_SSL_VERIFY ) {
                self::$CLIENT->disableSslVerify();
            }
        }

        self::setFilesStorageBucket();

        return self::$CLIENT;
    }

    /**
     * set $FILES_STORAGE_BUCKET
     */
    protected static function setFilesStorageBucket() {
        if ( null === INIT::$AWS_STORAGE_BASE_BUCKET ) {
            throw new DomainException( '$AWS_STORAGE_BASE_BUCKET param is missing in INIT.php.' );
        }

        static::$FILES_STORAGE_BUCKET = INIT::$AWS_STORAGE_BASE_BUCKET;
    }

    /**
     * get $FILES_STORAGE_BUCKET
     *
     * @return string
     */
    public static function getFilesStorageBucket() {
        return static::$FILES_STORAGE_BUCKET;
    }

    /**
     **********************************************************************************************
     * 1. CACHE PACKAGE
     **********************************************************************************************
     */

    /**
     * Create the cache folder on S3 and store the files
     *
     * @param      $hash
     * @param      $lang
     * @param bool $originalPath
     * @param      $xliffPath
     *
     * @return bool
     * @throws Exception
     */
    public function makeCachePackage( $hash, $lang, $originalPath, $xliffPath ) {

        // get the prefix
        $prefix = $this->getCachePackageHashFolder( $hash, $lang );
        $file   = $prefix . '/work/' . $this->getTheLastPartOfKey( $xliffPath );
        $valid  = $this->s3Client->hasItem( [ 'bucket' => static::$FILES_STORAGE_BUCKET, 'key' => $file ] );

        if ( INIT::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION !== false && $valid ) {
            return true;
        }

        // We need to execute uploadItem in a try/catch block because $origDestination string can be safe but $xliffDestination can be not
        //
        // Example: حديث_أمني_ريف_حلب_الغربي.docx (OK) -----> حديث_أمني_ريف_حلب_الغربي.dox.xliff (TOO LONG)
        //
        try {
            $xliffDestination = $this->storeOriginalFileAndGetXliffDestination( $prefix, $xliffPath, static::$FILES_STORAGE_BUCKET, $originalPath );

            $this->s3Client->uploadItem( [
                    'bucket' => static::$FILES_STORAGE_BUCKET,
                    'key'    => $xliffDestination,
                    'source' => $xliffPath
            ] );

            Log::doJsonLog( 'Successfully uploaded file ' . $xliffDestination . ' into ' . static::$FILES_STORAGE_BUCKET . ' bucket.' );

            unlink( $xliffPath );

            return true;

            // If $xliffDestination is too long, delete $origDestination item
        } catch ( Exception $e ) {

            $raw_file_path   = explode( DIRECTORY_SEPARATOR, $originalPath );
            $file_name       = array_pop( $raw_file_path );
            $origDestination = $prefix . DIRECTORY_SEPARATOR . 'orig' . DIRECTORY_SEPARATOR . $file_name;

            $this->s3Client->deleteItem( [
                    'bucket' => static::$FILES_STORAGE_BUCKET,
                    'key'    => $origDestination,
            ] );

            Log::doJsonLog( 'Deleting original cache file ' . $origDestination . ' from ' . static::$FILES_STORAGE_BUCKET . ' bucket.' );

            throw $e;
        }
    }

    /**
     * @param $hash
     * @param $lang
     *
     * @return string
     */
    public function getCachePackageHashFolder( $hash, $lang ) {
        $hashTree = self::composeCachePath( $hash );

        return self::CACHE_PACKAGE_FOLDER . DIRECTORY_SEPARATOR . $hashTree[ 'firstLevel' ] . DIRECTORY_SEPARATOR . $hashTree[ 'secondLevel' ] . DIRECTORY_SEPARATOR . $hashTree[ 'thirdLevel' ] .
                self::OBJECTS_SAFE_DELIMITER . $lang;
    }

    /**
     * @param      $prefix
     * @param      $xliffPath
     * @param      $bucketName
     * @param bool $originalPath
     *
     * @return string
     */
    private function storeOriginalFileAndGetXliffDestination( $prefix, $xliffPath, $bucketName, $originalPath = false ) {
        if ( !$originalPath ) {
            $force_extension = "";
            $fileType        = XliffProprietaryDetect::getInfo( $xliffPath );
            if ( !$fileType[ 'proprietary' ] && $fileType[ 'info' ][ 'extension' ] != 'sdlxliff' ) {
                $force_extension = '.sdlxliff';
            }

            return $prefix . DIRECTORY_SEPARATOR . 'work' . DIRECTORY_SEPARATOR . static::basename_fix( $xliffPath ) . $force_extension;
        }

        $raw_file_path   = explode( DIRECTORY_SEPARATOR, $originalPath );
        $file_name       = array_pop( $raw_file_path );
        $origDestination = $prefix . DIRECTORY_SEPARATOR . 'orig' . DIRECTORY_SEPARATOR . $file_name;

        $this->s3Client->uploadItem( [
                'bucket' => $bucketName,
                'key'    => $origDestination,
                'source' => $originalPath
        ] );

        Log::doJsonLog( 'Successfully uploaded file ' . $origDestination . ' into ' . $bucketName . ' bucket.' );

        $file_extension = '.sdlxliff';

        return $prefix . DIRECTORY_SEPARATOR . 'work' . DIRECTORY_SEPARATOR . $file_name . $file_extension;
    }

    /**
     * @param $hash
     * @param $lang
     *
     * @return mixed
     * @throws Exception
     */
    public function getOriginalFromCache( $hash, $lang ) {
        return $this->findAKeyInCachePackageBucket( $hash, $lang, 'orig' );
    }

    /**
     * @param $hash
     * @param $lang
     *
     * @return mixed
     * @throws Exception
     */

    // $sha1_original = $hashFile[ 0 ]; 6981e08bc467f8af85fd686c54287ac755408e89
    // $lang          = $hashFile[ 1 ]; it-it
    // $cachedXliffFilePathName = $fs->getXliffFromCache( $sha1_original, $lang ); cache-package/69/81/e08bc467f8af85fd686c54287ac755408e89__it-it/work/os.odt.sdlxliff

    public function getXliffFromCache( $hash, $lang ) {
        return $this->findAKeyInCachePackageBucket( $hash, $lang, 'work' );
    }

    /**
     * @param $hash
     * @param $lang
     * @param $keyToSearch
     *
     * @return mixed
     * @throws Exception
     */
    private function findAKeyInCachePackageBucket( $hash, $lang, $keyToSearch ) {
        $prefix = $this->getCachePackageHashFolder( $hash, $lang ) . DIRECTORY_SEPARATOR . $keyToSearch; // example: c1/68/9bd71f45e76fd5e428f35c00d1f289a7e9e9__it-IT/work
        $items  = $this->s3Client->getItemsInABucket( [ 'bucket' => static::$FILES_STORAGE_BUCKET, 'prefix' => $prefix ] );

        return ( isset( $items[ 0 ] ) ) ? $items[ 0 ] : null;
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
     * @return bool
     * @throws Exception
     */
    public function moveFromCacheToFileDir( $dateHashPath, $lang, $idFile, $newFileName = null ) {
        $hashes   = explode( DIRECTORY_SEPARATOR, $dateHashPath );
        $datePath = $hashes[ 0 ];
        $hash     = $hashes[ 1 ];

        $origPrefix = $this->getCachePackageHashFolder( $hash, $lang ) . '/orig';
        $workPrefix = $this->getCachePackageHashFolder( $hash, $lang ) . '/work';

        // get records from S3 cache
        $origItems   = $this->s3Client->getItemsInABucket( [ 'bucket' => static::$FILES_STORAGE_BUCKET, 'prefix' => $origPrefix ] );
        $workItems   = $this->s3Client->getItemsInABucket( [ 'bucket' => static::$FILES_STORAGE_BUCKET, 'prefix' => $workPrefix ] );
        $sourceItems = array_merge( $origItems, $workItems );

        // if $sourceItems is empty, try to get the records from S3, skipping the cache
        if ( empty( $sourceItems ) ) {
            $origItems   = $this->s3Client->getItemsInABucket( [ 'bucket' => static::$FILES_STORAGE_BUCKET, 'prefix' => $origPrefix, 'exclude-cache' => true ] );
            $workItems   = $this->s3Client->getItemsInABucket( [ 'bucket' => static::$FILES_STORAGE_BUCKET, 'prefix' => $workPrefix, 'exclude-cache' => true ] );
            $sourceItems = array_merge( $origItems, $workItems );
        }

        // if $sourceItems is still empty, return false and then throw an Exception
        if ( empty( $sourceItems ) ) {
            return false;
        }

        $destItems = [];
        foreach ( $sourceItems as $key ) {
            if ( strpos( $key, '/orig/' ) !== false ) {
                $folder = '/orig/';
            } else {
                $folder = '/xliff/';
            }

            $destItems[] = self::FILES_FOLDER . DIRECTORY_SEPARATOR . $datePath . DIRECTORY_SEPARATOR . $idFile . $folder . $this->getTheLastPartOfKey( $key );
        }

        try {
            $copied = $this->s3Client->copyInBatch( [
                    'source_bucket' => static::$FILES_STORAGE_BUCKET,
                    'files'         => [
                            'source' => $sourceItems,
                            'target' => $destItems,
                    ],
            ] );

            Log::doJsonLog( $this->getArrayMessageForLogs( $idFile, $datePath, $sourceItems, $destItems, $copied ) );

            return $copied;
        } catch ( Exception $e ) {
            foreach ( $sourceItems as $item ) {
                $this->s3Client->deleteItem( [
                        'bucket' => static::$FILES_STORAGE_BUCKET,
                        'key'    => $item,
                ] );
            }

            throw $e;
        }
    }

    /**
     * @param $idFile
     * @param $datePath
     * @param $sourceItems
     * @param $destItems
     * @param $copied
     *
     * @return array
     */
    private function getArrayMessageForLogs( $idFile, $datePath, $sourceItems, $destItems, $copied ) {
        $log = [
                'id_file'   => $idFile,
                'date_path' => $datePath,
                'files'     => [
                        'source' => $sourceItems,
                        'target' => $destItems,
                ]
        ];

        $message = ( $copied === true ) ? 'Successfully copied files from cache package to files directory.' : 'Error during copying files from cache package to files directory.';

        $log[ 'message' ] = $message;
        $log[ 'copied' ]  = $copied;

        return $log;
    }

    /**
     * @param $id
     * @param $dateHashPath
     *
     * @return bool|mixed|string
     * @throws Exception
     */
    public function getOriginalFromFileDir( $id, $dateHashPath ) {
        return $this->findAKeyInProjectBucket( $id, $dateHashPath, 'orig' );
    }

    /**
     * @param $id
     * @param $dateHashPath
     *
     * @return mixed
     * @throws Exception
     */
    public function getXliffFromFileDir( $id, $dateHashPath ) {
        return $this->findAKeyInProjectBucket( $id, $dateHashPath, 'xliff' );
    }

    /**
     * @param $id
     * @param $dateHashPath
     * @param $keyToSearch
     *
     * @return mixed
     */
    private function findAKeyInProjectBucket( $id, $dateHashPath, $keyToSearch ) {
        $hashes   = explode( DIRECTORY_SEPARATOR, $dateHashPath );
        $datePath = $hashes[ 0 ];

        $prefix = self::FILES_FOLDER . DIRECTORY_SEPARATOR . $datePath . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . $keyToSearch; // example: 20181212/13/work
        $items  = $this->s3Client->getItemsInABucket( [ 'bucket' => static::$FILES_STORAGE_BUCKET, 'prefix' => $prefix ] );

        return ( isset( $items[ 0 ] ) ) ? $items[ 0 ] : null;
    }

    /**
     **********************************************************************************************
     * 3. QUEUE
     **********************************************************************************************
     */

    /**
     * @param $uploadSession
     *
     * @return void
     * @throws Exception
     */
    public static function moveFileFromUploadSessionToQueuePath( $uploadSession ) {

        $s3Client = self::getStaticS3Client();

        $hasSet = [];

        /** @var DirectoryIterator $item */
        /** @var RecursiveDirectoryIterator $iterator */
        foreach (
                $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator( INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $uploadSession, FilesystemIterator::SKIP_DOTS ),
                        RecursiveIteratorIterator::SELF_FIRST ) as $item
        ) {
            // Example: {CAD1B6E1-B312-8713-E8C3-97145410FD37}} --> cad1b6e1-b312-8713-e8c3-97145410fd37}
            $prefix = self::QUEUE_FOLDER . DIRECTORY_SEPARATOR . self::getUploadSessionSafeName( $uploadSession );

            // Example: aad03b600bc4792b3dc4bf3a2d7191327a482d4a|it-IT --> aad03b600bc4792b3dc4bf3a2d7191327a482d4a__it-IT
            $subPathName = str_replace( '|', self::OBJECTS_SAFE_DELIMITER, $iterator->getSubPathName() );

            $key = $prefix . DIRECTORY_SEPARATOR . $subPathName;

            if ( $item->isDir() ) {
                // create folder
                $s3Client->createFolder( [ 'bucket' => static::$FILES_STORAGE_BUCKET, 'key' => $key ] );
            } else {

                // upload file
                $s3Client->uploadItem( [
                        'bucket' => static::$FILES_STORAGE_BUCKET,
                        'key'    => $key,
                        'source' => $item->getPathName()
                ] );

                // save on redis the hash map files
                if ( strpos( $key, '.' ) === false ) {
                    $hasSet[ $key ] = file( $item->getPathname(), FILE_IGNORE_NEW_LINES );
                }

            }

        }

        ( new RedisHandler() )->getConnection()->hset( self::getUploadSessionSafeName( $uploadSession ), 'file_map', serialize( $hasSet ) );
        Utils::deleteDir( INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $uploadSession );

    }

    /**
     * @param $dirToScan
     *
     * @return array
     * @throws Exception
     */
    public function getHashesFromDir( $dirToScan ) {
        $zipFilesHash  = [];
        $filesHashInfo = [];

        $redisPosition = self::getUploadSessionSafeName( $this->getTheLastPartOfKey( $dirToScan ) );
        $fileMap       = unserialize( ( new RedisHandler() )->getConnection()->hget( $redisPosition, 'file_map' ) );

        foreach ( $fileMap as $hashName => $fileNameList ) {

            if ( strpos( $hashName, $this->getOriginalZipPlaceholder() ) !== false ) {
                $zipFilesHash[] = self::ZIP_FOLDER . DIRECTORY_SEPARATOR . "cache" . DIRECTORY_SEPARATOR . $this->getTheLastPartOfKey( $hashName );
            } else {
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
                $filesHashInfo[ 'sha' ][]                 = $hashName;
                $filesHashInfo[ 'fileName' ][ $hashName ] = $fileNameList;
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
     * @return string
     */
    public static function getUploadSessionSafeName( $uploadSession ) {
        return str_replace( [ '{', '}' ], '', strtolower( $uploadSession ) );
    }

    /**
     * Delete the entire queue folder
     *
     * @param $uploadDir
     */
    public function deleteQueue( $uploadDir ) {
        $this->s3Client->deleteFolder( [
                'bucket' => static::$FILES_STORAGE_BUCKET,
                'prefix' => self::QUEUE_FOLDER . DIRECTORY_SEPARATOR . self::getUploadSessionSafeName( $this->getTheLastPartOfKey( $uploadDir ) )
        ] );
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
     * @throws Exception
     */
    public static function storeFastAnalysisFile( $id_project, array $segments_metadata = [] ) {

        $upload = self::getStaticS3Client()->uploadItemFromBody( [
                'bucket' => static::$FILES_STORAGE_BUCKET,
                'key'    => self::getFastAnalysisFileName( $id_project ),
                'body'   => serialize( $segments_metadata )
        ] );

        if ( false === $upload ) {
            throw new UnexpectedValueException( 'Internal Error: Failed to store segments for fast analysis on Amazon S3 bucket.', -14 );
        }
    }

    /**
     * @param $id_project
     *
     * @return mixed
     * @throws Exception
     */
    public static function getFastAnalysisData( $id_project ) {

        $analysisData = unserialize( self::getStaticS3Client()->openItem( [ 'bucket' => static::$FILES_STORAGE_BUCKET, 'key' => self::getFastAnalysisFileName( $id_project ) ] ) );

        if ( false === $analysisData ) {
            throw new UnexpectedValueException( 'Internal Error: Failed to retrieve analysis information from Amazon S3 bucket.', -15 );
        }

        return $analysisData;
    }

    /**
     * @param $id_project
     *
     * @throws Exception
     */
    public static function deleteFastAnalysisFile( $id_project ) {
        self::getStaticS3Client()->deleteItem( [ 'bucket' => static::$FILES_STORAGE_BUCKET, 'key' => self::getFastAnalysisFileName( $id_project ) ] );
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
     * @throws Exception
     */
    public function cacheZipArchive( $hash, $zipPath ) {

        $prefix  = self::ZIP_FOLDER . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . $hash . $this->getOriginalZipPlaceholder();
        $outcome = $this->s3Client->uploadItem( [
                'bucket' => static::$FILES_STORAGE_BUCKET,
                'key'    => $prefix . DIRECTORY_SEPARATOR . static::basename_fix( $zipPath ),
                'source' => $zipPath
        ] );

        if ( !$outcome ) {
            //Original directory deleted!!!
            //CLEAR ALL CACHE
            Utils::deleteDir( $this->zipDir . DIRECTORY_SEPARATOR . $hash . $this->getOriginalZipPlaceholder() );

            return $outcome;
        }

        unlink( $zipPath );

        //link this zip to the upload directory by creating a file name as the ash of the zip file
        touch( dirname( $zipPath ) . DIRECTORY_SEPARATOR . $hash . $this->getOriginalZipPlaceholder() );

        return true;
    }

    /**
     * @param $create_date
     * @param $zipHash
     * @param $projectID
     *
     * @return bool
     */
    public function linkZipToProject( $create_date, $zipHash, $projectID ) {
        $cacheZipPackage = $zipHash;

        foreach ( $this->s3Client->getItemsInABucket( [ 'bucket' => static::$FILES_STORAGE_BUCKET, 'prefix' => $cacheZipPackage ] ) as $key ) {

            $destination = self::ZIP_FOLDER . DIRECTORY_SEPARATOR . $this->getDatePath( $create_date ) . DIRECTORY_SEPARATOR . $projectID . DIRECTORY_SEPARATOR . $this->getTheLastPartOfKey( $key );

            $copied = $this->s3Client->copyItem( [
                    'source_bucket' => static::$FILES_STORAGE_BUCKET,
                    'source'        => $key,
                    'target_bucket' => static::$FILES_STORAGE_BUCKET,
                    'target'        => $destination
            ] );

            if ( !$copied ) {
                return $copied;
            }

            $delete = $this->s3Client->deleteItem( [ 'bucket' => static::$FILES_STORAGE_BUCKET, 'key' => $key ] );

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
        return self::ZIP_FOLDER . DIRECTORY_SEPARATOR . $this->getDatePath( $projectDate ) . DIRECTORY_SEPARATOR . $projectID . DIRECTORY_SEPARATOR . $zipName;
    }

    /**
     * @param $projectDate
     * @param $projectID
     *
     * @return string
     */
    public function getOriginalZipDir( $projectDate, $projectID ) {
        return 'work' . DIRECTORY_SEPARATOR . $this->getDatePath( $projectDate ) . DIRECTORY_SEPARATOR . $projectID;
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
    public function getTheLastPartOfKey( $key ) {
        $explode = explode( DIRECTORY_SEPARATOR, $key );

        return end( $explode );
    }

    /**
     * Return safe S3 object safe name
     *
     * @return string
     */
    private function getOriginalZipPlaceholder() {
        return str_replace( '#', '', self::ORIGINAL_ZIP_PLACEHOLDER );
    }

    /**
     **********************************************************************************************
     * 6. TRANSFER FILES
     **********************************************************************************************
     */

    /**
     * @param string $source
     * @param string $destination
     *
     * @return bool
     * @throws Exception
     */
    public function transferFiles( $source, $destination ) {
        return $this->s3Client->transfer( [
                'source' => $source,
                'dest'   => $destination,
        ] );
    }
}
