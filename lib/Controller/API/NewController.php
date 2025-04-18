<?php

use API\Commons\Exceptions\AuthenticationError;
use Constants\ConversionHandlerStatus;
use Conversion\ConvertedFileModel;
use Exceptions\NotFoundException;
use Exceptions\ValidationError;
use FilesStorage\AbstractFilesStorage;
use FilesStorage\FilesStorageFactory;
use Filters\FiltersConfigTemplateDao;
use Langs\LanguageDomains;
use Langs\Languages;
use LQA\ModelDao;
use LQA\ModelStruct;
use Matecat\XliffParser\Utils\Files as XliffFiles;
use Matecat\XliffParser\XliffUtils\XliffProprietaryDetect;
use PayableRates\CustomPayableRateDao;
use PayableRates\CustomPayableRateStruct;
use ProjectQueue\Queue;
use QAModelTemplate\QAModelTemplateDao;
use QAModelTemplate\QAModelTemplateStruct;
use TaskRunner\Exceptions\EndQueueException;
use TaskRunner\Exceptions\ReQueueException;
use Teams\MembershipDao;
use TMS\TMSService;
use Validator\EngineValidator;
use Validator\JSONValidator;
use Validator\JSONValidatorObject;
use Validator\MMTValidator;
use Xliff\XliffConfigTemplateDao;

//limit execution time to 300 seconds
set_time_limit( 300 );

/**
 *
 * Create new Project on Matecat With HTTP POST ( multipart/form-data ) protocol
 *
 * POST Params:
 *
 * 'project_name'       => (string) The name of the project you want create
 * 'source_lang'        => (string) RFC 3066 language Code ( en-US )
 * 'target_lang'        => (string) RFC 3066 language(s) Code. Comma separated ( it-IT,fr-FR,es-ES )
 * 'tms_engine'         => (int)    Identifier for Memory Server ( ZERO means disabled, ONE means MyMemory )
 * 'mt_engine'          => (int)    Identifier for TM Server ( ZERO means disabled, ONE means MyMemory )
 * 'private_tm_key'     => (string) Private Key for MyMemory ( set to new to create a new one )
 *
 */
class NewController extends ajaxController {

    /**
     * @var array
     */
    private $private_tm_key;

    /**
     * @var array
     */
    private $tm_prioritization = false;

    private $private_tm_user = null;
    private $private_tm_pass = null;

    protected $new_keys = [];

    /**
     * @var Engines_AbstractEngine
     */
    private $mt_engine;

    /**
     * @var BasicFeatureStruct[]
     */
    private $projectFeatures = [];

    private $metadata = [];

    const MAX_NUM_KEYS = 10;

    private static $allowed_seg_rules = [
            'standard',
            'patent',
            'paragraph',
            ''
    ];

    protected $api_output = [
            'status'  => 'FAIL',
            'message' => 'Untraceable error (sorry, not mapped)'
    ];

    /**
     * @var \Teams\TeamStruct
     */
    protected $team;

    /**
     * @var ModelStruct
     */
    protected $qaModel;

    protected $projectStructure;

    /**
     * @var QAModelTemplateStruct
     */
    protected $qaModelTemplate;

    /**
     * @var CustomPayableRateStruct
     */
    protected $payableRateModelTemplate;

    /**
     * @var ProjectManager
     */
    protected $projectManager;

    public $postInput;

    /**
     * @var AbstractFilesStorage
     */
    protected $files_storage;

    protected $httpHeader = "HTTP/1.0 200 OK";

    /**
     * @var array
     */
    private $mmtGlossaries;

    private $deepl_formality;

    private $deepl_id_glossary;

    private $dialect_strict;

    private $filters_extraction_parameters;

    private $xliff_parameters;

    // LEGACY PARAMS TO BE REMOVED
    private $dictation;
    private $show_whitespace;
    private $character_counter;
    private $ai_assistant;

    private $character_counter_count_tags;
    private $character_counter_mode;

    private function setBadRequestHeader() {
        $this->httpHeader = 'HTTP/1.0 400 Bad Request';
    }

    private function setUnauthorizedHeader() {
        $this->httpHeader = 'HTTP/1.0 401 Unauthorized';
    }

    private function setInternalErrorHeader() {
        $this->httpHeader = 'HTTP/1.0 500 Internal Server Error';
    }

    private function setInternalTimeoutHeader() {
        $this->httpHeader = 'HTTP/1.0 504 Gateway Timeout';
    }

    public function __construct() {

        parent::__construct();

        //force client to close connection, avoid UPLOAD_ERR_PARTIAL for keep-alive connections
        header( "Connection: close" );

        if ( !$this->isLoggedIn() ) {
            $this->api_output[ 'message' ] = 'Project Creation Failure';
            $this->api_output[ 'debug' ]   = 'Authentication failed';
            $this->setUnauthorizedHeader();
            $this->finalize();
            die();
        }

        if ( $this->userIsLogged ) {
            $this->featureSet->loadFromUserEmail( $this->user->email );
        }

        $filterArgs = [
                'project_name'               => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'source_lang'                => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'target_lang'                => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'due_date'                   => [ 'filter' => FILTER_VALIDATE_INT ],
                'tms_engine'                 => [
                        'filter'  => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR,
                        'options' => [ 'default' => 1, 'min_range' => 0 ]
                ],
                'mt_engine'                  => [
                        'filter'  => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR,
                        'options' => [ 'default' => 1, 'min_range' => 0 ]
                ],
                'private_tm_key'             => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'private_tm_key_json'        => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'subject'                    => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'segmentation_rule'          => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'metadata'                   => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'pretranslate_100'           => [
                        'filter' => [ 'filter' => FILTER_VALIDATE_INT ]
                ],
                'pretranslate_101'           => [
                        'filter' => [ 'filter' => FILTER_VALIDATE_INT ]
                ],
                'id_team'                      => [ 'filter' => FILTER_VALIDATE_INT ],
                'id_qa_model'                  => [ 'filter' => FILTER_VALIDATE_INT ],
                'id_qa_model_template'         => [ 'filter' => FILTER_VALIDATE_INT ],
                'payable_rate_template_id'     => [ 'filter' => FILTER_VALIDATE_INT ],
                'payable_rate_template_name'   => [ 'filter' => FILTER_SANITIZE_STRING ],
                'dialect_strict'               => [ 'filter' => FILTER_SANITIZE_STRING ],
                'lexiqa'                       => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'speech2text'                  => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'tag_projection'               => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'project_completion'           => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'get_public_matches'           => [ 'filter' => FILTER_VALIDATE_BOOLEAN ], // disable public TM matches
                'dictation'                    => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'show_whitespace'              => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'character_counter'            => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'character_counter_count_tags' => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'character_counter_mode'       => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ],
                'ai_assistant'                 => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'instructions'                 => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_REQUIRE_ARRAY,
                ],
                'project_info'                 => [ 'filter' => FILTER_SANITIZE_STRING ],
                'mmt_glossaries'               => [ 'filter' => FILTER_SANITIZE_STRING ],

                'deepl_formality'   => [ 'filter' => FILTER_SANITIZE_STRING ],
                'deepl_id_glossary' => [ 'filter' => FILTER_SANITIZE_STRING ],

                'filters_extraction_parameters' => [ 'filter' => FILTER_SANITIZE_STRING ],
                'xliff_parameters'              => [ 'filter' => FILTER_SANITIZE_STRING ],

                'filters_extraction_parameters_template_id' => [ 'filter' => FILTER_VALIDATE_INT ],
                'xliff_parameters_template_id'              => [ 'filter' => FILTER_VALIDATE_INT ],

                'mt_evaluation' => [ 'filter' => FILTER_VALIDATE_BOOLEAN ]
        ];

        $this->postInput = filter_input_array( INPUT_POST, $filterArgs );


        /**
         * ----------------------------------
         * Note 2022-10-13
         * ----------------------------------
         *
         * We trim every space in instructions
         * in order to avoid mispelling errors
         *
         */
        $this->postInput[ 'instructions' ] = $this->featureSet->filter( 'encodeInstructions', $_POST[ 'instructions' ] ?? null );

        /**
         * ----------------------------------
         * Note 2021-05-28
         * ----------------------------------
         *
         * We trim every space private_tm_key
         * in order to avoid mispelling errors
         *
         */
        $this->postInput[ 'private_tm_key' ] = preg_replace( "/\s+/", "", $this->postInput[ 'private_tm_key' ] );

        if ( empty( $_FILES ) ) {
            $this->setBadRequestHeader();
            $this->result[ 'errors' ][] = [ "code" => -1, "message" => "Missing file. Not Sent." ];

            return -1;
        }

        try {
            $this->__validateMetadataParam();
            $this->__validateCharacterCounterMode();
            $this->__validateEngines();
            $this->__validateSubjects();
            $this->__validateSegmentationRules();
            $this->__validateTmAndKeys();
            $this->__validateTeam();
            $this->__validateQaModelTemplate();
            $this->__validatePayableRateTemplate();
            $this->__validateQaModel();
            $this->__validateUserMTEngine();
            $this->__validateMMTGlossaries();
            $this->__validateDeepLGlossaryParams();
            $this->__validateDialectStrictParam();
            $this->__validateFiltersExtractionParameters();
            $this->__validateXliffParameters();
            $this->__appendFeaturesToProject();
            $this->__generateTargetEngineAssociation();
        } catch ( Exception $ex ) {
            $this->api_output[ 'message' ] = "Project Creation Failure";
            $this->api_output[ 'debug' ]   = $ex->getMessage();
            Log::doJsonLog( $ex->getMessage() );
            $this->setBadRequestHeader();

            return $ex->getCode();
        }

        $this->files_storage = FilesStorageFactory::create();

    }

    /**
     * @throws Exception
     */
    private function __validateSegmentationRules() {
        $this->postInput[ 'segmentation_rule' ] = Constants::validateSegmentationRules( $this->postInput[ 'segmentation_rule' ] );
    }

    /**
     * @throws Exception
     */
    private function __validateSubjects() {

        $langDomains = LanguageDomains::getInstance();
        $subjectMap  = $langDomains::getEnabledHashMap();

        $this->postInput[ 'subject' ] = ( !empty( $this->postInput[ 'subject' ] ) ) ? $this->postInput[ 'subject' ] : 'general';
        if ( empty( $subjectMap[ $this->postInput[ 'subject' ] ] ) ) {
            throw new Exception( "Subject not allowed: " . $this->postInput[ 'subject' ], -3 );
        }

    }

    private function __appendFeaturesToProject() {
        if ( $this->postInput[ 'project_completion' ] ) {
            $feature                                         = new BasicFeatureStruct();
            $feature->feature_code                           = 'project_completion';
            $this->projectFeatures[ $feature->feature_code ] = $feature;
        }

        $this->projectFeatures = $this->featureSet->filter(
                'filterCreateProjectFeatures', $this->projectFeatures, $this
        );

    }

    /**
     * This could be already set by MMT engine if enabled ( so check key existence and do not override )
     *
     * @see filterCreateProjectFeatures callback
     * @see NewController::__appendFeaturesToProject()
     */
    private function __generateTargetEngineAssociation() {
        if ( !isset( $this->postInput[ 'target_language_mt_engine_id' ] ) ) { // this could be already set by MMT engine if enabled ( so check and do not override )
            foreach ( explode( ",", $this->postInput[ 'target_lang' ] ) as $_matecatTarget ) {
                $this->postInput[ 'target_language_mt_engine_id' ][ $_matecatTarget ] = $this->postInput[ 'mt_engine' ];
            }
        }
    }

    /**
     * @throws Exception
     */
    private function __validateCharacterCounterMode() {
        if ( isset( $this->postInput[ 'character_counter_mode' ] ) ) {
            $allowed = [
                "google_ads",
                "exclude_cjk",
                "all_one"
            ];

            if(!in_array($this->postInput[ 'character_counter_mode' ], $allowed)){
                throw new Exception( "Invalid character counter mode.", -2 );
            }
        }
    }

    /**
     * @throws Exception
     */
    private function __validateEngines() {

        if ( !isset( $this->postInput[ 'tms_engine' ] ) ) {
            $this->postInput[ 'tms_engine' ] = 1;
        }

        if ( !isset( $this->postInput[ 'mt_engine' ] ) ) {
            $this->postInput[ 'mt_engine' ] = 1;
        }

        if ( $this->postInput[ 'tms_engine' ] != 0 ) {
            Engine::getInstance( $this->postInput[ 'tms_engine' ] );
        }

        if ( $this->postInput[ 'mt_engine' ] != 0 && $this->postInput[ 'mt_engine' ] != 1 ) {
            if ( !$this->userIsLogged ) {
                throw new Exception( "Invalid MT Engine.", -2 );
            } else {
                $testEngine = Engine::getInstance( $this->postInput[ 'mt_engine' ] );
                if ( $testEngine->getEngineRecord()->uid != $this->getUser()->uid ) {
                    throw new Exception( "Invalid MT Engine.", -21 );
                }
            }
        }

    }

    public function finalize() {
        header( $this->httpHeader );
        $toJson = json_encode( $this->api_output );
        echo $toJson;
    }

    /**
     * @throws ReQueueException
     * @throws AuthenticationError
     * @throws ValidationError
     * @throws NotFoundException
     * @throws EndQueueException
     * @throws ReflectionException
     * @throws Exception
     */
    public function doAction() {

        $fs = FilesStorageFactory::create();

        if ( isset( $this->api_output[ 'debug' ] ) && count( $this->api_output[ 'debug' ] ) > 0 ) {
            $this->setBadRequestHeader();

            return -1;
        }

        $uploadFile = new Upload();

        try {
            $stdResult = $uploadFile->uploadFiles( $_FILES );
        } catch ( Exception $e ) {
            $this->setBadRequestHeader();
            $stdResult                     = [];
            $this->result                  = [
                    'errors' => [
                            [ "code" => -1, "message" => $e->getMessage() ]
                    ]
            ];
            $this->api_output[ 'message' ] = $e->getMessage();
        }

        $arFiles = [];

        foreach ( $stdResult as $input_name => $input_value ) {
            $arFiles[] = $input_value->name;
        }

        //if fileupload was failed this index ( 0 = does not exists )
        $default_project_name = $arFiles[ 0 ] ?? null;
        if ( count( $arFiles ) > 1 ) {
            $default_project_name = "MATECAT_PROJ-" . date( "Ymdhi" );
        }

        if ( empty( $this->postInput[ 'project_name' ] ) ) {
            $this->postInput[ 'project_name' ] = $default_project_name; //'NO_NAME'.$this->create_project_name();
        }

        $this->__validateSourceLang( Languages::getInstance() );
        $this->__validateTargetLangs( Languages::getInstance() );

        //ONE OR MORE ERRORS OCCURRED : EXITING
        //for now we sent to api output only the LAST error message, but we log all
        if ( !empty( $this->result[ 'errors' ] ) ) {
            $msg = "Error \n\n " . var_export( array_merge( $this->result, $_POST ), true );
            Log::doJsonLog( $msg );
            Utils::sendErrMailReport( $msg );
            $this->setBadRequestHeader();

            return -1; //exit code
        }

        $cookieDir = $uploadFile->getDirUploadToken();
        $intDir    = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $cookieDir;
        $errDir    = INIT::$STORAGE_DIR . DIRECTORY_SEPARATOR . 'conversion_errors' . DIRECTORY_SEPARATOR . $cookieDir;

        $status = [];

        foreach ( $arFiles as $file_name ) {
            $ext = AbstractFilesStorage::pathinfo_fix( $file_name, PATHINFO_EXTENSION );

            $conversionHandler = new ConversionHandler();
            $conversionHandler->setFileName( $file_name );
            $conversionHandler->setSourceLang( $this->postInput[ 'source_lang' ] );
            $conversionHandler->setTargetLang( $this->postInput[ 'target_lang' ] );
            $conversionHandler->setSegmentationRule( $this->postInput[ 'segmentation_rule' ] );
            $conversionHandler->setCookieDir( $cookieDir );
            $conversionHandler->setIntDir( $intDir );
            $conversionHandler->setErrDir( $errDir );
            $conversionHandler->setFeatures( $this->featureSet );
            $conversionHandler->setUserIsLogged( $this->userIsLogged );
            $conversionHandler->setFiltersExtractionParameters( $this->filters_extraction_parameters );

            if ( $ext == "zip" ) {
                // this makes the conversionhandler accumulate eventual errors on files and continue
                $conversionHandler->setStopOnFileException( false );

                $fileObjects = $conversionHandler->extractZipFile();

                \Log::doJsonLog( 'fileObjets', $fileObjects );

                //call convertFileWrapper and start conversions for each file

                if ( $conversionHandler->uploadError ) {
                    $fileErrors = $conversionHandler->getUploadedFiles();

                    foreach ( $fileErrors as $fileError ) {
                        if ( count( $fileError->error ) == 0 ) {
                            continue;
                        }

                        $brokenFileName = ZipArchiveExtended::getFileName( $fileError->name );

                        $this->result = new ConvertedFileModel( $fileError->error[ 'code' ] );
                        $this->result->addError( $fileError->error[ 'message' ], $brokenFileName );
                    }

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

                $this->result[ 'data' ][ $file_name ] = json_encode( $realFileObjectNames );

                $stdFileObjects = [];

                if ( $fileObjects !== null ) {
                    foreach ( $fileObjects as $fName ) {

                        if ( isset( $fileErrors ) &&
                                isset( $fileErrors->{$fName} ) &&
                                !empty( $fileErrors->{$fName}->error )
                        ) {
                            continue;
                        }

                        $newStdFile       = new stdClass();
                        $newStdFile->name = $fName;
                        $stdFileObjects[] = $newStdFile;

                    }
                } else {
                    $errors = $conversionHandler->getResult();
                    $errors = array_map( [ 'Upload', 'formatExceptionMessage' ], $errors->getErrors() );

                    $this->result[ 'errors' ]      = array_merge( $this->result[ 'errors' ], $errors );
                    $this->api_output[ 'message' ] = "Zip Error";
                    $this->api_output[ 'debug' ]   = $this->result[ 'errors' ];

                    return false;
                }

                /* Do conversions here */
                $converter              = new ConvertFileWrapper( $stdFileObjects, false );
                $converter->intDir      = $intDir;
                $converter->errDir      = $errDir;
                $converter->cookieDir   = $cookieDir;
                $converter->source_lang = $this->postInput[ 'source_lang' ];
                $converter->target_lang = $this->postInput[ 'target_lang' ];
                $converter->featureSet  = $this->featureSet;
                $converter->setUser( $this->user );
                $converter->doAction();

                $status = $error = $converter->checkResult();
                if ( $error !== null and !empty( $error->getErrors() ) ) {

                    $this->result = new ConvertedFileModel( ConversionHandlerStatus::ZIP_HANDLING );
                    $this->result->changeCode( $error->getCode() );
                    $savedErrors    = $this->result->getErrors();
                    $brokenFileName = ZipArchiveExtended::getFileName( array_keys( $error->getErrors() )[ 0 ] );

                    if ( !isset( $savedErrors[ $brokenFileName ] ) ) {
                        $this->result->addError( $error->getErrors()[ 0 ][ 'message' ], $brokenFileName );
                    }

                    $this->result = $status = [
                            'code'   => $error->getCode(),
                            'data'   => $error->getData(),
                            'errors' => $error->getErrors(),
                    ];
                }
            } else {

                $conversionHandler->processConversion();

                $result = $conversionHandler->getResult();
                if ( $result->getCode() < 0 ) {
                    $status[] = $result;
                }

            }
        }

        $status = array_values( $status );

        // Upload errors handling
        if ( !empty( $status ) ) {
            $this->api_output[ 'message' ] = 'Project Conversion Failure';
            $this->api_output[ 'debug' ]   = $status[ 2 ][ array_keys( $status[ 2 ] )[ 0 ] ];
            $this->result[ 'errors' ]      = $status[ 2 ][ array_keys( $status[ 2 ] )[ 0 ] ];
            Log::doJsonLog( $status );
            $this->setBadRequestHeader();

            return -1;
        }
        /* Do conversions here */

        if ( isset( $this->result[ 'data' ] ) && !empty( $this->result[ 'data' ] ) ) {
            foreach ( $this->result[ 'data' ] as $zipFileName => $zipFiles ) {
                $zipFiles  = json_decode( $zipFiles, true );
                $fileNames = array_column( $zipFiles, 'name' );
                $arFiles   = array_merge( $arFiles, $fileNames );
            }
        }

        $newArFiles = [];
        $linkFiles  = scandir( $intDir );

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


        $this->projectManager = new ProjectManager();
        $projectStructure     = $this->projectManager->getProjectStructure();

        $projectStructure[ 'sanitize_project_options' ] = false;

        $projectStructure[ 'project_name' ] = $this->postInput[ 'project_name' ];
        $projectStructure[ 'job_subject' ]  = $this->postInput[ 'subject' ];

        $projectStructure[ 'private_tm_key' ]    = $this->private_tm_key;
        $projectStructure[ 'private_tm_user' ]   = $this->private_tm_user;
        $projectStructure[ 'private_tm_pass' ]   = $this->private_tm_pass;
        $projectStructure[ 'uploadToken' ]       = $uploadFile->getDirUploadToken();
        $projectStructure[ 'array_files' ]       = $arFiles; //list of file name
        $projectStructure[ 'array_files_meta' ]  = $arMeta; //list of file metadata
        $projectStructure[ 'source_language' ]   = $this->postInput[ 'source_lang' ];
        $projectStructure[ 'target_language' ]   = explode( ',', $this->postInput[ 'target_lang' ] );
        $projectStructure[ 'mt_engine' ]         = $this->postInput[ 'mt_engine' ];
        $projectStructure[ 'tms_engine' ]        = $this->postInput[ 'tms_engine' ];
        $projectStructure[ 'tm_prioritization' ] = $this->tm_prioritization;
        $projectStructure[ 'status' ]            = Constants_ProjectStatus::STATUS_NOT_READY_FOR_ANALYSIS;
        $projectStructure[ 'owner' ]             = $this->user->email;
        $projectStructure[ 'metadata' ]          = $this->metadata;
        $projectStructure[ 'pretranslate_100' ]  = (int)!!$this->postInput[ 'pretranslate_100' ]; // Force pretranslate_100 to be 0 or 1
        $projectStructure[ 'pretranslate_101' ]  = isset( $this->postInput[ 'pretranslate_101' ] ) ? (int)$this->postInput[ 'pretranslate_101' ] : 1;

        $projectStructure[ 'dictation' ]              = $this->postInput[ 'dictation' ] ?? null;
        $projectStructure[ 'show_whitespace' ]        = $this->postInput[ 'show_whitespace' ] ?? null;
        $projectStructure[ 'character_counter' ]      = $this->postInput[ 'character_counter' ] ?? null;
        $projectStructure[ 'character_counter_mode' ] = $this->postInput[ 'character_counter_mode' ] ?? null;
        $projectStructure[ 'ai_assistant' ]           = $this->postInput[ 'ai_assistant' ] ?? null;

        $projectStructure[ 'character_counter_mode' ]       = $this->postInput['character_counter_mode'] ?? null;
        $projectStructure[ 'character_counter_count_tags' ] = $this->postInput['character_counter_count_tags'] ?? null;

        //default get all public matches from TM
        $projectStructure[ 'only_private' ] = ( !isset( $this->postInput[ 'get_public_matches' ] ) ? false : !$this->postInput[ 'get_public_matches' ] );

        $projectStructure[ 'user_ip' ]                      = Utils::getRealIpAddr();
        $projectStructure[ 'HTTP_HOST' ]                    = INIT::$HTTPHOST;
        $projectStructure[ 'due_date' ]                     = ( !isset( $this->postInput[ 'due_date' ] ) ? null : Utils::mysqlTimestamp( $this->postInput[ 'due_date' ] ) );
        $projectStructure[ 'target_language_mt_engine_id' ] = $this->postInput[ 'target_language_mt_engine_id' ];
        $projectStructure[ 'instructions' ]                 = $this->postInput[ 'instructions' ];

        if ( $this->user ) {
            $projectStructure[ 'userIsLogged' ] = true;
            $projectStructure[ 'uid' ]          = $this->user->getUid();
            $projectStructure[ 'id_customer' ]  = $this->user->getEmail();
            $this->projectManager->setTeam( $this->team );
        }

        // mmtGlossaries
        if ( $this->mmtGlossaries ) {
            $projectStructure[ 'mmt_glossaries' ] = $this->mmtGlossaries;
        }

        // DeepL
        if ( $this->mt_engine instanceof Engines_DeepL and $this->deepl_formality !== null ) {
            $projectStructure[ 'deepl_formality' ] = $this->deepl_formality;
        }

        if ( $this->mt_engine instanceof Engines_DeepL and $this->deepl_id_glossary !== null ) {
            $projectStructure[ 'deepl_id_glossary' ] = $this->deepl_id_glossary;
        }

        // with the qa template id
        if ( $this->qaModelTemplate ) {
            $projectStructure[ 'qa_model_template' ] = $this->qaModelTemplate->getDecodedModel();
        }

        if ( $this->qaModel ) {
            $projectStructure[ 'qa_model' ] = $this->qaModel->getDecodedModel();
        }

        if ( $this->payableRateModelTemplate ) {
            $projectStructure[ 'payable_rate_model_id' ] = $this->payableRateModelTemplate->id;
        }

        if ( $this->dialect_strict ) {
            $projectStructure[ 'dialect_strict' ] = $this->dialect_strict;
        }

        if ( $this->filters_extraction_parameters ) {
            $projectStructure[ 'filters_extraction_parameters' ] = $this->filters_extraction_parameters;
        }

        if ( $this->xliff_parameters ) {
            $projectStructure[ 'xliff_parameters' ] = $this->xliff_parameters;
        }

        if ( $this->postInput[ 'mt_evaluation' ] ) {
            $projectStructure[ 'mt_evaluation' ] = true;
        }

        //set features override
        $projectStructure[ 'project_features' ] = $this->projectFeatures;

        try {
            $this->projectManager->sanitizeProjectStructure();
        } catch ( Exception $e ) {
            $this->api_output[ 'message' ] = $e->getMessage();
            $this->api_output[ 'debug' ]   = $e->getCode();
            $this->setBadRequestHeader();

            return -1;
        }

        $fs::moveFileFromUploadSessionToQueuePath( $uploadFile->getDirUploadToken() );

        //reserve a project id from the sequence
        $projectStructure[ 'id_project' ] = Database::obtain()->nextSequence( Database::SEQ_ID_PROJECT )[ 0 ];
        $projectStructure[ 'ppassword' ]  = $this->projectManager->generatePassword();

        $projectStructure = $this->featureSet->filter( 'addNewProjectStructureAttributes', $projectStructure, $this->postInput );

        // flag to mark the project "from API"
        $projectStructure[ 'from_api' ] = true;

        $this->projectStructure = $projectStructure;

        Queue::sendProject( $projectStructure );

        $this->_pollForCreationResult();

        $this->_outputResult();
    }

    /**
     * @param $filename
     *
     * @return array
     * @throws AuthenticationError
     * @throws NotFoundException
     * @throws ValidationError
     * @throws EndQueueException
     * @throws ReQueueException
     */
    private function getFileMetadata( $filename ) {
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

    protected function _outputResult() {
        if ( $this->result == null ) {
            $this->api_output[ 'status' ]  = 504;
            $this->api_output[ 'message' ] = 'Project Creation Failure';
            $this->api_output[ 'debug' ]   = 'Execution timeout';
            $this->setInternalTimeoutHeader();
        } elseif ( !empty( $this->result[ 'errors' ] ) ) {
            //errors already logged
            $this->api_output[ 'status' ]  = 500;
            $this->api_output[ 'message' ] = 'Project Creation Failure';
            $this->api_output[ 'debug' ]   = array_values( $this->result[ 'errors' ] );
            $this->setInternalErrorHeader();
        } else {
            //everything ok
            $this->_outputForSuccess();
        }
    }

    protected function _outputForSuccess() {
        $this->api_output[ 'status' ]       = 'OK';
        $this->api_output[ 'message' ]      = 'Success';
        $this->api_output[ 'id_project' ]   = $this->projectStructure[ 'id_project' ];
        $this->api_output[ 'project_pass' ] = $this->projectStructure[ 'ppassword' ];
        $this->api_output[ 'new_keys' ]     = $this->new_keys;
        $this->api_output[ 'analyze_url' ]  = $this->projectManager->getAnalyzeURL();
    }

    protected function _pollForCreationResult() {
        $this->result[ 'errors' ] = $this->projectStructure[ 'result' ][ 'errors' ]->getArrayCopy();
    }

    private function __validateSourceLang( Languages $lang_handler ) {
        try {
            $lang_handler->validateLanguage( $this->postInput[ 'source_lang' ] );
        } catch ( Exception $e ) {
            $this->api_output[ 'message' ] = $e->getMessage();
            $this->result[ 'errors' ][]    = [ "code" => -3, "message" => $e->getMessage() ];
        }
    }

    private function __validateTargetLangs( Languages $lang_handler ) {
        try {
            $this->postInput[ 'target_lang' ] = $lang_handler->validateLanguageListAsString( $this->postInput[ 'target_lang' ] );
        } catch ( Exception $e ) {
            $this->api_output[ 'message' ] = $e->getMessage();
            $this->result[ 'errors' ][]    = [ "code" => -4, "message" => $e->getMessage() ];
        }
    }

    /**
     * @param $elem
     *
     * @return array
     */
    private static function __sanitizeTmKeyArr( $elem ) {

        $element                  = new TmKeyManagement_TmKeyStruct( $elem );
        $element->complete_format = true;
        $elem                     = TmKeyManagement_TmKeyManagement::sanitize( $element );

        return $elem->toArray();

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
    private function __validateMetadataParam() {

        if ( !empty( $this->postInput[ 'metadata' ] ) ) {

            if ( strlen( $this->postInput[ 'metadata' ] ) > 2048 ) {
                throw new Exception( 'metadata string is too long' );
            }

            $depth                         = 2; // only converts key value structures
            $assoc                         = true;
            $this->postInput[ 'metadata' ] = html_entity_decode( $this->postInput[ 'metadata' ] );
            $parsedMetadata                = json_decode( $this->postInput[ 'metadata' ], $assoc, $depth );

            if ( is_array( $parsedMetadata ) ) {
                $this->metadata = $parsedMetadata;
            }

            Log::doJsonLog( "Passed parameter metadata as json string." );
        }

        // new raw counter model
        $this->metadata[ Projects_MetadataDao::WORD_COUNT_TYPE_KEY ] = Projects_MetadataDao::WORD_COUNT_RAW;

        // project_info
        if ( !empty( $this->postInput[ 'project_info' ] ) ) {
            $this->metadata[ 'project_info' ] = $this->postInput[ 'project_info' ];
        }

        if ( !empty( $this->postInput[ 'dialect_strict' ] ) ) {
            $this->metadata[ 'dialect_strict' ] = $this->postInput[ 'dialect_strict' ];
        }

        //override metadata with explicitly declared keys ( we maintain metadata for backward compatibility )
        if ( !empty( $this->postInput[ 'lexiqa' ] ) ) {
            $this->metadata[ 'lexiqa' ] = $this->postInput[ 'lexiqa' ];
        }

        if ( !empty( $this->postInput[ 'speech2text' ] ) ) {
            $this->metadata[ 'speech2text' ] = $this->postInput[ 'speech2text' ];
        }

        if ( !empty( $this->postInput[ 'tag_projection' ] ) ) {
            $this->metadata[ 'tag_projection' ] = $this->postInput[ 'tag_projection' ];
        }

        if ( !empty( $this->postInput[ 'project_completion' ] ) ) {
            $this->metadata[ 'project_completion' ] = $this->postInput[ 'project_completion' ];
        }

        if ( !empty( $this->postInput[ 'segmentation_rule' ] ) ) {
            $this->metadata[ 'segmentation_rule' ] = $this->postInput[ 'segmentation_rule' ];
        }

        $this->metadata = $this->featureSet->filter( 'filterProjectMetadata', $this->metadata, $this->postInput );
        $this->metadata = $this->featureSet->filter( 'createProjectAssignInputMetadata', $this->metadata, [
                'input' => $this->postInput
        ] );

    }

    private static function __parseTmKeyInput( $tmKeyString ) {
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
                throw new Exception( "Permission modifier string not allowed. Allowed: <empty>, $allowed_permissions" );
                break;
        }

        return [
                'key' => $tmKeyInfo[ 0 ],
                'r'   => $read,
                'w'   => $write,
        ];
    }

    protected function __validateTmAndKeys() {

        try {
            if(!empty($this->postInput[ 'private_tm_key_json' ])){
                $json = html_entity_decode( $this->postInput[ 'private_tm_key_json' ] );

                // first check if `filters_extraction_parameters` is a valid JSON
                if ( !Utils::isJson( $json ) ) {
                    throw new Exception( "private_tm_key_json is not a valid JSON" );
                }

                $schema = file_get_contents( INIT::$ROOT . '/inc/validation/schema/private_tm_key_json.json' );

                $validatorObject       = new JSONValidatorObject();
                $validatorObject->json = $json;

                $validator = new JSONValidator( $schema );
                $validator->validate( $validatorObject );

                $privateTmKeyJsonObject = json_decode($json);

                $this->tm_prioritization = $privateTmKeyJsonObject->tm_prioritization;

                $this->private_tm_key = array_map(
                    function ($item){
                        return [
                            'key' => $item->key,
                            'r' => $item->read,
                            'w' => $item->write,
                            'penalty' => $item->penalty,
                        ];
                    },
                    $privateTmKeyJsonObject->keys
                );

            } else {
                $this->private_tm_key = array_map(
                    [ 'NewController', '__parseTmKeyInput' ],
                    explode( ",", $this->postInput[ 'private_tm_key' ] )
                );
            }
        } catch ( Exception $e ) {
            throw new Exception( $e->getMessage(), -6 );
        }

        if ( count( $this->private_tm_key ) > self::MAX_NUM_KEYS ) {
            throw new Exception( "Too much keys provided. Max number of keys is " . self::MAX_NUM_KEYS, -2 );
        }

        $this->private_tm_key = array_values( array_filter( $this->private_tm_key ) );

        //If a TMX file has been uploaded and no key was provided, create a new key.
        if ( empty( $this->private_tm_key ) ) {
            foreach ( $_FILES as $_fileinfo ) {
                $pathinfo = AbstractFilesStorage::pathinfo_fix( $_fileinfo[ 'name' ] );
                if ( $pathinfo[ 'extension' ] == 'tmx' ) {
                    $this->private_tm_key[] = [ 'key' => 'new' ];
                    break;
                }
            }
        }

        //remove all empty entries
        foreach ( $this->private_tm_key as $__key_idx => $tm_key ) {
            //from api a key is sent and the value is 'new'
            if ( $tm_key[ 'key' ] == 'new' ) {

                try {

                    $APIKeySrv = new TMSService();

                    $newUser = $APIKeySrv->createMyMemoryKey();

                    $this->private_tm_user = $newUser->id;
                    $this->private_tm_pass = $newUser->pass;

                    $this->private_tm_key[ $__key_idx ] =
                            [
                                    'key'     => $newUser->key,
                                    'name'    => null,
                                    'penalty' => $tm_key[ 'penalty' ] ?? null,
                                    'r'       => $tm_key[ 'r' ],
                                    'w'       => $tm_key[ 'w' ],

                            ];
                    $this->new_keys[]                   = $newUser->key;

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

                $this->private_tm_key[ $__key_idx ] = $this_tm_key;
            }

            $this->private_tm_key[ $__key_idx ] = self::__sanitizeTmKeyArr( $this->private_tm_key[ $__key_idx ] );

        }

    }

    /**
     * @throws Exception
     */
    private function __validateTeam() {
        if ( $this->user && !empty( $this->postInput[ 'id_team' ] ) ) {
            $dao = new MembershipDao();
            $org = $dao->findTeamByIdAndUser( $this->postInput[ 'id_team' ], $this->user );

            if ( !$org ) {
                throw new Exception( 'Team and user membership does not match', -1 );
            } else {
                $this->team = $org;
            }
        } else {
            if ( $this->user ) {
                $this->team = $this->user->getPersonalTeam();
            }
        }
    }

    /**
     * @throws Exception
     */
    private function __validateQaModelTemplate() {
        if ( !empty( $this->postInput[ 'id_qa_model_template' ] ) ) {
            $qaModelTemplate = QAModelTemplateDao::get( [
                    'id'  => $this->postInput[ 'id_qa_model_template' ],
                    'uid' => $this->getUser()->uid
            ] );

            // check if qa_model template exists
            if ( null === $qaModelTemplate ) {
                throw new Exception( 'This QA Model template does not exists or does not belongs to the logged in user' );
            }

            $this->qaModelTemplate = $qaModelTemplate;
        }
    }

    /**
     * @throws Exception
     */
    private function __validatePayableRateTemplate() {
        $payableRateModelTemplate = null;

        if ( !empty( $this->postInput[ 'payable_rate_template_name' ] ) ) {
            if ( empty( $this->postInput[ 'payable_rate_template_id' ] ) ) {
                throw new Exception( '`payable_rate_template_id` param is missing' );
            }
        }

        if ( !empty( $this->postInput[ 'payable_rate_template_id' ] ) ) {
            if ( empty( $this->postInput[ 'payable_rate_template_name' ] ) ) {
                throw new Exception( '`payable_rate_template_name` param is missing' );
            }
        }

        if ( !empty( $this->postInput[ 'payable_rate_template_name' ] ) and !empty( $this->postInput[ 'payable_rate_template_id' ] ) ) {

            $payableRateTemplateId   = $this->postInput[ 'payable_rate_template_id' ];
            $payableRateTemplateName = $this->postInput[ 'payable_rate_template_name' ];
            $userId                  = $this->getUser()->uid;

            $payableRateModelTemplate = CustomPayableRateDao::getByIdAndUser( $payableRateTemplateId, $userId );

            if ( null === $payableRateModelTemplate ) {
                throw new Exception( 'Payable rate model id not valid' );
            }

            if ( $payableRateModelTemplate->name !== $payableRateTemplateName ) {
                throw new Exception( 'Payable rate model name not matching' );
            }
        }

        $this->payableRateModelTemplate = $payableRateModelTemplate;
    }

    /**
     * Checks if id_qa_model is valid
     *
     * @throws Exception
     */
    private function __validateQaModel() {
        if ( !empty( $this->postInput[ 'id_qa_model' ] ) ) {

            $qaModel = ModelDao::findByIdAndUser( $this->postInput[ 'id_qa_model' ], $this->getUser()->uid );

            //XXX FALLBACK for models created before "required-login" feature (on these models there is no ownership check)
            if ( empty( $qaModel ) ) {
                $qaModel = ModelDao::findById( $this->postInput[ 'id_qa_model' ] );
                $qaModel->uid = $this->getUser()->uid;
            }

            // check if qa_model exists
            if ( empty( $qaModel ) ) {
                throw new Exception( 'This QA Model does not exists' );
            }

            // check featureSet
            $qaModelLabel    = strtolower( $qaModel->label );
            if ( $qaModelLabel !== 'default' and $qaModel->uid != $this->getUser()->uid ) {
                throw new Exception( 'This QA Model does not belong to the authenticated user' );
            }

            $this->qaModel = $qaModel;
        }
    }

    /**
     * @throws Exception
     */
    private function __validateUserMTEngine() {

        // any other engine than MyMemory
        if ( $this->postInput[ 'mt_engine' ] and $this->postInput[ 'mt_engine' ] > 1 ) {
            EngineValidator::engineBelongsToUser( $this->postInput[ 'mt_engine' ], $this->user->uid );
        }
    }

    /**
     * @throws Exception
     */
    private function __validateMMTGlossaries() {

        if ( !empty( $this->postInput[ 'mmt_glossaries' ] ) ) {

            $mmtGlossaries = html_entity_decode( $this->postInput[ 'mmt_glossaries' ] );
            MMTValidator::validateGlossary( $mmtGlossaries );

            $this->mmtGlossaries = $mmtGlossaries;
        }
    }

    /**
     * Validate DeepL params
     */
    private function __validateDeepLGlossaryParams() {

        if ( !empty( $this->postInput[ 'deepl_formality' ] ) ) {

            $allowedFormalities = [
                    'default',
                    'prefer_less',
                    'prefer_more'
            ];

            if ( in_array( $this->postInput[ 'deepl_formality' ], $allowedFormalities ) ) {
                $this->deepl_formality = $this->postInput[ 'deepl_formality' ];
            }
        }

        if ( !empty( $this->postInput[ 'deepl_id_glossary' ] ) ) {
            $this->deepl_id_glossary = $this->postInput[ 'deepl_id_glossary' ];
        }
    }

    /**
     * Validate `dialect_strict` param vs target languages
     *
     * Example: {"it-IT": true, "en-US": false, "fr-FR": false}
     *
     * @throws Exception
     */
    private function __validateDialectStrictParam() {
        if ( !empty( $this->postInput[ 'dialect_strict' ] ) ) {

            $dialect_strict   = trim( html_entity_decode( $this->postInput[ 'dialect_strict' ] ) );
            $target_languages = preg_replace( '/\s+/', '', $this->postInput[ 'target_lang' ] );
            $targets          = explode( ',', trim( $target_languages ) );

            // first check if `dialect_strict` is a valid JSON
            if ( !Utils::isJson( $dialect_strict ) ) {
                throw new Exception( "dialect_strict is not a valid JSON" );
            }

            $dialectStrictObj = json_decode( $dialect_strict, true );

            foreach ( $dialectStrictObj as $lang => $value ) {
                if ( !in_array( $lang, $targets ) ) {
                    throw new Exception( 'Wrong `dialect_strict` object, language, ' . $lang . ' is not one of the project target languages' );
                }

                if ( !is_bool( $value ) ) {
                    throw new Exception( 'Wrong `dialect_strict` object, not boolean declared value for ' . $lang );
                }
            }

            $this->dialect_strict = html_entity_decode( $dialect_strict );
        }
    }

    /**
     * @throws Exception
     */
    private function __validateFiltersExtractionParameters() {

        if ( !empty( $this->postInput[ 'filters_extraction_parameters' ] ) ) {

            $json = html_entity_decode( $this->postInput[ 'filters_extraction_parameters' ] );

            // first check if `filters_extraction_parameters` is a valid JSON
            if ( !Utils::isJson( $json ) ) {
                throw new Exception( "filters_extraction_parameters is not a valid JSON" );
            }

            $schema = file_get_contents( INIT::$ROOT . '/inc/validation/schema/filters_extraction_parameters.json' );

            $validatorObject       = new JSONValidatorObject();
            $validatorObject->json = $json;

            $validator = new JSONValidator( $schema );
            $validator->validate( $validatorObject );

            $this->filters_extraction_parameters = json_decode( $json );

        } elseif ( !empty( $this->postInput[ 'filters_extraction_parameters_template_id' ] ) ) {

            $filtersTemplate = FiltersConfigTemplateDao::getByIdAndUser( $this->postInput[ 'filters_extraction_parameters_template_id' ], $this->getUser()->uid );

            if ( $filtersTemplate === null ) {
                throw new Exception( "filters_extraction_parameters_template_id not valid" );
            }

            $this->filters_extraction_parameters = $filtersTemplate;
        }
    }

    /**
     * @throws Exception
     */
    private function __validateXliffParameters() {

        if ( !empty( $this->postInput[ 'xliff_parameters' ] ) ) {

            $json = html_entity_decode( $this->postInput[ 'xliff_parameters' ] );

            // first check if `xliff_parameters` is a valid JSON
            if ( !Utils::isJson( $json ) ) {
                throw new Exception( "xliff_parameters is not a valid JSON" );
            }

            $schema = file_get_contents( INIT::$ROOT . '/inc/validation/schema/xliff_parameters_rules_content.json' );

            $validatorObject       = new JSONValidatorObject();
            $validatorObject->json = $json;

            $validator = new JSONValidator( $schema, true );
            $validator->validate( $validatorObject );
            $this->xliff_parameters = json_decode( $json, true ); // decode again because we need an associative array and not stdClass

        } elseif ( !empty( $this->postInput[ 'xliff_parameters_template_id' ] ) ) {

            $xliffConfigTemplate = XliffConfigTemplateDao::getByIdAndUser( $this->postInput[ 'xliff_parameters_template_id' ], $this->getUser()->uid );

            if ( $xliffConfigTemplate === null ) {
                throw new Exception( "xliff_parameters_template_id not valid" );
            }

            $this->xliff_parameters = $xliffConfigTemplate->rules->getArrayCopy();
        }
    }
}
