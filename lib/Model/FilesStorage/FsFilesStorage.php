<?php

namespace FilesStorage;

use FilesStorage\Exceptions\FileSystemException;
use FilesystemIterator;
use INIT;
use Log;
use Matecat\XliffParser\Utils\Files as XliffFiles;
use Matecat\XliffParser\XliffUtils\XliffProprietaryDetect;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use UnexpectedValueException;
use Utils;

/**
 * Class FsFilesStorage
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
class FsFilesStorage extends AbstractFilesStorage {
    /**
     **********************************************************************************************
     * 1. CACHE PACKAGE
     **********************************************************************************************
     */

    /**
     * @param      $hash
     * @param      $lang
     * @param bool $originalPath
     * @param      $xliffPath
     *
     * @return bool
     * @throws FileSystemException
     */
    public function makeCachePackage( $hash, $lang, $originalPath, $xliffPath ) {

        $cacheTree = implode( DIRECTORY_SEPARATOR, static::composeCachePath( $hash ) );

        //don't save in cache when a specified filter version is forced
        if ( INIT::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION !== false && is_dir( $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . "|" . $lang ) ) {
            return true;
        }

        //create cache dir structure
        mkdir( $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . "|" . $lang, 0755, true );
        $cacheDir = $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . "|" . $lang . DIRECTORY_SEPARATOR . "package";
        mkdir( $cacheDir, 0755, true );
        mkdir( $cacheDir . DIRECTORY_SEPARATOR . "orig" );
        mkdir( $cacheDir . DIRECTORY_SEPARATOR . "work" );

        //if it's not a xliff as original
        if ( !$originalPath ) {

            //if there is not an original path this is an unconverted file,
            // the original does not exist
            // detect which type of xliff
            //check also for the extension, if already present do not force
            $force_extension = "";
            $fileType = XliffProprietaryDetect::getInfo( $xliffPath );
            if ( !$fileType[ 'proprietary' ] && $fileType[ 'info' ][ 'extension' ] != 'sdlxliff' ) {
                $force_extension = '.sdlxliff';
            }

            //use original xliff
            $xliffDestination = $cacheDir . DIRECTORY_SEPARATOR . "work" . DIRECTORY_SEPARATOR . static::basename_fix( $xliffPath ) . $force_extension;
        } else {

            //move original
            $raw_file_path = explode( DIRECTORY_SEPARATOR, $originalPath );
            $file_name     = array_pop( $raw_file_path );

            $outcome1 = copy( $originalPath, $cacheDir . DIRECTORY_SEPARATOR . "orig" . DIRECTORY_SEPARATOR . $file_name );

            if ( !$outcome1 ) {
                // Original directory deleted!!!
                // CLEAR ALL CACHE

                $cacheDirToDelete = $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . "|" . $lang;

                // check if cache dir exists
                if ( !file_exists( $cacheDirToDelete ) ) {
                    throw new FileSystemException( $cacheDirToDelete . ' directory does not exists. Maybe there is a problem with folder permissions.' );
                }

                Utils::deleteDir( $cacheDirToDelete );

                return false;
            }

            $file_extension = '.sdlxliff';

            //set naming for converted xliff
            $xliffDestination = $cacheDir . DIRECTORY_SEPARATOR . "work" . DIRECTORY_SEPARATOR . $file_name . $file_extension;
        }

        //move converted xliff
        //In Unix you can't rename or move between filesystems,
        //Instead you must copy the file from one source location to the destination location, then delete the source.
        $outcome2 = copy( $xliffPath, $xliffDestination );

        if ( !$outcome2 ) {
            //Original directory deleted!!!
            //CLEAR ALL CACHE - FATAL

            $cacheDirToDelete = $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . "|" . $lang;

            // check if cache dir exists
            if ( !file_exists( $cacheDirToDelete ) ) {
                throw new FileSystemException( $cacheDirToDelete . ' directory does not exists. Maybe there is a problem with folder permissions.' );
            }

            Utils::deleteDir( $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . "|" . $lang );

            return false;
        }

        unlink( $xliffPath );

        return true;
    }

    /**
     * Rebuild the filename that will be taken from disk in the cache directory
     *
     * @param $hash
     * @param $lang
     *
     * @return bool|string
     */
    public function getOriginalFromCache( $hash, $lang ) {

        //compose path
        $cacheTree = implode( DIRECTORY_SEPARATOR, static::composeCachePath( $hash ) );

        $path = $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . "|" . $lang . DIRECTORY_SEPARATOR . "package" . DIRECTORY_SEPARATOR . "orig";

        //return file
        $filePath = $this->getSingleFileInPath( $path );

        //an unconverted xliff is never stored in orig dir; look for it in xliff dir
        if ( !$filePath ) {
            $filePath = $this->getXliffFromCache( $hash, $lang );
        }

        return $filePath;
    }

    /**
     * @param $hash
     * @param $lang
     *
     * @return bool|string
     */
    public function getXliffFromCache( $hash, $lang ) {

        $cacheTree = implode( DIRECTORY_SEPARATOR, static::composeCachePath( $hash ) );

        //compose path
        $path = $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . "|" . $lang . DIRECTORY_SEPARATOR . "package" . DIRECTORY_SEPARATOR . "work";

        //return file
        return $this->getSingleFileInPath( $path );
    }

    /**
     **********************************************************************************************
     * 2. PROJECT
     **********************************************************************************************
     */

    /**
     * @param      $dateHashPath
     * @param      $lang
     * @param      $idFile
     * @param null $newFileName
     *
     * @return bool
     */
    public function moveFromCacheToFileDir( $dateHashPath, $lang, $idFile, $newFileName = null ) {

        list( $datePath, $hash ) = explode( DIRECTORY_SEPARATOR, $dateHashPath );
        $cacheTree = implode( DIRECTORY_SEPARATOR, static::composeCachePath( $hash ) );

        //destination dir
        $fileDir  = $this->filesDir . DIRECTORY_SEPARATOR . $datePath . DIRECTORY_SEPARATOR . $idFile;
        $cacheDir = $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . "|" . $lang . DIRECTORY_SEPARATOR . "package";

        Log::doJsonLog( $fileDir );
        Log::doJsonLog( $cacheDir );

        $res = true;
        //check if it doesn't exist
        if ( !is_dir( $fileDir ) ) {
            //make files' directory structure
            $res &= mkdir( $fileDir, 0755, true );
            $res &= mkdir( $fileDir . DIRECTORY_SEPARATOR . "package" );
            $res &= mkdir( $fileDir . DIRECTORY_SEPARATOR . "package" . DIRECTORY_SEPARATOR . "orig" );
            $res &= mkdir( $fileDir . DIRECTORY_SEPARATOR . "package" . DIRECTORY_SEPARATOR . "work" );
            $res &= mkdir( $fileDir . DIRECTORY_SEPARATOR . "orig" );
            $res &= mkdir( $fileDir . DIRECTORY_SEPARATOR . "xliff" );
        }

        //make links from cache to files
        //BUG: this stuff may not work if FILES and CACHES are on different filesystems
        //orig, suppress error because of xliff files have not original one
        $origDir = $cacheDir . DIRECTORY_SEPARATOR . "orig";
        Log::doJsonLog( $origDir );

        $origFilePath    = $this->getSingleFileInPath( $origDir );
        $tmpOrigFileName = $origFilePath;
        if ( is_file( $origFilePath ) ) {

            /*
             * Force the new filename if it is provided
             */
            if ( !empty( $newFileName ) ) {
                $tmpOrigFileName = $newFileName;
            }
            $res &= $this->link( $origFilePath, $fileDir . DIRECTORY_SEPARATOR . "orig" . DIRECTORY_SEPARATOR . static::basename_fix( $tmpOrigFileName ) );

        }

        //work
        /*
         * Force the new filename if it is provided
         */
        $d = $cacheDir . DIRECTORY_SEPARATOR . "work";
        Log::doJsonLog( $d );
        $convertedFilePath = $this->getSingleFileInPath( $d );

        Log::doJsonLog( $convertedFilePath );

        $tmpConvertedFilePath = $convertedFilePath;
        if ( !empty( $newFileName ) ) {
            if ( !XliffFiles::isXliff( $newFileName ) ) {
                $convertedExtension   = static::pathinfo_fix( $convertedFilePath, PATHINFO_EXTENSION );
                $tmpConvertedFilePath = $newFileName . "." . $convertedExtension;
            }
        }

        Log::doJsonLog( $convertedFilePath );  // <--------- TODO: this is empty!

        $dest = $fileDir . DIRECTORY_SEPARATOR . "xliff" . DIRECTORY_SEPARATOR . static::basename_fix( $tmpConvertedFilePath );

        Log::doJsonLog( $dest );

        $res &= $this->link( $convertedFilePath, $dest );

        if ( !$res ) {
            throw new UnexpectedValueException( 'Internal Error: Failed to create/copy the file on disk from cache.', -13 );
        }

        return (bool)$res;

    }

    /**
     * Rebuild the filename that will be taken from disk in files directory
     *
     * @param $id
     * @param $dateHashPath
     *
     * @return bool|string
     */
    public function getOriginalFromFileDir( $id, $dateHashPath ) {

        list( $datePath, ) = explode( DIRECTORY_SEPARATOR, $dateHashPath );

        //compose path
        $path = $this->filesDir . DIRECTORY_SEPARATOR . $datePath . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . "orig";

        //return file
        $filePath = $this->getSingleFileInPath( $path );

        //an unconverted xliff is never stored in orig dir; look for it in xliff dir
        if ( !$filePath ) {
            $filePath = $this->getXliffFromFileDir( $id, $dateHashPath );
        }

        return $filePath;
    }

    /**
     * @param $id
     * @param $dateHashPath
     *
     * @return bool|string
     */
    public function getXliffFromFileDir( $id, $dateHashPath ) {

        list( $datePath, ) = explode( DIRECTORY_SEPARATOR, $dateHashPath );

        //compose path
        $path = $this->filesDir . DIRECTORY_SEPARATOR . $datePath . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . "xliff";

        //return file
        return $this->getSingleFileInPath( $path );
    }

    /**
     * @param $dirToScan
     *
     * @return array
     */
    public function getHashesFromDir( $dirToScan ) {

        //fetch cache links, created by converter, from a directory
        $linkFiles     = scandir( $dirToScan );
        $zipFilesHash  = [];
        $filesHashInfo = [];
        //remove dir hardlinks, as uninteresting, as well as regular files; only hash-links
        foreach ( $linkFiles as $k => $linkFile ) {

            if ( strpos( $linkFile, self::ORIGINAL_ZIP_PLACEHOLDER ) !== false ) {
                $zipFilesHash[] = $linkFile;
                unset( $linkFiles[ $k ] );
            } elseif ( strpos( $linkFile, '.' ) !== false or strpos( $linkFile, '|' ) === false ) {
                unset( $linkFiles[ $k ] );
            } else {
                $filesHashInfo[ 'sha' ][]                 = $linkFile;
                $filesHashInfo[ 'fileName' ][ $linkFile ] = file(
                        $dirToScan . DIRECTORY_SEPARATOR . $linkFile,
                        FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
                );
            }

        }

        return [
                'conversionHashes' => $filesHashInfo,
                'zipHashes'        => $zipFilesHash
        ];

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
     */
    public static function moveFileFromUploadSessionToQueuePath( $uploadSession ) {

        $destination = INIT::$QUEUE_PROJECT_REPOSITORY . DIRECTORY_SEPARATOR . $uploadSession;
        mkdir( $destination, 0755 );
        foreach (
                $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator( INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $uploadSession, FilesystemIterator::SKIP_DOTS ),
                        RecursiveIteratorIterator::SELF_FIRST ) as $item
        ) {
            if ( $item->isDir() ) {
                mkdir( $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName() );
            } else {
                copy( $item, $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName() );
            }
        }

        Utils::deleteDir( INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $uploadSession );

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
     */
    public static function storeFastAnalysisFile( $id_project, array $segments_metadata = [] ) {

        $storedBytes = file_put_contents( INIT::$ANALYSIS_FILES_REPOSITORY . DIRECTORY_SEPARATOR . "waiting_analysis_$id_project.ser", serialize( $segments_metadata ) );
        if ( $storedBytes === false ) {
            throw new UnexpectedValueException( 'Internal Error: Failed to store segments for fast analysis on disk.', -14 );
        }

    }

    /**
     * @param $id_project
     *
     * @return array
     * @throws UnexpectedValueException
     *
     */
    public static function getFastAnalysisData( $id_project ) {

        $analysisData = unserialize( file_get_contents( INIT::$ANALYSIS_FILES_REPOSITORY . DIRECTORY_SEPARATOR . "waiting_analysis_$id_project.ser" ) );
        if ( $analysisData === false ) {
            throw new UnexpectedValueException( 'Internal Error: Failed to retrieve analysis information from disk.', -15 );
        }

        return $analysisData;

    }

    /**
     * @param $id_project
     *
     * @return bool
     */
    public static function deleteFastAnalysisFile( $id_project ) {
        return unlink( INIT::$ANALYSIS_FILES_REPOSITORY . DIRECTORY_SEPARATOR . "waiting_analysis_$id_project.ser" );
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
     */
    public function cacheZipArchive( $hash, $zipPath ) {

        $thisZipDir = $this->zipDir . DIRECTORY_SEPARATOR . $hash . self::ORIGINAL_ZIP_PLACEHOLDER;

        //ensure old stuff is overwritten
        if ( is_dir( $thisZipDir ) ) {
            Utils::deleteDir( $thisZipDir );
        }

        //create cache dir structure
        $created = mkdir( $thisZipDir, 0755, true );

        if ( !$created ) {
            return false;
        }

        //move original
        $outcome1 = copy( $zipPath, $thisZipDir . DIRECTORY_SEPARATOR . static::basename_fix( $zipPath ) );

        if ( !$outcome1 ) {
            //Original directory deleted!!!
            //CLEAR ALL CACHE
            Utils::deleteDir( $this->zipDir . DIRECTORY_SEPARATOR . $hash . self::ORIGINAL_ZIP_PLACEHOLDER );

            return false;
        }

        unlink( $zipPath );

        //link this zip to the upload directory by creating a file name as the ash of the zip file
        touch( dirname( $zipPath ) . DIRECTORY_SEPARATOR . $hash . self::ORIGINAL_ZIP_PLACEHOLDER );

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

        $datePath = $this->getDatePath( $create_date );

        $fileName = static::basename_fix( $this->getSingleFileInPath( $this->zipDir . DIRECTORY_SEPARATOR . $zipHash ) );

        //destination dir
        $newZipDir = $this->zipDir . DIRECTORY_SEPARATOR . $datePath . DIRECTORY_SEPARATOR . $projectID;

        //check if it doesn't exist
        if ( !is_dir( $newZipDir ) ) {
            //make files' directory structure
            if ( !mkdir( $newZipDir, 0755, true ) ) {
                return false;
            }
        }

        //link original
        $outcome1 = $this->link( $this->getSingleFileInPath( $this->zipDir . DIRECTORY_SEPARATOR . $zipHash ), $newZipDir . DIRECTORY_SEPARATOR . $fileName );

        if ( !$outcome1 ) {
            //Failed to copy the original file zip
            return $outcome1;
        }

        Utils::deleteDir( $this->zipDir . DIRECTORY_SEPARATOR . $zipHash );

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

        $datePath = date_create( $projectDate )->format( 'Ymd' );

        return $this->zipDir . DIRECTORY_SEPARATOR . $datePath . DIRECTORY_SEPARATOR . $projectID . DIRECTORY_SEPARATOR . $zipName;

    }

    /**
     * @param $projectDate
     * @param $projectID
     *
     * @return string
     */
    public function getOriginalZipDir( $projectDate, $projectID ) {

        $datePath = date_create( $projectDate )->format( 'Ymd' );

        return $this->zipDir . DIRECTORY_SEPARATOR . $datePath . DIRECTORY_SEPARATOR . $projectID;

    }

    private function link( $source, $destination ) {
        return link( $source, $destination );
    }

    /**
     **********************************************************************************************
     * 6. TRANSFER FILES
     **********************************************************************************************
     */

    public function transferFiles( $source, $destination ) {
    }
}

