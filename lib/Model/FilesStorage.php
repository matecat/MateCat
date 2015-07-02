<?

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

        //ensure old stuff is overwritten
        if ( is_dir( $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . "|" . $lang ) ) {
            Utils::deleteDir( $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . "|" . $lang );
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
            $fileType = DetectProprietaryXliff::getInfo( $xliffPath );
            if ( !$fileType[ 'proprietary' ] ) {
                $force_extension = '.sdlxliff';
            }

            //use original xliff
            $xliffDestination = $cacheDir . DIRECTORY_SEPARATOR . "work" . DIRECTORY_SEPARATOR . basename( $xliffPath ) . @$force_extension;
        } else {
            //move original
            $outcome1 = copy( $originalPath, $cacheDir . DIRECTORY_SEPARATOR . "orig" . DIRECTORY_SEPARATOR . basename( $originalPath ) );

            if( !$outcome1 ){
                //Original directory deleted!!!
                //CLEAR ALL CACHE
                Utils::deleteDir( $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . "|" . $lang );
                return $outcome1;
            }

            //check if manifest from a LongHorn conversion exists
            $manifestFile = $cacheDir . DIRECTORY_SEPARATOR . "manifest.rkm";
            if ( file_exists( $manifestFile ) ) {
                Log::doLog( "Alternative Conversion detected" );
                $file_extension = '.xlf';
            } else{
                Log::doLog( "Normal Conversion detected" );
                $file_extension = '.sdlxliff';
            }

            //set naming for converted xliff
            $xliffDestination = $cacheDir . DIRECTORY_SEPARATOR . "work" . DIRECTORY_SEPARATOR . basename( $originalPath ) . $file_extension;
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

        $thisZipDir = $this->zipDir . DIRECTORY_SEPARATOR . $hash . "__##originalZip##";

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
        $outcome1 = copy( $zipPath, $thisZipDir . DIRECTORY_SEPARATOR . basename( $zipPath ) );

        if( !$outcome1 ){
            //Original directory deleted!!!
            //CLEAR ALL CACHE
            Utils::deleteDir( $this->zipDir . DIRECTORY_SEPARATOR . $hash . "__##originalZip##" );
            return $outcome1;
        }

        unlink( $zipPath );

        //link this zip to the upload directory by creating a file name as the ash of the zip file
        touch( dirname( $zipPath ) . DIRECTORY_SEPARATOR . $hash . "__##originalZip##" );

        return true;

    }

    public function linkZipToProject( $create_date, $zipHash, $projectID ){

        $datePath = date_create( $create_date )->format( 'Ymd' );

        $fileName = basename( $this->getSingleFileInPath( $this->zipDir . DIRECTORY_SEPARATOR . $zipHash ) );

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
        $outcome1 = link( $this->getSingleFileInPath( $this->zipDir . DIRECTORY_SEPARATOR . $zipHash ) , $newZipDir . DIRECTORY_SEPARATOR . $fileName );

        if( !$outcome1 ){
            //Failed to copy the original file zip
            return $outcome1;
        }

        Utils::deleteDir( $this->zipDir . DIRECTORY_SEPARATOR . $zipHash );

        return true;

    }

    public function getOriginalZipPath( $projectDate, $projectID, $zipName ){

        $datePath = date_create( $projectDate )->format('Ymd');
        $zipDir = $this->zipDir . DIRECTORY_SEPARATOR . $datePath . DIRECTORY_SEPARATOR . $projectID . DIRECTORY_SEPARATOR . $zipName;

        return $zipDir;

    }

    public function getHashesFromDir( $dirToScan ){

        //fetch cache links, created by converter, from a directory
        $linkFiles = scandir( $dirToScan );
        $zipFilesHash = array();
        //remove dir hardlinks, as uninteresting, as well as regular files; only hash-links
        foreach ( $linkFiles as $k => $linkFile ) {

            if( strpos( $linkFile, "__##originalZip##" ) !== false ){
                $zipFilesHash[ ] = $linkFile;
                unset( $linkFiles[ $k ] );
            } elseif ( strpos( $linkFile, '.' ) !== false or strpos( $linkFile, '|' ) === false ) {
                unset( $linkFiles[ $k ] );
            }

        }

        return array(
                'conversionHashes' => $linkFiles,
                'zipHashes'        => $zipFilesHash
        );

    }

    public function linkSessionToCache( $hash, $lang, $uid ) {
        //get upload dir
        $dir = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $uid;

        //create a file in it, named after cache position on storage
        return touch( $dir . DIRECTORY_SEPARATOR . $hash . "|" . $lang );
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
            if ( stripos( $fileInfo->getFilename(), $shasum ) !== false ) {
                unlink( $fileInfo->getPathname() );
                Log::doLog( "Deleted Hash " . $fileInfo->getPathname() );
            }
        }

    }

    public function moveFromCacheToFileDir( $dateHashPath, $lang, $idFile ) {

        list( $datePath, $hash ) = explode( DIRECTORY_SEPARATOR, $dateHashPath );
        $cacheTree = implode( DIRECTORY_SEPARATOR, $this->_composeCachePath( $hash ) );

        //destination dir
        $fileDir  = $this->filesDir . DIRECTORY_SEPARATOR . $datePath . DIRECTORY_SEPARATOR . $idFile;
        $cacheDir = $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . "|" . $lang . DIRECTORY_SEPARATOR . "package";

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
        $filePath = $this->getSingleFileInPath( $cacheDir . DIRECTORY_SEPARATOR . "orig" );
        if( is_file( $filePath ) ){
            $res &= link( $filePath, $fileDir . DIRECTORY_SEPARATOR . "orig" . DIRECTORY_SEPARATOR . basename( $filePath ) );
        }

        //work
        $filePath = $this->getSingleFileInPath( $cacheDir . DIRECTORY_SEPARATOR . "work" );
        $res &= link( $filePath, $fileDir . DIRECTORY_SEPARATOR . "xliff" . DIRECTORY_SEPARATOR . basename( $filePath ) );

        //check if manifest from a LongHorn conversion exists
        $manifestFile = $cacheDir . DIRECTORY_SEPARATOR . "manifest.rkm";
        if ( file_exists( $manifestFile ) ) {
            $res &= link( $manifestFile, $fileDir . DIRECTORY_SEPARATOR . "package" . DIRECTORY_SEPARATOR . basename( $manifestFile ) );
            $res &= link( $filePath, $fileDir . DIRECTORY_SEPARATOR . "package" . DIRECTORY_SEPARATOR . "orig" . DIRECTORY_SEPARATOR . basename( $filePath ) );
            $res &= link( $filePath, $fileDir . DIRECTORY_SEPARATOR . "package" . DIRECTORY_SEPARATOR . "work" . DIRECTORY_SEPARATOR . basename( $filePath ) );
        }

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
            Log::doLog( "Directory $path does not exists." );
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
			where fj.id_job = $id_job $where_id_file";

        $db      = Database::obtain();
        $results = $db->fetch_array( $query );

        foreach ( $results as $k => $result ) {
            //try fetching from files dir
            $originalPath = $this->getOriginalFromFileDir( $result[ 'id_file' ], $result[ 'sha1_original_file' ] );

            if ( !$originalPath ) {
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
}

