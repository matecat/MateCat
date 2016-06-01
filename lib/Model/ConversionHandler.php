<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 18/06/15
 * Time: 17.32
 */
class ConversionHandler {

    protected $result;

    protected $file_name;
    protected $source_lang;
    protected $target_lang;
    protected $segmentation_rule;

    protected $cache_days = 10;

    protected $intDir;
    protected $errDir;

    protected $cookieDir;

    protected $stopOnFileException = true;

    protected $uploadedFiles;
    public $uploadError = false;


    public function __construct() {
        $this->result = array(
                'code' => 1 //set OK default
        );
    }

    public function doAction() {

        $this->file_name = html_entity_decode( $this->file_name, ENT_QUOTES );
        $file_path       = $this->intDir . DIRECTORY_SEPARATOR . $this->file_name;

        if ( !file_exists( $file_path ) ) {
            $this->result[ 'code' ]     = -6; // No Good, Default
            $this->result[ 'errors' ][] = array(
                    "code"    => -6,
                    "message" => "Error during upload. Please retry.",
                    'debug'   => FilesStorage::basename_fix( $this->file_name )
            );

            return -1;
        }

        //XLIFF Conversion management
        //cyclomatic complexity 9999999 ..... but it works, for now.
        try {

            $fileType = DetectProprietaryXliff::getInfo( $file_path );

            if ( DetectProprietaryXliff::isXliffExtension() || DetectProprietaryXliff::getMemoryFileType() ) {

                if ( !empty(INIT::$FILTERS_ADDRESS) ) {

                    //conversion enforce
                    if ( !INIT::$FORCE_XLIFF_CONVERSION ) {

                        //if file is not proprietary AND Enforce is disabled
                        //we take it as is
                        if ( !$fileType[ 'proprietary' ] || DetectProprietaryXliff::getMemoryFileType() ) {
                            $this->result[ 'code' ] = 1; // OK for client

                            //This file has to be linked to cache!
                            return 0; //ok don't convert a standard sdlxliff
                        }

                    } else {

                        // if conversion enforce is active
                        // we force all xliff files but not files produced by
                        // SDL Studio or by the MateCAT converters, because we
                        // can handle them
                        if ($fileType[ 'proprietary_short_name' ] == 'matecat_converter'
                                || $fileType[ 'proprietary_short_name' ] == 'trados'
                                || DetectProprietaryXliff::getMemoryFileType() ) {
                            $this->result[ 'code' ]     = 1; // OK for client
                            $this->result[ 'errors' ][] = array( "code" => 0, "message" => "OK" );

                            return 0; //ok don't convert a standard sdlxliff
                        }

                    }

                } elseif ( $fileType[ 'proprietary' ] ) {

                    unlink( $file_path );
                    $this->result[ 'code' ]     = -7; // No Good, Default
                    $this->result[ 'errors' ][] = array(
                            "code"    => -7,
                            "message" => 'Matecat Open-Source does not support ' . ucwords( $fileType[ 'proprietary_name' ] ) . '. Use MatecatPro.',
                            'debug'   => FilesStorage::basename_fix( $this->file_name )
                    );

                    return -1;

                } elseif ( !$fileType[ 'proprietary' ] ) {

                    $this->result[ 'code' ]     = 1; // OK for client
                    $this->result[ 'errors' ][] = array( "code" => 0, "message" => "OK" );

                    return 0; //ok don't convert a standard sdlxliff

                }

            }

        } catch ( Exception $e ) { //try catch not used because of exception no more raised
            $this->result[ 'code' ]     = -8; // No Good, Default
            $this->result[ 'errors' ][] = array( "code" => -8, "message" => $e->getMessage() );
            Log::doLog( $e->getMessage() );

            return -1;
        }

        //compute hash to locate the file in the cache
        $sha1 = sha1_file( $file_path );

        //initialize path variable
        $cachedXliffPath = false;

        //get storage object
        $fs = new FilesStorage();

        //don't load from cache when a specified filter version is forced
        if ( INIT::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION !== false ) {
            INIT::$SAVE_SHASUM_FOR_FILES_LOADED = false;
        }

        //if already present in database cache get the converted without convert it again
        if ( INIT::$SAVE_SHASUM_FOR_FILES_LOADED ) {

            //move the file in the right directory from the packages to the file dir
            $cachedXliffPath = $fs->getXliffFromCache( $sha1, $this->source_lang );

            if ( !$cachedXliffPath ) {
                Log::doLog( "Failed to fetch xliff for $sha1 from disk cache (is file there?)" );
            }
        }

        //if invalid or no cached version
        if ( !isset( $cachedXliffPath ) or empty( $cachedXliffPath ) ) {
            //we have to convert it

            $ocrCheck = new \Filters\OCRCheck( $this->source_lang );
            if ( !$ocrCheck->isValid( $file_path ) ) {
                $this->result[ 'code' ]     = -20; // No Good, Default
                $this->result[ 'errors' ][] = array( "code" => -20, "message" =>
                        "Scanned PDF and image conversion for languages not using the latin script is experimental. 
                        Please, check the conversion of the file and download a Preview before starting the translation."
                );
            }

            if ( strpos( $this->target_lang, ',' ) !== false ) {
                $single_language = explode( ',', $this->target_lang );
                $single_language = $single_language[ 0 ];
            } else {
                $single_language = $this->target_lang;
            }

            $convertResult = Filters::sourceToXliff( $file_path, $this->source_lang, $single_language, $this->segmentation_rule);
            Filters::logConversionToXliff($convertResult, $file_path, $this->source_lang, $this->target_lang, $this->segmentation_rule);

            if ( $convertResult[ 'isSuccess' ] == 1 ) {

                //store converted content on a temporary path on disk (and off RAM)
                $cachedXliffPath = tempnam( "/tmp", "MAT_XLF" );
                file_put_contents( $cachedXliffPath, $convertResult[ 'xliffContent' ] );
                unset( $convertResult[ 'xliffContent' ] );

                /*
                   store the converted file in the cache
                   put a reference in the upload dir to the cache dir, so that from the UUID we can reach the converted file in the cache
                   (this is independent by the "save xliff for caching" options, since we always end up storing original and xliff on disk)
                 */
                //save in cache
                $res_insert = $fs->makeCachePackage( $sha1, $this->source_lang, $file_path, $cachedXliffPath );

                if ( !$res_insert ) {
                    //custom error message passed directly to javascript client and displayed as is
                    $convertResult[ 'errorMessage' ] = "Error: File upload failed because you have MateCat running in multiple tabs. Please close all other MateCat tabs in your browser.";
                    $this->result[ 'code' ]          = -103;
                    $this->result[ 'errors' ][]      = array(
                            "code"  => -103, "message" => $convertResult[ 'errorMessage' ],
                            'debug' => FilesStorage::basename_fix( $this->file_name )
                    );

                    unset( $cachedXliffPath );

                    return false;
                }

            } else {

                //custom error message passed directly to javascript client and displayed as is
                $this->result[ 'code' ]     = -100;
                $this->result[ 'errors' ][] = array(
                        "code" => -100, "message" => $convertResult[ 'errorMessage' ], "debug" => FilesStorage::basename_fix( $this->file_name )
                );
            }

        }

        //if everything went well and we've obtained a path toward a valid package (original+xliff), either via cache or conversion
        if ( isset( $cachedXliffPath ) and !empty( $cachedXliffPath ) ) {

            //FILE Found in cache, destroy the already present shasum for other languages ( if user swapped languages )
            $uploadDir = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $this->cookieDir;
            $fs->deleteHashFromUploadDir( $uploadDir, $sha1 . "|" . $this->source_lang );

            //put reference to cache in upload dir to link cache to session
            $fs->linkSessionToCache(
                    $sha1,
                    $this->source_lang,
                    $this->cookieDir,
                    FilesStorage::basename_fix( $file_path )
            );

        }

        return 0;
    }

    public function extractZipFile() {
        $this->file_name = html_entity_decode( $this->file_name, ENT_QUOTES );
        $file_path       = $this->intDir . DIRECTORY_SEPARATOR . $this->file_name;

        //The zip file name is set in $this->file_name

        $za = new ZipArchiveExtended();

        $za->open( $file_path );

        try {
            $za->createTree();

            //get system temporary folder
            $tmpFolder = ini_get( 'upload_tmp_dir' );
            ( empty( $tmpFolder ) ) ? $tmpFolder = "/tmp" : null;
            $tmpFolder .= "/" . uniqid( '' ) . "/";

            mkdir( $tmpFolder, 0777, true );

            $fileErrors = $za->extractFilesInTmp( $tmpFolder );

            $za->close();

            //compose an array that has the same structure of $_FILES
            $filesArray = array();
            foreach ( $za->treeList as $fileName ) {

                $filesArray[ $fileName ] = array(
                        'name'     => $fileName,
                        'tmp_name' => $tmpFolder . $fileName,
                        'error'    => null,
                        'size'     => filesize( $tmpFolder . $fileName )
                );
            }

            /***
             *
             * ERRORE di un file extratto dallo zip ( isset( $fileErrors[ $fileName ] ) ) ? $fileErrors[ $fileName ] :
             *
             **/

            // The $this->cookieDir parameter makes Upload get the upload directory from the cookie.
            // In this way it'll find the unzipped files
            $uploadFile = new Upload( $this->cookieDir );

            $uploadFile->setRaiseException( $this->stopOnFileException );

            try {
                $stdResult = $uploadFile->uploadFiles( $filesArray );

                if ( $this->uploadFailed( $stdResult ) ) {
                    $this->uploadError   = true;
                    $this->uploadedFiles = $stdResult;
                }

            } catch ( Exception $e ) {
                $stdResult                     = array();
                $this->result                  = array(
                        'errors' => array(
                                array( "code" => -1, "message" => $e->getMessage() )
                        )
                );
                $this->api_output[ 'message' ] = $e->getMessage();

                return null;
            }

            return array_map( "Upload::fixFileName", $za->treeList );

        } catch ( Exception $e ) {

            Log::doLog( "ExtendedZipArchive Exception: {$e->getCode()} : {$e->getMessage()}" );
            $this->result[ 'errors' ] [] = array(
                    'code'    => $e->getCode(),
                    'message' => "Zip error: " . $e->getMessage(),
                    'debug'   => $this->file_name
            );

            return null;
        }

        return array();

    }

    /**
     * @param $stdResult
     *
     * @return bool
     */
    public function uploadFailed( $stdResult ) {

        $error = false;

        foreach ( $stdResult as $stdFileResult ) {
            if ( $error == true ) {
                break;
            }

            if ( isset( $stdFileResult->error ) && !empty( $stdFileResult->error ) ) {
                $error = true;
            }
        }

        return $error;

    }

    /**
     * @return mixed
     */
    public function getUploadedFiles() {
        return $this->uploadedFiles;
    }


    /**
     * @return mixed
     */
    public function getResult() {
        return $this->result;
    }

    /**
     * @return mixed
     */
    public function getFileName() {
        return $this->file_name;
    }

    /**
     * @param mixed $file_name
     */
    public function setFileName( $file_name ) {
        $this->file_name = $file_name;
    }

    /**
     * @return mixed
     */
    public function getSourceLang() {
        return $this->source_lang;
    }

    /**
     * @param mixed $source_lang
     */
    public function setSourceLang( $source_lang ) {
        $this->source_lang = $source_lang;
    }

    /**
     * @return mixed
     */
    public function getTargetLang() {
        return $this->target_lang;
    }

    /**
     * @param mixed $target_lang
     */
    public function setTargetLang( $target_lang ) {
        $this->target_lang = $target_lang;
    }

    /**
     * @return mixed
     */
    public function getSegmentationRule() {
        return $this->segmentation_rule;
    }

    /**
     * @param mixed $segmentation_rule
     */
    public function setSegmentationRule( $segmentation_rule ) {
        $this->segmentation_rule = $segmentation_rule;
    }

    /**
     * @return int
     */
    public function getCacheDays() {
        return $this->cache_days;
    }

    /**
     * @param int $cache_days
     */
    public function setCacheDays( $cache_days ) {
        $this->cache_days = $cache_days;
    }

    /**
     * @return mixed
     */
    public function getIntDir() {
        return $this->intDir;
    }

    /**
     * @param mixed $intDir
     */
    public function setIntDir( $intDir ) {
        $this->intDir = $intDir;
    }

    /**
     * @return mixed
     */
    public function getErrDir() {
        return $this->errDir;
    }

    /**
     * @param mixed $errDir
     */
    public function setErrDir( $errDir ) {
        $this->errDir = $errDir;
    }

    /**
     * @return mixed
     */
    public function getCookieDir() {
        return $this->cookieDir;
    }

    /**
     * @param mixed $cookieDir
     */
    public function setCookieDir( $cookieDir ) {
        $this->cookieDir = $cookieDir;
    }

    /**
     * @param boolean $stopOnFileException
     */
    public function setStopOnFileException( $stopOnFileException ) {
        $this->stopOnFileException = $stopOnFileException;
    }


}
