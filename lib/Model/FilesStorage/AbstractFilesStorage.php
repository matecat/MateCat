<?php

namespace FilesStorage;

use Chunks_ChunkStruct;
use Database;
use DirectoryIterator;
use Exception;
use Glossary\Blacklist\BlacklistDao;
use Glossary\Blacklist\BlacklistStruct;
use INIT;
use Log;
use PDO;
use Predis\Connection\ConnectionException;
use ReflectionException;

/**
 * Class FsFilesStorage
 *
 * INDEX
 * -------------------------------------------------------------------------
 * 1. FILE HANDLING ON FILE SYSTEM
 * 2. CACHE PACKAGE HELPERS
 * 3. PROJECT
 * 4. ZIP ARCHIVES HANDLING
 * 5. MISC
 *
 * @package FilesStorage
 */
abstract class AbstractFilesStorage implements IFilesStorage {

    const ORIGINAL_ZIP_PLACEHOLDER = "__##originalZip##";

    protected $filesDir;
    protected $cacheDir;
    protected $zipDir;

    public function __construct( $files = null, $cache = null, $zip = null ) {

        //override default config
        if ( $files ) {
            $this->filesDir = $files;
        } else {
            $this->filesDir = INIT::$FILES_REPOSITORY;
        }

        if ( $cache ) {
            $this->cacheDir = $cache;
        } else {
            $this->cacheDir = INIT::$CACHE_REPOSITORY;
        }

        if ( $zip ) {
            $this->zipDir = $zip;
        } else {
            $this->zipDir = INIT::$ZIP_REPOSITORY;
        }
    }

    /**
     **********************************************************************************************
     * 1. FILE HANDLING ON FILE SYSTEM
     **********************************************************************************************
     */

    /**
     * @param $path
     *
     * @return mixed
     */
    public static function basename_fix( $path ) {
        $rawPath  = explode( DIRECTORY_SEPARATOR, $path );
        $basename = array_pop( $rawPath );

        return $basename;
    }

    /**
     * PHP Pathinfo is not UTF-8 aware, so we rewrite it.
     * It returns array with complete info about a path
     * [
     *    'dirname'   => PATHINFO_DIRNAME,
     *    'basename'  => PATHINFO_BASENAME,
     *    'extension' => PATHINFO_EXTENSION,
     *    'filename'  => PATHINFO_FILENAME
     * ]
     *
     * @param     $path
     * @param int $options
     *
     * @return array|mixed
     */
    public static function pathinfo_fix( $path, $options = 15 ) {
        $rawPath = explode( DIRECTORY_SEPARATOR, $path );

        $basename = array_pop( $rawPath );
        $dirname  = implode( DIRECTORY_SEPARATOR, $rawPath );

        $explodedFileName = explode( ".", $basename );
        $extension        = strtolower( array_pop( $explodedFileName ) );
        $filename         = implode( ".", $explodedFileName );

        $return_array = [];

        $flagMap = [
                'dirname'   => PATHINFO_DIRNAME,
                'basename'  => PATHINFO_BASENAME,
                'extension' => PATHINFO_EXTENSION,
                'filename'  => PATHINFO_FILENAME
        ];

        // foreach flag, add in $return_array the corresponding field,
        // obtained by variable name correspondence
        foreach ( $flagMap as $field => $i ) {
            //binary AND
            if ( ( $options & $i ) > 0 ) {
                //variable substitution: $field can be one between 'dirname', 'basename', 'extension', 'filename'
                // $$field gets the value of the variable named $field
                $return_array[ $field ] = $$field;
            }
        }

        if ( count( $return_array ) == 1 ) {
            $return_array = array_pop( $return_array );
        }

        return $return_array;
    }

    /**
     * @param $path
     *
     * @return bool|string
     */
    public function getSingleFileInPath( $path ) {

        //check if it actually exist
        $filePath = false;
        $files    = [];
        try {
            $files = new DirectoryIterator( $path );
        } catch ( Exception $e ) {
            //directory does not exists
            Log::doJsonLog( "Directory $path does not exists. If you are creating a project check the source language." );
        }

        foreach ( $files as $key => $file ) {

            if ( $file->isDot() ) {
                continue;
            }

            //get the remaining file (it's the only file in dir)
            $filePath = $path . DIRECTORY_SEPARATOR . $file->getFilename();
            //no need to loop anymore
            break;

        }

        return $filePath;
    }

    /**
     * Delete a hash from upload directory
     *
     * @param $uploadDirPath
     * @param $linkFile
     */
    public function deleteHashFromUploadDir( $uploadDirPath, $linkFile ) {
        @list( $shasum, $srcLang ) = explode( "|", $linkFile );
        @list( $shasum, $segRule ) = explode( "_", $shasum ); // remove the segmentation rule from hash to clean all reverse index maps

        $iterator = new DirectoryIterator( $uploadDirPath );

        foreach ( $iterator as $fileInfo ) {
            if ( $fileInfo->isDot() || $fileInfo->isDir() ) {
                continue;
            }

            // remove only the wrong languages, the same code|language must be
            // retained because of the file name append
            if ( $fileInfo->getFilename() != $linkFile &&
                    stripos( $fileInfo->getFilename(), $shasum ) !== false ) {

                unlink( $fileInfo->getPathname() );
                Log::doJsonLog( "Deleted Hash " . $fileInfo->getPathname() );

            }
        }
    }

    /**
     * @param string $haystack
     * @param string $needle
     *
     * @return bool
     */
    public static function fileEndsWith( $haystack, $needle ) {
        $length = strlen( $needle );
        if ( $length == 0 ) {
            return true;
        }

        return ( substr( $haystack, -$length ) === $needle );
    }

    /**
     * @param $create_date
     *
     * @return string
     */
    public function getDatePath( $create_date ) {
        return date_create( $create_date )->format( 'Ymd' );
    }

    /**
     **********************************************************************************************
     * 2. CACHE PACKAGE HELPERS
     **********************************************************************************************
     */

    /**
     * Return an array to build thr cache path from an hash
     *
     * @param $hash
     *
     * @return array
     */
    public static function composeCachePath( $hash ) {

        return [
                'firstLevel'  => $hash[ 0 ] . $hash[ 1 ],
                'secondLevel' => $hash[ 2 ] . $hash[ 3 ],
                'thirdLevel'  => substr( $hash, 4 )
        ];

    }

    /**
     * @param $hash
     * @param $lang
     * @param $uid
     * @param $realFileName
     *
     * @return int
     */
    public function linkSessionToCacheForAlreadyConvertedFiles( $hash, $lang, $uid, $realFileName ) {
        //get upload dir
        $dir = INIT::$QUEUE_PROJECT_REPOSITORY . DIRECTORY_SEPARATOR . $uid;

        //create a file in it, which is called as the hash that indicates the location of the cache for storage
        return $this->_linkToCache( $dir, $hash, $lang, $realFileName );
    }

    /**
     * @param $hash
     * @param $lang
     * @param $uid
     * @param $realFileName
     *
     * @return int
     */
    public function linkSessionToCacheForOriginalFiles( $hash, $lang, $uid, $realFileName ) {
        //get upload dir
        $dir = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $uid;

        //create a file in it, which is called as the hash that indicates the location of the cache for storage
        return $this->_linkToCache( $dir, $hash, $lang, $realFileName );
    }

    /**
     * Appends a string like $dir . DIRECTORY_SEPARATOR . $hash . "|" . $lang (the path in cache package of file in file storage system)
     * on $realFileName file
     *
     * @param $dir
     * @param $hash
     * @param $lang
     * @param $realFileName
     *
     * @return int
     */
    protected function _linkToCache( $dir, $hash, $lang, $realFileName ) {
        return file_put_contents( $dir . DIRECTORY_SEPARATOR . $hash . "|" . $lang, $realFileName . "\n", FILE_APPEND | LOCK_EX );
    }

    /**
     **********************************************************************************************
     * 3. PROJECT
     **********************************************************************************************
     */

    /**
     * Used when we take the files after the translation ( Download )
     *
     * @param int  $id_job
     * @param bool $getXliffPath
     *
     * @return array
     */
    public function getFilesForJob( $id_job, $getXliffPath = true ) {

        $query = "SELECT 
              files_job.id_file, 
              files.filename, 
              files.id_project, 
              jobs.source, 
              mime_type, 
              sha1_original_file,
              jobs.create_date
            FROM files_job
            JOIN files ON files.id = files_job.id_file
            JOIN jobs ON jobs.id = files_job.id_job
            WHERE files_job.id_job = :id_job 
            GROUP BY files_job.id_file";

        $db   = Database::obtain();
        $stmt = $db->getConnection()->prepare( $query );
        $stmt->setFetchMode( PDO::FETCH_ASSOC );
        $stmt->execute( [ 'id_job' => $id_job ] );
        $results = $stmt->fetchAll();

        foreach ( $results as $k => $result ) {
            //try fetching from files dir
            $originalPath = $this->getOriginalFromFileDir( $result[ 'id_file' ], $result[ 'sha1_original_file' ] );

            if ( !empty( $originalPath ) ) {
                $results[ $k ][ 'originalFilePath' ] = $originalPath;
            }

            //we MUST have originalFilePath
            if ( $getXliffPath ) {

                //note that we trust this to succeed on first try since, at this stage, we already built the file package
                $results[ $k ][ 'xliffFilePath' ] = $this->getXliffFromFileDir( $result[ 'id_file' ], $result[ 'sha1_original_file' ] );

                //when we ask for XliffPath ( $getXliffPath == true ) we are downloading translations
                // if original file path is empty means that the file was already a supported xliff type ( ex: trados sdlxliff )
                //use the xliff as original
                if ( empty( $originalPath ) ) {
                    $results[ $k ][ 'originalFilePath' ] = $results[ $k ][ 'xliffFilePath' ];
                }

            } else {

                //when we do NOT ask for XliffPath ( $getXliffPath == false ) we are downloading original
                // if original file path is empty means that the file was already a supported xliff type ( ex: trados sdlxliff )
                //// get the original xliff
                if ( empty( $originalPath ) ) {
                    $results[ $k ][ 'originalFilePath' ] = $this->getXliffFromFileDir( $result[ 'id_file' ], $result[ 'sha1_original_file' ] );
                }

            }

            // this line creates a bug, if the file contains a space at the beginning, we can't download it anymore
            // $results[ $k ][ 'filename' ]  = trim( $results[ $k ][ 'filename' ] );
            $results[ $k ][ 'mime_type' ] = trim( $results[ $k ][ 'mime_type' ] );

        }

        return $results;
    }

    /**
     **********************************************************************************************
     * 4. ZIP ARCHIVES HANDLING
     **********************************************************************************************
     */

    /**
     * Gets the file path of the temporary uploaded zip, when the project is not
     * yet created. Useful to perform preliminary validation on the project.
     * This function was created to perform validations on the TKIT zip file
     * format loaded via API.
     *
     * WARNING: This function only handles the case in which the zip file is *one* for the
     * project.
     *
     * @param $uploadToken
     *
     * @return bool|string
     * @throws ConnectionException
     * @throws ReflectionException
     */
    public function getTemporaryUploadedZipFile( $uploadToken ) {
        $isFsOnS3 = AbstractFilesStorage::isOnS3();

        if ( $isFsOnS3 ) {
            $s3Client = S3FilesStorage::getStaticS3Client();
            $files    = $s3Client->getItemsInABucket( [
                    'bucket' => S3FilesStorage::getFilesStorageBucket(),
                    'prefix' => S3FilesStorage::QUEUE_FOLDER . DIRECTORY_SEPARATOR . S3FilesStorage::getUploadSessionSafeName( $uploadToken )
            ] );
        } else {
            $files = scandir( INIT::$QUEUE_PROJECT_REPOSITORY . '/' . $uploadToken );
        }

        $zip_name = null;
        $zip_file = null;

        foreach ( $files as $file ) {
            Log::doJsonLog( $file );
            if ( strpos( $file, static::ORIGINAL_ZIP_PLACEHOLDER ) !== false ) {
                $zip_name = $file;
            }
        }

        if ( $isFsOnS3 ) {
            $files = $s3Client->getItemsInABucket( [
                    'bucket' => S3FilesStorage::getFilesStorageBucket(),
                    'prefix' => S3FilesStorage::ZIP_FOLDER . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . static::pathinfo_fix( $zip_name, PATHINFO_BASENAME )
            ] );
        } else {
            $files = scandir( INIT::$ZIP_REPOSITORY . '/' . $zip_name );
        }

        foreach ( $files as $file ) {
            if ( strpos( $file, '.zip' ) !== false ) {
                $zip_file = $file;
                break;
            }
        }

        if ( $zip_name == null && $zip_file == null ) {
            return false;
        } elseif ( $isFsOnS3 ) {
            $params[ 'bucket' ]  = INIT::$AWS_STORAGE_BASE_BUCKET;
            $params[ 'key' ]     = $zip_file;
            $params[ 'save_as' ] = "/tmp/" . static::pathinfo_fix( $zip_file, PATHINFO_BASENAME );
            $s3Client->downloadItem( $params );

            return $params[ 'save_as' ];
        } else {
            return INIT::$ZIP_REPOSITORY . '/' . $zip_name . '/' . $zip_file;
        }
    }

    /**
     **********************************************************************************************
     * 4. MISC
     **********************************************************************************************
     */

    /**
     * @return bool
     */
    public static function isOnS3() {
        return ( INIT::$FILE_STORAGE_METHOD === 's3' );
    }

    /**
     **********************************************************************************************
     * 5. BLACKLIST FILES
     **********************************************************************************************
     */

    /**
     * @param $filePath
     *
     * @return bool
     * @throws ConnectionException
     * @throws ReflectionException
     */
    public function deleteBlacklistFile( $filePath ) {
        $isFsOnS3 = AbstractFilesStorage::isOnS3();

        if ( $isFsOnS3 ) {
            $s3Client = S3FilesStorage::getStaticS3Client();

            return $s3Client->deleteItem( [
                    'bucket' => static::$FILES_STORAGE_BUCKET,
                    'key'    => $filePath,
            ] );
        }

        return unlink( $filePath );
    }

    /**
     * @param string              $filePath
     * @param Chunks_ChunkStruct $chunkStruct
     * @param                     $uid
     *
     * @return mixed
     * @throws ConnectionException
     * @throws ReflectionException
     */
    public function saveBlacklistFile( $filePath, Chunks_ChunkStruct $chunkStruct, $uid ) {

        $isFsOnS3 = AbstractFilesStorage::isOnS3();
        $jid      = $chunkStruct->id;
        $password = $chunkStruct->password;

        if ( $isFsOnS3 ) {
            $blacklistPath = 'glossary' . DIRECTORY_SEPARATOR . $jid . DIRECTORY_SEPARATOR . $password . DIRECTORY_SEPARATOR . 'blacklist.txt';
            $s3Client      = S3FilesStorage::getStaticS3Client();
            $s3Client->uploadItem( [
                    'bucket' => static::$FILES_STORAGE_BUCKET,
                    'key'    => $blacklistPath,
                    'source' => $filePath
            ] );
        } else {
            $blacklistPath = INIT::$BLACKLIST_REPOSITORY . DIRECTORY_SEPARATOR . $jid . DIRECTORY_SEPARATOR . $password;
            if ( !is_dir( $blacklistPath ) ) {
                mkdir( $blacklistPath, 0755, true );
            }

            $storedBytes = file_put_contents( $blacklistPath . DIRECTORY_SEPARATOR . "blacklist.txt", file_get_contents( $filePath ) );
            if ( $storedBytes === false ) {
                throw new Exception( 'Failed to save blacklist file on disk.', -14 );
            }
        }

        $model            = new BlacklistStruct( $chunkStruct );
        $model->uid       = $uid;
        $model->target    = $chunkStruct->target;
        $model->file_name = "blacklist.txt";
        $model->file_path = $blacklistPath;

        $dao = new BlacklistDao();

        return $dao->save( $model );
    }
}
