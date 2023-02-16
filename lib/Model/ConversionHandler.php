<?php

use Constants\ConversionHandlerStatus;
use Conversion\ConvertedFileModel;
use FilesStorage\AbstractFilesStorage;
use FilesStorage\FilesStorageFactory;
use FilesStorage\Exceptions\FileSystemException;
use Matecat\XliffParser\XliffUtils\XliffProprietaryDetect;

class ConversionHandler {

    /**
     * @var ConvertedFileModel
     */
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
    public    $uploadError = false;
    protected $_userIsLogged;

    /**
     * @var FeatureSet
     */
    public $features;

    /**
     * ConversionHandler constructor.
     */
    public function __construct() {
        $this->result = new ConvertedFileModel(ConversionHandlerStatus::OK);
    }

    public function doAction() {

        $fs              = FilesStorageFactory::create();
        $this->file_name = html_entity_decode( $this->file_name, ENT_QUOTES );
        $file_path       = $this->intDir . DIRECTORY_SEPARATOR . $this->file_name;

        if ( !file_exists( $file_path ) ) {
            $this->result->changeCode(ConversionHandlerStatus::UPLOAD_ERROR);
            $this->result->addError("Error during upload. Please retry.", AbstractFilesStorage::basename_fix( $this->file_name ));

            return -1;
        }

        $forceXliff = $this->features->filter( 'forceXLIFFConversion', INIT::$FORCE_XLIFF_CONVERSION, $this->_userIsLogged, $file_path );

        //XLIFF Conversion management
        $fileMustBeConverted = XliffProprietaryDetect::fileMustBeConverted( $file_path, $forceXliff, INIT::$FILTERS_ADDRESS );

        switch ( $fileMustBeConverted ) {

            case true:
                //Continue with conversion
                break;
            case false:
                return 0;
                break;
            case -1:
            default:
                /**
                 * Application misconfiguration.
                 * upload should not be happened, but if we are here, raise an error.
                 * @see upload.class.php
                 */
                unlink( $file_path );

                $this->result->changeCode(ConversionHandlerStatus::MISCONFIGURATION);
                $this->result->addError('Matecat Open-Source does not support ' . ucwords( XliffProprietaryDetect::getInfo( $file_path )[ 'proprietary_name' ] ) . '. Use MatecatPro.',
                        AbstractFilesStorage::basename_fix( $this->file_name ));

                return -1;
                break;

        }

        //compute hash to locate the file in the cache
        $sha1 = sha1_file( $file_path );

        //initialize path variable
        $cachedXliffPath = false;

        //don't load from cache when a specified filter version is forced
        if ( INIT::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION !== false ) {
            INIT::$SAVE_SHASUM_FOR_FILES_LOADED = false;
        }

        //if already present in database cache get the converted without convert it again
        if ( INIT::$SAVE_SHASUM_FOR_FILES_LOADED ) {

            //move the file in the right directory from the packages to the file dir
            $cachedXliffPath = $fs->getXliffFromCache( $sha1, $this->source_lang );

            if ( !$cachedXliffPath ) {
                Log::doJsonLog( "Failed to fetch xliff for $sha1 from disk cache (is file there?)" );
            }
        }

        //if invalid or no cached version
        if ( !isset( $cachedXliffPath ) or empty( $cachedXliffPath ) ) {
            //we have to convert it

            $ocrCheck = new \Filters\OCRCheck( $this->source_lang );
            if ( $ocrCheck->thereIsError( $file_path ) ) {
                $this->result->changeCode(ConversionHandlerStatus::OCR_ERROR);
                $this->result->addError("File is not valid. OCR for RTL languages is not supported.");

                return false; //break project creation
            }
            if ( $ocrCheck->thereIsWarning( $file_path ) ) {
                $this->result->changeCode(ConversionHandlerStatus::OCR_WARNING);
                $this->result->addError("File uploaded successfully. Before translating, download the Preview to check the conversion. OCR support for non-latin scripts is experimental.");
            }

            if ( strpos( $this->target_lang, ',' ) !== false ) {
                $single_language = explode( ',', $this->target_lang );
                $single_language = $single_language[ 0 ];
            } else {
                $single_language = $this->target_lang;
            }

            $convertResult = Filters::sourceToXliff( $file_path, $this->source_lang, $single_language, $this->segmentation_rule );
            Filters::logConversionToXliff( $convertResult, $file_path, $this->source_lang, $this->target_lang, $this->segmentation_rule );

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
                try {
                    $res_insert = $fs->makeCachePackage( $sha1, $this->source_lang, $file_path, $cachedXliffPath );

                    if ( !$res_insert ) {
                        //custom error message passed directly to javascript client and displayed as is
                        $convertResult[ 'errorMessage' ] = "Error: File upload failed because you have MateCat running in multiple tabs. Please close all other MateCat tabs in your browser.";

                        $this->result->changeCode(ConversionHandlerStatus::FILESYSTEM_ERROR);
                        $this->result->addError($convertResult[ 'errorMessage' ], AbstractFilesStorage::basename_fix( $this->file_name ));

                        unset( $cachedXliffPath );

                        return false;
                    }

                } catch (FileSystemException $e){

                    \Log::doJsonLog("FileSystem Exception: Message: " . $e->getMessage());

                    $this->result->changeCode(ConversionHandlerStatus::FILESYSTEM_ERROR);
                    $this->result->addError($e->getMessage());

                    return false;

                } catch (\Exception $e){

                    \Log::doJsonLog("S3 Exception: Message: " . $e->getMessage());

                    $this->result->changeCode(ConversionHandlerStatus::S3_ERROR);
                    $this->result->addError('Sorry, file name too long. Try shortening it and try again.');

                    return false;
                }

            } else {

                $this->result->changeCode(ConversionHandlerStatus::GENERIC_ERROR);
                $this->result->addError($convertResult[ 'errorMessage' ], AbstractFilesStorage::basename_fix( $this->file_name ));
            }

        }

        //if everything went well and we've obtained a path toward a valid package (original+xliff), either via cache or conversion
        if ( isset( $cachedXliffPath ) and !empty( $cachedXliffPath ) ) {

            //FILE Found in cache, destroy the already present shasum for other languages ( if user swapped languages )
            $uploadDir = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $this->cookieDir;
            $fs->deleteHashFromUploadDir( $uploadDir, $sha1 . "|" . $this->source_lang );

            if( is_file( $file_path ) ){
                //put reference to cache in upload dir to link cache to session
                $fs->linkSessionToCacheForOriginalFiles(
                        $sha1,
                        $this->source_lang,
                        $this->cookieDir,
                        AbstractFilesStorage::basename_fix( $file_path )
                );
            } else {
                Log::doJsonLog( "File not found in path. linkSessionToCacheForOriginalFiles Skipped." );
            }

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

            $filesArray = $za->extractFilesInTmp( $tmpFolder );

            $za->close();

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

                $this->result->changeCode(ConversionHandlerStatus::INVALID_FILE);
                $this->result->addError($e->getMessage());

                // ???
                $this->api_output[ 'message' ] = $e->getMessage();

                return null;
            }

            return array_map( function ( $fileName ) use ( $uploadFile ) {
                return $uploadFile->fixFileName( $fileName, false );
            }, $za->treeList );

        } catch ( Exception $e ) {

            Log::doJsonLog( "ExtendedZipArchive Exception: {$e->getCode()} : {$e->getMessage()}" );

            $this->result->changeCode($e->getCode());
            $this->result->addError("Zip error: " . $e->getMessage(), $this->file_name);

            return null;
        }

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
     * @return ConvertedFileModel
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

    /**
     * @param FeatureSet $features
     *
     * @return $this
     */
    public function setFeatures( FeatureSet $features ) {
        $this->features = $features;

        return $this;
    }

    /**
     * @param mixed $_userIsLogged
     *
     * @return $this
     */
    public function setUserIsLogged( $_userIsLogged ) {
        $this->_userIsLogged = $_userIsLogged;

        return $this;
    }

}
