<?php

use FilesStorage\AbstractFilesStorage;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 19/06/15
 * Time: 13.43
 */
class ZipArchiveExtended extends ZipArchive {

    const REFERENCE_FOLDER = '__reference';
    const META_FOLDER      = '__meta';
    const PREVIEWS_FOLDER  = '__previews';

    const MAX_VISITED_DEPTH             = 5;
    const MAX_VISITED_FOLDERS_PER_DEPTH = 10;
    const MAX_FOLDERS                   = 100;
    const INTERNAL_SEPARATOR            = "___SEP___";

    const ARRAY_FILES_PREFIX = "@@_prefix_@@";

    public $tree;
    public $treeList;

    protected static $MAX_FILES;

    public function message( $code ) {
        switch ( $code ) {
            case 0:
                return 'No error';

            case 1:
                return 'Multi-disk zip archives not supported';

            case 2:
                return 'Renaming temporary file failed';

            case 3:
                return 'Closing zip archive failed';

            case 4:
                return 'Seek error';

            case 5:
                return 'Read error';

            case 6:
                return 'Write error';

            case 7:
                return 'CRC error';

            case 8:
                return 'Containing zip archive was closed';

            case 9:
                return 'No such file';

            case 10:
                return 'File already exists';

            case 11:
                return 'Can\'t open file';

            case 12:
                return 'Failure to create temporary file';

            case 13:
                return 'Zlib error';

            case 14:
                return 'Malloc failure';

            case 15:
                return 'Entry has been changed';

            case 16:
                return 'Compression method not supported';

            case 17:
                return 'Premature EOF';

            case 18:
                return 'Invalid argument';

            case 19:
                return 'Not a zip archive';

            case 20:
                return 'Internal error';

            case 21:
                return 'Zip archive inconsistent';

            case 22:
                return 'Can\'t remove file';

            case 23:
                return 'Entry has been deleted';

            default:
                return 'An unknown error has occurred(' . intval( $code ) . ')';
        }
    }

    public function isDir( $path ) {
        return substr( $path, -1 ) == DIRECTORY_SEPARATOR;
    }

    /**
     * @throws Exception
     */
    public function createTree() {

        self::$MAX_FILES = INIT::$MAX_NUM_FILES;

        $Tree              = [];
        $path2numOfFolders = [];
        $filePaths         = [];

        $numOfFolders = 0;
        $numOfFiles   = 0;

        for ( $i = 0; $i < $this->numFiles; $i++ ) {

            $path = $this->getNameIndex( $i );

            $pathBySlash = array_values( explode( '/', $path ) );

            if ( $pathBySlash[ 0 ] == '__MACOSX' ) {
                continue;
            }

            if ( $pathBySlash[ 0 ] == self::REFERENCE_FOLDER ) {
                continue;
            }

            if ( $pathBySlash[ 0 ] == self::META_FOLDER ) {
                continue;
            }

            if ( $pathBySlash[ 0 ] == self::PREVIEWS_FOLDER ) {
                continue;
            }

            if ( end( $pathBySlash ) == '.DS_Store' ) {
                continue;
            }

            $pathBySlash = array_map( [ 'ZipArchiveExtended', 'treeKey' ], $pathBySlash );

            $pathWithoutFile = $pathBySlash;
            $fileName        = array_pop( $pathWithoutFile );
            $lastFolder      = array_pop( $pathWithoutFile );
            $pathWithoutFile = implode( DIRECTORY_SEPARATOR, $pathWithoutFile );

            if ( $pathWithoutFile != "" && !isset( $path2numOfFolders[ $pathWithoutFile ] ) ) {
                $path2numOfFolders[ $pathWithoutFile ] = 0;
            }

            if ( $pathWithoutFile != "" && $fileName == self::ARRAY_FILES_PREFIX ) {    //this is the path of a directory: add directory count
                $path2numOfFolders[ $pathWithoutFile ]++;
                $numOfFolders++;
            } else {
                $numOfFiles++;
            }

            $c = count( $pathBySlash );

            if ( $c > self::MAX_VISITED_DEPTH + 1 ) { //+1 makes the algo ignore the file, whether it exists or not
                throw new Exception( "Max allowed depth exceeded.", -1 );
            }

            if ( $numOfFiles > self::$MAX_FILES || $numOfFolders > self::MAX_FOLDERS ) {
                throw new Exception( "Max number of files or folders exceeded.", -2 );
            }


            $folderCanBeVisited = true;
            //check that every ancestor folder has a number of folders below the allowed threshold
            foreach ( $path2numOfFolders as $fpath => $number ) {
                if ( @strpos( $pathWithoutFile, $fpath ) > -1 && $number > self::MAX_VISITED_FOLDERS_PER_DEPTH ) {
                    $folderCanBeVisited = false;
                    break;
                }
            }

            if ( !$folderCanBeVisited ) {
                throw new Exception( "Max number of folders per depth exceeded.", -3 );
            }

            if ( $fileName != "" && $fileName != self::ARRAY_FILES_PREFIX ) {
                $filePaths[] = $path;
            }

            $temp = &$Tree;
            for ( $j = 0; $j < $c - 1; $j++ ) {
                $count       = 1;
                $originalKey = str_replace( self::ARRAY_FILES_PREFIX, "", $pathBySlash[ $j ], $count );
                if ( isset( $temp[ $originalKey ] ) ) {
                    $temp = &$temp[ $originalKey ];
                } else {
                    $temp[ $originalKey ] = [];
                    $temp                 = &$temp[ $originalKey ];
                }
            }

            $last_originalKey = str_replace( self::ARRAY_FILES_PREFIX, "", $pathBySlash[ $c - 1 ], $count );
            if ( $this->isDir( $path ) ) {
                $temp[ $last_originalKey ] = [];
            } else {
                $temp[] = $last_originalKey;
            }
        }

        $this->tree     = $Tree;
        $this->treeList = array_unique( $filePaths );
        $this->treeList = str_replace( DIRECTORY_SEPARATOR, self::INTERNAL_SEPARATOR, $this->treeList );
        $this->treeList = array_map( [ 'ZipArchiveExtended', 'prependZipFileName' ], $this->treeList );

    }

    public function extractFilesInTmp( $tmp_folder ) {

        $filesArray = [];
        $fileErrors = [];

        //pre: createTree() must have been called so that $this->treeList is not empty.
        foreach ( $this->treeList as $filePath ) {

            $realPath = str_replace(
                    [ self::INTERNAL_SEPARATOR, AbstractFilesStorage::pathinfo_fix( $this->filename, PATHINFO_BASENAME ) ],
                    [ DIRECTORY_SEPARATOR, "" ],
                    $filePath
            );

            $realPath = ltrim( $realPath, "/" );

            $fp = $this->getStream( $realPath );

            $tmpFp = fopen( $tmp_folder . $filePath, "w" );

            if ( !$fp ) {
                $fileErrors[ $filePath ] = "Unable to extract the file.";
                break;
            }

            $sizeExceeded = false;
            $fileSize     = 0;
            while ( !feof( $fp ) && !$sizeExceeded ) {
                fwrite( $tmpFp, fread( $fp, 8192 ) );
                $fileSize += 8192;

                if ( $fileSize > INIT::$MAX_UPLOAD_FILE_SIZE ) {
                    $sizeExceeded = true;
                    continue;
                }
            }

            if ( $sizeExceeded ) {
                $fileErrors[ $filePath ] = 'Max upload file size exceeded.';
            }

            fclose( $fp );
            fclose( $tmpFp );

            $filesArray[ $filePath ] = [
                    'name'     => $filePath,
                    'tmp_name' => $tmp_folder . $filePath,
            ];

        }

        foreach ( $filesArray as $filePath => &$objectFile ) {
            $objectFile[ 'error' ] = $fileErrors[ $filePath ];
            $objectFile[ 'type' ]  = @mime_content_type( $tmp_folder . $filePath );
        }

        return $filesArray;

    }

    private function treeKey( $key ) {
        return self::ARRAY_FILES_PREFIX . $key;
    }

    private function prependZipFileName( $fName ) {
        return AbstractFilesStorage::pathinfo_fix( $this->filename, PATHINFO_BASENAME ) . self::INTERNAL_SEPARATOR . $fName;
    }

    /**
     * Gets path informations for a file unzipped using ZipArchiveExtended.
     *
     * @param $path        string A valid ZipArchiveExtended path. it must be a path that uses the internal
     *                     separator of this class.
     *
     * @return array|null Returns null if path is not valid, otherwise it will return the array returned
     *                    by pathinfo() function, plus a 'zipfilename' key, containing the zip file name.
     */
    public static function zipPathInfo( $path ) {
        if ( strpos( $path, self::INTERNAL_SEPARATOR ) === false ) {
            return null;
        }
        $path = explode( self::INTERNAL_SEPARATOR, $path );

        $zipFile  = array_shift( $path );
        $basename = array_pop( $path );

        $filenameInfo = explode( ".", $basename );
        $extension    = array_pop( $filenameInfo );
        $filename     = implode( ".", $filenameInfo );
        $dirname      = implode( DIRECTORY_SEPARATOR, $path );

        $pathInfo = [
                'dirname'     => $dirname,
                'basename'    => $basename,
                'extension'   => $extension,
                'filename'    => $filename,
                'zipfilename' => $zipFile
        ];

        return $pathInfo;
    }

    /**
     * @param $internalFileName string
     *
     * @return string
     */
    public static function getFileName( $internalFileName ) {
        $path = explode( self::INTERNAL_SEPARATOR, $internalFileName );

        return implode( DIRECTORY_SEPARATOR, $path );
    }

    /**
     * @param $fileName string
     *
     * @return string
     */
    public static function getInternalFileName( $fileName ) {
        $path = explode( DIRECTORY_SEPARATOR, $fileName );

        return implode( self::INTERNAL_SEPARATOR, $path );
    }


}
