<?php

namespace FilesStorage;

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
     * FILE HANDLING ON FILE SYSTEM
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
     **********************************************************************************************
     * CACHE PACKAGE HELPERS
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
}
