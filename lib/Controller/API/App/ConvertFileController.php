<?php

namespace API\App;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use Constants;
use ConversionHandler;
use ConvertFile;
use Exception;
use FilesStorage\AbstractFilesStorage;
use Filters\FiltersConfigTemplateDao;
use RuntimeException;
use INIT;
use InvalidArgumentException;
use Klein\Response;
use Langs\Languages;
use ReflectionException;
use stdClass;
use Utils;
use ZipArchiveExtended;

class ConvertFileController extends KleinController {

    private $data = [];

    //this will prevent recursion loop when ConvertFileWrapper will call the doAction()
    protected bool      $convertZipFile = true;

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function handle(): Response
    {
        try {
            $this->data = $this->validateTheRequest();
            $cookieDir = $_COOKIE[ 'upload_token' ];
            $intDir    = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $cookieDir;
            $errDir    = INIT::$STORAGE_DIR . DIRECTORY_SEPARATOR . 'conversion_errors' . DIRECTORY_SEPARATOR . $cookieDir;

            if ( !Utils::isTokenValid( $cookieDir ) ) {
                throw new RuntimeException("Invalid Upload Token.");
            }

            $this->featureSet->loadFromUserEmail($this->user->email);

            $ext = AbstractFilesStorage::pathinfo_fix( $this->data['file_name'], PATHINFO_EXTENSION );

            $conversionHandler = new ConversionHandler();
            $conversionHandler->setFileName( $this->data['file_name'] );
            $conversionHandler->setSourceLang( $this->data['source_lang'] );
            $conversionHandler->setTargetLang( $this->data['target_lang'] );
            $conversionHandler->setSegmentationRule( $this->data['segmentation_rule'] );
            $conversionHandler->setCookieDir( $cookieDir );
            $conversionHandler->setIntDir( $intDir );
            $conversionHandler->setErrDir( $errDir );
            $conversionHandler->setFeatures( $this->featureSet );
            $conversionHandler->setUserIsLogged( true );
            $conversionHandler->setFiltersExtractionParameters( $this->data['filters_extraction_parameters'] );
            $conversionHandler->setReconversion( $this->data['restarted_conversion'] );

            if ( $ext == "zip" and $this->convertZipFile ) {
                $result = $this->handleZip( $conversionHandler, $intDir, $errDir, $cookieDir );
            } else {
                $conversionHandler->processConversion();
                $result = $conversionHandler->getResult();
            }

            return $this->response->json($result);

        } catch (Exception $exception){
            return $this->returnException($exception);
        }
    }

    /**
     * @return array|\Klein\Response
     * @throws Exception
     */
    private function validateTheRequest(): array
    {
        $file_name = filter_var( $this->request->param( 'file_name' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW ] );
        $source_lang = filter_var( $this->request->param( 'source_lang' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $target_lang = filter_var( $this->request->param( 'target_lang' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $segmentation_rule = filter_var( $this->request->param( 'segmentation_rule' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $filters_extraction_parameters = filter_var( $this->request->param( 'filters_extraction_parameters' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $filters_extraction_parameters_template_id = filter_var( $this->request->param( 'filters_extraction_parameters_template_id' ), FILTER_SANITIZE_NUMBER_INT );
        $restarted_conversion = filter_var( $this->request->param( 'restarted_conversion' ), FILTER_VALIDATE_BOOLEAN );

        if(empty($file_name)){
            throw new InvalidArgumentException("Missing file name.");
        }

        if(empty($source_lang)){
            throw new InvalidArgumentException("Missing source language.");
        }

        if(empty($target_lang)){
            throw new InvalidArgumentException("Missing target language.");
        }

        if(empty($segmentation_rule)){
            throw new InvalidArgumentException("Missing segmentation rule.");
        }

        if ( !Utils::isValidFileName( $file_name ) ) {
            throw new InvalidArgumentException("Invalid file name.");
        }

        $segmentation_rule = Constants::validateSegmentationRules( $segmentation_rule );
        $filters_extraction_parameters = $this->validateFiltersExtractionParametersTemplateId($filters_extraction_parameters_template_id);
        $source_lang = $this->validateSourceLang($source_lang);
        $target_lang = $this->validateTargetLangs($target_lang);

        return [
            'file_name' => $file_name,
            'source_lang' => $source_lang,
            'target_lang' => $target_lang,
            'segmentation_rule' => $segmentation_rule,
            'filters_extraction_parameters' => $filters_extraction_parameters,
            'filters_extraction_parameters_template_id' => (int)$filters_extraction_parameters_template_id,
            'restarted_conversion' => $restarted_conversion,
        ];
    }

    /**
     * @param $source_lang
     * @return string
     * @throws \Langs\InvalidLanguageException
     */
    private function validateSourceLang($source_lang) {
        $lang_handler = Languages::getInstance();

        return $lang_handler->validateLanguage( $source_lang );
    }

    /**
     * @param $target_lang
     * @return string
     * @throws \Langs\InvalidLanguageException
     */
    private function validateTargetLangs($target_lang) {
        $lang_handler = Languages::getInstance();

        return $lang_handler->validateLanguageListAsString( $target_lang );
    }

    /**
     * @param null $filters_extraction_parameters_template_id
     * @return \Filters\FiltersConfigTemplateStruct|null
     * @throws ReflectionException
     */
    private function validateFiltersExtractionParametersTemplateId($filters_extraction_parameters_template_id = null) {
        if ( !empty( $filters_extraction_parameters_template_id ) ) {

            $filtersTemplate = FiltersConfigTemplateDao::getByIdAndUser( $filters_extraction_parameters_template_id, $this->getUser()->uid );

            if ( $filtersTemplate === null ) {
                throw new Exception( "filters_extraction_parameters_template_id not valid" );
            }

            return $filtersTemplate;
        }
    }

    /**
     * @param ConversionHandler $conversionHandler
     * @param $intDir
     * @param $errDir
     * @param $cookieDir
     * @return array
     * @throws Exception
     */
    private function handleZip( ConversionHandler $conversionHandler, $intDir, $errDir, $cookieDir )
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

                throw new RuntimeException("Error during processing file " .$brokenFileName. ":" . $fileError->error[ 'message' ]);
            }
        }

        $realFileNames = array_map(
            [ 'ZipArchiveExtended', 'getFileName' ],
            $internalZipFileNames
        );

        foreach ( $realFileNames as $i => &$fileObject ) {
            $fileObject = [
                'name' => $fileObject,
                'size' => filesize( $intDir . DIRECTORY_SEPARATOR . $internalZipFileNames[ $i ] )
            ];
        }

        $zipFiles = [ 'zipFiles' => json_encode( $realFileNames ) ];

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
                throw new RuntimeException( $error );
            }
        }

        /* Do conversions here */
        foreach ($stdFileObjects as $stdFileObject){
            $convertFile = new ConvertFile(
                $stdFileObject->name,
                $this->data['source_lang'],
                $this->data['target_lang'],
                $intDir,
                $errDir,
                $cookieDir,
                $this->data['segmentation_rule'],
                $this->featureSet,
                $this->data['filters_extraction_parameters'],
                false
            );

            $convertFile->convertFiles();

            if($convertFile->hasErrors()){
                foreach ( $convertFile->getErrors() as $error ) {
                    throw new RuntimeException( $error );
                }
            }
        }

        return [
            'code' => Constants\ConversionHandlerStatus::ZIP_HANDLING,
            'data' => $zipFiles,
            'errors' => []  ,
            'warning' => [],
        ];
    }
}