<?php

use API\Commons\Exceptions\AuthenticationError;
use Constants\ConversionHandlerStatus;
use Conversion\ConvertedFileModel;
use Exceptions\NotFoundException;
use Exceptions\ValidationError;
use FilesStorage\AbstractFilesStorage;
use FilesStorage\Exceptions\FileSystemException;
use FilesStorage\FilesStorageFactory;
use Filters\DTO\IDto;
use Filters\FiltersConfigTemplateStruct;
use Filters\OCRCheck;
use Matecat\XliffParser\XliffUtils\XliffProprietaryDetect;
use TaskRunner\Exceptions\EndQueueException;
use TaskRunner\Exceptions\ReQueueException;

class ConversionHandler {

    /**
     * @var ConvertedFileModel
     */
    protected ConvertedFileModel $result;

    protected string                       $file_name;
    protected string                       $source_lang;
    protected string                       $target_lang;
    protected ?string                      $segmentation_rule             = null;
    protected string                       $intDir;
    protected string                       $errDir;
    protected string                       $cookieDir;
    protected bool                         $stopOnFileException           = true;
    protected ?object                      $uploadedFiles                 = null;
    public bool                            $uploadError                   = false;
    protected bool                         $_userIsLogged;
    protected ?FiltersConfigTemplateStruct $filters_extraction_parameters = null;

    /**
     * @var FeatureSet
     */
    public         $features;
    protected bool $isReconversion = false;

    /**
     * ConversionHandler constructor.
     */
    public function __construct() {
        $this->result = new ConvertedFileModel( ConversionHandlerStatus::OK );
    }

    public function fileMustBeConverted() {
        return XliffProprietaryDetect::fileMustBeConverted( $this->getLocalFilePath(), true, INIT::$FILTERS_ADDRESS );
    }

    public function getLocalFilePath(): string {
        $this->file_name = html_entity_decode( $this->file_name, ENT_QUOTES );

        return $this->intDir . DIRECTORY_SEPARATOR . $this->file_name;
    }

    /**
     * @throws NotFoundException
     * @throws EndQueueException
     * @throws ReQueueException
     * @throws ValidationError
     * @throws AuthenticationError
     * @throws Exception
     */
    public function processConversion(): ?array {

        $fs        = FilesStorageFactory::create();
        $file_path = $this->getLocalFilePath();

        if ( !file_exists( $file_path ) ) {
            $this->result->changeCode( ConversionHandlerStatus::UPLOAD_ERROR );
            $this->result->addError( "Error during upload. Please retry.", AbstractFilesStorage::basename_fix( $this->file_name ) );

            return null;
        }

        //XLIFF Conversion management
        $fileMustBeConverted = $this->fileMustBeConverted();

        if ( $fileMustBeConverted === false ) {
            return null;
        } else {
            if ( $fileMustBeConverted === true ) {
                //Continue with conversion
            } else {
                /**
                 * Application misconfiguration.
                 * upload should not be happened, but if we are here, raise an error.
                 * @see upload.class.php
                 */
                unlink( $file_path );

                $this->result->changeCode( ConversionHandlerStatus::MISCONFIGURATION );
                $this->result->addError( 'Matecat Open-Source does not support ' . ucwords( XliffProprietaryDetect::getInfo( $file_path )[ 'proprietary_name' ] ) . '. Use MatecatPro.',
                        AbstractFilesStorage::basename_fix( $this->file_name ) );

                return null;
            }
        }

        //compute hash to locate the file in the cache, add the segmentation rule and extraction parameters
        $extraction_parameters = $this->getRightExtractionParameter( $file_path );

        $hash_name_for_disk =
                sha1_file( $file_path )
                . "_" .
                sha1( ( $this->segmentation_rule ?? '' ) . ( $extraction_parameters ? json_encode( $extraction_parameters ) : '' ) )
                . "|" .
                $this->source_lang;

        $short_hash = sha1( $hash_name_for_disk );

        //initialize path variable
        $cachedXliffPath = null;

        //don't load from cache when a specified filter version is forced
        if ( INIT::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION !== false ) {
            INIT::$SAVE_SHASUM_FOR_FILES_LOADED = false;
        }

        //if already present in database cache get the converted without convert it again
        if ( INIT::$SAVE_SHASUM_FOR_FILES_LOADED ) {

            //move the file in the right directory from the packages to the file dir
            $cachedXliffPath = $fs->getXliffFromCache( $short_hash, $this->source_lang );

            if ( !$cachedXliffPath ) {
                Log::doJsonLog( "Failed to fetch xliff for $short_hash from disk cache (is file there?)" );
            }
        }

        //if invalid or no cached version
        if ( empty( $cachedXliffPath ) ) {
            //we have to convert it

            $ocrCheck = new OCRCheck( $this->source_lang );
            if ( $ocrCheck->thereIsError( $file_path ) ) {
                $this->result->changeCode( ConversionHandlerStatus::OCR_ERROR );
                $this->result->addError( "File is not valid. OCR for RTL languages is not supported." );

                return null; //break project creation
            }
            if ( $ocrCheck->thereIsWarning( $file_path ) ) {
                $this->result->changeCode( ConversionHandlerStatus::OCR_WARNING );
                $this->result->addWarning( "File uploaded successfully. Before translating, download the Preview to check the conversion. OCR support for non-latin scripts is experimental." );
            }

            if ( strpos( $this->target_lang, ',' ) !== false ) {
                $single_language = explode( ',', $this->target_lang );
                $single_language = $single_language[ 0 ];
            } else {
                $single_language = $this->target_lang;
            }

            $convertResult = Filters::sourceToXliff(
                    $file_path,
                    $this->source_lang,
                    $single_language,
                    $this->segmentation_rule,
                    $extraction_parameters
            );
            Filters::logConversionToXliff( $convertResult, $file_path, $this->source_lang, $this->target_lang, $this->segmentation_rule, $extraction_parameters );

            if ( $convertResult[ 'successful' ] == 1 ) {

                //store converted content on a temporary path on disk (and off RAM)
                $cachedXliffPath = tempnam( "/tmp", "MAT_XLF" );
                file_put_contents( $cachedXliffPath, $convertResult[ 'xliff' ] );
                unset( $convertResult[ 'xliff' ] );

                /*
                   store the converted file in the cache
                   put a reference in the upload dir to the cache dir, so that from the UUID we can reach the converted file in the cache
                   (this is independent by the "save xliff for caching" options, since we always end up storing original and xliff on disk)
                 */
                //save in cache
                try {
                    $res_insert = $fs->makeCachePackage( $short_hash, $this->source_lang, $file_path, $cachedXliffPath );

                    if ( !$res_insert ) {
                        //custom error message passed directly to JavaScript client and displayed as is
                        $convertResult[ 'errorMessage' ] = "Error: File upload failed because you have Matecat running in multiple tabs. Please close all other Matecat tabs in your browser.";

                        $this->result->changeCode( ConversionHandlerStatus::FILESYSTEM_ERROR );
                        $this->result->addError( $convertResult[ 'errorMessage' ], AbstractFilesStorage::basename_fix( $this->file_name ) );

                        unset( $cachedXliffPath );

                        return null;
                    }

                } catch ( FileSystemException $e ) {

                    Log::doJsonLog( "FileSystem Exception: Message: " . $e->getMessage() );

                    $this->result->changeCode( ConversionHandlerStatus::FILESYSTEM_ERROR );
                    $this->result->addError( $e->getMessage() );

                    return null;

                } catch ( Exception $e ) {

                    Log::doJsonLog( "S3 Exception: Message: " . $e->getMessage() );

                    $this->result->changeCode( ConversionHandlerStatus::S3_ERROR );
                    $this->result->addError( 'Sorry, file name too long. Try shortening it and try again.' );

                    return null;
                }

            } else {

                $this->result->changeCode( ConversionHandlerStatus::GENERIC_ERROR );
                $this->result->addError( $this->formatConversionFailureMessage( $convertResult[ 'errorMessage' ] ), AbstractFilesStorage::basename_fix( $this->file_name ) );

                return null;
            }

        }

        //if everything went well, and we've got a path toward a valid package (original+xliff), either via cache or conversion
        if ( !empty( $cachedXliffPath ) ) {

            //FILE Found in cache, destroy the already present shasum for other languages ( if user swapped languages )
            $uploadDir = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $this->cookieDir;
            $fs->deleteHashFromUploadDir( $uploadDir, $hash_name_for_disk );

            if ( is_file( $file_path ) ) {
                //put reference to cache in upload dir to link cache to session
                $fs->linkSessionToCacheForOriginalFiles(
                        $hash_name_for_disk,
                        $this->cookieDir,
                        AbstractFilesStorage::basename_fix( $file_path )
                );
            } else {
                Log::doJsonLog( "File not found in path. linkSessionToCacheForOriginalFiles Skipped." );
            }

        }

        return [ 'cacheHash' => $short_hash, 'diskHash' => $hash_name_for_disk ];
    }

    /**
     * @param string $filePath
     *
     * @return IDto|null
     */
    private function getRightExtractionParameter( string $filePath ): ?IDto {

        $extension = AbstractFilesStorage::pathinfo_fix( $filePath, PATHINFO_EXTENSION );

        $params = null;

        if ( $this->filters_extraction_parameters !== null ) {

            // send extraction params based on the file extension
            switch ( $extension ) {
                case "json":
                    if ( isset( $this->filters_extraction_parameters->json ) ) {
                        $params = $this->filters_extraction_parameters->json;
                    }
                    break;
                case "xml":
                    if ( isset( $this->filters_extraction_parameters->xml ) ) {
                        $params = $this->filters_extraction_parameters->xml;
                    }
                    break;
                case "yml":
                case "yaml":
                    if ( isset( $this->filters_extraction_parameters->yaml ) ) {
                        $params = $this->filters_extraction_parameters->yaml;
                    }
                    break;
                case "doc":
                case "docx":
                    if ( isset( $this->filters_extraction_parameters->ms_word ) ) {
                        $params = $this->filters_extraction_parameters->ms_word;
                    }
                    break;
                case "xls":
                case "xlsx":
                    if ( isset( $this->filters_extraction_parameters->ms_excel ) ) {
                        $params = $this->filters_extraction_parameters->ms_excel;
                    }
                    break;
                case "ppt":
                case "pptx":
                    if ( isset( $this->filters_extraction_parameters->ms_powerpoint ) ) {
                        $params = $this->filters_extraction_parameters->ms_powerpoint;
                    }
                    break;
                case "dita":
                case "ditamap":
                    if ( isset( $this->filters_extraction_parameters->dita ) ) {
                        $params = $this->filters_extraction_parameters->dita;
                    }
                    break;
            }
        }

        return $params;

    }

    /**
     * @param string $message
     *
     * @return string
     */
    private function formatConversionFailureMessage( $message ) {
        // WinConverter error
        if ( strpos( $message, 'WinConverter' ) !== false ) {

            // file conversion error
            if ( strpos( $message, 'WinConverter error 5' ) !== false ) {
                return 'Scanned file conversion issue, please convert it to editable format (e.g. docx) and retry upload';
            }

            return 'File conversion issue, please contact us at support@matecat.com';
        }

        return $message;
    }

    /**
     * @throws Exception
     */
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
            $tmpFolder = $tmpFolder ?: "/tmp";

            $tmpFolder .= "/" . uniqid() . "/";

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

                $this->result->changeCode( ConversionHandlerStatus::INVALID_FILE );
                $this->result->addError( $e->getMessage() );

                return [];
            }

            return array_map( function ( $fileName ) use ( $uploadFile ) {
                return $uploadFile->fixFileName( $fileName, false );
            }, $za->treeList );

        } catch ( Exception $e ) {

            Log::doJsonLog( "ExtendedZipArchive Exception: {$e->getCode()} : {$e->getMessage()}" );

            $this->result->changeCode( $e->getCode() );
            $this->result->addError( "Zip error: " . $e->getMessage(), $this->file_name );

            return [];
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
            if ( $error ) {
                break;
            }

            if ( !empty( $stdFileResult->error ) ) {
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
    public function getResult(): ConvertedFileModel {
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

    /**
     * @param mixed $filters_extraction_parameters
     */
    public function setFiltersExtractionParameters( ?FiltersConfigTemplateStruct $filters_extraction_parameters = null ) {
        $this->filters_extraction_parameters = $filters_extraction_parameters;
    }

    public function setReconversion( bool $isReconversion ) {
        $this->isReconversion = $isReconversion;
    }

}
