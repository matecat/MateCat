<?php

use Constants\ConversionHandlerStatus;
use Conversion\ConvertedFileModel;
use FilesStorage\AbstractFilesStorage;
use FilesStorage\FilesStorageFactory;
use Filters\FiltersConfigTemplateDao;
use Filters\FiltersConfigTemplateStruct;
use Langs\Languages;

set_time_limit( 0 );

class convertFileController extends ajaxController {

    /**
     * @var ConvertedFileModel
     */
    protected $result;

    protected string  $file_name;
    public string     $source_lang;
    public string     $target_lang;
    protected ?string $segmentation_rule = null;

    protected $intDir;
    protected $errDir;

    public string $cookieDir;

    //this will prevent recursion loop when ConvertFileWrapper will call the doAction()
    protected bool      $convertZipFile = true;
    protected Languages $lang_handler;

    protected int                          $filters_extraction_parameters_template_id;
    protected ?FiltersConfigTemplateStruct $filters_extraction_parameters = null;

    /**
     * @var AbstractFilesStorage
     */
    protected $files_storage;
    /**
     * @var mixed
     */
    protected bool $restarted_conversion = false;

    /**
     * @throws Exception
     */
    public function __construct() {
        parent::__construct();

        $filterArgs = [
                'file_name'                                 => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW // | FILTER_FLAG_STRIP_HIGH
                ],
                'source_lang'                               => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'target_lang'                               => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'segmentation_rule'                         => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'filters_extraction_parameters_template_id' => [
                        'filter' => FILTER_SANITIZE_NUMBER_INT
                ],
                'restarted_conversion'                      => [
                        'filter' => FILTER_VALIDATE_BOOLEAN
                ]
        ];

        $postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->file_name                                 = $postInput[ 'file_name' ];
        $this->source_lang                               = $postInput[ "source_lang" ];
        $this->target_lang                               = $postInput[ "target_lang" ];
        $this->segmentation_rule                         = $postInput[ "segmentation_rule" ];
        $this->filters_extraction_parameters_template_id = (int)$postInput[ "filters_extraction_parameters_template_id" ];
        $this->restarted_conversion                      = $postInput[ "restarted_conversion" ] ?? false;

        $this->cookieDir = $_COOKIE[ 'upload_token' ];
        $this->intDir    = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $this->cookieDir;
        $this->errDir    = INIT::$STORAGE_DIR . DIRECTORY_SEPARATOR . 'conversion_errors' . DIRECTORY_SEPARATOR . $this->cookieDir;

        $this->identifyUser();

        $this->files_storage = FilesStorageFactory::create();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function doAction() {

        $this->result = new ConvertedFileModel();

        $this->lang_handler = Languages::getInstance();
        $this->validateSourceLang();
        $this->validateTargetLangs();
        $this->validateFiltersExtractionParametersTemplateId();

        try {
            $this->segmentation_rule = Constants::validateSegmentationRules( $this->segmentation_rule );
        } catch ( Exception $e ) {
            $this->result->changeCode( ConversionHandlerStatus::INVALID_SEGMENTATION_RULE );
            $this->result->addError( $e->getMessage() );

            return false;
        }

        if ( !Utils::isTokenValid( $this->cookieDir ) ) {
            $this->result->changeCode( ConversionHandlerStatus::INVALID_TOKEN );
            $this->result->addError( "Invalid Upload Token." );

            return false;
        }

        if ( !Utils::isValidFileName( $this->file_name ) || empty( $this->file_name ) ) {
            $this->result->changeCode( ConversionHandlerStatus::INVALID_FILE );
            $this->result->addError( "Invalid File." );

            return false;
        }

        if ( $this->result->hasErrors() ) {
            return false;
        }

        if ( $this->isLoggedIn() ) {
            $this->featureSet->loadFromUserEmail( $this->user->email );
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
        $conversionHandler->setUserIsLogged( $this->userIsLogged );
        $conversionHandler->setFiltersExtractionParameters( $this->filters_extraction_parameters );
        $conversionHandler->setReconversion( $this->restarted_conversion );

        if ( $ext == "zip" ) {
            if ( $this->convertZipFile ) {
                $this->handleZip( $conversionHandler );
            } else {
                $this->result->changeCode( ConversionHandlerStatus::NESTED_ZIP_FILES_NOT_ALLOWED );
                $this->result->addError( "Nested zip files are not allowed." );

                return false;
            }
        } else {
            $conversionHandler->processConversion();
            $this->result = $conversionHandler->getResult();
        }
    }

    private function validateSourceLang() {
        try {
            $this->lang_handler->validateLanguage( $this->source_lang );
        } catch ( Exception $e ) {
            $this->result->changeCode( ConversionHandlerStatus::SOURCE_ERROR );
            $this->result->addError( $e->getMessage() );
        }
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    private function validateFiltersExtractionParametersTemplateId() {
        if ( !empty( $this->filters_extraction_parameters_template_id ) ) {

            $filtersTemplate = FiltersConfigTemplateDao::getByIdAndUser( $this->filters_extraction_parameters_template_id, $this->getUser()->uid );

            if ( $filtersTemplate === null ) {
                throw new Exception( "filters_extraction_parameters_template_id not valid" );
            }

            $this->filters_extraction_parameters = $filtersTemplate;
        }
    }

    /**
     * @throws Exception
     */
    private function validateTargetLangs() {
        try {
            $this->target_lang = $this->lang_handler->validateLanguageListAsString( $this->target_lang );
        } catch ( Exception $e ) {
            $this->result->changeCode( ConversionHandlerStatus::TARGET_ERROR );
            $this->result->addError( $e->getMessage() );
        }
    }

    /**
     * @param ConversionHandler $conversionHandler
     *
     * @return bool
     * @throws ReflectionException
     * @throws Exception
     */
    private function handleZip( ConversionHandler $conversionHandler ): bool {

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

                $this->result->changeCode( $fileError->error[ 'code' ] );
                $this->result->addError( $fileError->error[ 'message' ], $brokenFileName );
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

        $this->result->addData( 'zipFiles', json_encode( $realFileNames ) );

        $stdFileObjects = [];

        if ( $internalZipFileNames !== null ) {
            foreach ( $internalZipFileNames as $fName ) {

                $newStdFile       = new stdClass();
                $newStdFile->name = $fName;
                $stdFileObjects[] = $newStdFile;

            }
        } else {
            $errors = $conversionHandler->getResult();
            $errors = array_map( [ 'Upload', 'formatExceptionMessage' ], $errors->getErrors() );

            foreach ( $errors as $error ) {
                $this->result->addError( $error );
            }

            return false;
        }

        /* Do conversions here */
        $converter              = new ConvertFileWrapper( $stdFileObjects, false );
        $converter->intDir      = $this->intDir;
        $converter->errDir      = $this->errDir;
        $converter->cookieDir   = $this->cookieDir;
        $converter->source_lang = $this->source_lang;
        $converter->target_lang = $this->target_lang;
        $converter->featureSet  = $this->featureSet;
        $converter->doAction();

        $error = $converter->checkResult();

        $this->result->changeCode( ConversionHandlerStatus::ZIP_HANDLING );

        // Upload errors handling
        if ( $error !== null and !empty( $error->getErrors() ) ) {
            $this->result->changeCode( $error->getCode() );
            $savedErrors    = $this->result->getErrors();
            $brokenFileName = ZipArchiveExtended::getFileName( array_keys( $error->getErrors() )[ 0 ] );

            if ( !isset( $savedErrors[ $brokenFileName ] ) ) {
                $this->result->addError( $error->getErrors()[ 0 ][ 'message' ], $brokenFileName );
            }
        }

        return true;

    }
}
