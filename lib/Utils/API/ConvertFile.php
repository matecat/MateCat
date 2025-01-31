<?php

use Constants\ConversionHandlerStatus;
use Conversion\ConvertedFileModel;
use FilesStorage\AbstractFilesStorage;
use Langs\Languages;

class ConvertFile
{
    private $file_name;
    private $source_lang;
    private $target_lang;
    private $intDir;
    private $errDir;
    private $cookieDir;
    private $segmentation_rule;
    private $files;

    /**
     * @var ConvertedFileModel[]
     */
    private $resultStack = [];

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
     * @var Users_UserStruct
     */
    private ?Users_UserStruct $user = null;

    /**
     * ConvertFile constructor.
     * @param $files
     * @param $source_lang
     * @param $target_lang
     * @param $intDir
     * @param $errDir
     * @param $cookieDir
     * @param $segmentation_rule
     * @param FeatureSet $featureSet
     * @param $filters_extraction_parameters
     * @param bool $convertZipFile
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
        $convertZipFile = true
    )
    {
        $this->lang_handler = Languages::getInstance();
        $this->files = $files;
        $this->convertZipFile = $convertZipFile;
        $this->setSourceLang($source_lang);
        $this->setTargetLangs($target_lang);
        $this->intDir = $intDir;
        $this->errDir = $errDir;
        $this->cookieDir = $cookieDir;
        $this->segmentation_rule = $segmentation_rule;
        $this->featureSet = $featureSet;
        $this->filters_extraction_parameters = $filters_extraction_parameters;
    }

    /**
     * @param Users_UserStruct $user
     */
    public function setUser( Users_UserStruct $user )
    {
        $this->user = $user;
    }

    /**
     * @param $source_lang
     */
    private function setSourceLang($source_lang): void
    {
        try {
            $this->lang_handler->validateLanguage( $source_lang );
            $this->source_lang = $source_lang;
        } catch ( Exception $e ) {
            throw new InvalidArgumentException($e->getMessage(), ConversionHandlerStatus::SOURCE_ERROR);
        }
    }

    /**
     * @param $target_lang
     */
    private function setTargetLangs($target_lang): void
    {
        $targets = explode( ',', $target_lang );
        $targets = array_map( 'trim', $targets );
        $targets = array_unique( $targets );

        if ( empty( $targets ) ) {
            throw new InvalidArgumentException("Missing target language.");
        }

        try {
            foreach ( $targets as $target ) {
                $this->lang_handler->validateLanguage( $target );
            }

        } catch ( Exception $e ) {
            throw new InvalidArgumentException($e->getMessage(), ConversionHandlerStatus::TARGET_ERROR);
        }

        $this->target_lang = implode( ',', $targets );
    }

    /**
     * @return array
     * @throws Exception
     */
    public function convertFiles(): array
    {
        foreach ($this->files as $fileName ) {
            $this->file_name = $fileName;
            $this->resultStack[] = $this->convertFile();
        }

        return $this->resultStack;
    }

    /**
     * @return ConvertedFileModel
     * @throws Exception
     */
    private function convertFile(): ?ConvertedFileModel
    {
        $result = new ConvertedFileModel();

        try {
            $this->segmentation_rule = Constants::validateSegmentationRules( $this->segmentation_rule );
        } catch ( Exception $e ){
            throw new InvalidArgumentException($e->getMessage(), ConversionHandlerStatus::INVALID_SEGMENTATION_RULE);
        }

        if ( !Utils::isTokenValid( $this->cookieDir ) ) {
            throw new InvalidArgumentException("Invalid Upload Token.", ConversionHandlerStatus::INVALID_TOKEN);
        }

        if ( !Utils::isValidFileName( $this->file_name ) || empty( $this->file_name ) ) {
            throw new InvalidArgumentException("Invalid File.", ConversionHandlerStatus::INVALID_FILE);
        }

        $ext = AbstractFilesStorage::pathinfo_fix( $this->file_name, PATHINFO_EXTENSION );

        $conversionHandler = new ConversionHandler();
        $conversionHandler->setFileName( $this->file_name );
        $conversionHandler->setSourceLang( $this->source_lang );
        $conversionHandler->setTargetLang( $this->target_lang );
        $conversionHandler->setSegmentationRule( $this->segmentation_rule );
        $conversionHandler->setCookieDir( $this->cookieDir );
        $conversionHandler->setIntDir( $this->intDir );
        $conversionHandler->setErrDir( $this->errDir );
        $conversionHandler->setFeatures( $this->featureSet );
        $conversionHandler->setUserIsLogged( true );
        $conversionHandler->setFiltersExtractionParameters( $this->filters_extraction_parameters );

        if ( $ext == "zip" ) {
            if ( $this->convertZipFile ) {
               try {
                   $this->handleZip( $conversionHandler );
               } catch (Exception $exception){
                   throw new RuntimeException("Handling of zip files failed.", ConversionHandlerStatus::ZIP_HANDLING);
               }
            } else {
                throw new RuntimeException("Nested zip files are not allowed.", ConversionHandlerStatus::NESTED_ZIP_FILES_NOT_ALLOWED);
            }
        } else {
            $conversionHandler->doAction();
            $result = $conversionHandler->getResult();
        }

        return $result;
    }

    /**
     * @param ConversionHandler $conversionHandler
     *
     * @return bool
     * @throws Exception
     */
    private function handleZip( ConversionHandler $conversionHandler ): bool
    {
        // this makes the conversionhandler accumulate eventual errors on files and continue
        $conversionHandler->setStopOnFileException( false );

        $internalZipFileNames = $conversionHandler->extractZipFile();
        //call convertFileWrapper and start conversions for each file

        if ( $conversionHandler->uploadError ) {
            $fileErrors = $conversionHandler->getUploadedFiles();

            foreach ( $fileErrors as $fileError ) {
                if ( count( $fileError->error ) == 0 ) {
                    continue;
                }

                $brokenFileName = ZipArchiveExtended::getFileName( $fileError->name );

                throw new RuntimeException($fileError->error[ 'message' ], $fileError->error[ 'code' ]); //@TODO usare $brokenFileName
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

        $zipFiles = json_encode( $realFileNames );
        $stdFileObjects = [];

        if ( $internalZipFileNames !== null ) {
            foreach ( $internalZipFileNames as $fName ) {
                $stdFileObjects[] = $fName;
            }
        } else {
            $errors = $conversionHandler->getResult();
            $errors = array_map( [ 'Upload', 'formatExceptionMessage' ], $errors[ 'errors' ] );

            foreach ($errors as $error){
                throw new Exception($error);
            }

            return false;
        }

        /* Do conversions here */
        $converter = new ConvertFile(
            $stdFileObjects,
            $this->source_lang,
            $this->target_lang,
            $this->intDir,
            $this->errDir,
            $this->cookieDir,
            $this->segmentation_rule,
            $this->featureSet,
            $this->filters_extraction_parameters,
            false
        );

        $converter->convertFiles();
        $error = $converter->getErrors();

        //$this->result->changeCode(ConversionHandlerStatus::ZIP_HANDLING);

        // Upload errors handling
//        if($error !== null and !empty($error->getErrors())){
//            $this->result->changeCode($error->getCode());
//            $savedErrors = $this->result->getErrors();
//            $brokenFileName = ZipArchiveExtended::getFileName( array_keys($error->getErrors())[0] );
//
//            if( !isset( $savedErrors[$brokenFileName] ) ){
//                $this->result->addError($error->getErrors()[0]['message'], $brokenFileName);
//            }
//        }

        return $zipFiles;
    }

    /**
     * Check on executed conversion results
     * @return array
     */
    public function getErrors(): array
    {
        $errors = [];

        /** @var ConvertedFileModel $res */
        foreach ( $this->resultStack as $res ) {
            if ( $res->hasAnErrorCode() ) {
                $errors[] = $res->getErrors();
            }
        }

        return $errors;
    }
}