<?php

namespace Model\Conversion;

use Controller\API\Commons\Exceptions\AuthenticationError;
use Exception;
use Matecat\XliffParser\XliffUtils\XliffProprietaryDetect;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Model\FeaturesBase\FeatureSet;
use Model\FilesStorage\AbstractFilesStorage;
use Model\FilesStorage\Exceptions\FileSystemException;
use Model\FilesStorage\FilesStorageFactory;
use Model\Filters\DTO\IDto;
use Model\Filters\FiltersConfigTemplateStruct;
use Utils\Constants\ConversionHandlerStatus;
use Utils\Logger\Log;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;

class ConversionHandler {

    /**
     * @var ConvertedFileModel
     */
    protected ConvertedFileModel $result;

    protected string                       $file_name;
    protected string                       $source_lang;
    protected string                       $target_lang;
    protected ?string                      $segmentation_rule             = null;
    protected string                       $uploadDir;
    protected string                       $errDir;
    protected string                       $uploadTokenValue;
    protected bool                         $stopOnFileException           = true;
    protected array                        $zipExtractionErrorFiles       = [];
    public bool                            $zipExtractionErrorFlag        = false;
    protected ?FiltersConfigTemplateStruct $filters_extraction_parameters = null;

    /**
     * @var FeatureSet
     */
    public FeatureSet $features;

    /**
     * ConversionHandler constructor.
     */
    public function __construct() {
        $this->result = new ConvertedFileModel();
    }

    public function fileMustBeConverted() {
        return XliffProprietaryDetect::fileMustBeConverted( $this->getLocalFilePath(), true, AppConfig::$FILTERS_ADDRESS );
    }

    public function getLocalFilePath(): string {
        $this->file_name = html_entity_decode( $this->file_name, ENT_QUOTES );

        return $this->uploadDir . DIRECTORY_SEPARATOR . $this->file_name;
    }

    /**
     * @throws NotFoundException
     * @throws EndQueueException
     * @throws ReQueueException
     * @throws ValidationError
     * @throws AuthenticationError
     * @throws Exception
     */
    public function processConversion(): void {

        $fs        = FilesStorageFactory::create();
        $file_path = $this->getLocalFilePath();

        $isZipContent = !empty( ZipArchiveHandler::zipPathInfo( $file_path ) );
        $this->result->setFileName( ZipArchiveHandler::getFileName( AbstractFilesStorage::basename_fix( $this->file_name ) ), $isZipContent );

        if ( !file_exists( $file_path ) ) {
            $this->result->setErrorCode( ConversionHandlerStatus::UPLOAD_ERROR );
            $this->result->setErrorMessage( "Error during upload. Please retry." );

            return;
        }

        // XLIFF Conversion management
        $fileMustBeConverted = $this->fileMustBeConverted();

        if ( $fileMustBeConverted === false ) {
            $this->result->setSize( filesize( $file_path ) );

            return;
        } else {
            if ( $fileMustBeConverted === true ) {
                //Continue with conversion
            } else {
                /**
                 * Application misconfiguration.
                 * Upload should not be happened, but if we are here, raise an error.
                 * @see upload.class.php
                 */
                unlink( $file_path );

                $this->result->setErrorCode( ConversionHandlerStatus::MISCONFIGURATION );
                $this->result->setErrorMessage( 'Matecat Open-Source does not support ' . ucwords( XliffProprietaryDetect::getInfo( $file_path )[ 'proprietary_name' ] ) . '. Use MatecatPro.' );

                return;
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

        // Convert the file
        $ocrCheck = new OCRCheck( $this->source_lang );
        if ( $ocrCheck->thereIsError( $file_path ) ) {
            $this->result->setErrorCode( ConversionHandlerStatus::OCR_ERROR );
            $this->result->setErrorMessage( "File is not valid. OCR for RTL languages is not supported." );

            return; //break project creation
        }
        if ( $ocrCheck->thereIsWarning( $file_path ) ) {
            $this->result->setErrorCode( ConversionHandlerStatus::OCR_WARNING );
            $this->result->setErrorMessage( "File uploaded successfully. Before translating, download the Preview to check the conversion. OCR support for non-latin scripts is experimental." );
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

                    $this->result->setErrorCode( ConversionHandlerStatus::FILESYSTEM_ERROR );
                    $this->result->setErrorMessage( $convertResult[ 'errorMessage' ] );

                    unset( $cachedXliffPath );

                    return;
                }

            } catch ( FileSystemException $e ) {

                Log::doJsonLog( "FileSystem Exception: Message: " . $e->getMessage() );

                $this->result->setErrorCode( ConversionHandlerStatus::FILESYSTEM_ERROR );
                $this->result->setErrorMessage( $e->getMessage() );

                return;

            } catch ( Exception $e ) {

                Log::doJsonLog( "S3 Exception: Message: " . $e->getMessage() );

                $this->result->setErrorCode( ConversionHandlerStatus::S3_ERROR );
                $this->result->setErrorMessage( 'Sorry, file name too long. Try shortening it and try again.' );

                return;
            }

        } else {

            $this->result->setErrorCode( ConversionHandlerStatus::GENERIC_ERROR );
            $this->result->setErrorMessage( $this->formatConversionFailureMessage( $convertResult[ 'errorMessage' ] ) );

            return;
        }

        // If everything went well, and we've got a path toward a valid package (original+xliff), either via cache or conversion
        if ( !empty( $cachedXliffPath ) ) {

            //FILE Found in cache, destroy the already present shasum for other languages ( if user swapped languages )
            $uploadDir = $this->uploadDir;

            $fs->deleteHashFromUploadDir( $uploadDir, $hash_name_for_disk );

            if ( is_file( $file_path ) ) {
                //put reference to cache in upload dir to link cache to session
                $fs->linkSessionToCacheForOriginalFiles(
                        $hash_name_for_disk,
                        $this->uploadTokenValue,
                        AbstractFilesStorage::basename_fix( $file_path )
                );
            } else {
                Log::doJsonLog( "File not found in path. linkSessionToCacheForOriginalFiles Skipped." );
            }

        }

        $this->result->addConversionHashes(
                new InternalHashPaths( [
                        'cacheHash' => $short_hash,
                        'diskHash'  => $hash_name_for_disk
                ] )
        );

        $this->result->setSize( filesize( $file_path ) );
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
    private function formatConversionFailureMessage( string $message ): string {
        $errorPatterns = [
                'WinConverter error 5' => 'Scanned file conversion issue, please convert it to editable format (e.g. docx) and retry upload',
                'WinConverter'         => 'File conversion issue, please contact us at support@matecat.com',
                'java.lang.'           => 'File conversion issue, please contact us at support@matecat.com',
                '.okapi.'              => 'File conversion issue, please contact us at support@matecat.com',
        ];

        foreach ( $errorPatterns as $pattern => $response ) {
            if ( strpos( $message, $pattern ) !== false ) {
                return $response;
            }
        }

        if ( strpos( $message, 'Exception:' ) !== false ) {
            $msg = explode( 'Exception:', $message );

            return $msg[ 1 ];
        }

        return $message;
    }

    /**
     * @throws Exception
     */
    public function extractZipFile(): array {

        $this->file_name = html_entity_decode( $this->file_name, ENT_QUOTES );
        $file_path       = $this->uploadDir . DIRECTORY_SEPARATOR . $this->file_name;

        //The zip file name is set in $this->file_name
        $this->result->setFileName( AbstractFilesStorage::basename_fix( $this->file_name ) );

        $za = new ZipArchiveHandler();

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
            $uploadFile = new Upload( $this->uploadTokenValue );

            $uploadFile->setRaiseException( $this->stopOnFileException );

            try {
                $stdResult = $uploadFile->uploadFiles( $filesArray );

                if ( $this->isZipExtractionFailed( $stdResult ) ) {
                    $this->zipExtractionErrorFlag  = true;
                    $this->zipExtractionErrorFiles = (array)$stdResult;
                }

            } catch ( Exception $e ) {

                $this->result->setErrorCode( ConversionHandlerStatus::INVALID_FILE );
                $this->result->setErrorMessage( $e->getMessage() );

                return [];
            }

            return array_map( function ( $fileName ) use ( $uploadFile ) {
                return $uploadFile->fixFileName( $fileName, false );
            }, $za->treeList );

        } catch ( Exception $e ) {

            Log::doJsonLog( "ExtendedZipArchive Exception: {$e->getCode()} : {$e->getMessage()}" );

            $this->result->setErrorCode( $e->getCode() );
            $this->result->setErrorMessage( "Zip error: " . $e->getMessage() );

            return [];
        }

    }

    /**
     * @param $stdResult
     *
     * @return bool
     */
    public function isZipExtractionFailed( $stdResult ): bool {

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
    public function getZipExtractionErrorFiles(): array {
        return $this->zipExtractionErrorFiles;
    }


    /**
     * @return ConvertedFileModel
     */
    public function getResult(): ConvertedFileModel {
        return $this->result;
    }

    /**
     * @return string
     */
    public function getFileName(): string {
        return $this->file_name;
    }

    /**
     * @param mixed $file_name
     */
    public function setFileName( $file_name ) {
        $this->file_name = $file_name;
    }

    /**
     * @param mixed $source_lang
     */
    public function setSourceLang( $source_lang ) {
        $this->source_lang = $source_lang;
    }

    /**
     * @param mixed $target_lang
     */
    public function setTargetLang( $target_lang ) {
        $this->target_lang = $target_lang;
    }

    /**
     * @param mixed $segmentation_rule
     */
    public function setSegmentationRule( $segmentation_rule ) {
        $this->segmentation_rule = $segmentation_rule;
    }

    /**
     * @param string $uploadDir
     */
    public function setUploadDir( string $uploadDir ) {
        $this->uploadDir = $uploadDir;
    }

    /**
     * @param mixed $errDir
     */
    public function setErrDir( $errDir ) {
        $this->errDir = $errDir;
    }

    /**
     * @param mixed $uploadTokenValue
     */
    public function setUploadTokenValue( $uploadTokenValue ) {
        $this->uploadTokenValue = $uploadTokenValue;
    }

    /**
     * @param boolean $stopOnFileException
     */
    public function setStopOnFileException( bool $stopOnFileException ) {
        $this->stopOnFileException = $stopOnFileException;
    }

    /**
     * @param FeatureSet $features
     *
     * @return $this
     */
    public function setFeatures( FeatureSet $features ): ConversionHandler {
        $this->features = $features;

        return $this;
    }

    /**
     * @param mixed $filters_extraction_parameters
     */
    public function setFiltersExtractionParameters( ?FiltersConfigTemplateStruct $filters_extraction_parameters = null ) {
        $this->filters_extraction_parameters = $filters_extraction_parameters;
    }

}
