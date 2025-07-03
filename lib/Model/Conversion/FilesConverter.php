<?php

namespace Model\Conversion;

use Constants;
use Constants\ConversionHandlerStatus;
use DomainException;
use Exception;
use InvalidArgumentException;
use Langs\Languages;
use Model\FeaturesBase\FeatureSet;
use Model\FilesStorage\AbstractFilesStorage;
use Model\Filters\FiltersConfigTemplateStruct;
use RuntimeException;
use Utils;

class FilesConverter {
    private string  $source_lang;
    private string  $target_lang;
    private string  $fullUploadDirPath;
    private string  $errDir;
    private string  $uploadTokenValue;
    private ?string $segmentation_rule;
    private array   $files;

    /**
     * @var ConvertedFileList
     */
    private ConvertedFileList $resultStack;

    /**
     * @var Languages|null
     */
    private ?Languages $lang_handler;

    /**
     * @var FeatureSet
     */
    private FeatureSet $featureSet;

    private ?FiltersConfigTemplateStruct $filters_extraction_parameters;

    /**
     * FilesConverter constructor.
     *
     * @param array                            $files
     * @param string                           $source_lang
     * @param string                           $target_lang
     * @param string                           $intDir
     * @param string                           $errDir
     * @param string                           $uploadTokenValue
     * @param string|null                      $segmentation_rule
     * @param \Model\FeaturesBase\FeatureSet   $featureSet
     * @param FiltersConfigTemplateStruct|null $filters_extraction_parameters
     */
    public function __construct(
            array                        $files,
            string                       $source_lang,
            string                       $target_lang,
            string                       $intDir,
            string                       $errDir,
            string                       $uploadTokenValue,
            ?string                      $segmentation_rule,
            FeatureSet                   $featureSet,
            ?FiltersConfigTemplateStruct $filters_extraction_parameters = null
    ) {
        $this->lang_handler = Languages::getInstance();
        $this->files        = $files;
        $this->setSourceLang( $source_lang );
        $this->setTargetLangs( $target_lang );
        $this->fullUploadDirPath             = $intDir;
        $this->errDir                        = $errDir;
        $this->uploadTokenValue              = $uploadTokenValue;
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

                    $res = $this->convertFile( $internalFile );
                    $this->resultStack->add( $res );

                    if ( $res->isError() ) {
                        $this->resultStack->setErroredFile( $res );
                    } elseif ( $res->isWarning() ) {
                        $this->resultStack->setWarnedFile( $res );
                    }

                }

            } else {

                $res = $this->convertFile( $fileName );
                $this->resultStack->add( $res );

                if ( $res->isError() ) {
                    $this->resultStack->setErroredFile( $res );
                } elseif ( $res->isWarning() ) {
                    $this->resultStack->setWarnedFile( $res );
                }

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
        $conversionHandler->setUploadTokenValue( $this->uploadTokenValue );
        $conversionHandler->setUploadDir( $this->fullUploadDirPath );
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

        if ( !Utils::isTokenValid( $this->uploadTokenValue ) ) {
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

        // this makes the conversion-handler accumulate eventual errors on files and continue
        $conversionHandler->setStopOnFileException( false );

        $internalZipFileNames = $conversionHandler->extractZipFile();
        //call convertFileWrapper and start conversions for each file

        if ( $conversionHandler->zipExtractionErrorFlag ) {
            $fileErrors = $conversionHandler->getZipExtractionErrorFiles();

            foreach ( $fileErrors as $fileError ) {
                if ( count( $fileError->error ) == 0 ) {
                    continue;
                }

                throw new RuntimeException( $fileError->error[ 'message' ], $fileError->error[ 'code' ] );
            }
        }

        if ( empty( $internalZipFileNames ) ) {
            $errors = $conversionHandler->getResult();
            throw new DomainException( $errors->getMessage(), $errors->getCode() );
        }

        return $internalZipFileNames;

    }

    public function getResult(): ConvertedFileList {
        return $this->resultStack;
    }

}