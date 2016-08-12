<?php

/*

files
	|_file id
		|_package
		|	|_manifest
		|	|_orig
		|	|	|_original file
		|	|_work
		|		|_xliff file
		|_orig
		|	|_original file
		|_xliff
			|_xliff file

cache
	|_sha1+lang
		|_package
			|_manifest
			|_orig
			|	|_original file
			|_work
				|_xliff file

*/

class FilesStorage {

    private $filesDir;
    private $cacheDir;
    private $zipDir;

    const ORIGINAL_ZIP_PLACEHOLDER = "__##originalZip##";

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

        if( $zip ){
            $this->zipDir = $zip;
        } else {
            $this->zipDir = INIT::$ZIP_REPOSITORY;
        }

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
        $cacheTree = implode( DIRECTORY_SEPARATOR, $this->_composeCachePath( $hash ) );

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
     * Rebuild the filename that will be taken from disk in files directory
     *
     * @param $id
     *
     * @return bool|string
     */
    public function getOriginalFromFileDir( $id, $dateHashPath ) {

        list( $datePath, $hash ) = explode( DIRECTORY_SEPARATOR, $dateHashPath );

        //compose path
        $path = $this->filesDir . DIRECTORY_SEPARATOR . $datePath . DIRECTORY_SEPARATOR. $id . DIRECTORY_SEPARATOR . "orig";

        //return file
        $filePath = $this->getSingleFileInPath( $path );

        //an unconverted xliff is never stored in orig dir; look for it in xliff dir
        if ( !$filePath ) {
            $filePath = $this->getXliffFromFileDir( $id, $dateHashPath );
        }

        return $filePath;
    }

    /*
     * Cache Handling Methods --- START
     */

    protected function _composeCachePath( $hash ){

        $cacheTree = array(
                'firstLevel'  => $hash{0} . $hash{1},
                'secondLevel' => $hash{2} . $hash{3},
                'thirdLevel'  => substr( $hash, 4 )
        );

        return $cacheTree;

    }

    public function getXliffFromCache( $hash, $lang ) {

        $cacheTree = implode( DIRECTORY_SEPARATOR, $this->_composeCachePath( $hash ) );

        //compose path
        $path = $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . "|" . $lang . DIRECTORY_SEPARATOR . "package" . DIRECTORY_SEPARATOR . "work";

        //return file
        return $this->getSingleFileInPath( $path );
    }

    public function getXliffFromFileDir( $id, $dateHashPath ) {

        list( $datePath, $hash ) = explode( DIRECTORY_SEPARATOR, $dateHashPath );

        //compose path
        $path = $this->filesDir . DIRECTORY_SEPARATOR . $datePath . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . "xliff";

        //return file
        return $this->getSingleFileInPath( $path );
    }

    public function makeCachePackage( $hash, $lang, $originalPath = false, $xliffPath ) {

        $cacheTree = implode( DIRECTORY_SEPARATOR, $this->_composeCachePath( $hash ) );

        //don't save in cache when a specified filter version is forced
        if ( INIT::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION !== false &&  is_dir( $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . "|" . $lang ) ) {
            return true;
        }

        //create cache dir structure
        mkdir( $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . "|" . $lang, 0755, true );
        $cacheDir = $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . "|" . $lang . DIRECTORY_SEPARATOR . "package";
        mkdir( $cacheDir, 0755, true );
        mkdir( $cacheDir . DIRECTORY_SEPARATOR . "orig" );
        mkdir( $cacheDir . DIRECTORY_SEPARATOR . "work" );

        //if it's not an xliff as original
        if ( !$originalPath ) {

            //if there is not an original path this is an unconverted file,
            // the original does not exists
            // detect which type of xliff
            //check also for the extension, if already present do not force
            $fileType = DetectProprietaryXliff::getInfo( $xliffPath );
            if ( !$fileType[ 'proprietary' ] && $fileType[ 'info' ][ 'extension' ] != 'sdlxliff' ) {
                $force_extension = '.sdlxliff';
            }

            //use original xliff
            $xliffDestination = $cacheDir . DIRECTORY_SEPARATOR . "work" . DIRECTORY_SEPARATOR . FilesStorage::basename_fix( $xliffPath ) . @$force_extension;
        } else {
            //move original
            $raw_file_path = explode(DIRECTORY_SEPARATOR, $originalPath);
            $file_name = array_pop($raw_file_path);

            $outcome1 = copy( $originalPath, $cacheDir . DIRECTORY_SEPARATOR . "orig" . DIRECTORY_SEPARATOR . $file_name  );

            if( !$outcome1 ){
                //Original directory deleted!!!
                //CLEAR ALL CACHE
                Utils::deleteDir( $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . "|" . $lang );
                return $outcome1;
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
            Utils::deleteDir( $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . "|" . $lang );
            return $outcome2;
        }

        unlink( $xliffPath );
        return true;

    }

    /**
     * Make a temporary cache copy for the original zip file
     *
     * @param $hash
     * @param $zipPath
     *
     * @return bool
     */
    public function cacheZipArchive( $hash, $zipPath ){

        $thisZipDir = $this->zipDir . DIRECTORY_SEPARATOR . $hash . self::ORIGINAL_ZIP_PLACEHOLDER;

        //ensure old stuff is overwritten
        if ( is_dir( $thisZipDir ) ) {
            Utils::deleteDir( $thisZipDir );
        }

        //create cache dir structure
        $created = mkdir( $thisZipDir, 0755, true );

        if( !$created ){
            return $created;
        }

        //move original
        $outcome1 = copy( $zipPath, $thisZipDir . DIRECTORY_SEPARATOR . FilesStorage::basename_fix( $zipPath ) );

        if( !$outcome1 ){
            //Original directory deleted!!!
            //CLEAR ALL CACHE
            Utils::deleteDir( $this->zipDir . DIRECTORY_SEPARATOR . $hash . self::ORIGINAL_ZIP_PLACEHOLDER );
            return $outcome1;
        }

        unlink( $zipPath );

        //link this zip to the upload directory by creating a file name as the ash of the zip file
        touch( dirname( $zipPath ) . DIRECTORY_SEPARATOR . $hash . self::ORIGINAL_ZIP_PLACEHOLDER );

        return true;

    }

    public function linkZipToProject( $create_date, $zipHash, $projectID ){

        $datePath = date_create( $create_date )->format( 'Ymd' );

        $fileName = FilesStorage::basename_fix( $this->getSingleFileInPath( $this->zipDir . DIRECTORY_SEPARATOR . $zipHash ) );

        //destination dir
        $newZipDir  = $this->zipDir . DIRECTORY_SEPARATOR . $datePath . DIRECTORY_SEPARATOR . $projectID ;

        //check if doesn't exist
        if ( !is_dir( $newZipDir ) ) {
            //make files' directory structure
            if( ! mkdir( $newZipDir, 0755, true ) ) {
                return false;
            }
        }

        //link original
        $outcome1 = $this->link( $this->getSingleFileInPath( $this->zipDir . DIRECTORY_SEPARATOR . $zipHash ) , $newZipDir . DIRECTORY_SEPARATOR . $fileName );

        if( !$outcome1 ){
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
    public function getOriginalZipPath( $projectDate, $projectID, $zipName ){

        $datePath = date_create( $projectDate )->format('Ymd');
        $zipDir = $this->zipDir . DIRECTORY_SEPARATOR . $datePath . DIRECTORY_SEPARATOR . $projectID . DIRECTORY_SEPARATOR . $zipName;

        return $zipDir;

    }

    public function getHashesFromDir( $dirToScan ){

        //fetch cache links, created by converter, from a directory
        $linkFiles = scandir( $dirToScan );
        $zipFilesHash = array();
        $filesHashInfo    = array();
        //remove dir hardlinks, as uninteresting, as well as regular files; only hash-links
        foreach ( $linkFiles as $k => $linkFile ) {

            if( strpos( $linkFile, self::ORIGINAL_ZIP_PLACEHOLDER ) !== false ){
                $zipFilesHash[ ] = $linkFile;
                unset( $linkFiles[ $k ] );
            } elseif ( strpos( $linkFile, '.' ) !== false or strpos( $linkFile, '|' ) === false ) {
                unset( $linkFiles[ $k ] );
            } else {
                $filesHashInfo[ 'sha' ][] = $linkFiles[ $k ];
                $filesHashInfo[ 'fileName' ][ $linkFiles[ $k ] ] = file(
                        $dirToScan . DIRECTORY_SEPARATOR . $linkFiles[ $k ],
                        FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
                );
            }

        }

        return array(
                'conversionHashes' => $filesHashInfo,
                'zipHashes'        => $zipFilesHash
        );

    }

    public function linkSessionToCache( $hash, $lang, $uid, $realFileName ) {
        //get upload dir
        $dir = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $uid;

        //create a file in it, named after cache position on storage
        return file_put_contents( $dir . DIRECTORY_SEPARATOR . $hash . "|" . $lang, $realFileName . "\n" , FILE_APPEND | LOCK_EX );
    }

    /*
     * Cache Handling Methods --- END
     */

    public function deleteHashFromUploadDir( $uploadDirPath, $linkFile ){
        @list( $shasum, $srcLang ) = explode( "|", $linkFile );

        $iterator = new DirectoryIterator( $uploadDirPath );

        foreach ( $iterator as $fileInfo ) {
            if ( $fileInfo->isDot() || $fileInfo->isDir() ) {
                continue;
            }

            //remove only the wrong languages, the same code|language must be
            // retained because of the file name append
            if ( $fileInfo->getFilename() != $linkFile &&
                    stripos( $fileInfo->getFilename(), $shasum ) !== false ) {

                unlink( $fileInfo->getPathname() );
                Log::doLog( "Deleted Hash " . $fileInfo->getPathname() );
            }
        }

    }

    public function moveFromCacheToFileDir( $dateHashPath, $lang, $idFile, $newFileName = null ) {

        list( $datePath, $hash ) = explode( DIRECTORY_SEPARATOR, $dateHashPath );
        $cacheTree = implode( DIRECTORY_SEPARATOR, $this->_composeCachePath( $hash ) );

        //destination dir
        $fileDir  = $this->filesDir . DIRECTORY_SEPARATOR . $datePath . DIRECTORY_SEPARATOR . $idFile;
        $cacheDir = $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . "|" . $lang . DIRECTORY_SEPARATOR . "package";

        \Log::doLog( $fileDir ); 
        \Log::doLog( $cacheDir ); 

        $res = true;
        //check if doesn't exist
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
        $origDir = $cacheDir . DIRECTORY_SEPARATOR . "orig" ; 
        \Log::doLog( $origDir ); 

        $origFilePath = $this->getSingleFileInPath( $origDir );
        $tmpOrigFileName = $origFilePath;
        if( is_file( $origFilePath ) ){

            /*
             * Force the new filename if it is provided
             */
            if ( !empty( $newFileName ) ){
                $tmpOrigFileName = $newFileName;
            }
            $res &= $this->link( $origFilePath, $fileDir . DIRECTORY_SEPARATOR . "orig" . DIRECTORY_SEPARATOR . FilesStorage::basename_fix( $tmpOrigFileName ) );

        }

        //work
        /*
         * Force the new filename if it is provided
         */
        $d = $cacheDir . DIRECTORY_SEPARATOR . "work" ; 
        \Log::doLog( $d ); 
        $convertedFilePath = $this->getSingleFileInPath( $d );

        \Log::doLog( $convertedFilePath ); 

        $tmpConvertedFilePath = $convertedFilePath;
        if ( !empty( $newFileName ) ){
            if( !DetectProprietaryXliff::isXliffExtension( FilesStorage::pathinfo_fix( $newFileName ) ) ){
                $convertedExtension = FilesStorage::pathinfo_fix( $convertedFilePath, PATHINFO_EXTENSION );
                $tmpConvertedFilePath = $newFileName . "." . $convertedExtension;
            }
        }

        \Log::doLog( $convertedFilePath );  // <--------- TODO: this is empty! 

        $dest = $fileDir . DIRECTORY_SEPARATOR . "xliff" . DIRECTORY_SEPARATOR . FilesStorage::basename_fix( $tmpConvertedFilePath ) ; 

        \Log::doLog( $dest ); 

        $res &= $this->link( $convertedFilePath, $dest );

        if( !$res ){
            throw new UnexpectedValueException( 'Internal Error: Failed to create/copy the file on disk from cache.', -13 );
        }

    }

    public function getSingleFileInPath( $path ) {

        //check if it actually exist
        $filePath = false;
        $files = array();
        try {
            $files = new DirectoryIterator( $path );
        } catch ( Exception $e ) {
            //directory does not exists
            Log::doLog( "Directory $path does not exists. If you are creating a project check the source language." );
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

        $db      = Database::obtain();
        $results = $db->fetch_array( $query );

        foreach ( $results as $k => $result ) {
            //try fetching from files dir
            $filePath = $this->getOriginalFromFileDir( $result[ 'id_file' ], $result[ 'sha1_original_file' ] );

            if ( !$filePath ) {
                //file is on the database; let's copy it to disk to make it compliant to file-on-disk structure
                //this moves both original and xliff
                $this->migrateFileDB2FS( $result );

                //now, try again fetching from disk :)
                $filePath = $this->getOriginalFromFileDir( $result[ 'id_file' ], $result[ 'sha1_original_file' ] );
            }

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

        $query = "select fj.id_file, f.filename, f.id_project, j.source, mime_type, sha1_original_file from files_job fj
			inner join files f on f.id=fj.id_file
			join jobs as j on j.id=fj.id_job
			where fj.id_job = $id_job $where_id_file group by id_file";

        $db      = Database::obtain();
        $results = $db->fetch_array( $query );

        foreach ( $results as $k => $result ) {
            //try fetching from files dir
            $originalPath = $this->getOriginalFromFileDir( $result[ 'id_file' ], $result[ 'sha1_original_file' ] );

            if ( !$originalPath || !file_exists( $originalPath ) ) {
                //file is on the database; let's copy it to disk to make it compliant to file-on-disk structure
                //this moves both original and xliff
                $this->migrateFileDB2FS( $result );

                //now, try again fetching from disk :)
                $originalPath = $this->getOriginalFromFileDir( $result[ 'id_file' ], $result[ 'sha1_original_file' ] );
            }

            $results[ $k ][ 'originalFilePath' ] = $originalPath;

            //note that we trust this to succeed on first try since, at this stage, we already built the file package
            $results[ $k ][ 'xliffFilePath' ] = $this->getXliffFromFileDir( $result[ 'id_file' ], $result[ 'sha1_original_file' ] );
        }

        return $results;
    }

    /**
     * Backwards compatibility method and forward
     *
     * Works by Reference variable
     *
     * @param $fileMetaData [
     *                          "id_file",
     *                          "filename",
     *                          "source",
     *                          "mime_type",
     *                          "sha1_original_file"
     *                      ]
     */
    private function migrateFileDB2FS( &$fileMetaData ) {

        //create temporary storage to place stuff
        $tempDir = "/tmp" . DIRECTORY_SEPARATOR . uniqid( "", true );
        mkdir( $tempDir, 0755 );

        //fetch xliff from the files database
        $xliffContent = $this->getXliffFromDB( $fileMetaData[ 'id_file' ] );

        //try pulling the original content too (if it's empty it means that it was an unconverted xliff)
        $fileContent = $this->getOriginalFromDB( $fileMetaData[ 'id_file' ] );

        if ( !empty( $fileContent ) ) {
            //it's a converted file

            //i'd like to know it's real extension....
            //create temporary file with appropriately modified name
            $result = DetectProprietaryXliff::getInfoByStringData( $xliffContent );
            if( $result['proprietary_short_name'] == 'trados' ){
                $tempXliff = $tempDir . DIRECTORY_SEPARATOR . $fileMetaData[ 'filename' ] . ".sdlxliff";
            } else {
                $tempXliff = $tempDir . DIRECTORY_SEPARATOR . $fileMetaData[ 'filename' ] . ".xlf";
            }

            //create file
            $tempOriginal = $tempDir . DIRECTORY_SEPARATOR . $fileMetaData[ 'filename' ];

            //flush content
            file_put_contents( $tempOriginal, $fileContent );

            //get hash, based on original
            $sha1 = sha1( $fileContent );

            //free memory
            unset( $fileContent );
        } else {
            //if it's a unconverted xliff
            //create temporary file with original name
            $tempXliff = $tempDir . DIRECTORY_SEPARATOR . $fileMetaData[ 'filename' ];

            // set original to empty
            $tempOriginal = false;

            //get hash
            $sha1 = sha1( $xliffContent );
        }

        //flush xliff file content
        file_put_contents( $tempXliff, $xliffContent );

        //free memory
        unset( $xliffContent );

        if( stripos( $fileMetaData[ 'sha1_original_file' ], DIRECTORY_SEPARATOR ) === false ){

            $query        = "select create_date from projects where id = {$fileMetaData[ 'id_project' ]}";
            $db           = Database::obtain();
            $results      = $db->fetch_array( $query );
            $dateHashPath = date_create( $results[ 0 ][ 'create_date' ] )->format( 'Ymd' ) . DIRECTORY_SEPARATOR . $sha1;
            $db->update(
                    'files',
                    array( "sha1_original_file" => $dateHashPath ),
                    'id = ' . $fileMetaData[ 'id_file' ]
            );

            //update Reference
            $fileMetaData[ 'sha1_original_file' ] = $dateHashPath;

        }

        //build a cache package
        $this->makeCachePackage( $sha1, $fileMetaData[ 'source' ], $tempOriginal, $tempXliff );

        //build a file package
        $this->moveFromCacheToFileDir( $fileMetaData[ 'sha1_original_file' ], $fileMetaData[ 'source' ], $fileMetaData[ 'id_file' ] );

        //clean temporary stuff
        Utils::deleteDir( $tempDir );
    }

    public function getOriginalFromDB( $id_file ) {
        $query = "select original_file from files where id= $id_file";

        $db      = Database::obtain();
        $results = $db->fetch_array( $query );

        return gzinflate( $results[ 0 ][ 'original_file' ] );

    }

    public function getXliffFromDB( $id_file ) {
        $query = "select xliff_file from files where id= $id_file";

        $db      = Database::obtain();
        $results = $db->fetch_array( $query );

        return $results[ 0 ][ 'xliff_file' ];
    }

    /**
     * Gets the file path of the temporary uploaded zip, when the project is not
     * yet created. Useful to perform prelimiray validation on the project.
     * This function was created to perform validations on the TKIT zip file
     * format loaded via API.
     *
     * XXX: This function only handles the case in which the zip file is *one* for the
     * project.
     *
     * @param projectStructure the projectStructure of new project.
     */

    public function getTemporaryUploadedZipFile( $uploadToken ) {
        $files  = scandir( INIT::$UPLOAD_REPOSITORY . '/' . $uploadToken );
        $zip_name = null; 
        $zip_file = null; 

        foreach($files as $file) {
            Log::doLog( $file );
            if ( strpos( $file, FilesStorage::ORIGINAL_ZIP_PLACEHOLDER) !== false ) {
                $zip_name = $file ;
            }
        }

        $files = scandir(INIT::$ZIP_REPOSITORY .  '/' . $zip_name);
        foreach ($files as $file) {
            if ( strpos( $file, '.zip') !== false ) {
                $zip_file = $file;
                break;
            }
        }

        if ( $zip_name == null && $zip_file == null ) {
            return FALSE ; 
        } else {
            return INIT::$ZIP_REPOSITORY . '/' . $zip_name . '/' . $zip_file ;
        }
    }

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
     * @param     $path
     * @param int $options
     *
     * @return array|mixed
     */
    public static function pathinfo_fix( $path, $options=15) {
        $rawPath = explode( DIRECTORY_SEPARATOR, $path );

        $basename = array_pop( $rawPath );
        $dirname  = implode( DIRECTORY_SEPARATOR, $rawPath );

        $explodedFileName = explode( ".", $basename );
        $extension        = strtolower( array_pop( $explodedFileName ) );
        $filename         = implode( ".", $explodedFileName );

        $return_array = array();

        $flagMap = array(
                'dirname'   => PATHINFO_DIRNAME,
                'basename'  => PATHINFO_BASENAME,
                'extension' => PATHINFO_EXTENSION,
                'filename'  => PATHINFO_FILENAME
        );

        // foreach flag, add in $return_array the corresponding field,
        // obtained by variable name correspondance
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

    private function link($source, $destination) {
        return EnvWrap::link($source, $destination);
    }
}

