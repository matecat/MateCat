<?php

namespace FilesStorage;

/**
 * Class FsFilesStorage
 *
 * INDEX
 * -------------------------------------------------------------------------
 * 1. FILE HANDLING ON FILE SYSTEM
 * 2. CACHE PACKAGE HELPERS
 * 3. PROJECT
 *
 * @package FilesStorage
 */
abstract class AbstractFilesStorage implements IFilesStorage {
    protected $filesDir;
    protected $cacheDir;
    protected $zipDir;

    public function __construct( $files = null, $cache = null, $zip = null ) {

        //override default config
        if ( $files ) {
            $this->filesDir = $files;
        } else {
            $this->filesDir = \INIT::$FILES_REPOSITORY;
        }

        if ( $cache ) {
            $this->cacheDir = $cache;
        } else {
            $this->cacheDir = \INIT::$CACHE_REPOSITORY;
        }

        if ( $zip ) {
            $this->zipDir = $zip;
        } else {
            $this->zipDir = \INIT::$ZIP_REPOSITORY;
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
            $files = new \DirectoryIterator( $path );
        } catch ( \Exception $e ) {
            //directory does not exists
            \Log::doJsonLog( "Directory $path does not exists. If you are creating a project check the source language." );
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

        $iterator = new \DirectoryIterator( $uploadDirPath );

        foreach ( $iterator as $fileInfo ) {
            if ( $fileInfo->isDot() || $fileInfo->isDir() ) {
                continue;
            }

            // remove only the wrong languages, the same code|language must be
            // retained because of the file name append
            if ( $fileInfo->getFilename() != $linkFile &&
                    stripos( $fileInfo->getFilename(), $shasum ) !== false ) {

                unlink( $fileInfo->getPathname() );
                \Log::doJsonLog( "Deleted Hash " . $fileInfo->getPathname() );

            }
        }
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

        $cacheTree = [
                'firstLevel'  => $hash{0} . $hash{1},
                'secondLevel' => $hash{2} . $hash{3},
                'thirdLevel'  => substr( $hash, 4 )
        ];

        return $cacheTree;

    }

    /**
     **********************************************************************************************
     * 3. PROJECT
     **********************************************************************************************
     */

    /**
     *
     * Used when we get info to download the original file
     *
     * @param $id_job
     * @param $id_file
     * @param $password
     *
     * @return array
     */
    public function getOriginalFilesForJob( $id_job, $id_file, $password ) {

        $where_id_file = "";
        if ( !empty( $id_file ) ) {
            $where_id_file = " and fj.id_file=$id_file";
        }
        $query = "select fj.id_file, f.filename, f.id_project, j.source, mime_type, sha1_original_file, create_date from files_job fj
			inner join files f on f.id=fj.id_file
			inner join jobs j on j.id=fj.id_job
			where fj.id_job=$id_job $where_id_file and j.password='$password'";

        $db      = \Database::obtain();
        $results = $db->fetch_array( $query );

        foreach ( $results as $k => $result ) {
            //try fetching from files dir
            $filePath                            = $this->getOriginalFromFileDir( $result[ 'id_file' ], $result[ 'sha1_original_file' ] );
            $results[ $k ][ 'originalFilePath' ] = $filePath;
        }

        return $results;
    }

    /**
     * Used when we take the files after the translation ( Download )
     *
     * @param $id_job
     * @param $id_file
     *
     * @return array
     */
    public function getFilesForJob( $id_job, $id_file ) {

        $where_id_file = "";

        if ( !empty( $id_file ) ) {
            $where_id_file = " and id_file=$id_file";
        }

        $query = "SELECT fj.id_file, f.filename, f.id_project, j.source, mime_type, sha1_original_file 
            FROM files_job fj
            INNER JOIN files f ON f.id=fj.id_file
            JOIN jobs AS j ON j.id=fj.id_job
            WHERE fj.id_job = $id_job $where_id_file 
            GROUP BY id_file";

        $db      = \Database::obtain();
        $results = $db->fetch_array( $query );

        foreach ( $results as $k => $result ) {
            //try fetching from files dir
            $originalPath = $this->getOriginalFromFileDir( $result[ 'id_file' ], $result[ 'sha1_original_file' ] );

            $results[ $k ][ 'originalFilePath' ] = $originalPath;

            //note that we trust this to succeed on first try since, at this stage, we already built the file package
            $results[ $k ][ 'xliffFilePath' ] = $this->getXliffFromFileDir( $result[ 'id_file' ], $result[ 'sha1_original_file' ] );
        }

        return $results;
    }
}
