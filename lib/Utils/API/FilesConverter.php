<?php

use Constants\ConversionHandlerStatus;
use Conversion\ConversionHandler;
use Conversion\ConvertedFileList;
use Conversion\ConvertedFileModel;
use FilesStorage\AbstractFilesStorage;
use Langs\Languages;

class FilesConverter {
    private $source_lang;
    private $target_lang;
    private $intDir;
    private $errDir;
    private $cookieDir;
    private $segmentation_rule;
    private $files;

    /**
     * @var ConvertedFileList
     */
    private ConvertedFileList $resultStack;

    /**
     * @var Languages
     */
    private ?Languages $lang_handler = null;

    /**
     * @var FeatureSet
     */
    private $featureSet;

    private $filters_extraction_parameters;

    /**
     * @var bool
     */
    private bool $convertZipFile;

    /**
     * FilesConverter constructor.
     *
     * @param            $files
     * @param            $source_lang
     * @param            $target_lang
     * @param            $intDir
     * @param            $errDir
     * @param            $cookieDir
     * @param            $segmentation_rule
     * @param FeatureSet $featureSet
     * @param            $filters_extraction_parameters
     * @param bool       $convertZipFile
     */
    public function __construct(
            $files,
            $source_lang,
            $target_lang,
            $intDir,
            $errDir,
            $cookieDir,
            $segmentation_rule,
            FeatureSet $featureSet,
            $filters_extraction_parameters = null,
            bool $convertZipFile = true
    ) {
        $this->lang_handler   = Languages::getInstance();
        $this->files          = $files;
        $this->convertZipFile = $convertZipFile;
        $this->setSourceLang( $source_lang );
        $this->setTargetLangs( $target_lang );
        $this->intDir                        = $intDir;
        $this->errDir                        = $errDir;
        $this->cookieDir                     = $cookieDir;
        $this->segmentation_rule             = $segmentation_rule;
        $this->featureSet                    = $featureSet;
        $this->filters_extraction_parameters = $filters_extraction_parameters;
        $this->resultStack                   = new ConvertedFileList();
    }

    /**
     * @param $source_lang
     */
    private function setSourceLang( $source_lang ): void {
        try {
            $this->lang_handler->validateLanguage( $source_lang );
            $this->source_lang = $source_lang;
        } catch ( Exception $e ) {
            throw new InvalidArgumentException( $e->getMessage(), ConversionHandlerStatus::SOURCE_ERROR );
        }
    }

    /**
     * @param $target_lang
     */
    private function setTargetLangs( $target_lang ): void {
        $targets = explode( ',', $target_lang );
        $targets = array_map( 'trim', $targets );
        $targets = array_unique( $targets );

        if ( empty( $targets ) ) {
            throw new InvalidArgumentException( "Missing target language." );
        }

        try {
            foreach ( $targets as $target ) {
                $this->lang_handler->validateLanguage( $target );
            }

        } catch ( Exception $e ) {
            throw new InvalidArgumentException( $e->getMessage(), ConversionHandlerStatus::TARGET_ERROR );
        }

        $this->target_lang = implode( ',', $targets );
    }

    /**
     * @return ConvertedFileList
     * @throws Exception
     */
    public function convertFiles(): ConvertedFileList {
        foreach ( $this->files as $fileName ) {

            $ext = AbstractFilesStorage::pathinfo_fix( $fileName, PATHINFO_EXTENSION );
            if ( $ext == "zip" ) {

                $fileListContent = $this->getExtractedFilesContentList( $fileName );

                foreach ( $fileListContent as $internalFile ) {

                    $ext = AbstractFilesStorage::pathinfo_fix( $internalFile, PATHINFO_EXTENSION );
                    if ( $ext == "zip" ) {
                        throw new DomainException( "Nested zip files are not allowed.", ConversionHandlerStatus::NESTED_ZIP_FILES_NOT_ALLOWED );
                    }

                    $this->resultStack->add( $this->convertFile( $internalFile ) );

                }

            } else {
                $this->resultStack->add( $this->convertFile( $fileName ) );
            }
        }

        return $this->resultStack;
    }

    private function getConversionHandlerInstance( string $fileName ): ConversionHandler {
        $conversionHandler = new ConversionHandler();
        $conversionHandler->setFileName( $fileName );
        $conversionHandler->setSourceLang( $this->source_lang );
        $conversionHandler->setTargetLang( $this->target_lang );
        $conversionHandler->setSegmentationRule( $this->segmentation_rule );
        $conversionHandler->setCookieDir( $this->cookieDir );
        $conversionHandler->setIntDir( $this->intDir );
        $conversionHandler->setErrDir( $this->errDir );
        $conversionHandler->setFeatures( $this->featureSet );
        $conversionHandler->setFiltersExtractionParameters( $this->filters_extraction_parameters );

        return $conversionHandler;
    }

    /**
     * @return ConvertedFileModel
     * @throws Exception
     */
    private function convertFile( string $fileName ): ?ConvertedFileModel {

        try {
            $this->segmentation_rule = Constants::validateSegmentationRules( $this->segmentation_rule );
        } catch ( Exception $e ) {
            throw new InvalidArgumentException( $e->getMessage(), ConversionHandlerStatus::INVALID_SEGMENTATION_RULE );
        }

        if ( !Utils::isTokenValid( $this->cookieDir ) ) {
            throw new InvalidArgumentException( "Invalid Upload Token.", ConversionHandlerStatus::INVALID_TOKEN );
        }

        if ( !Utils::isValidFileName( $fileName ) || empty( $fileName ) ) {
            throw new InvalidArgumentException( "Invalid File.", ConversionHandlerStatus::INVALID_FILE );
        }

        $conversionHandler = $this->getConversionHandlerInstance( $fileName );
        $conversionHandler->processConversion();

        return $conversionHandler->getResult();
    }

    /**
     * @throws Exception
     */
    private function getExtractedFilesContentList( string $zipName ): array {

        $conversionHandler = $this->getConversionHandlerInstance( $zipName );

        // this makes the conversionhandler accumulate eventual errors on files and continue
        $conversionHandler->setStopOnFileException( false );

        $internalZipFileNames = $conversionHandler->extractZipFile();
        //call convertFileWrapper and start conversions for each file

        if ( $conversionHandler->zipExtractionErrorFlag ) {
            $fileErrors = $conversionHandler->getZipExtractionErrorFiles();

            foreach ( $fileErrors as $fileError ) {
                if ( count( $fileError->error ) == 0 ) {
                    continue;
                }

                $brokenFileName = ZipArchiveExtended::getFileName( $fileError->name );

                throw new RuntimeException( $fileError->error[ 'message' ], $fileError->error[ 'code' ] ); //@TODO usare $brokenFileName
            }
        }

        $realFileNames = array_map(
                [ 'ZipArchiveExtended', 'getFileName' ],
                $internalZipFileNames
        );

        foreach ( $realFileNames as $i => &$fileObject ) {
            $fileObject = [
                    'name' => $fileObject,
                    'size' => filesize( $this->intDir . DIRECTORY_SEPARATOR . $internalZipFileNames[ $i ] )
            ];
        }

        $stdFileObjects = [];
        if ( !empty( $internalZipFileNames ) ) {
            foreach ( $internalZipFileNames as $fName ) {
                $stdFileObjects[] = $fName;
            }
        } else {

            //handling errors of zip file extraction
            $errors = $conversionHandler->getResult();
            throw new DomainException( $errors->getError(), $errors->getCode() );

        }

        return $stdFileObjects;

    }

    public function getResult(): ConvertedFileList {
        return $this->resultStack;
    }

    public static function extractFileNameFromErrorString( $randomString ) {
        $path = explode( ZipArchiveExtended::INTERNAL_SEPARATOR, $randomString );

        if ( count( $path ) < 2 ) {
            return null;
        }

        $regexp = '';

        return array_pop( $path );
    }

}