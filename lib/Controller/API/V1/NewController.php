<?php

namespace API\V1;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use BasicFeatureStruct;
use Constants;
use Constants\ConversionHandlerStatus;
use Constants_ProjectStatus;
use Constants_TmKeyPermissions;
use Conversion\ConvertedFileModel;
use ConversionHandler;
use ConvertFile;
use Database;
use Engine;
use Engines_DeepL;
use Exception;
use FilesStorage\AbstractFilesStorage;
use FilesStorage\FilesStorageFactory;
use Filters\FiltersConfigTemplateDao;
use Filters\FiltersConfigTemplateStruct;
use INIT;
use InvalidArgumentException;
use Langs\LanguageDomains;
use Langs\Languages;
use Log;
use LQA\ModelDao;
use LQA\ModelStruct;
use Matecat\XliffParser\Utils\Files as XliffFiles;
use Matecat\XliffParser\XliffUtils\XliffProprietaryDetect;
use MTQE\Templates\DTO\MTQEWorkflowParams;
use MTQE\Templates\MTQEWorkflowTemplateDao;
use PayableRates\CustomPayableRateDao;
use PayableRates\CustomPayableRateStruct;
use ProjectManager;
use ProjectQueue\Queue;
use Projects_MetadataDao;
use QAModelTemplate\QAModelTemplateDao;
use QAModelTemplate\QAModelTemplateStruct;
use RuntimeException;
use SebastianBergmann\Invoker\TimeoutException;
use stdClass;
use Teams\MembershipDao;
use Teams\TeamStruct;
use TmKeyManagement_MemoryKeyDao;
use TmKeyManagement_MemoryKeyStruct;
use TmKeyManagement_TmKeyManagement;
use TmKeyManagement_TmKeyStruct;
use TMS\TMSService;
use Upload;
use Utils;
use Validator\EngineValidator;
use Validator\JSONValidator;
use Validator\JSONValidatorObject;
use Validator\MMTValidator;
use Xliff\XliffConfigTemplateDao;
use ZipArchiveExtended;

class NewController extends KleinController {
    const MAX_NUM_KEYS = 6;

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function create() {
        try {
            $this->featureSet->loadFromUserEmail( $this->user->email );
            $request    = $this->validateTheRequest();
            $fs         = FilesStorageFactory::create();
            $uploadFile = new Upload();

            try {
                $stdResult = $uploadFile->uploadFiles( $_FILES );
            } catch ( Exception $e ) {
                throw new RuntimeException( $e->getMessage(), -1 );
            }

            $arFiles = [];

            foreach ( $stdResult as $input_value ) {
                $arFiles[] = $input_value->name;
            }

            // if fileupload was failed this index ( 0 = does not exists )
            $default_project_name = @$arFiles[ 0 ];
            if ( count( $arFiles ) > 1 ) {
                $default_project_name = "MATECAT_PROJ-" . date( "Ymdhi" );
            }

            if ( empty( $request[ 'project_name' ] ) ) {
                $request[ 'project_name' ] = $default_project_name; //'NO_NAME'.$this->create_project_name();
            }

            $cookieDir = $uploadFile->getDirUploadToken();
            $intDir    = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $cookieDir;
            $errDir    = INIT::$STORAGE_DIR . DIRECTORY_SEPARATOR . 'conversion_errors' . DIRECTORY_SEPARATOR . $cookieDir;

            $status = [];

            foreach ( $arFiles as $file_name ) {
                $ext = AbstractFilesStorage::pathinfo_fix( $file_name, PATHINFO_EXTENSION );

                $conversionHandler = new ConversionHandler();
                $conversionHandler->setFileName( $file_name );
                $conversionHandler->setSourceLang( $request[ 'source_lang' ] );
                $conversionHandler->setTargetLang( $request[ 'target_lang' ] );
                $conversionHandler->setSegmentationRule( $request[ 'segmentation_rule' ] );
                $conversionHandler->setCookieDir( $cookieDir );
                $conversionHandler->setIntDir( $intDir );
                $conversionHandler->setErrDir( $errDir );
                $conversionHandler->setFeatures( $this->featureSet );
                $conversionHandler->setUserIsLogged( $this->userIsLogged );
                $conversionHandler->setFiltersExtractionParameters( $request[ 'filters_extraction_parameters' ] );

                if ( $ext == "zip" ) {
                    // this makes the conversionhandler accumulate eventual errors on files and continue
                    $conversionHandler->setStopOnFileException( false );
                    $fileObjects = $conversionHandler->extractZipFile();
                    Log::doJsonLog( 'fileObjets', $fileObjects );

                    // call convertFileWrapper and start conversions for each file
                    if ( $conversionHandler->uploadError ) {
                        $fileErrors = $conversionHandler->getUploadedFiles();

                        foreach ( $fileErrors as $fileError ) {
                            if ( count( $fileError->error ) == 0 ) {
                                continue;
                            }

                            $brokenFileName = ZipArchiveExtended::getFileName( $fileError->name );
                            $result         = new ConvertedFileModel( $fileError->error[ 'code' ] );
                            $result->addError( $fileError->error[ 'message' ], $brokenFileName );
                        }

                        $realFileObjectInfo  = $fileObjects;
                        $realFileObjectNames = array_map(
                                [ 'ZipArchiveExtended', 'getFileName' ],
                                $fileObjects
                        );

                        foreach ( $realFileObjectNames as $i => &$fileObject ) {
                            $__fileName     = $fileObject;
                            $__realFileName = $realFileObjectInfo[ $i ];
                            $filesize       = filesize( $intDir . DIRECTORY_SEPARATOR . $__realFileName );

                            $fileObject               = [
                                    'name' => $__fileName,
                                    'size' => $filesize
                            ];
                            $realFileObjectInfo[ $i ] = $fileObject;
                        }

                        $result[ 'data' ][ $file_name ] = json_encode( $realFileObjectNames );
                        $stdFileObjects                 = [];

                        if ( $fileObjects !== null ) {
                            foreach ( $fileObjects as $fName ) {

                                if ( isset( $fileErrors->{$fName} ) && !empty( $fileErrors->{$fName}->error ) ) {
                                    continue;
                                }

                                $newStdFile       = new stdClass();
                                $newStdFile->name = $fName;
                                $stdFileObjects[] = $newStdFile;

                            }
                        } else {
                            $errors             = $conversionHandler->getResult();
                            $errors             = array_map( [ 'Upload', 'formatExceptionMessage' ], $errors->getErrors() );
                            $result[ 'errors' ] = array_merge( $result[ 'errors' ], $errors );
                            Log::doJsonLog( "Zip error:" . $result[ 'errors' ] );

                            throw new RuntimeException( "Zip Error" );
                        }

                        /* Do conversions here */
                        $converter = new ConvertFile(
                                $stdFileObjects,
                                $request[ 'source_lang' ],
                                $request[ 'target_lang' ],
                                $intDir,
                                $errDir,
                                $cookieDir,
                                $request[ 'segmentation_rule' ],
                                $this->featureSet,
                                $request[ 'filters_extraction_parameters' ],
                                false );

                        $converter->setUser( $this->user );
                        $converter->convertFiles();

                        $status = $errors = $converter->getErrors();

                        if ( !empty( $errors ) ) {

                            $result = new ConvertedFileModel( ConversionHandlerStatus::ZIP_HANDLING );
                            $result->changeCode( 500 );
                            $savedErrors    = $result->getErrors();
                            $brokenFileName = ZipArchiveExtended::getFileName( array_keys( $errors )[ 0 ] );

                            if ( !isset( $savedErrors[ $brokenFileName ] ) ) {
                                $result->addError( $errors[ 0 ][ 'message' ], $brokenFileName );
                            }

                            $result = $status = [
                                    'code'   => 500,
                                    'data'   => [], // Is it correct????
                                    'errors' => $errors,
                            ];
                        }
                    }

                } else {
                    $conversionHandler->processConversion();
                    $res = $conversionHandler->getResult();
                    if ( $res->getCode() < 0 ) {
                        $status[] = [
                                'code'     => $res->getCode(),
                                'data'     => $res->getData(), // Is it correct????
                                'errors'   => $res->getErrors(),
                                'warnings' => $res->getWarnings(),
                        ];
                    }
                }
            }

            $status = array_values( $status );

            // Upload errors handling
            if ( !empty( $status ) ) {
                throw new RuntimeException( 'Project Conversion Failure' );
            }

            /* Do conversions here */
            if ( !empty( $result[ 'data' ] ) ) {
                foreach ( $result[ 'data' ] as $zipFiles ) {
                    $zipFiles  = json_decode( $zipFiles, true );
                    $fileNames = array_column( $zipFiles, 'name' );
                    $arFiles   = array_merge( $arFiles, $fileNames );
                }
            }

            $newArFiles = [];

            foreach ( $arFiles as $__fName ) {
                if ( 'zip' == AbstractFilesStorage::pathinfo_fix( $__fName, PATHINFO_EXTENSION ) ) {


                    $fs->cacheZipArchive( sha1_file( $intDir . DIRECTORY_SEPARATOR . $__fName ), $intDir . DIRECTORY_SEPARATOR . $__fName );

                    $linkFiles = scandir( $intDir );

                    //fetch cache links, created by converter, from upload directory
                    foreach ( $linkFiles as $storedFileName ) {
                        //check if file begins with the name of the zip file.
                        // If so, then it was stored in the zip file.
                        if ( strpos( $storedFileName, $__fName ) !== false &&
                                substr( $storedFileName, 0, strlen( $__fName ) ) == $__fName
                        ) {
                            //add file name to the files array
                            $newArFiles[] = $storedFileName;
                        }
                    }

                } else { //this file was not in a zip. Add it normally
                    if ( file_exists( $intDir . DIRECTORY_SEPARATOR . $__fName ) ) {
                        $newArFiles[] = $__fName;
                    }
                }
            }

            $arFiles = $newArFiles;
            $arMeta  = [];

            // create array_files_meta
            foreach ( $arFiles as $arFile ) {
                $arMeta[] = $this->getFileMetadata( $intDir . DIRECTORY_SEPARATOR . $arFile );
            }

            $projectManager                                 = new ProjectManager();
            $projectStructure                               = $projectManager->getProjectStructure();
            $projectStructure[ 'sanitize_project_options' ] = false;
            $projectStructure[ 'project_name' ]             = $request[ 'project_name' ];
            $projectStructure[ 'job_subject' ]              = $request[ 'subject' ];
            $projectStructure[ 'private_tm_key' ]           = $request[ 'private_tm_key' ];
            $projectStructure[ 'private_tm_user' ]          = $request[ 'private_tm_user' ];
            $projectStructure[ 'private_tm_pass' ]          = $request[ 'private_tm_pass' ];
                $projectStructure[ 'tm_prioritization' ]        = $request[ 'tm_prioritization' ];
            $projectStructure[ 'uploadToken' ]              = $uploadFile->getDirUploadToken();
            $projectStructure[ 'array_files' ]              = $arFiles; //list of file name
            $projectStructure[ 'array_files_meta' ]         = $arMeta; //list of file metadata
            $projectStructure[ 'source_language' ]          = $request[ 'source_lang' ];
            $projectStructure[ 'target_language' ]          = explode( ',', $request[ 'target_lang' ] );
            $projectStructure[ 'mt_engine' ]                = $request[ 'mt_engine' ];
            $projectStructure[ 'tms_engine' ]               = $request[ 'tms_engine' ];
            $projectStructure[ 'status' ]                   = Constants_ProjectStatus::STATUS_NOT_READY_FOR_ANALYSIS;
            $projectStructure[ 'owner' ]                    = $this->user->email;
            $projectStructure[ 'metadata' ]                 = $request[ 'metadata' ];
            $projectStructure[ 'pretranslate_100' ]         = (int)!!$request[ 'pretranslate_100' ]; // Force pretranslate_100 to be 0 or 1
            $projectStructure[ 'pretranslate_101' ]         = isset( $request[ 'pretranslate_101' ] ) ? (int)$request[ 'pretranslate_101' ] : 1;

            //default get all public matches from TM
            $projectStructure[ 'only_private' ] = ( !isset( $request[ 'get_public_matches' ] ) ? false : !$request[ 'get_public_matches' ] );

            $projectStructure[ 'user_ip' ]                               = Utils::getRealIpAddr();
            $projectStructure[ 'HTTP_HOST' ]                             = INIT::$HTTPHOST;
            $projectStructure[ 'due_date' ]                              = ( empty( $request[ 'due_date' ] ) ? null : Utils::mysqlTimestamp( $request[ 'due_date' ] ) );
            $projectStructure[ 'target_language_mt_engine_association' ] = $request[ 'target_language_mt_engine_association' ];
            $projectStructure[ 'instructions' ]                          = $request[ 'instructions' ];

            $projectStructure[ 'userIsLogged' ] = true;
            $projectStructure[ 'uid' ]          = $this->user->getUid();
            $projectStructure[ 'id_customer' ]  = $this->user->getEmail();
            $projectManager->setTeam( $request[ 'team' ] );

            $projectStructure[ 'ai_assistant' ]                 = (!empty($request[ 'ai_assistant' ])) ? $request[ 'ai_assistant' ] : null;
            $projectStructure[ 'dictation' ]                    = (!empty($request[ 'dictation' ])) ? $request[ 'dictation' ] : null;
            $projectStructure[ 'show_whitespace' ]              = (!empty($request[ 'show_whitespace' ])) ? $request[ 'show_whitespace' ] : null;
            $projectStructure[ 'character_counter_mode' ]       = (!empty($request[ 'character_counter_mode' ])) ? $request[ 'character_counter_mode' ] : null;
            $projectStructure[ 'character_counter_count_tags' ] = (!empty($request[ 'character_counter_count_tags' ])) ? $request[ 'character_counter_count_tags' ] : null;

            // mmtGlossaries
            if ( $request[ 'mmt_glossaries' ] ) {
                $projectStructure[ 'mmt_glossaries' ] = $request[ 'mmt_glossaries' ];
            }

            // DeepL
            $engine = Engine::getInstance( $request[ 'mt_engine' ] );
            if ( $engine instanceof Engines_DeepL and $request[ 'deepl_formality' ] !== null ) {
                $projectStructure[ 'deepl_formality' ] = $request[ 'deepl_formality' ];
            }

            if ( $engine instanceof Engines_DeepL and $request[ 'deepl_id_glossary' ] !== null ) {
                $projectStructure[ 'deepl_id_glossary' ] = $request[ 'deepl_id_glossary' ];
            }

            // with the qa template id
            if ( $request[ 'qaModelTemplate' ] ) {
                $projectStructure[ 'qa_model_template' ] = $request[ 'qaModelTemplate' ]->getDecodedModel();
            }

            if ( $request[ 'qaModel' ] ) {
                $projectStructure[ 'qa_model' ] = $request[ 'qaModel' ]->getDecodedModel();
            }

            if ( $request[ 'payableRateModelTemplate' ] ) {
                $projectStructure[ 'payable_rate_model_id' ] = $request[ 'payableRateModelTemplate' ]->id;
            }

            if ( $request[ 'dialect_strict' ] ) {
                $projectStructure[ 'dialect_strict' ] = $request[ 'dialect_strict' ];
            }

            if ( $request[ 'filters_extraction_parameters' ] ) {
                $projectStructure[ 'filters_extraction_parameters' ] = $request[ 'filters_extraction_parameters' ];
            }

            if ( $request[ 'xliff_parameters' ] ) {
                $projectStructure[ 'xliff_parameters' ] = $request[ 'xliff_parameters' ];
            }

            if ( $request[ 'mt_evaluation' ] ) {
                $projectStructure[ 'mt_evaluation' ] = true;
            }

            //set features override
            $projectStructure[ 'project_features' ] = $request[ 'project_features' ];

            try {
                $projectManager->sanitizeProjectStructure();
            } catch ( Exception $e ) {
                throw new RuntimeException( $e->getMessage(), -1 );
            }

            $fs::moveFileFromUploadSessionToQueuePath( $uploadFile->getDirUploadToken() );

            //reserve a project id from the sequence
            $projectStructure[ 'id_project' ] = Database::obtain()->nextSequence( Database::SEQ_ID_PROJECT )[ 0 ];
            $projectStructure[ 'ppassword' ]  = $projectManager->generatePassword();

            $projectStructure = $this->featureSet->filter( 'addNewProjectStructureAttributes', $projectStructure, $_POST );

            // flag to mark the project "from API"
            $projectStructure[ 'from_api' ] = true;

            Queue::sendProject( $projectStructure );

            $result[ 'errors' ] = $this->pollForCreationResult( $projectStructure );

            if ( $result == null ) {
                throw new TimeoutException( 'Project Creation Failure' );
            }

            if ( !empty( $result[ 'errors' ] ) ) {
                throw new RuntimeException( 'Project Creation Failure' );
            }

            return $this->response->json( [
                    'status'       => 'OK',
                    'message'      => 'Success',
                    'id_project'   => $projectStructure[ 'id_project' ],
                    'project_pass' => $projectStructure[ 'ppassword' ],
                    'new_keys'     => $request[ 'new_keys' ],
                    'analyze_url'  => $projectManager->getAnalyzeURL()
            ] );

        } catch ( Exception $exception ) {
            return $this->returnException( $exception );
        }
    }

    /**
     * @param $projectStructure
     *
     * @return mixed
     */
    private function pollForCreationResult( $projectStructure ) {
        return $projectStructure[ 'result' ][ 'errors' ]->getArrayCopy();
    }

    /**
     * @return void
     * @throws Exception
     */
    private function validateTheRequest(): array {
        $metadata                                  = filter_var( $this->request->param( 'metadata' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $segmentation_rule                         = filter_var( $this->request->param( 'segmentation_rule' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $project_name                              = filter_var( $this->request->param( 'project_name' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        $source_lang                               = filter_var( $this->request->param( 'source_lang' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        $target_lang                               = filter_var( $this->request->param( 'target_lang' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        $subject                                   = filter_var( $this->request->param( 'subject' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        $due_date                                  = filter_var( $this->request->param( 'due_date' ), FILTER_SANITIZE_NUMBER_INT );
        $mt_engine                                 = filter_var( $this->request->param( 'mt_engine' ), FILTER_SANITIZE_NUMBER_INT, [ 'filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => [ 'default' => 1, 'min_range' => 0 ] ] );
        $tms_engine                                = filter_var( $this->request->param( 'tms_engine' ), FILTER_VALIDATE_INT, [ 'filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => [ 'default' => 1, 'min_range' => 0 ] ] );
        $private_tm_key                            = filter_var( $this->request->param( 'private_tm_key' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        $private_tm_key_json                       = filter_var( $this->request->param( 'private_tm_key_json' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        $pretranslate_100                          = filter_var( $this->request->param( 'pretranslate_100' ), FILTER_SANITIZE_NUMBER_INT );
        $pretranslate_101                          = filter_var( $this->request->param( 'pretranslate_101' ), FILTER_SANITIZE_NUMBER_INT );
        $id_team                                   = filter_var( $this->request->param( 'id_team' ), FILTER_SANITIZE_NUMBER_INT, [ 'flags' => FILTER_REQUIRE_SCALAR ] );
        $project_completion                        = filter_var( $this->request->param( 'project_completion' ), FILTER_VALIDATE_BOOLEAN );
        $get_public_matches                        = filter_var( $this->request->param( 'get_public_matches' ), FILTER_VALIDATE_BOOLEAN );
        $dialect_strict                            = filter_var( $this->request->param( 'dialect_strict' ), FILTER_SANITIZE_STRING );
        $qa_model_template_id                      = filter_var( $this->request->param( 'qa_model_template_id' ), FILTER_SANITIZE_NUMBER_INT );
        $payable_rate_template_id                  = filter_var( $this->request->param( 'payable_rate_template_id' ), FILTER_SANITIZE_NUMBER_INT );
        $payable_rate_template_name                = filter_var( $this->request->param( 'payable_rate_template_name' ), FILTER_SANITIZE_STRING );
        $id_qa_model                               = filter_var( $this->request->param( 'id_qa_model' ), FILTER_SANITIZE_NUMBER_INT );
        $id_qa_model_template                      = filter_var( $this->request->param( 'id_qa_model_template' ), FILTER_SANITIZE_NUMBER_INT );
        $lexiqa                                    = filter_var( $this->request->param( 'lexiqa' ), FILTER_VALIDATE_BOOLEAN );
        $speech2text                               = filter_var( $this->request->param( 'speech2text' ), FILTER_VALIDATE_BOOLEAN );
        $tag_projection                            = filter_var( $this->request->param( 'tag_projection' ), FILTER_VALIDATE_BOOLEAN );
        $instructions                              = filter_var( $this->request->param( 'instructions' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_REQUIRE_ARRAY ] );
        $project_info                              = filter_var( $this->request->param( 'project_info' ), FILTER_SANITIZE_STRING );
        $mmt_glossaries                            = filter_var( $this->request->param( 'mmt_glossaries' ), FILTER_SANITIZE_STRING );
        $deepl_formality                           = filter_var( $this->request->param( 'deepl_formality' ), FILTER_SANITIZE_STRING );
        $deepl_id_glossary                         = filter_var( $this->request->param( 'deepl_id_glossary' ), FILTER_SANITIZE_STRING );
        $filters_extraction_parameters             = filter_var( $this->request->param( 'filters_extraction_parameters' ), FILTER_SANITIZE_STRING );
        $xliff_parameters                          = filter_var( $this->request->param( 'xliff_parameters' ), FILTER_SANITIZE_STRING );
        $filters_extraction_parameters_template_id = filter_var( $this->request->param( 'filters_extraction_parameters_template_id' ), FILTER_SANITIZE_NUMBER_INT );
        $xliff_parameters_template_id              = filter_var( $this->request->param( 'xliff_parameters_template_id' ), FILTER_SANITIZE_NUMBER_INT );
        $mt_qe_workflow_enable                     = filter_var( $this->request->param( 'mt_qe_workflow_enable' ), FILTER_VALIDATE_BOOLEAN );
        $mt_qe_workflow_template_id                = filter_var( $this->request->param( 'mt_qe_workflow_qe_model_id' ), FILTER_SANITIZE_NUMBER_INT ) ?: null;         // QE workflow parameters
        $mt_qe_workflow_template_raw_parameters    = filter_var( $this->request->param( 'mt_qe_workflow_template_raw_parameters' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] ) ?: null;  // QE workflow parameters in raw string JSON format
        $mt_qe_workflow_payable_rate_template_id   = filter_var( $this->request->param( 'mt_qe_workflow_payable_rate_template_id' ), FILTER_SANITIZE_NUMBER_INT ) ?: null;         // QE workflow parameters
        $mt_quality_value_in_editor                = filter_var( $this->request->param( 'mt_quality_value_in_editor' ), FILTER_SANITIZE_NUMBER_INT ) ?: 85; // used to set the absolute value of an MT match (previously fixed to 85) //YYY
        $mt_evaluation                             = filter_var( $this->request->param( 'mt_evaluation' ), FILTER_VALIDATE_BOOLEAN );
        $character_counter_count_tags              = filter_var( $this->request->param( 'character_counter_count_tags' ), FILTER_VALIDATE_BOOLEAN );
        $character_counter_mode                    = filter_var( $this->request->param( 'character_counter_mode' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW ] );
        $dictation                                 = filter_var( $this->request->param( 'dictation' ), FILTER_VALIDATE_BOOLEAN );
        $show_whitespace                           = filter_var( $this->request->param( 'show_whitespace' ), FILTER_VALIDATE_BOOLEAN );
        $ai_assistant                              = filter_var( $this->request->param( 'ai_assistant' ), FILTER_VALIDATE_BOOLEAN );

        /**
         * Uber plugin callback
         */
        $instructions = $this->featureSet->filter( 'encodeInstructions', $instructions ?? null );

        if ( empty( $_FILES ) ) {
            throw new InvalidArgumentException( "Missing file. Not Sent." );
        }

        $lang_handler = Languages::getInstance();

        $source_lang = $this->validateSourceLang( $lang_handler, $source_lang );
        $target_lang = $this->validateTargetLangs( $lang_handler, $target_lang );
        [ $tms_engine, $mt_engine ] = $this->validateEngines( $tms_engine, $mt_engine ); // YYY Fix bug
        $subject           = $this->validateSubject( $subject );
        $segmentation_rule = $this->validateSegmentationRules( $segmentation_rule );
        [ $private_tm_user, $private_tm_pass, $private_tm_key, $new_keys, $tm_prioritization ] = $this->validateTmAndKeys( $private_tm_key, $private_tm_key_json );
        $team                                  = $this->validateTeam( $id_team );
        $qaModelTemplate                       = $this->validateQaModelTemplate( $id_qa_model_template );
        $payableRateModelTemplate              = $this->validatePayableRateTemplate( $payable_rate_template_name, $payable_rate_template_id );
        $qaModel                               = $this->validateQaModel( $id_qa_model );
        $mt_engine                             = $this->validateUserMTEngine( $mt_engine );
        $mmt_glossaries                        = $this->validateMMTGlossaries( $mmt_glossaries );
        $deepl_formality                       = $this->validateDeepLFormality( $deepl_formality );
        $dialect_strict                        = $this->validateDialectStrictParam( $target_lang, $dialect_strict );
        $filters_extraction_parameters         = $this->validateFiltersExtractionParameters( $filters_extraction_parameters, $filters_extraction_parameters_template_id );
        $xliff_parameters                      = $this->validateXliffParameters( $xliff_parameters, $xliff_parameters_template_id );
        $metadata                              = $this->validateMetadataParam( $metadata );
        $project_features                      = $this->appendFeaturesToProject( (bool)$project_completion, $mt_engine );
        $target_language_mt_engine_association = $this->generateTargetEngineAssociation( $target_lang, $mt_engine );

        if ( $mt_qe_workflow_enable ) {
            $metadata[ 'mt_qe_workflow_enable' ]     = $mt_qe_workflow_enable;
            $metadata[ 'mt_qe_workflow_parameters' ] = $this->validateMTQEParametersOrDefault( $mt_qe_workflow_template_id, $mt_qe_workflow_template_raw_parameters ); // or default
            if ( !empty( $mt_qe_workflow_payable_rate_template_id ) ) {
                $metadata[ 'mt_qe_workflow_payable_rate_template_id' ] = $mt_qe_workflow_payable_rate_template_id; // YYY TODO or default
            }
        }

        if ( !empty( $project_info ) ) {
            $metadata[ 'project_info' ] = $project_info;
        }

        if ( !empty( $dialect_strict ) ) {
            $metadata[ 'dialect_strict' ] = $dialect_strict;
        }

        if ( !empty( $lexiqa ) ) {
            $metadata[ 'lexiqa' ] = $lexiqa;
        }

        if ( !empty( $speech2text ) ) {
            $metadata[ 'speech2text' ] = $speech2text;
        }

        if ( !empty( $tag_projection ) ) {
            $metadata[ 'tag_projection' ] = $tag_projection;
        }

        if ( !empty( $project_completion ) ) {
            $metadata[ 'project_completion' ] = $project_completion;
        }

        if ( !empty( $segmentation_rule ) ) {
            $metadata[ 'segmentation_rule' ] = $segmentation_rule;
        }

        if ( $mt_quality_value_in_editor ) {
            $metadata[ 'mt_quality_value_in_editor' ] = $mt_quality_value_in_editor;
        }

        return [
                'project_info'                              => $project_info,
                'project_name'                              => $project_name,
                'source_lang'                               => $source_lang,
                'target_lang'                               => $target_lang,
                'subject'                                   => $subject,
                'pretranslate_100'                          => $pretranslate_100,
                'pretranslate_101'                          => $pretranslate_101,
                'id_team'                                   => $id_team,
                'team'                                      => $team,
                'mmt_glossaries'                            => $mmt_glossaries,
                'deepl_id_glossary'                         => $deepl_id_glossary,
                'deepl_formality'                           => $deepl_formality,
                'project_completion'                        => $project_completion,
                'get_public_matches'                        => $get_public_matches,
                'dialect_strict'                            => $dialect_strict,
                'filters_extraction_parameters'             => $filters_extraction_parameters,
                'xliff_parameters'                          => $xliff_parameters,
                'filters_extraction_parameters_template_id' => $filters_extraction_parameters_template_id,
                'qa_model_template_id'                      => $qa_model_template_id,
                'payable_rate_template_id'                  => $payable_rate_template_id,
                'tms_engine'                                => $tms_engine,
                'mt_engine'                                 => $mt_engine,
                'private_tm_key'                            => $private_tm_key,
                'private_tm_user'                           => $private_tm_user,
                'private_tm_pass'                           => $private_tm_pass,
                'tm_prioritization'                         => $tm_prioritization,
                'new_keys'                                  => $new_keys,
                'due_date'                                  => $due_date,
                'id_qa_model'                               => $id_qa_model,
                'qaModel'                                   => $qaModel,
                'metadata'                                  => $metadata,
                'segmentation_rule'                         => $segmentation_rule,
                'id_qa_model_template'                      => $id_qa_model_template,
                'qaModelTemplate'                           => $qaModelTemplate,
                'payableRateModelTemplate'                  => $payableRateModelTemplate,
                'instructions'                              => $instructions,
                'lexiqa'                                    => $lexiqa,
                'speech2text'                               => $speech2text,
                'tag_projection'                            => $tag_projection,
                'project_features'                          => $project_features,
                'mt_evaluation'                             => $mt_evaluation,
                'character_counter_count_tags'              => $character_counter_count_tags,
                'character_counter_mode'                    => $character_counter_mode,
                'dictation'                                 => $dictation,
                'show_whitespace'                           => $show_whitespace,
                'ai_assistant'                              => $ai_assistant,
                'target_language_mt_engine_association'     => $target_language_mt_engine_association
        ];
    }

    /**
     * Expects the metadata param to be a json formatted string and tries to convert it
     * in array.
     * Json string is expected to be flat key value, this is enforced padding 1 to json
     * conversion depth param.
     *
     *
     * @throws Exception
     */
    private function validateMetadataParam( $metadata = null ) {
        if ( !empty( $metadata ) ) {

            if ( strlen( $metadata ) > 2048 ) {
                throw new InvalidArgumentException( 'metadata string is too long' );
            }

            $depth          = 2; // only converts key value structures
            $metadata       = html_entity_decode( $metadata );
            $parsedMetadata = json_decode( $metadata, true, $depth );

            if ( is_array( $parsedMetadata ) ) {
                $metadata = $parsedMetadata;
            }

            Log::doJsonLog( "Passed parameter metadata as json string." );
        } else {
            $metadata = [];
        }

        // new raw counter model
        $metadata[ Projects_MetadataDao::WORD_COUNT_TYPE_KEY ] = Projects_MetadataDao::WORD_COUNT_RAW;

        return $metadata;

    }

    /**
     * @param int $tms_engine
     * @param int $mt_engine
     *
     * @return array
     * @throws Exception
     */
    private function validateEngines( int $tms_engine, int $mt_engine ): array {

        if ( $tms_engine > 1 ) {
            throw new InvalidArgumentException( "Invalid TM Engine.", -21 );
        }

        if ( $mt_engine > 1 ) {

            if ( !$this->userIsLogged ) {
                throw new InvalidArgumentException( "Invalid MT Engine.", -2 );
            }

            try {
                EngineValidator::engineBelongsToUser( $mt_engine, $this->user->uid );
            } catch ( Exception $exception ) {
                throw new InvalidArgumentException( $exception->getMessage(), -2 );
            }

        }

        return [ $tms_engine, $mt_engine ];
    }

    /**
     * @param $subject
     *
     * @return string
     */
    private function validateSubject( $subject ): string {
        $langDomains = LanguageDomains::getInstance();
        $subjectMap  = $langDomains::getEnabledHashMap();

        $subject = ( !empty( $subject ) ) ? $subject : 'general';

        if ( empty( $subjectMap[ $subject ] ) ) {
            throw new InvalidArgumentException( "Subject not allowed: " . $subject, -3 );
        }

        return $subject;
    }

    /**
     * @param Languages $lang_handler
     * @param           $source_lang
     *
     * @return string
     */
    private function validateSourceLang( Languages $lang_handler, $source_lang ): string {
        try {
            $lang_handler->validateLanguage( $source_lang );

            return $source_lang;
        } catch ( Exception $e ) {
            throw new InvalidArgumentException( "Missing source language." );
        }
    }

    /**
     * @param Languages $lang_handler
     * @param           $target_lang
     *
     * @return string
     */
    private function validateTargetLangs( Languages $lang_handler, $target_lang ): string {
        $targets = explode( ',', $target_lang );
        $targets = array_map( 'trim', $targets );
        $targets = array_unique( $targets );

        if ( empty( $targets ) ) {
            throw new InvalidArgumentException( "Missing target language." );
        }

        try {
            foreach ( $targets as $target ) {
                $lang_handler->validateLanguage( $target );
            }
        } catch ( Exception $e ) {
            throw new InvalidArgumentException( $e->getMessage() );
        }

        return implode( ',', $targets );
    }

    /**
     * @param bool $project_completion
     * @param int  $mt_engine
     *
     * @return array
     * @throws Exception
     */
    private function appendFeaturesToProject( bool $project_completion, int $mt_engine ): array {
        $projectFeatures = [];

        if ( $project_completion ) {
            $feature                                   = new BasicFeatureStruct();
            $feature->feature_code                     = 'project_completion';
            $projectFeatures[ $feature->feature_code ] = $feature;
        }

        return $this->featureSet->filter(
                'filterCreateProjectFeatures', $projectFeatures, $this, $mt_engine
        );
    }

    /**
     * @param      $target_langs
     * @param      $mt_engine
     *
     * @return array
     * @see filterCreateProjectFeatures callback
     * @see NewController::appendFeaturesToProject()
     * @deprecated
     */
    private function generateTargetEngineAssociation( $target_langs, $mt_engine ): ?array { // TODO YYY remove map association, MMT now supports all languages. Remove from ProjectManager also
        $assoc = [];

        foreach ( explode( ",", $target_langs ) as $_matecatTarget ) {
            $assoc[ $_matecatTarget ] = $mt_engine;
        }

        return $assoc;
    }

    /**
     * @param $segmentation_rule
     *
     * @return string|null
     * @throws Exception
     */
    private function validateSegmentationRules( $segmentation_rule ): ?string {
        return Constants::validateSegmentationRules( $segmentation_rule );
    }

    /**
     * @param string $private_tm_key
     * @param string $private_tm_key_json
     *
     * @return array
     * @throws Exception
     */
    protected function validateTmAndKeys( string $private_tm_key = "", string $private_tm_key_json = "" ): array {

        $new_keys          = [];
        $private_tm_user   = null;
        $private_tm_pass   = null;
        $tm_prioritization = null;

        try {
            if ( !empty( $private_tm_key_json ) ) {
                $json = html_entity_decode( $private_tm_key_json );

                // first check if `filters_extraction_parameters` is a valid JSON
                if ( !Utils::isJson( $json ) ) {
                    throw new Exception( "private_tm_key_json is not a valid JSON" );
                }

                $schema = file_get_contents( INIT::$ROOT . '/inc/validation/schema/private_tm_key_json.json' );

                $validatorObject       = new JSONValidatorObject();
                $validatorObject->json = $json;

                $validator  = new JSONValidator( $schema );
                $validator->validate( $validatorObject );

                $privateTmKeyJsonObject = json_decode($json);
                $tm_prioritization = $privateTmKeyJsonObject->tm_prioritization;

                $private_tm_key = array_map(
                        function ( $item ) {
                            return [
                                    'key'     => $item->key,
                                    'r'       => $item->read,
                                    'w'       => $item->write,
                                    'penalty' => $item->penalty,
                            ];
                        },
                        $privateTmKeyJsonObject->keys
                );

            } else {

                /**
                 * ----------------------------------
                 * Note 2021-05-28
                 * ----------------------------------
                 *
                 * We trim every space private_tm_key
                 *  to avoid misspelling errors
                 *
                 */
                $private_tm_key = preg_replace( "/\s+/", "", $private_tm_key );
                $private_tm_key = array_map(
                        [ $this, 'parseTmKeyInput' ],
                        explode( ",", $private_tm_key )
                );

            }
        } catch ( Exception $e ) {
            throw new InvalidArgumentException( $e->getMessage(), -6 );
        }

        if ( count( $private_tm_key ) > self::MAX_NUM_KEYS ) {
            throw new Exception( "Too much keys provided. Max number of keys is " . self::MAX_NUM_KEYS, -2 );
        }

        $private_tm_key = array_values( array_filter( $private_tm_key ) );

        //If a TMX file has been uploaded and no key was provided, create a new key.
        if ( empty( $private_tm_key ) ) {
            foreach ( $_FILES as $_fileinfo ) {
                $pathinfo = AbstractFilesStorage::pathinfo_fix( $_fileinfo[ 'name' ] );
                if ( $pathinfo[ 'extension' ] == 'tmx' ) {
                    $private_tm_key[] = [ 'key' => 'new' ];
                    break;
                }
            }
        }

        //remove all empty entries
        foreach ( $private_tm_key as $__key_idx => $tm_key ) {
            //from api a key is sent and the value is 'new'
            if ( $tm_key[ 'key' ] == 'new' ) {

                try {
                    $APIKeySrv = new TMSService();
                    $newUser   = $APIKeySrv->createMyMemoryKey();

                    $private_tm_user = $newUser->id;
                    $private_tm_pass = $newUser->pass;

                    $private_tm_key[ $__key_idx ] =
                            [
                                    'key'     => $newUser->key,
                                    'name'    => null,
                                    'penalty' => $tm_key[ 'penalty' ] ?? null,
                                    'r'       => $tm_key[ 'r' ],
                                    'w'       => $tm_key[ 'w' ]

                            ];
                    $new_keys[]                   = $newUser->key;

                } catch ( Exception $e ) {
                    throw new Exception( $e->getMessage(), -1 );
                }

            } //if a string is sent, transform it into a valid array
            elseif ( !empty( $tm_key ) ) {

                $uid = $this->user->uid;

                $this_tm_key = [
                        'key'     => $tm_key[ 'key' ],
                        'name'    => null,
                        'penalty' => $tm_key[ 'penalty' ] ?? null,
                        'r'       => $tm_key[ 'r' ],
                        'w'       => $tm_key[ 'w' ]
                ];

                /**
                 * Get the key description/name from the user keyring
                 */
                if ( $uid ) {
                    $mkDao = new TmKeyManagement_MemoryKeyDao();

                    /**
                     * @var $keyRing TmKeyManagement_MemoryKeyStruct[]
                     */
                    $keyRing = $mkDao->read(
                            ( new TmKeyManagement_MemoryKeyStruct( [
                                    'uid'    => $uid,
                                    'tm_key' => new TmKeyManagement_TmKeyStruct( $this_tm_key )
                            ] )
                            )
                    );

                    if ( count( $keyRing ) > 0 ) {
                        $this_tm_key[ 'name' ] = $keyRing[ 0 ]->tm_key->name;
                    }
                }

                $private_tm_key[ $__key_idx ] = $this_tm_key;
            }

            $private_tm_key[ $__key_idx ] = $this->sanitizeTmKeyArr( $private_tm_key[ $__key_idx ] );
        }

        return [
                $private_tm_user,
                $private_tm_pass,
                $private_tm_key,
                $new_keys,
                $tm_prioritization,
        ];
    }

    /**
     * @param $elem
     *
     * @return array
     */
    private static function sanitizeTmKeyArr( $elem ): array {

        $element                  = new TmKeyManagement_TmKeyStruct( $elem );
        $element->complete_format = true;
        $elem                     = TmKeyManagement_TmKeyManagement::sanitize( $element );

        return $elem->toArray();
    }

    /**
     * @param null $id_team
     *
     * @return TeamStruct|null
     *
     * @throws Exception
     */
    private function validateTeam( $id_team = null ): ?TeamStruct {
        if ( !empty( $id_team ) ) {
            $dao = new MembershipDao();
            $org = $dao->findTeamByIdAndUser( $id_team, $this->user );

            if ( !$org ) {
                throw new Exception( 'Team and user membership does not match', -1 );
            }

            return $org;
        }

        return $this->user->getPersonalTeam();
    }

    /**
     * @param null $id_qa_model_template
     *
     * @return QAModelTemplateStruct|null
     * @throws Exception
     */
    private function validateQaModelTemplate( $id_qa_model_template = null ): ?QAModelTemplateStruct {
        if ( !empty( $id_qa_model_template ) ) {
            $qaModelTemplate = QAModelTemplateDao::get( [
                    'id'  => $id_qa_model_template,
                    'uid' => $this->getUser()->uid
            ] );

            // check if qa_model template exists
            if ( null === $qaModelTemplate ) {
                throw new InvalidArgumentException( 'This QA Model template does not exists or does not belongs to the logged in user' );
            }

            return $qaModelTemplate;
        }

        return null;
    }

    /**
     * @param $payable_rate_template_name
     * @param $payable_rate_template_id
     *
     * @return CustomPayableRateStruct|null
     * @throws Exception
     */
    private function validatePayableRateTemplate( $payable_rate_template_name = null, $payable_rate_template_id = null ): ?CustomPayableRateStruct {
        $payableRateModelTemplate = null;

        if ( !empty( $payable_rate_template_name ) ) {
            if ( empty( $payable_rate_template_id ) ) {
                throw new InvalidArgumentException( '`payable_rate_template_id` param is missing' );
            }
        }

        if ( !empty( $payable_rate_template_id ) ) {
            if ( empty( $payable_rate_template_name ) ) {
                throw new InvalidArgumentException( '`payable_rate_template_name` param is missing' );
            }
        }

        if ( !empty( $payable_rate_template_name ) and !empty( $payable_rate_template_id ) ) {

            $userId                   = $this->getUser()->uid;
            $payableRateModelTemplate = CustomPayableRateDao::getByIdAndUser( $payable_rate_template_id, $userId );

            if ( null === $payableRateModelTemplate ) {
                throw new InvalidArgumentException( 'Payable rate model id not valid' );
            }

            if ( $payableRateModelTemplate->name !== $payable_rate_template_name ) {
                throw new InvalidArgumentException( 'Payable rate model name not matching' );
            }
        }

        return $payableRateModelTemplate;
    }

    /**
     * Checks if id_qa_model is valid
     *
     * @param null $id_qa_model
     *
     * @return ModelStruct|null
     * @throws Exception
     */
    private function validateQaModel( $id_qa_model = null ): ?ModelStruct {
        if ( !empty( $id_qa_model ) ) {

            $qaModel = ModelDao::findById( $id_qa_model );

            // check if qa_model exists
            if ( null === $qaModel ) {
                throw new InvalidArgumentException( 'This QA Model does not exists' );
            }

            // check featureSet
            $qaModelLabel    = strtolower( $qaModel->label );
            $featureSetCodes = $this->getFeatureSet()->getCodes();

            if ( $qaModelLabel !== 'default' and !in_array( $qaModelLabel, $featureSetCodes ) ) {
                throw new InvalidArgumentException( 'This QA Model does not belong to the authenticated user' );
            }

            return $qaModel;
        }

        return null;
    }

    /**
     * @param null $mt_engine
     *
     * @return string|null
     * @throws Exception
     */
    private function validateUserMTEngine( $mt_engine = null ): ?string {
        // any other engine than MyMemory
        if ( $mt_engine !== null and $mt_engine > 1 ) {
            try {
                EngineValidator::engineBelongsToUser( $mt_engine, $this->user->uid );
            } catch ( Exception $exception ) {
                throw new InvalidArgumentException( $exception->getMessage() );
            }
        }

        return $mt_engine;
    }

    /**
     * @param null $mmt_glossaries
     *
     * @return string|null
     */
    private function validateMMTGlossaries( $mmt_glossaries = null ): ?string {
        if ( !empty( $mmt_glossaries ) ) {
            try {
                $mmtGlossaries = html_entity_decode( $mmt_glossaries );
                MMTValidator::validateGlossary( $mmtGlossaries );

                return $mmtGlossaries;
            } catch ( Exception $exception ) {
                throw new InvalidArgumentException( $exception->getMessage() );
            }
        }

        return null;
    }

    /**
     * Validate DeepL params
     *
     * @param null $deepl_formality
     *
     * @return string|null
     */
    private function validateDeepLFormality( $deepl_formality = null ): ?string {

        if ( !empty( $deepl_formality ) ) {

            $allowedFormalities = [
                    'default',
                    'prefer_less',
                    'prefer_more'
            ];

            if ( !in_array( $deepl_formality, $allowedFormalities ) ) {
                throw new InvalidArgumentException( "Incorrect DeepL formality value (default, prefer_less and prefer_more are the allowed values)" );
            }

            return $deepl_formality;
        }

        return null;
    }

    /**
     * Validate `dialect_strict` param vs target languages
     *
     * Example: {"it-IT": true, "en-US": false, "fr-FR": false}
     *
     * @param      $target_lang
     * @param null $dialect_strict
     *
     * @return string|null
     */
    private function validateDialectStrictParam( $target_lang, $dialect_strict = null ): ?string {
        if ( !empty( $dialect_strict ) ) {

            $dialect_strict   = trim( html_entity_decode( $dialect_strict ) );
            $target_languages = preg_replace( '/\s+/', '', $target_lang );
            $targets          = explode( ',', trim( $target_languages ) );

            // first check if `dialect_strict` is a valid JSON
            if ( !Utils::isJson( $dialect_strict ) ) {
                throw new InvalidArgumentException( "dialect_strict is not a valid JSON" );
            }

            $dialectStrictObj = json_decode( $dialect_strict, true );

            foreach ( $dialectStrictObj as $lang => $value ) {
                if ( !in_array( $lang, $targets ) ) {
                    throw new InvalidArgumentException( 'Wrong `dialect_strict` object, language, ' . $lang . ' is not one of the project target languages' );
                }

                if ( !is_bool( $value ) ) {
                    throw new InvalidArgumentException( 'Wrong `dialect_strict` object, not boolean declared value for ' . $lang );
                }
            }

            return html_entity_decode( $dialect_strict );
        }

        return null;
    }

    /**
     * @param null $filters_extraction_parameters
     * @param null $filters_extraction_parameters_template_id
     *
     * @return FiltersConfigTemplateStruct|mixed|null
     * @throws Exception
     */
    private function validateFiltersExtractionParameters( $filters_extraction_parameters = null, $filters_extraction_parameters_template_id = null ) {
        if ( !empty( $filters_extraction_parameters ) ) {

            $json = html_entity_decode( $filters_extraction_parameters );

            // first check if `filters_extraction_parameters` is a valid JSON
            if ( !Utils::isJson( $json ) ) {
                throw new InvalidArgumentException( "filters_extraction_parameters is not a valid JSON" );
            }

            $schema = file_get_contents( INIT::$ROOT . '/inc/validation/schema/filters_extraction_parameters.json' );

            $validatorObject       = new JSONValidatorObject();
            $validatorObject->json = $json;

            $validator = new JSONValidator( $schema );
            $validator->validate( $validatorObject );

            return json_decode( $json );

        }

        if ( !empty( $filters_extraction_parameters_template_id ) ) {

            $filtersTemplate = FiltersConfigTemplateDao::getByIdAndUser( $filters_extraction_parameters_template_id, $this->getUser()->uid );

            if ( $filtersTemplate === null ) {
                throw new InvalidArgumentException( "filters_extraction_parameters_template_id not valid" );
            }

            return $filtersTemplate;
        }

        return null;
    }

    /**
     * YYY json validation
     *
     * @param int|null    $mt_qe_workflow_template_id
     * @param string|null $mt_qe_workflow_template_raw_parameters
     *
     * @return MTQEWorkflowParams
     * @throws Exception
     */
    private function validateMTQEParametersOrDefault( ?int $mt_qe_workflow_template_id = null, ?string $mt_qe_workflow_template_raw_parameters = null ): MTQEWorkflowParams {

        if ( !empty( $mt_qe_workflow_template_raw_parameters ) ) {

            $json = html_entity_decode( $mt_qe_workflow_template_raw_parameters );

            // first check if `mt_qe_workflow_template_raw_parameters` is a valid JSON
            if ( !Utils::isJson( $json ) ) {
                throw new InvalidArgumentException( "mt_qe_workflow_template_raw_parameters is not a valid JSON" );
            }

            $schema = file_get_contents( INIT::$ROOT . '/inc/validation/schema/mt_qe_workflow_params.json' );

            $validatorObject       = new JSONValidatorObject();
            $validatorObject->json = $json;

            $validator  = new JSONValidator( $schema, true );
            $jsonObject = $validator->validate( $validatorObject );

            return new MTQEWorkflowParams( (array)( $jsonObject->decoded ) );

        } elseif ( !empty( $mt_qe_workflow_template_id ) ) {

            $mtQeWorkflowTemplate = MTQEWorkflowTemplateDao::getByIdAndUser( $mt_qe_workflow_template_id, $this->getUser()->uid );

            if ( $mtQeWorkflowTemplate === null ) {
                throw new InvalidArgumentException( "mt_qe_workflow_template_id not valid" );
            }

            return $mtQeWorkflowTemplate->params;

        } else {
            return new MTQEWorkflowParams();
        }

    }

    /**
     * @param null $xliff_parameters
     * @param null $xliff_parameters_template_id
     *
     * @return array|mixed|null
     * @throws Exception
     */
    private function validateXliffParameters( $xliff_parameters = null, $xliff_parameters_template_id = null ) {
        if ( !empty( $xliff_parameters ) ) {

            $json = html_entity_decode( $xliff_parameters );

            // first check if `xliff_parameters` is a valid JSON
            if ( !Utils::isJson( $json ) ) {
                throw new InvalidArgumentException( "xliff_parameters is not a valid JSON" );
            }

            $schema = file_get_contents( INIT::$ROOT . '/inc/validation/schema/xliff_parameters_rules_content.json' );

            $validatorObject       = new JSONValidatorObject();
            $validatorObject->json = $json;

            $validator = new JSONValidator( $schema, true );
            $validator->validate( $validatorObject );

            return json_decode( $json, true ); // decode again because we need an associative array and not stdClass
        }

        if ( !empty( $xliff_parameters_template_id ) ) {

            $xliffConfigTemplate = XliffConfigTemplateDao::getByIdAndUser( $xliff_parameters_template_id, $this->getUser()->uid );

            if ( $xliffConfigTemplate === null ) {
                throw new InvalidArgumentException( "xliff_parameters_template_id not valid" );
            }

            return $xliffConfigTemplate->rules->getArrayCopy();
        }

        return null;
    }

    /**
     * @param $tmKeyString
     *
     * @return array|null
     * @throws Exception
     */
    private function parseTmKeyInput( $tmKeyString ): ?array {
        $tmKeyString = trim( $tmKeyString );
        $tmKeyInfo   = explode( ":", $tmKeyString );
        $read        = true;
        $write       = true;

        $permissionString = $tmKeyInfo[ 1 ] ?? null;

        //if the key is not set, return null. It will be filtered in the next lines.
        if ( empty( $tmKeyInfo[ 0 ] ) ) {
            return null;
        } //if permissions are set, check if they are allowed or not and eventually set permissions

        //permission string check
        switch ( $permissionString ) {
            case 'r':
                $write = false;
                break;
            case 'w':
                $read = false;
                break;
            case 'rw':
            case ''  :
            case null:
                break;
            //permission string not allowed
            default:
                $allowed_permissions = implode( ", ", Constants_TmKeyPermissions::$_accepted_grants );
                throw new Exception( "Invalid permission modifier string. Allowed: <empty>, $allowed_permissions" );
        }

        return [
                'key' => $tmKeyInfo[ 0 ],
                'r'   => $read,
                'w'   => $write,
        ];
    }

    /**
     * @param $filename
     *
     * @return array
     * @throws Exception
     */
    private function getFileMetadata( $filename ): array {
        $info          = XliffProprietaryDetect::getInfo( $filename );
        $isXliff       = XliffFiles::isXliff( $filename );
        $isGlossary    = XliffFiles::isGlossaryFile( $filename );
        $isTMX         = XliffFiles::isTMXFile( $filename );
        $getMemoryType = XliffFiles::getMemoryFileType( $filename );

        $forceXliff      = $this->getFeatureSet()->filter(
                'forceXLIFFConversion',
                INIT::$FORCE_XLIFF_CONVERSION,
                $this->userIsLogged,
                $info[ 'info' ][ 'dirname' ] . DIRECTORY_SEPARATOR . "$filename"
        );
        $mustBeConverted = XliffProprietaryDetect::fileMustBeConverted( $filename, $forceXliff, INIT::$FILTERS_ADDRESS );

        $metadata                      = [];
        $metadata[ 'basename' ]        = $info[ 'info' ][ 'basename' ];
        $metadata[ 'dirname' ]         = $info[ 'info' ][ 'dirname' ];
        $metadata[ 'extension' ]       = $info[ 'info' ][ 'extension' ];
        $metadata[ 'filename' ]        = $info[ 'info' ][ 'filename' ];
        $metadata[ 'mustBeConverted' ] = $mustBeConverted;
        $metadata[ 'getMemoryType' ]   = $getMemoryType;
        $metadata[ 'isXliff' ]         = $isXliff;
        $metadata[ 'isGlossary' ]      = $isGlossary;
        $metadata[ 'isTMX' ]           = $isTMX;
        $metadata[ 'proprietary' ]     = [
                'proprietary'            => $info[ 'proprietary' ],
                'proprietary_name'       => $info[ 'proprietary_name' ],
                'proprietary_short_name' => $info[ 'proprietary_short_name' ],
        ];

        return $metadata;
    }
}